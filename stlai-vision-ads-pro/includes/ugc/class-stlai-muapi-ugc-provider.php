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

        $image_url = self::prepare_image_url( $payload['image_url'] ?? '', $config );
        if ( is_wp_error( $image_url ) ) {
            return $image_url;
        }

        $body = array_filter(
            array(
                'prompt'       => (string) ( $payload['prompt'] ?? '' ),
                'image_url'    => $image_url,
                'aspect_ratio' => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
                'duration'     => (int) ( $payload['duration'] ?? 9 ),
                'resolution'   => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
                'quality'      => sanitize_key( (string) ( $payload['quality'] ?? '' ) ),
            ),
            function ( $value ) {
                return '' !== $value && null !== $value;
            }
        );

        $response = wp_remote_post(
            self::endpoint_url( $config['base_url'], $config['model'] ),
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
            return new WP_Error( 'MUAPI_START_REQUEST_FAILED', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'MUAPI_START_FAILED', self::safe_error_message( $data, 'MuAPI não iniciou o vídeo UGC.' ) );
        }

        $request_id = self::extract_request_id( $data );
        if ( empty( $request_id ) ) {
            return new WP_Error( 'MUAPI_REQUEST_ID_MISSING', 'MuAPI não retornou request_id para o vídeo UGC.' );
        }

        return array(
            'success'    => true,
            'request_id' => $request_id,
            'status'     => 'processing',
            'provider'   => 'muapi',
            'model'      => $config['model'],
            'image_url'  => $image_url,
            'raw_status' => sanitize_key( (string) ( $data['status'] ?? 'processing' ) ),
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
            trailingslashit( $config['base_url'] ) . 'api/v1/predictions/' . rawurlencode( $request_id ) . '/result',
            array(
                'headers' => array( 'x-api-key' => $config['api_key'] ),
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

        $raw_status = sanitize_key( (string) ( $data['status'] ?? ( $data['data']['status'] ?? '' ) ) );
        $status = self::normalize_status( $raw_status );
        $video_url = self::extract_video_url( $data );
        if ( $video_url ) {
            $status = 'ready';
        }

        return array(
            'success'    => true,
            'status'     => $status,
            'video_url'  => $video_url,
            'raw_status' => $raw_status,
            'provider'   => 'muapi',
            'model'      => $config['model'],
            'message'    => self::message_for_status( $status ),
        );
    }

    public static function config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $provider = sanitize_key( (string) ( $settings['ugcProvider'] ?? 'none' ) );
        if ( 'muapi' !== $provider ) {
            return new WP_Error( 'UGC_PROVIDER_DISABLED', 'Ative MuAPI como provider UGC nas configurações.' );
        }

        $api_key = trim( (string) ( $settings['muApiKey'] ?? '' ) );
        if ( '' === $api_key ) {
            return new WP_Error( 'MUAPI_KEY_MISSING', 'MuAPI Key não configurada.' );
        }

        $base_url = esc_url_raw( (string) ( $settings['muApiBaseUrl'] ?? self::DEFAULT_BASE_URL ) );
        if ( empty( $base_url ) ) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $model = sanitize_text_field( (string) ( $settings['muApiModel'] ?? self::DEFAULT_MODEL ) );
        if ( empty( $model ) ) {
            $model = self::DEFAULT_MODEL;
        }

        return array(
            'api_key'  => $api_key,
            'base_url' => untrailingslashit( $base_url ),
            'model'    => ltrim( $model, '/' ),
        );
    }

    private static function prepare_image_url( $image_url, array $config ) {
        $image_url = trim( (string) $image_url );
        if ( '' === $image_url ) {
            return new WP_Error( 'UGC_IMAGE_MISSING', 'Escolha uma imagem de referência para gerar o UGC.' );
        }

        if ( preg_match( '#^https?://#i', $image_url ) ) {
            return esc_url_raw( $image_url );
        }

        if ( 0 !== strpos( $image_url, 'data:image/' ) ) {
            return new WP_Error( 'UGC_IMAGE_INVALID', 'Imagem de referência inválida para UGC.' );
        }

        return self::upload_data_url( $image_url, $config );
    }

    private static function upload_data_url( $data_url, array $config ) {
        if ( ! class_exists( 'CURLFile' ) ) {
            return new WP_Error( 'MUAPI_UPLOAD_UNAVAILABLE', 'Upload MuAPI indisponível neste servidor.' );
        }

        if ( ! preg_match( '#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $data_url, $matches ) ) {
            return new WP_Error( 'MUAPI_UPLOAD_INVALID_DATA_URL', 'Imagem base64 inválida para upload UGC.' );
        }

        $mime = sanitize_mime_type( $matches[1] );
        $binary = base64_decode( $matches[2], true );
        if ( false === $binary ) {
            return new WP_Error( 'MUAPI_UPLOAD_DECODE_FAILED', 'Não foi possível preparar a imagem para MuAPI.' );
        }

        $extension = 'jpg';
        if ( false !== strpos( $mime, 'png' ) ) {
            $extension = 'png';
        } elseif ( false !== strpos( $mime, 'webp' ) ) {
            $extension = 'webp';
        }

        $tmp = wp_tempnam( 'stlai-ugc-image.' . $extension );
        if ( ! $tmp ) {
            return new WP_Error( 'MUAPI_UPLOAD_TEMP_FAILED', 'Não foi possível criar arquivo temporário UGC.' );
        }

        file_put_contents( $tmp, $binary );

        $response = wp_remote_post(
            trailingslashit( $config['base_url'] ) . 'api/v1/upload_file',
            array(
                'headers' => array( 'x-api-key' => $config['api_key'] ),
                'body'    => array( 'file' => new CURLFile( $tmp, $mime, 'stlai-ugc-image.' . $extension ) ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        @unlink( $tmp );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'MUAPI_UPLOAD_FAILED', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'MUAPI_UPLOAD_BAD_RESPONSE', self::safe_error_message( $data, 'MuAPI não aceitou o upload da imagem.' ) );
        }

        $url = self::extract_video_url( $data );
        if ( ! $url ) {
            $url = esc_url_raw( (string) ( $data['file_url'] ?? ( $data['data']['file_url'] ?? '' ) ) );
        }
        if ( ! $url ) {
            return new WP_Error( 'MUAPI_UPLOAD_URL_MISSING', 'MuAPI não retornou URL da imagem enviada.' );
        }

        return $url;
    }

    private static function endpoint_url( $base_url, $model ) {
        if ( preg_match( '#^https?://#i', $model ) ) {
            return esc_url_raw( $model );
        }
        return trailingslashit( $base_url ) . 'api/v1/' . ltrim( $model, '/' );
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
            $data['output']['url'] ?? '',
            $data['data']['url'] ?? '',
            $data['data']['outputs'][0] ?? '',
            $data['data']['output']['url'] ?? '',
            $data['file_url'] ?? '',
        );

        foreach ( $candidates as $candidate ) {
            if ( is_string( $candidate ) && preg_match( '#^https?://#i', $candidate ) ) {
                return esc_url_raw( $candidate );
            }
        }

        return '';
    }

    private static function normalize_status( $status ) {
        $status = sanitize_key( $status );
        if ( in_array( $status, array( 'completed', 'succeeded', 'success' ), true ) ) {
            return 'ready';
        }
        if ( in_array( $status, array( 'failed', 'error' ), true ) ) {
            return 'failed';
        }
        return 'processing';
    }

    private static function message_for_status( $status ) {
        if ( 'ready' === $status ) {
            return 'Vídeo UGC pronto.';
        }
        if ( 'failed' === $status ) {
            return 'Não foi possível gerar o vídeo UGC.';
        }
        return 'Gerando vídeo UGC.';
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
