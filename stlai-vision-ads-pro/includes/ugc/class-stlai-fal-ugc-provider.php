<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Fal_UGC_Provider {
    const DEFAULT_BASE_URL = 'https://fal.run';
    const DEFAULT_MODEL = 'bytedance/seedance-2.0/image-to-video';
    const DEFAULT_POLL_ENDPOINT = '/{model}/requests/{id}';
    const DEFAULT_TIMEOUT = 120;

    public static function start_job( array $payload ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $body = array(
            'prompt'       => (string) ( $payload['prompt'] ?? '' ),
            'image_url'    => (string) ( $payload['image_url'] ?? '' ),
            'duration'     => (int) ( $payload['duration'] ?? 5 ),
            'resolution'   => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
            'aspect_ratio' => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
        );

        $response = wp_remote_post(
            self::model_url( $config ),
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Key ' . $config['api_key'],
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'FAL_START_REQUEST_FAILED', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'FAL_START_FAILED', self::safe_error_message( $data, 'Fal.ai não iniciou o vídeo UGC.' ) );
        }

        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $operation_id = STLAI_UGC_Job_Service::extract_provider_operation_id( $data );
        $status_url = esc_url_raw( (string) ( $data['status_url'] ?? ( $data['data']['status_url'] ?? '' ) ) );
        $result_url = esc_url_raw( (string) ( $data['response_url'] ?? ( $data['result_url'] ?? ( $data['data']['response_url'] ?? '' ) ) ) );
        if ( ! $operation_id && $status_url ) {
            $operation_id = self::id_from_url( $status_url );
        }
        if ( ! $operation_id && ! $video_url ) {
            return new WP_Error( 'FAL_OPERATION_ID_MISSING', 'Fal.ai não retornou request_id nem vídeo pronto.' );
        }

        return array(
            'success'      => true,
            'operation_id' => $operation_id,
            'request_id'   => $operation_id,
            'status'       => $video_url ? 'ready' : 'processing',
            'video_url'    => $video_url,
            'provider'     => 'fal',
            'model'        => $config['model'],
            'status_url'   => $status_url ?: ( $operation_id ? self::poll_url( $config, $operation_id ) : '' ),
            'result_url'   => $result_url ?: ( $operation_id ? self::poll_url( $config, $operation_id ) : '' ),
            'raw_status'   => STLAI_UGC_Job_Service::extract_provider_status( $data ) ?: 'processing',
            'endpoint_used' => self::safe_endpoint_label( $config['model'] ),
        );
    }

    public static function poll_job( $operation_id, array $job = array() ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $operation_id = sanitize_text_field( $operation_id );
        $url = esc_url_raw( (string) ( $job['status_url'] ?? '' ) );
        if ( ! $url ) {
            $url = self::poll_url( $config, $operation_id );
        }
        if ( empty( $operation_id ) && empty( $url ) ) {
            return new WP_Error( 'FAL_EMPTY_OPERATION_ID', 'request_id UGC vazio.' );
        }

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array( 'Authorization' => 'Key ' . $config['api_key'] ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'FAL_POLL_REQUEST_FAILED', $response->get_error_message() );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $http_status < 200 || $http_status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'FAL_POLL_FAILED', self::safe_error_message( $data, 'Fal.ai não retornou o status do vídeo UGC.' ) );
        }

        $raw_status = STLAI_UGC_Job_Service::extract_provider_status( $data );
        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $status = STLAI_UGC_Job_Service::normalize_provider_status( $raw_status, $video_url );

        return array(
            'success'      => true,
            'status'       => $status,
            'video_url'    => $video_url,
            'raw_status'   => $raw_status,
            'provider'     => 'fal',
            'model'        => $config['model'],
            'operation_id' => $operation_id,
            'request_id'   => $operation_id,
            'status_url'   => $url,
            'result_url'   => esc_url_raw( (string) ( $job['result_url'] ?? $url ) ),
            'message'      => STLAI_UGC_Job_Service::message_for_provider_status( $status, $video_url ),
        );
    }

    public static function config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();

        $api_key = trim( (string) ( $settings['ugcFalApiKey'] ?? '' ) );
        if ( '' === $api_key ) {
            return new WP_Error( 'FAL_KEY_MISSING', 'Fal.ai API Key não configurada.' );
        }

        $base_url = esc_url_raw( (string) ( $settings['ugcFalBaseUrl'] ?? self::DEFAULT_BASE_URL ) );
        $model = sanitize_text_field( (string) ( $settings['ugcFalModel'] ?? self::DEFAULT_MODEL ) );
        $poll_endpoint = sanitize_text_field( (string) ( $settings['ugcFalPollEndpoint'] ?? self::DEFAULT_POLL_ENDPOINT ) );

        return array(
            'api_key'       => $api_key,
            'base_url'      => untrailingslashit( $base_url ?: self::DEFAULT_BASE_URL ),
            'model'         => trim( $model ?: self::DEFAULT_MODEL, '/' ),
            'poll_endpoint' => $poll_endpoint ?: self::DEFAULT_POLL_ENDPOINT,
        );
    }

    private static function model_url( array $config ) {
        return trailingslashit( $config['base_url'] ) . ltrim( $config['model'], '/' );
    }

    private static function poll_url( array $config, $operation_id ) {
        $endpoint = str_replace(
            array( '{model}', '{id}' ),
            array( trim( $config['model'], '/' ), rawurlencode( $operation_id ) ),
            $config['poll_endpoint']
        );
        if ( preg_match( '#^https?://#i', $endpoint ) ) {
            return esc_url_raw( $endpoint );
        }
        return trailingslashit( $config['base_url'] ) . ltrim( $endpoint, '/' );
    }

    private static function id_from_url( $url ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $parts = array_values( array_filter( explode( '/', $path ) ) );
        return sanitize_text_field( end( $parts ) ?: '' );
    }

    private static function safe_endpoint_label( $endpoint ) {
        return sanitize_text_field( preg_replace( '#^https?://[^/]+#i', '', (string) $endpoint ) );
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
