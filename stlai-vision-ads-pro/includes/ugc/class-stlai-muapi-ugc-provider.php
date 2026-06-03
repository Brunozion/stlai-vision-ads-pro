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
                            'payload_keys' => array_keys( self::start_body( $payload, '', $config['model'] ) ),
                            'response_http_code' => 0,
                            'raw_response_excerpt' => '',
                        )
                    )
                ),
            )
        );
    }

    private static function start_with_image_url( array $payload, array $config, $image_url, $upload_fallback_used ) {
        $start_url = self::start_url( $config );
        $raw_body = '';
        $image_probe = self::validate_public_image_url( $image_url );
        if ( is_wp_error( $image_probe ) ) {
            return $image_probe;
        }
        $image_field = self::get_muapi_image_field_for_model( $config['model'] );
        $retried_with_images_list = false;
        $retried_with_image_url = false;
        $body = self::start_body( $payload, $image_url, $config['model'], $image_field );
        $base_debug = array(
            'payload_keys' => array_keys( $body ),
            'payload' => self::safe_payload_debug( $body ),
            'image_url' => $image_url,
            'image_accessible' => ! is_wp_error( $image_probe ),
            'image_probe' => is_wp_error( $image_probe ) ? $image_probe->get_error_data() : $image_probe,
            'model_may_require_last_frame' => self::model_may_require_last_frame( $config['model'] ),
            'muapi_image_field_used' => $image_field,
            'images_list_count' => self::images_list_count( $body ),
            'retried_with_images_list' => false,
            'retried_with_image_url' => false,
        );

        $response = self::post_start_request( $start_url, $config, $body );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'MUAPI_START_REQUEST_FAILED',
                'MuAPI não iniciou a geração UGC. Verifique o modelo, chave e configuração.',
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
                            array_merge(
                                $base_debug,
                                array(
                                'response_http_code' => 0,
                                    'raw_response_excerpt' => $response->get_error_message(),
                                    'wp_error_code' => $response->get_error_code(),
                                    'wp_error_message' => $response->get_error_message(),
                                    'admin_message' => 'MuAPI start failed: WP_Error — ' . $response->get_error_message(),
                                )
                            )
                        )
                    ),
                )
            );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $raw_body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $raw_body, true );

        if ( $http_status >= 400 && self::should_retry_with_alternate_image_field( $image_field, $http_status, $raw_body, $data ) ) {
            if ( 'image_url' === $image_field ) {
                $image_field = 'images_list';
                $retried_with_images_list = true;
            } else {
                $image_field = 'image_url';
                $retried_with_image_url = true;
            }
            $body = self::start_body( $payload, $image_url, $config['model'], $image_field );
            $base_debug = array_merge(
                $base_debug,
                array(
                    'payload_keys' => array_keys( $body ),
                    'payload' => self::safe_payload_debug( $body ),
                    'muapi_image_field_used' => $image_field,
                    'images_list_count' => self::images_list_count( $body ),
                    'retried_with_images_list' => $retried_with_images_list,
                    'retried_with_image_url' => $retried_with_image_url,
                    'retry_reason' => '422 missing alternate image field',
                )
            );
            $response = self::post_start_request( $start_url, $config, $body );
            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'MUAPI_START_REQUEST_FAILED',
                    'MuAPI não iniciou a geração UGC. Verifique o modelo, chave e configuração.',
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
                                array_merge(
                                    $base_debug,
                                    array(
                                        'response_http_code' => 0,
                                        'raw_response_excerpt' => $response->get_error_message(),
                                        'wp_error_code' => $response->get_error_code(),
                                        'wp_error_message' => $response->get_error_message(),
                                        'admin_message' => 'MuAPI start failed after image field retry: WP_Error — ' . $response->get_error_message(),
                                    )
                                )
                            )
                        ),
                    )
                );
            }
            $http_status = (int) wp_remote_retrieve_response_code( $response );
            $raw_body = (string) wp_remote_retrieve_body( $response );
            $data = json_decode( $raw_body, true );
        }

        if ( $http_status < 200 || $http_status >= 300 ) {
            return new WP_Error(
                'MUAPI_START_FAILED',
                'MuAPI não iniciou a geração UGC. Verifique o modelo, chave e configuração.',
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
                            array_merge(
                                $base_debug,
                                array(
                                'response_http_code' => $http_status,
                                    'raw_response_excerpt' => substr( $raw_body, 0, 1000 ),
                                    'error_message' => self::safe_error_message( $data, '' ),
                                    'admin_message' => 'MuAPI start failed: HTTP ' . $http_status . ' — ' . substr( $raw_body, 0, 1000 ),
                                )
                            )
                        )
                    ),
                )
            );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error(
                'MUAPI_START_BAD_RESPONSE',
                'MuAPI não retornou uma resposta válida para iniciar o UGC.',
                array(
                    'http_status' => $http_status,
                    'debug' => self::debug_string(
                        self::safe_debug(
                            $config,
                            $start_url,
                            '',
                            '',
                            array(),
                            true,
                            (bool) $upload_fallback_used,
                            array_merge(
                                $base_debug,
                                array(
                                    'response_http_code' => $http_status,
                                    'raw_response_excerpt' => substr( $raw_body, 0, 1000 ),
                                    'admin_message' => 'MuAPI start failed: HTTP ' . $http_status . ' — invalid JSON response',
                                )
                            )
                        )
                    ),
                )
            );
        }

        $request_id = self::extract_request_id( $data );
        $video_url = self::extract_video_url( $data );
        if ( empty( $request_id ) && empty( $video_url ) ) {
            return new WP_Error(
                'MUAPI_START_BAD_RESPONSE',
                'MuAPI não retornou request_id nem vídeo pronto para o vídeo UGC.',
                array(
                    'http_status' => $http_status,
                    'debug' => self::debug_string(
                        self::safe_debug(
                            $config,
                            $start_url,
                            '',
                            '',
                            $data,
                            true,
                            (bool) $upload_fallback_used,
                            array_merge(
                                $base_debug,
                                array(
                                    'response_http_code' => $http_status,
                                    'raw_response_excerpt' => substr( $raw_body, 0, 1000 ),
                                    'error_message' => self::safe_error_message( $data, '' ),
                                    'admin_message' => 'MuAPI start failed: HTTP ' . $http_status . ' — missing request_id/video_url',
                                )
                            )
                        )
                    ),
                )
            );
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
                array_merge(
                    $base_debug,
                    array(
                    'response_http_code' => $http_status,
                        'raw_response_excerpt' => substr( $raw_body, 0, 1000 ),
                    )
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

    private static function start_body( array $payload, $image_url, $model = '', $image_field = '' ) {
        $image_field = $image_field ?: self::get_muapi_image_field_for_model( $model );
        $image_urls = array_values( array_filter( array(
            esc_url_raw( $image_url ),
            esc_url_raw( (string) ( $payload['last_image_url'] ?? ( $payload['last_image'] ?? '' ) ) ),
        ) ) );
        if ( empty( $image_urls ) && $image_url ) {
            $image_urls = array( esc_url_raw( $image_url ) );
        }

        $body = array(
            'prompt'       => (string) ( $payload['prompt'] ?? '' ),
            'aspect_ratio' => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
            'duration'     => (int) ( $payload['duration'] ?? 9 ),
            'resolution'   => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
            'quality'      => sanitize_key( (string) ( $payload['quality'] ?? '' ) ),
            'mode'         => sanitize_text_field( (string) ( $payload['mode'] ?? '' ) ),
            'name'         => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ),
        );

        if ( 'images_list' === $image_field ) {
            // TODO: support an optional last frame in the UI and pass it as a second URL.
            $body['images_list'] = $image_urls ? array_slice( $image_urls, 0, 2 ) : array();
        } else {
            $body['image_url'] = esc_url_raw( $image_url );
        }

        return array_filter(
            $body,
            function ( $value ) {
                return '' !== $value && null !== $value && array() !== $value;
            }
        );
    }

    private static function post_start_request( $start_url, array $config, array $body ) {
        return wp_remote_post(
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
    }

    private static function get_muapi_image_field_for_model( $model ) {
        $model = strtolower( (string) $model );
        foreach ( array( 'first-last-frame', 'first_last_frame', 'vip-first-last-frame', 'sd-2-vip-first-last-frame' ) as $pattern ) {
            if ( false !== strpos( $model, $pattern ) ) {
                return 'images_list';
            }
        }
        return 'image_url';
    }

    private static function images_list_count( array $body ) {
        return isset( $body['images_list'] ) && is_array( $body['images_list'] ) ? count( $body['images_list'] ) : 0;
    }

    private static function should_retry_with_alternate_image_field( $image_field, $http_status, $raw_body, $data ) {
        if ( 422 !== (int) $http_status ) {
            return false;
        }
        $haystack = strtolower( (string) $raw_body . ' ' . wp_json_encode( is_array( $data ) ? $data : array() ) );
        if ( 'image_url' === $image_field ) {
            return false !== strpos( $haystack, 'images_list' ) && false !== strpos( $haystack, 'field required' );
        }
        return false !== strpos( $haystack, 'image_url' ) && false !== strpos( $haystack, 'field required' );
    }

    private static function validate_public_image_url( $image_url ) {
        if ( ! self::is_public_url( $image_url ) ) {
            return self::image_access_error( $image_url, 0, '', 0, '', 'URL da imagem não é pública.' );
        }

        $head = wp_remote_head(
            $image_url,
            array(
                'timeout'     => 15,
                'redirection' => 3,
            )
        );
        $head_probe = self::image_probe_from_response( $head, '' );
        if ( self::image_probe_ok( $head_probe ) ) {
            $head_probe['method'] = 'HEAD';
            return $head_probe;
        }

        $get = wp_remote_get(
            $image_url,
            array(
                'timeout'     => 15,
                'redirection' => 3,
            )
        );
        $body = is_wp_error( $get ) ? '' : (string) wp_remote_retrieve_body( $get );
        $get_probe = self::image_probe_from_response( $get, $body );
        if ( self::image_probe_ok( $get_probe ) ) {
            $get_probe['method'] = 'GET';
            $get_probe['head_failed'] = true;
            $get_probe['head_http_code'] = (int) ( $head_probe['http_code'] ?? 0 );
            return $get_probe;
        }

        return self::image_access_error(
            $image_url,
            (int) ( $get_probe['http_code'] ?? 0 ),
            (string) ( $get_probe['content_type'] ?? '' ),
            (int) ( $get_probe['content_length'] ?? 0 ),
            (string) ( $get_probe['response_excerpt'] ?? '' ),
            (string) ( $get_probe['wp_error_message'] ?? 'A imagem publicada não está acessível publicamente para a MuAPI.' )
        );
    }

    private static function image_probe_from_response( $response, $body ) {
        if ( is_wp_error( $response ) ) {
            return array(
                'ok'               => false,
                'http_code'        => 0,
                'content_type'     => '',
                'content_length'   => 0,
                'response_excerpt' => '',
                'wp_error_code'    => sanitize_key( $response->get_error_code() ),
                'wp_error_message' => sanitize_text_field( $response->get_error_message() ),
            );
        }

        $content_type = strtolower( trim( (string) wp_remote_retrieve_header( $response, 'content-type' ) ) );
        $content_type = trim( strtok( $content_type, ';' ) ?: $content_type );
        $content_length = (int) wp_remote_retrieve_header( $response, 'content-length' );
        $body_length = strlen( (string) $body );
        if ( $body_length > 0 ) {
            $content_length = max( $content_length, $body_length );
        }

        return array(
            'ok'               => false,
            'http_code'        => (int) wp_remote_retrieve_response_code( $response ),
            'content_type'     => sanitize_mime_type( $content_type ),
            'content_length'   => $content_length,
            'response_excerpt' => self::body_excerpt( $body, 300 ),
        );
    }

    private static function image_probe_ok( array $probe ) {
        $http_code = (int) ( $probe['http_code'] ?? 0 );
        $content_type = (string) ( $probe['content_type'] ?? '' );
        $content_length = (int) ( $probe['content_length'] ?? 0 );
        if ( 200 !== $http_code ) {
            return false;
        }
        if ( ! in_array( $content_type, array( 'image/jpeg', 'image/jpg', 'image/png', 'image/webp' ), true ) ) {
            return false;
        }
        return 0 === $content_length || $content_length > 0;
    }

    private static function image_access_error( $image_url, $http_code, $content_type, $content_length, $response_excerpt, $message ) {
        $debug = array(
            'image_url'        => esc_url_raw( $image_url ),
            'http_code'        => (int) $http_code,
            'content_type'     => sanitize_mime_type( $content_type ),
            'content_length'   => (int) $content_length,
            'response_excerpt' => sanitize_textarea_field( $response_excerpt ),
        );
        return new WP_Error(
            'UGC_IMAGE_PUBLIC_URL_NOT_ACCESSIBLE',
            'A imagem publicada não está acessível publicamente para a MuAPI.',
            array(
                'debug' => self::debug_string(
                    array_merge(
                        $debug,
                        array( 'admin_message' => sanitize_text_field( $message ) )
                    )
                ),
            )
        );
    }

    private static function safe_payload_debug( array $payload ) {
        $safe = array();
        foreach ( $payload as $key => $value ) {
            $safe_key = sanitize_key( $key );
            if ( 'prompt' === $key ) {
                $safe[ $safe_key ] = self::body_excerpt( (string) $value, 300 );
            } elseif ( is_array( $value ) ) {
                $safe[ $safe_key ] = self::sanitize_debug_value( $value );
            } elseif ( is_scalar( $value ) ) {
                $safe[ $safe_key ] = sanitize_text_field( (string) $value );
            }
        }
        return $safe;
    }

    private static function model_may_require_last_frame( $model ) {
        return false !== stripos( (string) $model, 'first-last-frame' );
    }

    private static function body_excerpt( $body, $limit = 1000 ) {
        $excerpt = substr( (string) $body, 0, $limit );
        return sanitize_textarea_field( preg_replace( '/[^\P{C}\t\r\n]+/u', '', $excerpt ) );
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
            $data['output'] ?? '',
            $data['output']['url'] ?? '',
            $data['data']['url'] ?? '',
            $data['data']['output'] ?? '',
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
        $debug = array(
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
            'http_code'             => (int) ( $extra['response_http_code'] ?? 0 ),
            'raw_response_excerpt'  => self::body_excerpt( (string) ( $extra['raw_response_excerpt'] ?? '' ), 1000 ),
            'response_body_excerpt' => self::body_excerpt( (string) ( $extra['raw_response_excerpt'] ?? '' ), 1000 ),
        );

        foreach ( array( 'image_url', 'error_message', 'wp_error_code', 'wp_error_message', 'admin_message', 'muapi_image_field_used', 'retry_reason' ) as $key ) {
            if ( isset( $extra[ $key ] ) && '' !== $extra[ $key ] ) {
                $debug[ $key ] = 'image_url' === $key ? esc_url_raw( $extra[ $key ] ) : sanitize_text_field( (string) $extra[ $key ] );
            }
        }
        if ( isset( $extra['images_list_count'] ) ) {
            $debug['images_list_count'] = (int) $extra['images_list_count'];
        }
        if ( isset( $extra['retried_with_images_list'] ) ) {
            $debug['retried_with_images_list'] = ! empty( $extra['retried_with_images_list'] );
        }
        if ( isset( $extra['retried_with_image_url'] ) ) {
            $debug['retried_with_image_url'] = ! empty( $extra['retried_with_image_url'] );
        }

        if ( isset( $extra['payload'] ) && is_array( $extra['payload'] ) ) {
            $debug['payload'] = self::sanitize_debug_value( $extra['payload'] );
        }
        if ( isset( $extra['image_accessible'] ) ) {
            $debug['image_accessible'] = ! empty( $extra['image_accessible'] );
        }
        if ( isset( $extra['image_probe'] ) && is_array( $extra['image_probe'] ) ) {
            $debug['image_probe'] = self::sanitize_debug_value( $extra['image_probe'] );
        }
        if ( isset( $extra['model_may_require_last_frame'] ) ) {
            $debug['model_may_require_last_frame'] = ! empty( $extra['model_may_require_last_frame'] );
        }

        return $debug;
    }

    private static function sanitize_debug_value( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            return $value;
        }
        if ( is_array( $value ) ) {
            $safe = array();
            foreach ( $value as $key => $item ) {
                $safe_key = is_string( $key ) ? sanitize_key( $key ) : (int) $key;
                if ( in_array( $safe_key, array( 'api_key', 'x_api_key', 'authorization', 'secret', 'token' ), true ) ) {
                    continue;
                }
                $safe[ $safe_key ] = self::sanitize_debug_value( $item );
            }
            return $safe;
        }
        return sanitize_textarea_field( (string) $value );
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
