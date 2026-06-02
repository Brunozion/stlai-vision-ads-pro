<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Seedance_UGC_Provider {
    const DEFAULT_MODEL = 'bytedance/seedance-2.0/image-to-video';
    const DEFAULT_TIMEOUT = 120;

    public static function start_job( array $payload ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $body = array(
            'model'          => $config['model'],
            'prompt'         => (string) ( $payload['prompt'] ?? '' ),
            'image_url'      => (string) ( $payload['image_url'] ?? '' ),
            'image'          => (string) ( $payload['image_url'] ?? '' ),
            'duration'       => (int) ( $payload['duration'] ?? 5 ),
            'resolution'     => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
            'aspect_ratio'   => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
            'ratio'          => sanitize_text_field( (string) ( $payload['aspect_ratio'] ?? '9:16' ) ),
            'generate_audio' => false,
            'watermark'      => false,
        );

        $response = wp_remote_post(
            self::join_url( $config['base_url'], $config['endpoint'] ),
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $config['api_key'],
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'SEEDANCE_START_REQUEST_FAILED', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'SEEDANCE_START_FAILED', self::safe_error_message( $data, 'Seedance/BytePlus não iniciou o vídeo UGC.' ) );
        }

        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $operation_id = STLAI_UGC_Job_Service::extract_provider_operation_id( $data );
        if ( ! $operation_id && ! $video_url ) {
            return new WP_Error( 'SEEDANCE_OPERATION_ID_MISSING', 'Seedance/BytePlus não retornou ID da operação nem vídeo pronto.' );
        }

        return array(
            'success'      => true,
            'operation_id' => $operation_id,
            'request_id'   => $operation_id,
            'status'       => $video_url ? 'ready' : 'processing',
            'video_url'    => $video_url,
            'provider'     => 'seedance',
            'model'        => $config['model'],
            'status_url'   => $operation_id ? self::poll_url( $config, $operation_id ) : '',
            'result_url'   => $operation_id ? self::poll_url( $config, $operation_id ) : '',
            'raw_status'   => STLAI_UGC_Job_Service::extract_provider_status( $data ) ?: 'processing',
            'endpoint_used' => self::safe_endpoint_label( $config['endpoint'] ),
        );
    }

    public static function poll_job( $operation_id, array $job = array() ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $operation_id = sanitize_text_field( $operation_id );
        if ( empty( $operation_id ) ) {
            return new WP_Error( 'SEEDANCE_EMPTY_OPERATION_ID', 'operation_id UGC vazio.' );
        }

        $response = wp_remote_get(
            self::poll_url( $config, $operation_id ),
            array(
                'headers' => array( 'Authorization' => 'Bearer ' . $config['api_key'] ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'SEEDANCE_POLL_REQUEST_FAILED', $response->get_error_message() );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $http_status < 200 || $http_status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'SEEDANCE_POLL_FAILED', self::safe_error_message( $data, 'Seedance/BytePlus não retornou o status do vídeo UGC.' ) );
        }

        $raw_status = STLAI_UGC_Job_Service::extract_provider_status( $data );
        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $status = STLAI_UGC_Job_Service::normalize_provider_status( $raw_status, $video_url );

        return array(
            'success'      => true,
            'status'       => $status,
            'video_url'    => $video_url,
            'raw_status'   => $raw_status,
            'provider'     => 'seedance',
            'model'        => $config['model'],
            'operation_id' => $operation_id,
            'request_id'   => $operation_id,
            'status_url'   => self::poll_url( $config, $operation_id ),
            'result_url'   => self::poll_url( $config, $operation_id ),
            'message'      => STLAI_UGC_Job_Service::message_for_provider_status( $status, $video_url ),
        );
    }

    public static function config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();

        $api_key = trim( (string) ( $settings['seedanceApiKey'] ?? '' ) );
        if ( '' === $api_key ) {
            return new WP_Error( 'SEEDANCE_KEY_MISSING', 'Seedance API Key não configurada.' );
        }

        $base_url = esc_url_raw( (string) ( $settings['seedanceBaseUrl'] ?? '' ) );
        $endpoint = sanitize_text_field( (string) ( $settings['seedanceEndpoint'] ?? '' ) );
        $poll_endpoint = sanitize_text_field( (string) ( $settings['seedancePollEndpoint'] ?? '' ) );
        if ( empty( $base_url ) || empty( $endpoint ) || empty( $poll_endpoint ) ) {
            return new WP_Error( 'SEEDANCE_ENDPOINT_MISSING', 'Configure Base URL, Endpoint e Poll Endpoint do Seedance/BytePlus para gerar vídeos UGC.' );
        }

        $model = sanitize_text_field( (string) ( $settings['seedanceModel'] ?? self::DEFAULT_MODEL ) );
        return array(
            'api_key'       => $api_key,
            'base_url'      => untrailingslashit( $base_url ),
            'endpoint'      => $endpoint,
            'poll_endpoint' => $poll_endpoint,
            'model'         => $model ?: self::DEFAULT_MODEL,
        );
    }

    private static function poll_url( array $config, $operation_id ) {
        $endpoint = str_replace( '{id}', rawurlencode( $operation_id ), $config['poll_endpoint'] );
        return self::join_url( $config['base_url'], $endpoint );
    }

    private static function join_url( $base_url, $endpoint ) {
        if ( preg_match( '#^https?://#i', $endpoint ) ) {
            return esc_url_raw( $endpoint );
        }
        return trailingslashit( $base_url ) . ltrim( $endpoint, '/' );
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
