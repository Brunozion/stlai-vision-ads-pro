<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Composer_Provider {
    const DEFAULT_MODE = 'external_service';
    const DEFAULT_TIMEOUT = 300;

    public static function compose( array $job ) {
        return self::start_composition( $job );
    }

    public static function start_composition( array $job ) {
        $settings = self::settings();
        $mode = self::composer_mode( self::setting_value( $settings, array( 'videoComposerMode', 'video_composer_mode' ), '' ) );

        if ( 'local_ffmpeg' === $mode ) {
            return self::local_ffmpeg_unavailable();
        }

        return self::start_external_job( $job, $settings, $mode );
    }

    public static function get_composition_status( $render_job_id ) {
        $settings = self::settings();
        $mode = self::composer_mode( self::setting_value( $settings, array( 'videoComposerMode', 'video_composer_mode' ), '' ) );

        if ( 'local_ffmpeg' === $mode ) {
            return self::local_ffmpeg_unavailable();
        }

        $endpoint = self::endpoint( $settings );
        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        $api_key = self::api_key( $settings );
        if ( is_wp_error( $api_key ) ) {
            return $api_key;
        }

        $render_job_id = sanitize_text_field( (string) $render_job_id );
        if ( empty( $render_job_id ) ) {
            return self::error( 'COMPOSER_STATUS_ERROR', 'Job de composição externo ausente.', 'render_job_id vazio.' );
        }

        $timeout = self::timeout( self::setting_value( $settings, array( 'videoComposerTimeout', 'video_composer_timeout' ), self::DEFAULT_TIMEOUT ) );
        $status_url = rtrim( $endpoint, '/' ) . '/' . rawurlencode( $render_job_id );
        self::log(
            'status_request',
            array(
                'render_job_id' => $render_job_id,
                'endpoint_configured' => ! empty( $endpoint ),
            )
        );
        $response = wp_remote_get(
            $status_url,
            array(
                'timeout' => min( 30, $timeout ),
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::log(
                'status_request_error',
                array(
                    'render_job_id' => $render_job_id,
                    'code'          => $response->get_error_code(),
                    'message'       => $response->get_error_message(),
                )
            );
            return self::error( 'COMPOSER_STATUS_ERROR', 'Não foi possível consultar o status da composição.', self::safe_debug( $response->get_error_message() ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        self::log(
            'status_response',
            array(
                'render_job_id' => $render_job_id,
                'http_status'   => $status_code,
            )
        );
        if ( $status_code < 200 || $status_code >= 300 ) {
            return self::error( 'COMPOSER_STATUS_ERROR', 'O serviço de composição retornou erro ao consultar o status.', 'HTTP ' . $status_code . '; ' . self::safe_debug( $body ) );
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return self::error( 'COMPOSER_STATUS_ERROR', 'O serviço de composição retornou status inválido.', self::safe_debug( $body ) );
        }

        if ( empty( $json['success'] ) ) {
            return self::error(
                self::safe_code( $json['code'] ?? 'COMPOSER_RENDER_ERROR' ),
                sanitize_text_field( $json['message'] ?? 'Não foi possível compor o vídeo final.' ),
                self::safe_debug( $json['debug'] ?? '' )
            );
        }

        $status = sanitize_key( $json['status'] ?? 'processing' );
        $final_video_url = self::normalize_media_url( $json['final_video_url'] ?? '' );
        self::log(
            'status_json',
            array(
                'render_job_id' => sanitize_text_field( $json['render_job_id'] ?? $render_job_id ),
                'status'        => $status,
                'final_video_url_exists' => ! empty( $final_video_url ),
                'code'          => $json['code'] ?? '',
                'message'       => $json['message'] ?? '',
            )
        );
        if ( 'ready' === $status && empty( $final_video_url ) ) {
            return self::error( 'FINAL_VIDEO_URL_MISSING', 'O serviço de composição não retornou a URL do vídeo final.', 'status ready sem final_video_url.' );
        }

        return array(
            'render_job_id'        => sanitize_text_field( $json['render_job_id'] ?? $render_job_id ),
            'status'               => $status,
            'progress'             => max( 0, min( 100, (int) ( $json['progress'] ?? 0 ) ) ),
            'message'              => sanitize_text_field( $json['message'] ?? 'Composição final em andamento...' ),
            'final_video_url'      => $final_video_url,
            'final_video_duration' => max( 0, (float) ( $json['duration'] ?? 0 ) ),
            'transition_used'       => sanitize_key( $json['transition_used'] ?? '' ),
            'fallback_used'         => sanitize_key( $json['fallback_used'] ?? '' ),
            'render_time_seconds'   => max( 0, (float) ( $json['render_time_seconds'] ?? 0 ) ),
            'background_music_used' => ! empty( $json['background_music_used'] ),
            'background_music_volume' => max( 0, min( 1, (float) ( $json['background_music_volume'] ?? 0 ) ) ),
            'fast_compose'          => ! empty( $json['fast_compose'] ),
            'composer_mode'        => $mode,
            'composer_provider'    => 'external_service',
            'composed_at'          => 'ready' === $status ? current_time( 'mysql' ) : '',
            'code'                 => ! empty( $json['code'] ) ? self::safe_code( $json['code'] ) : '',
            'debug'                => self::safe_debug( $json['debug'] ?? ( 'external_status=' . $status . '; progress=' . max( 0, min( 100, (int) ( $json['progress'] ?? 0 ) ) ) ) ),
        );
    }

    private static function start_external_job( array $job, array $settings, $mode ) {
        $endpoint = self::endpoint( $settings );
        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        $api_key = self::api_key( $settings );
        if ( is_wp_error( $api_key ) ) {
            return $api_key;
        }

        $payload = self::external_payload( $job );
        if ( is_wp_error( $payload ) ) {
            return $payload;
        }

        self::log(
            'start_composition',
            array(
                'job_id'              => $job['job_id'] ?? '',
                'clips_count'         => count( $payload['clips'] ?? array() ),
                'audio_url_exists'    => ! empty( $payload['audio_url'] ),
                'endpoint_configured' => ! empty( self::setting_value( $settings, array( 'videoComposerEndpoint', 'video_composer_endpoint' ), '' ) ),
                'endpoint_host'       => self::endpoint_host( $endpoint ),
                'endpoint_path'       => self::endpoint_path( $endpoint ),
                'composer_mode'       => $mode,
            )
        );

        $timeout = self::timeout( self::setting_value( $settings, array( 'videoComposerTimeout', 'video_composer_timeout' ), self::DEFAULT_TIMEOUT ) );
        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => min( 30, $timeout ),
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body'    => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::log(
                'start_request_error',
                array(
                    'job_id'  => $job['job_id'] ?? '',
                    'code'    => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                )
            );
            return self::error( 'COMPOSER_JOB_START_ERROR', 'Não foi possível iniciar a composição externa.', self::safe_debug( $response->get_error_message() ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        self::log(
            'start_response',
            array(
                'job_id'      => $job['job_id'] ?? '',
                'http_status' => $status_code,
            )
        );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return self::error( 'COMPOSER_JOB_START_ERROR', 'O serviço externo não aceitou o job de composição.', 'HTTP ' . $status_code . '; ' . self::safe_debug( $body ) );
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return self::error( 'COMPOSER_JOB_START_ERROR', 'O serviço externo retornou uma resposta inválida ao iniciar composição.', self::safe_debug( $body ) );
        }

        if ( empty( $json['success'] ) ) {
            self::log(
                'start_json_error',
                array(
                    'job_id'  => $job['job_id'] ?? '',
                    'status'  => $json['status'] ?? '',
                    'code'    => $json['code'] ?? '',
                    'message' => $json['message'] ?? '',
                )
            );
            return self::error(
                self::safe_code( $json['code'] ?? 'COMPOSER_JOB_START_ERROR' ),
                sanitize_text_field( $json['message'] ?? 'Não foi possível iniciar a composição externa.' ),
                self::safe_debug( $json['debug'] ?? '' )
            );
        }

        $status = sanitize_key( $json['status'] ?? 'queued' );
        $final_video_url = self::normalize_media_url( $json['final_video_url'] ?? '' );
        self::log(
            'start_json',
            array(
                'job_id'        => $job['job_id'] ?? '',
                'render_job_id' => $json['render_job_id'] ?? '',
                'status'        => $status,
                'has_render_job_id' => ! empty( $json['render_job_id'] ?? '' ),
                'final_video_url_exists' => ! empty( $final_video_url ),
                'code'          => $json['code'] ?? '',
            )
        );

        if ( 'ready' === $status ) {
            if ( empty( $final_video_url ) ) {
                return self::error( 'FINAL_VIDEO_URL_MISSING', 'O serviço de composição não retornou a URL do vídeo final.', 'resposta inicial ready sem final_video_url.' );
            }

            return array(
                'render_job_id'        => sanitize_text_field( $json['render_job_id'] ?? '' ),
                'status'               => 'ready',
                'progress'             => 100,
                'final_video_url'      => $final_video_url,
                'final_video_duration' => max( 0, (float) ( $json['duration'] ?? 0 ) ),
                'transition_used'       => sanitize_key( $json['transition_used'] ?? '' ),
                'fallback_used'         => sanitize_key( $json['fallback_used'] ?? '' ),
                'render_time_seconds'   => max( 0, (float) ( $json['render_time_seconds'] ?? 0 ) ),
                'background_music_used' => ! empty( $json['background_music_used'] ),
                'background_music_volume' => max( 0, min( 1, (float) ( $json['background_music_volume'] ?? 0 ) ) ),
                'fast_compose'          => ! empty( $json['fast_compose'] ),
                'composer_mode'         => $mode,
                'composer_provider'     => 'external_service',
                'message'               => sanitize_text_field( $json['message'] ?? 'Vídeo final composto com sucesso.' ),
                'debug'                 => 'external_sync_ready=true',
            );
        }

        $render_job_id = sanitize_text_field( $json['render_job_id'] ?? '' );
        if ( empty( $render_job_id ) ) {
            return self::error( 'COMPOSER_JOB_START_ERROR', 'O serviço externo não retornou o ID do job de composição.', 'render_job_id ausente.' );
        }

        return array(
            'render_job_id'     => $render_job_id,
            'status'            => in_array( $status, array( 'queued', 'processing' ), true ) ? $status : 'queued',
            'progress'          => 'processing' === $status ? 82 : 80,
            'composer_mode'     => $mode,
            'composer_provider' => 'external_service',
            'message'           => sanitize_text_field( $json['message'] ?? 'Composição recebida e iniciada.' ),
            'debug'             => 'external_job_started=' . $render_job_id,
        );
    }

    public static function configured_timeout() {
        $settings = self::settings();
        return self::timeout( self::setting_value( $settings, array( 'videoComposerTimeout', 'video_composer_timeout' ), self::DEFAULT_TIMEOUT ) );
    }

    public static function diagnostics() {
        $settings = self::settings();
        $endpoint = self::endpoint( $settings );
        $raw_endpoint = trim( (string) self::setting_value( $settings, array( 'videoComposerEndpoint', 'video_composer_endpoint' ), '' ) );

        return array(
            'composer_mode'                => self::composer_mode( self::setting_value( $settings, array( 'videoComposerMode', 'video_composer_mode' ), '' ) ),
            'composer_endpoint_configured' => ! empty( $raw_endpoint ) && ! is_wp_error( $endpoint ),
            'composer_endpoint_host'       => self::endpoint_host( is_wp_error( $endpoint ) ? $raw_endpoint : $endpoint ),
            'composer_endpoint_path'       => self::endpoint_path( is_wp_error( $endpoint ) ? $raw_endpoint : $endpoint ),
        );
    }

    private static function endpoint( array $settings ) {
        $raw_endpoint = trim( (string) self::setting_value( $settings, array( 'videoComposerEndpoint', 'video_composer_endpoint' ), '' ) );
        if ( empty( $raw_endpoint ) ) {
            return self::error( 'COMPOSER_ENDPOINT_MISSING', 'Configure o endpoint do serviço externo de composição de vídeo.', 'videoComposerEndpoint vazio.' );
        }

        $endpoint = esc_url_raw( $raw_endpoint );
        $parts = wp_parse_url( $endpoint );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return self::error( 'COMPOSER_ENDPOINT_INVALID', 'Configure um endpoint de composição válido.', 'endpoint sem scheme/host.' );
        }

        $path = rtrim( (string) ( $parts['path'] ?? '' ), '/' );
        $port = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
        $base_url = $parts['scheme'] . '://' . $parts['host'] . $port;
        if ( '/health' === $path || self::ends_with( $path, '/health' ) ) {
            return self::error( 'COMPOSER_ENDPOINT_INVALID', 'O endpoint de composição deve apontar para /render, não /health.', 'endpoint termina em /health.' );
        }

        if ( '' === $path || '/' === $path ) {
            $endpoint = $base_url . '/render';
        } elseif ( ! self::ends_with( $path, '/render' ) ) {
            return self::error( 'COMPOSER_ENDPOINT_INVALID', 'O endpoint de composição deve apontar para /render.', 'path=' . $path );
        } else {
            $endpoint = $base_url . $path;
        }

        return $endpoint;
    }

    private static function api_key( array $settings ) {
        $api_key = trim( (string) self::setting_value( $settings, array( 'videoComposerApiKey', 'video_composer_api_key' ), '' ) );
        if ( empty( $api_key ) ) {
            return self::error( 'COMPOSER_API_KEY_MISSING', 'Configure a API key do serviço externo de composição de vídeo.', 'videoComposerApiKey vazio.' );
        }

        return $api_key;
    }

    private static function external_payload( array $job ) {
        $audio_url = self::normalize_media_url( $job['audio_url'] ?? '' );
        if ( empty( $audio_url ) ) {
            return self::error( 'COMPOSER_INVALID_RESPONSE', 'A narração não está disponível para composição.', 'audio_url ausente no job.' );
        }

        $clips = array();
        foreach ( array_slice( self::normalized_clips( $job['clips'] ?? array() ), 0, 4 ) as $clip ) {
            $clips[] = array(
                'index' => (int) ( $clip['index'] ?? 0 ),
                'role'  => sanitize_key( $clip['role'] ?? '' ),
                'url'   => self::normalize_media_url( $clip['url'] ?? '' ),
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
            'enable_fade'                    => true,
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
            $url = self::normalize_media_url( $clip['url'] ?? '' );
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

    private static function normalize_media_url( $url ) {
        $url = trim( (string) $url );
        $url = trim( $url, "\"' \t\n\r\0\x0B" );
        $url = str_replace( '\\/', '/', $url );

        return esc_url_raw( $url );
    }

    private static function settings() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        return is_array( $settings ) ? $settings : array();
    }

    private static function setting_value( array $settings, array $keys, $default = '' ) {
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $settings ) && '' !== $settings[ $key ] && null !== $settings[ $key ] ) {
                return $settings[ $key ];
            }
        }

        return $default;
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

    private static function endpoint_host( $endpoint ) {
        $parts = wp_parse_url( (string) $endpoint );
        return sanitize_text_field( (string) ( $parts['host'] ?? '' ) );
    }

    private static function endpoint_path( $endpoint ) {
        $parts = wp_parse_url( (string) $endpoint );
        return sanitize_text_field( (string) ( $parts['path'] ?? '' ) );
    }

    private static function ends_with( $haystack, $needle ) {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ( '' === $needle ) {
            return true;
        }
        return substr( $haystack, -strlen( $needle ) ) === $needle;
    }

    private static function log( $event, array $context = array() ) {
        $safe = array();
        foreach ( $context as $key => $value ) {
            if ( preg_match( '/api|key|token|authorization/i', (string) $key ) ) {
                continue;
            }
            if ( is_bool( $value ) ) {
                $safe[ $key ] = $value;
            } elseif ( is_numeric( $value ) ) {
                $safe[ $key ] = $value;
            } else {
                $safe[ $key ] = self::safe_debug( (string) $value );
            }
        }
        error_log( '[STLAI video composer] ' . sanitize_key( $event ) . ' ' . wp_json_encode( $safe ) );
    }
}
