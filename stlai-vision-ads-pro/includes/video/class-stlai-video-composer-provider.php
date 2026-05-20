<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Composer_Provider {
    const DEFAULT_MODE = 'external_service';
    const DEFAULT_TIMEOUT = 300;

    public static function compose( array $job ) {
        $settings = self::settings();
        $mode = self::composer_mode( $settings['videoComposerMode'] ?? '' );

        if ( 'local_ffmpeg' === $mode ) {
            return self::local_ffmpeg_unavailable();
        }

        return self::compose_with_external_service( $job, $settings, $mode );
    }

    private static function compose_with_external_service( array $job, array $settings, $mode ) {
        $endpoint = esc_url_raw( trim( (string) ( $settings['videoComposerEndpoint'] ?? '' ) ) );
        if ( empty( $endpoint ) ) {
            return self::error( 'COMPOSER_ENDPOINT_MISSING', 'Configure o endpoint do serviço externo de composição de vídeo.', 'videoComposerEndpoint vazio.' );
        }

        $api_key = trim( (string) ( $settings['videoComposerApiKey'] ?? '' ) );
        if ( empty( $api_key ) ) {
            return self::error( 'COMPOSER_API_KEY_MISSING', 'Configure a API key do serviço externo de composição de vídeo.', 'videoComposerApiKey vazio.' );
        }

        $payload = self::external_payload( $job );
        if ( is_wp_error( $payload ) ) {
            return $payload;
        }

        $timeout = self::timeout( $settings['videoComposerTimeout'] ?? self::DEFAULT_TIMEOUT );
        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => $timeout,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body'    => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return self::error( 'COMPOSER_REQUEST_ERROR', 'Não foi possível conectar ao serviço de composição de vídeo.', self::safe_debug( $response->get_error_message() ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return self::error( 'COMPOSER_HTTP_ERROR', 'O serviço de composição retornou erro ao preparar o vídeo final.', 'HTTP ' . $status_code . '; ' . self::safe_debug( $body ) );
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return self::error( 'COMPOSER_INVALID_RESPONSE', 'O serviço de composição retornou uma resposta inválida.', self::safe_debug( $body ) );
        }

        if ( empty( $json['success'] ) ) {
            return self::error(
                self::safe_code( $json['code'] ?? 'COMPOSER_REQUEST_ERROR' ),
                sanitize_text_field( $json['message'] ?? 'O serviço externo não conseguiu compor o vídeo final.' ),
                self::safe_debug( $json['debug'] ?? '' )
            );
        }

        $final_video_url = esc_url_raw( $json['final_video_url'] ?? '' );
        if ( empty( $final_video_url ) ) {
            return self::error( 'FINAL_VIDEO_URL_MISSING', 'O serviço de composição não retornou a URL do vídeo final.', 'final_video_url ausente.' );
        }

        return array(
            'final_video_url'      => $final_video_url,
            'final_video_duration' => max( 0, (float) ( $json['duration'] ?? 0 ) ),
            'composer_mode'        => $mode,
            'composer_provider'    => 'external_service',
            'composed_at'          => current_time( 'mysql' ),
            'message'              => sanitize_text_field( $json['message'] ?? 'Vídeo final composto com sucesso.' ),
            'debug'                => 'external_service=ok; duration=' . max( 0, (float) ( $json['duration'] ?? 0 ) ),
        );
    }

    private static function external_payload( array $job ) {
        $audio_url = esc_url_raw( $job['audio_url'] ?? '' );
        if ( empty( $audio_url ) ) {
            return self::error( 'COMPOSER_INVALID_RESPONSE', 'A narração não está disponível para composição.', 'audio_url ausente no job.' );
        }

        $clips = array();
        foreach ( array_slice( self::normalized_clips( $job['clips'] ?? array() ), 0, 4 ) as $clip ) {
            $clips[] = array(
                'index' => (int) ( $clip['index'] ?? 0 ),
                'role'  => sanitize_key( $clip['role'] ?? '' ),
                'url'   => esc_url_raw( $clip['url'] ?? '' ),
            );
        }

        if ( count( $clips ) < 4 ) {
            return self::error( 'COMPOSER_INVALID_RESPONSE', 'Os 4 clipes precisam estar disponíveis antes da composição.', 'clips insuficientes no job.' );
        }

        return array(
            'job_id'                         => sanitize_text_field( $job['job_id'] ?? '' ),
            'format'                         => sanitize_text_field( $job['format'] ?? '9:16' ),
            'audio_url'                      => $audio_url,
            'clips'                          => $clips,
            'transition'                     => 'fade',
            'fade_duration'                  => 0.4,
            'repeat_clips_until_audio_ends'  => true,
            'trim_to_audio_duration'         => true,
            'remove_clip_audio'              => true,
        );
    }

    private static function normalized_clips( $clips ) {
        if ( ! is_array( $clips ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $clips as $clip ) {
            if ( ! is_array( $clip ) ) {
                continue;
            }

            $index = (int) ( $clip['index'] ?? 0 );
            $url = esc_url_raw( $clip['url'] ?? '' );
            if ( $index < 1 || $index > 4 || empty( $url ) ) {
                continue;
            }

            $normalized[ $index ] = array(
                'index' => $index,
                'role'  => sanitize_key( $clip['role'] ?? '' ),
                'url'   => $url,
            );
        }

        ksort( $normalized );
        return array_values( $normalized );
    }

    private static function local_ffmpeg_unavailable() {
        $settings = self::settings();
        $configured_path = trim( (string) ( $settings['ffmpegPath'] ?? '' ) );
        $tested = array_filter(
            array(
                $configured_path ? 'configured=' . $configured_path : '',
                '/usr/bin/ffmpeg',
                '/usr/local/bin/ffmpeg',
                'PATH:ffmpeg',
            )
        );

        return self::error(
            'LOCAL_FFMPEG_UNAVAILABLE',
            'FFmpeg local não está disponível neste plano de hospedagem. Use o serviço externo de composição.',
            'tested=' . implode( ', ', $tested )
        );
    }

    private static function settings() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        return is_array( $settings ) ? $settings : array();
    }

    private static function composer_mode( $mode ) {
        $mode = sanitize_key( (string) $mode );
        return in_array( $mode, array( 'external_service', 'local_ffmpeg' ), true ) ? $mode : self::DEFAULT_MODE;
    }

    private static function timeout( $timeout ) {
        $timeout = (int) $timeout;
        if ( $timeout <= 0 ) {
            return self::DEFAULT_TIMEOUT;
        }

        return min( 900, max( 30, $timeout ) );
    }

    private static function error( $code, $message, $debug = '' ) {
        return new WP_Error(
            self::safe_code( $code ),
            sanitize_text_field( $message ),
            array(
                'debug' => self::safe_debug( $debug ),
            )
        );
    }

    private static function safe_code( $code ) {
        $code = strtoupper( preg_replace( '/[^A-Z0-9_]/i', '_', (string) $code ) );
        return $code ?: 'COMPOSER_REQUEST_ERROR';
    }

    private static function safe_debug( $debug ) {
        $debug = sanitize_text_field( (string) $debug );
        $debug = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $debug );
        $debug = preg_replace( '/(api[_-]?key|token|authorization)\s*[:=]\s*[^;\s]+/i', '$1=[redacted]', $debug );
        return substr( $debug, 0, 600 );
    }
}
