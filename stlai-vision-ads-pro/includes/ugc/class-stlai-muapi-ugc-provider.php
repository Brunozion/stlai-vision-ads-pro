<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_MuAPI_UGC_Provider {
    const DEFAULT_BASE_URL = 'https://api.muapi.ai';
    const DEFAULT_MODEL = 'seedance-2.0-image-to-video';
    const DEFAULT_TIMEOUT = 120;

    public static function start_job( array $payload ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $original_image_url = trim( (string) ( $payload['image_url'] ?? '' ) );
        if ( '' === $original_image_url ) {
            return new WP_Error( 'UGC_IMAGE_MISSING', 'Escolha uma imagem de referência para gerar o UGC.' );
        }

        $direct_image_url = self::is_public_url( $original_image_url ) ? esc_url_raw( $original_image_url ) : '';

        if ( $direct_image_url ) {
            return self::start_with_image_url( $payload, $config, $direct_image_url, false );
        }

        $code = 0 === strpos( $original_image_url, 'data:image/' ) ? 'UGC_IMAGE_NOT_PUBLISHED' : 'UGC_IMAGE_REQUIRES_PUBLIC_URL';
        $message = 'UGC_IMAGE_NOT_PUBLISHED' === $code
            ? 'A imagem precisa ser publicada antes de gerar UGC.'
            : 'MuAPI precisa de uma URL pública da imagem para gerar UGC. Selecione uma imagem publicada do WordPress.';

        return new WP_Error(
            $code,
            $message,
            array(
                'debug' => self::debug_string(
                    self::safe_debug(
                        $config,
                        self::start_url( $config ),
                        '',
                        '',
                        array(),
                        false,
                        false,
                        array(
                            'payload_keys' => array_keys( self::start_body( $payload, '' ) ),
                            'response_http_code' => 0,
                            'raw_response_excerpt' => '',
                        )
                    )
                ),
            )
        );
    }

    private static function start_with_image_url( array $payload, array $config, $image_url, $upload_fallback_used ) {
        $body = self::start_body( $payload, $image_url );
        $start_url = self::start_url( $config );
        $raw_body = '';

        $response = wp_remote_post(
            $start_url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key'    => $config['api_key'],
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'MUAPI_START_REQUEST_FAILED',
                $response->get_error_message(),
                array(
                    'debug' => self::debug_string(
                        self::safe_debug(
                            $config,
                            $start_url,
                            '',
                            '',
                            array(),
                            true,
                            (bool) $upload_fallback_used,
                            array(
                                'payload_keys' => array_keys( $body ),
                                'response_http_code' => 0,
                                'raw_response_excerpt' => $response->get_error_message(),
                            )
                        )
                    ),
                )
            );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $raw_body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $raw_body, true );
        if ( $http_status < 200 || $http_status >= 300 || ! is_array( $data ) ) {
            return new WP_Error(
                'MUAPI_START_FAILED',
                self::safe_error_message( $data, 'MuAPI não iniciou a geração UGC. Verifique o modelo, chave e se a imagem está acessível publicamente.' ),
                array(
                    'response' => is_array( $data ) ? $data : array(),
                    'http_status' => $http_status,
                    'debug' => self::debug_string(
                        self::safe_debug(
                            $config,
                            $start_url,
                            '',
                            '',
                            is_array( $data ) ? $data : array(),
                            true,
                            (bool) $upload_fallback_used,
                            array(
                                'payload_keys' => array_keys( $body ),
                                'response_http_code' => $http_status,
                                'raw_response_excerpt' => substr( $raw_body, 0, 500 ),
                            )
                        )
                    ),
                )
            );
        }

        $request_id = self::extract_request_id( $data );
        $video_url = self::extract_video_url( $data );
        if ( empty( $request_id ) && empty( $video_url ) ) {
            return new WP_Error( 'MUAPI_REQUEST_ID_MISSING', 'MuAPI não retornou request_id nem vídeo pronto para o vídeo UGC.' );
        }

        $poll_url = $request_id ? self::poll_url( $config, $request_id ) : '';

        return array(
            'success'      => true,
            'operation_id' => $request_id,
            'request_id'   => $request_id,
            'status'       => $video_url ? 'ready' : 'processing',
            'video_url'    => $video_url,
            'provider'     => 'muapi',
            'model'        => $config['model'],
            'image_url'    => $image_url,
            'status_url'   => $poll_url,
            'result_url'   => $poll_url,
            'raw_status'   => sanitize_key( (string) ( $data['status'] ?? 'processing' ) ),
            'endpoint_used' => 'api/v1/' . ltrim( $config['model'], '/' ),
            'provider_debug' => self::safe_debug(
                $config,
                $start_url,
                $poll_url,
                $request_id,
                $data,
                ! $upload_fallback_used,
                $upload_fallback_used,
                array(
                    'payload_keys' => array_keys( $body ),
                    'response_http_code' => $http_status,
                    'raw_response_excerpt' => substr( $raw_body, 0, 500 ),
                )
            ),
        );
    }

    public static function poll_job( $request_id ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $request_id = sanitize_text_field( $request_id );
        if ( empty( $request_id ) ) {
            return new WP_Error( 'MUAPI_EMPTY_REQUEST_ID', 'request_id UGC vazio.' );
        }

        $response = wp_remote_get(
            self::poll_url( $config, $request_id ),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key'    => $config['api_key'],
                ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'MUAPI_POLL_REQUEST_FAILED', $response->get_error_message() );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $http_status < 200 || $http_status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'MUAPI_POLL_FAILED', self::safe_error_message( $data, 'MuAPI não retornou o status do vídeo UGC.' ) );
        }

        $raw_status = STLAI_UGC_Job_Service::extract_provider_status( $data );
        $video_url = self::extract_video_url( $data );
        $status = STLAI_UGC_Job_Service::normalize_provider_status( $raw_status, $video_url );
        if ( 'failed' === $status && in_array( sanitize_key( (string) $raw_status ), array( 'completed', 'succeeded', 'success', 'done', 'ready' ), true ) && empty( $video_url ) ) {
            return new WP_Error( 'MUAPI_COMPLETED_WITHOUT_VIDEO', 'MuAPI concluiu, mas não retornou URL de vídeo.' );
        }

        $poll_url = self::poll_url( $config, $request_id );

        return array(
            'success'      => true,
            'status'       => $status,
            'video_url'    => $video_url,
            'raw_status'   => $raw_status,
            'provider'     => 'muapi',
            'model'        => $config['model'],
            'operation_id' => $request_id,
            'request_id'   => $request_id,
            'status_url'   => $poll_url,
            'result_url'   => $poll_url,
            'message'      => STLAI_UGC_Job_Service::message_for_provider_status( $status, $video_url ),
            'provider_debug' => self::safe_debug( $config, self::start_url( $config ), $poll_url, $request_id, $data, true, false ),
        );
    }

    public static function config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $api_key = trim( (string) ( $settings['muApiKey'] ?? '' ) );
        if ( '' === $api_key ) {
            return new WP_Error( 'MUAPI_KEY_MISSING', 'MuAPI Key não configurada.' );
        }

        $legacy_base_url = (string) ( $settings['muApiBaseUrl'] ?? '' );
        $base_url = self::normalize_base_url( $legacy_base_url );
        $model = self::normalize_model( (string) ( $settings['muApiModel'] ?? '' ), $legacy_base_url );

        return array(
            'api_key'  => $api_key,
            'base_url' => $base_url,
            'model'    => $model,
        );
    }

    public static function normalize_base_url( $base_url ) {
        $base_url = trim( (string) $base_url );
        if ( '' === $base_url ) {
            return self::DEFAULT_BASE_URL;
        }
        $base_url = preg_replace( '#/api/v1(?:/.*)?$#i', '', $base_url );
        $base_url = esc_url_raw( $base_url );
        return untrailingslashit( $base_url ?: self::DEFAULT_BASE_URL );
    }

    public static function normalize_model( $model, $legacy_base_url = '' ) {
        $model = trim( (string) $model );
        if ( '' === $model && preg_match( '#/api/v1/(.+)$#i', (string) $legacy_base_url, $matches ) ) {
            $model = $matches[1];
        }
        if ( preg_match( '#^https?://#i', $model ) ) {
            $path = (string) wp_parse_url( $model, PHP_URL_PATH );
            if ( preg_match( '#/api/v1/(.+)$#i', $path, $matches ) ) {
                $model = $matches[1];
            } else {
                $parts = array_values( array_filter( explode( '/', $path ) ) );
                $model = end( $parts ) ?: '';
            }
        }
        $model = preg_replace( '#^/?api/v1/#i', '', $model );
        $model = trim( $model, " \t\n\r\0\x0B/" );
        $model = sanitize_text_field( $model );
        return $model ?: self::DEFAULT_MODEL;
    }

    private static function start_body( array $payload, $image_url ) {
        return array_filter(
            array(
                'prompt'       => (string) ( $payload['prompt'] ?? '' ),
                'image_url'    => esc_url_raw( $image_url ),
                'aspect_ratio' => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
                'duration'     => (int) ( $payload['duration'] ?? 9 ),
                'resolution'   => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
                'quality'      => sanitize_key( (string) ( $payload['quality'] ?? '' ) ),
                'mode'         => sanitize_text_field( (string) ( $payload['mode'] ?? '' ) ),
                'name'         => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ),
            ),
            function ( $value ) {
                return '' !== $value && null !== $value;
            }
        );
    }

    private static function start_url( array $config ) {
        return trailingslashit( $config['base_url'] ) . 'api/v1/' . ltrim( $config['model'], '/' );
    }

    private static function poll_url( array $config, $request_id ) {
        return trailingslashit( $config['base_url'] ) . 'api/v1/predictions/' . rawurlencode( $request_id ) . '/result';
    }

    private static function is_public_url( $url ) {
        return is_string( $url ) && preg_match( '#^https?://#i', $url );
    }

    private static function extract_request_id( array $data ) {
        foreach ( array( 'request_id', 'id', 'prediction_id' ) as $key ) {
            if ( ! empty( $data[ $key ] ) ) {
                return sanitize_text_field( $data[ $key ] );
            }
        }
        foreach ( array( 'data', 'prediction' ) as $key ) {
            if ( ! empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                $id = self::extract_request_id( $data[ $key ] );
                if ( $id ) {
                    return $id;
                }
            }
        }
        return '';
    }

    private static function extract_video_url( array $data ) {
        $candidates = array(
            $data['outputs'][0] ?? '',
            $data['url'] ?? '',
            $data['video_url'] ?? '',
            $data['output']['url'] ?? '',
            $data['data']['url'] ?? '',
            $data['data']['outputs'][0] ?? '',
            $data['data']['output']['url'] ?? '',
            $data['result']['outputs'][0] ?? '',
            $data['result']['url'] ?? '',
            $data['result']['output']['url'] ?? '',
            $data['file_url'] ?? '',
            $data['data']['file_url'] ?? '',
        );

        foreach ( $candidates as $candidate ) {
            if ( is_string( $candidate ) && preg_match( '#^https?://#i', $candidate ) ) {
                return esc_url_raw( $candidate );
            }
        }

        return '';
    }

    private static function safe_debug( array $config, $start_url, $poll_url, $request_id, array $response, $direct_image_url_used, $upload_fallback_used, array $extra = array() ) {
        return array(
            'provider'              => 'muapi',
            'base_url_normalized'   => esc_url_raw( $config['base_url'] ),
            'model_normalized'      => sanitize_text_field( $config['model'] ),
            'start_url'             => esc_url_raw( $start_url ),
            'poll_url'              => esc_url_raw( $poll_url ),
            'direct_image_url_used' => (bool) $direct_image_url_used,
            'upload_attempted'      => (bool) $upload_fallback_used,
            'request_id'            => sanitize_text_field( $request_id ),
            'response_keys'         => array_values( array_map( 'sanitize_key', array_keys( $response ) ) ),
            'payload_keys'          => array_values( array_map( 'sanitize_key', $extra['payload_keys'] ?? array() ) ),
            'response_http_code'    => (int) ( $extra['response_http_code'] ?? 0 ),
            'raw_response_excerpt'  => sanitize_textarea_field( (string) ( $extra['raw_response_excerpt'] ?? '' ) ),
        );
    }

    private static function debug_string( array $debug ) {
        return wp_json_encode( $debug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    private static function safe_error_message( $data, $fallback ) {
        if ( is_array( $data ) ) {
            $message = $data['error']['message'] ?? ( $data['message'] ?? ( $data['data']['message'] ?? '' ) );
            if ( is_string( $message ) && '' !== trim( $message ) ) {
                return sanitize_text_field( $message );
            }
        }
        return $fallback;
    }
}
