<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_ElevenLabs_Provider {
    const DEFAULT_MODEL = 'eleven_multilingual_v2';
    const MAX_SCRIPT_LENGTH = 2500;

    public static function generate_audio( $text, $narration_type ) {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $api_key = trim( (string) ( $settings['elevenLabsApiKey'] ?? '' ) );

        if ( empty( $api_key ) ) {
            return self::error( 'MISSING_ELEVENLABS_API_KEY', 'Configure a API key do ElevenLabs no painel.' );
        }

        $script = self::strip_narration_directions( $text );
        if ( empty( $script ) ) {
            return self::error( 'EMPTY_NARRATION_TEXT', 'O roteiro da narração está vazio.' );
        }

        if ( strlen( $script ) > self::MAX_SCRIPT_LENGTH ) {
            return self::error( 'NARRATION_TOO_LONG', 'O roteiro está muito longo. Reduza o texto da narração.' );
        }

        $voice_id = self::choose_voice_id( $settings, $narration_type );
        if ( is_wp_error( $voice_id ) ) {
            return $voice_id;
        }

        $model = trim( (string) ( $settings['elevenLabsModel'] ?? '' ) );
        if ( empty( $model ) ) {
            $model = self::DEFAULT_MODEL;
        }

        $response = wp_remote_post(
            'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode( $voice_id ),
            array(
                'timeout' => 60,
                'headers' => array(
                    'xi-api-key'   => $api_key,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'audio/mpeg',
                ),
                'body'    => wp_json_encode(
                    array(
                        'text'           => $script,
                        'model_id'       => $model,
                        'voice_settings' => self::voice_settings_for_type( $narration_type ),
                    )
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return self::error(
                'ELEVENLABS_REQUEST_ERROR',
                'Não foi possível conectar ao serviço de voz.',
                sanitize_text_field( $response->get_error_message() )
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );

        if ( 200 !== $status_code ) {
            $provider_message = self::extract_provider_message( $body );
            $debug = 'HTTP ' . $status_code;
            if ( ! empty( $provider_message ) ) {
                $debug .= ' - ' . $provider_message;
            }

            return self::error(
                'ELEVENLABS_HTTP_ERROR',
                'O serviço de voz recusou a solicitação.',
                $debug
            );
        }

        if ( empty( $body ) || false !== stripos( $content_type, 'application/json' ) ) {
            return self::error(
                'ELEVENLABS_INVALID_RESPONSE',
                'O serviço de voz não retornou um áudio válido.',
                'HTTP ' . $status_code . '; content-type: ' . sanitize_text_field( $content_type )
            );
        }

        $saved = self::save_audio_file( $body );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return array(
            'audio_url'  => $saved['url'],
            'audio_path' => $saved['path'],
            'provider'   => 'elevenlabs',
            'model'      => $model,
            'voice_id'   => $voice_id,
        );
    }

    private static function choose_voice_id( array $settings, $narration_type ) {
        $emotional = trim( (string) ( $settings['elevenLabsVoiceEmotional'] ?? '' ) );
        $persuasive = trim( (string) ( $settings['elevenLabsVoicePersuasive'] ?? '' ) );

        if ( 'emocional' === $narration_type ) {
            $voice_id = $emotional ?: $persuasive;
        } else {
            $voice_id = $persuasive ?: $emotional;
        }

        if ( empty( $voice_id ) ) {
            return self::error( 'MISSING_VOICE_ID', 'Configure o Voice ID da narração no painel.' );
        }

        return sanitize_text_field( $voice_id );
    }

    private static function strip_narration_directions( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( '/\[[^\]\r\n]{1,80}\]\s*/u', '', $text );
        $text = preg_replace( '/\b(thoughtful|warmly|short pause|delighted|excited|softly|amazed|chuckles|sighs|confident|impressed)\b\s*/iu', '', $text );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        $text = preg_replace( '/\s+([,.!?;:])/', '$1', $text );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( (string) $text );
    }

    private static function voice_settings_for_type( $narration_type ) {
        if ( 'emocional' === sanitize_key( $narration_type ) ) {
            return array(
                'stability'         => 0.34,
                'similarity_boost'  => 0.86,
                'style'             => 0.68,
                'use_speaker_boost' => true,
            );
        }

        return array(
            'stability'         => 0.40,
            'similarity_boost'  => 0.85,
            'style'             => 0.55,
            'use_speaker_boost' => true,
        );
    }

    private static function save_audio_file( $audio_binary ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return self::error( 'AUDIO_SAVE_ERROR', 'Não foi possível salvar o áudio gerado.', sanitize_text_field( $uploads['error'] ) );
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'stlai-vision-audio/';
        $url = trailingslashit( $uploads['baseurl'] ) . 'stlai-vision-audio/';

        if ( ! wp_mkdir_p( $dir ) ) {
            return self::error( 'AUDIO_SAVE_ERROR', 'Não foi possível salvar o áudio gerado.', 'Falha ao criar pasta de audio.' );
        }

        $filename = 'stlai-narration-' . wp_generate_uuid4() . '.mp3';
        $path = $dir . $filename;

        if ( false === file_put_contents( $path, $audio_binary ) ) {
            return self::error( 'AUDIO_SAVE_ERROR', 'Não foi possível salvar o áudio gerado.', 'Falha ao gravar arquivo mp3.' );
        }

        return array(
            'path' => $path,
            'url'  => $url . $filename,
        );
    }

    private static function extract_provider_message( $body ) {
        $decoded = json_decode( (string) $body, true );

        if ( is_array( $decoded ) ) {
            if ( ! empty( $decoded['detail']['message'] ) && is_string( $decoded['detail']['message'] ) ) {
                return sanitize_text_field( $decoded['detail']['message'] );
            }

            if ( ! empty( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
                return sanitize_text_field( $decoded['message'] );
            }

            if ( ! empty( $decoded['detail'] ) && is_string( $decoded['detail'] ) ) {
                return sanitize_text_field( $decoded['detail'] );
            }
        }

        $body = trim( wp_strip_all_tags( (string) $body ) );
        if ( empty( $body ) ) {
            return '';
        }

        return sanitize_text_field( substr( $body, 0, 300 ) );
    }

    private static function error( $code, $message, $debug = '' ) {
        return new WP_Error(
            $code,
            $message,
            array(
                'debug' => sanitize_text_field( (string) $debug ),
            )
        );
    }
}
