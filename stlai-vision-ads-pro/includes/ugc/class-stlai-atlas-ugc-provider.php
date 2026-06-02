<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Atlas_UGC_Provider {
    const DEFAULT_BASE_URL = 'https://api.atlascloud.ai';
    const DEFAULT_ENDPOINT = '/api/v1/model/generateVideo';
    const DEFAULT_POLL_ENDPOINT = '/api/v1/predictions/{id}';
    const DEFAULT_MODEL = 'bytedance/seedance-2.0/image-to-video';
    const DEFAULT_TIMEOUT = 120;

    public static function start_job( array $payload ) {
        $config = self::config();
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $body = array(
            'model'             => $config['model'],
            'prompt'            => (string) ( $payload['prompt'] ?? '' ),
            'image'             => (string) ( $payload['image_url'] ?? '' ),
            'last_image'        => (string) ( $payload['last_image_url'] ?? '' ),
            'duration'          => (int) ( $payload['duration'] ?? 5 ),
            'resolution'        => sanitize_key( (string) ( $payload['resolution'] ?? '720p' ) ),
            'ratio'             => self::ratio( $payload['aspect_ratio'] ?? '9:16' ),
            'generate_audio'    => (bool) $config['generate_audio'],
            'watermark'         => (bool) $config['watermark'],
            'return_last_frame' => (bool) $config['return_last_frame'],
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
            return new WP_Error( 'ATLAS_START_REQUEST_FAILED', $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'ATLAS_START_FAILED', self::safe_error_message( $data, 'Atlas Cloud não iniciou o vídeo UGC.' ) );
        }

        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $operation_id = STLAI_UGC_Job_Service::extract_provider_operation_id( $data );
        if ( ! $operation_id && ! $video_url ) {
            return new WP_Error( 'ATLAS_OPERATION_ID_MISSING', 'Atlas Cloud não retornou ID da operação nem vídeo pronto.' );
        }

        return array(
            'success'      => true,
            'operation_id' => $operation_id,
            'request_id'   => $operation_id,
            'status'       => $video_url ? 'ready' : 'processing',
            'video_url'    => $video_url,
            'provider'     => 'atlas',
            'model'        => $config['model'],
            'status_url'   => $operation_id ? self::poll_url( $config, $operation_id ) : '',
            'result_url'   => $operation_id ? self::poll_url( $config, $operation_id ) : '',
            'raw_status'   => sanitize_key( (string) ( $data['status'] ?? ( $data['data']['status'] ?? 'processing' ) ) ),
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
            return new WP_Error( 'ATLAS_EMPTY_OPERATION_ID', 'operation_id UGC vazio.' );
        }

        $response = wp_remote_get(
            self::poll_url( $config, $operation_id ),
            array(
                'headers' => array( 'Authorization' => 'Bearer ' . $config['api_key'] ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'ATLAS_POLL_REQUEST_FAILED', $response->get_error_message() );
        }

        $http_status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $http_status < 200 || $http_status >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'ATLAS_POLL_FAILED', self::safe_error_message( $data, 'Atlas Cloud não retornou o status do vídeo UGC.' ) );
        }

        return self::normalized_result( $data, $config, $operation_id );
    }

    public static function config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();

        $api_key = trim( (string) ( $settings['ugcAtlasApiKey'] ?? '' ) );
        if ( '' === $api_key ) {
            return new WP_Error( 'ATLAS_KEY_MISSING', 'Atlas Cloud API Key não configurada.' );
        }

        $base_url = esc_url_raw( (string) ( $settings['ugcAtlasBaseUrl'] ?? self::DEFAULT_BASE_URL ) );
        $endpoint = sanitize_text_field( (string) ( $settings['ugcAtlasEndpoint'] ?? self::DEFAULT_ENDPOINT ) );
        $poll_endpoint = sanitize_text_field( (string) ( $settings['ugcAtlasPollEndpoint'] ?? self::DEFAULT_POLL_ENDPOINT ) );
        $model = sanitize_text_field( (string) ( $settings['ugcAtlasModel'] ?? self::DEFAULT_MODEL ) );

        return array(
            'api_key'           => $api_key,
            'base_url'          => untrailingslashit( $base_url ?: self::DEFAULT_BASE_URL ),
            'endpoint'          => $endpoint ?: self::DEFAULT_ENDPOINT,
            'poll_endpoint'     => $poll_endpoint ?: self::DEFAULT_POLL_ENDPOINT,
            'model'             => $model ?: self::DEFAULT_MODEL,
            'generate_audio'    => ! empty( $settings['ugcAtlasGenerateAudio'] ),
            'watermark'         => ! empty( $settings['ugcAtlasWatermark'] ),
            'return_last_frame' => ! empty( $settings['ugcAtlasReturnLastFrame'] ),
        );
    }

    private static function normalized_result( array $data, array $config, $operation_id ) {
        $raw_status = STLAI_UGC_Job_Service::extract_provider_status( $data );
        $video_url = STLAI_UGC_Job_Service::extract_provider_video_url( $data );
        $status = STLAI_UGC_Job_Service::normalize_provider_status( $raw_status, $video_url );

        return array(
            'success'      => true,
            'status'       => $status,
            'video_url'    => $video_url,
            'raw_status'   => $raw_status,
            'provider'     => 'atlas',
            'model'        => $config['model'],
            'operation_id' => sanitize_text_field( $operation_id ),
            'request_id'   => sanitize_text_field( $operation_id ),
            'status_url'   => self::poll_url( $config, $operation_id ),
            'result_url'   => self::poll_url( $config, $operation_id ),
            'message'      => STLAI_UGC_Job_Service::message_for_provider_status( $status, $video_url ),
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

    private static function ratio( $aspect_ratio ) {
        $aspect_ratio = sanitize_text_field( (string) $aspect_ratio );
        return in_array( $aspect_ratio, array( '9:16', '16:9', '1:1', 'adaptive' ), true ) ? $aspect_ratio : '9:16';
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
