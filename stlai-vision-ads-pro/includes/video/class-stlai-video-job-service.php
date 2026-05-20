<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'STLAI_Video_Composer_Provider' ) ) {
    require_once __DIR__ . '/class-stlai-video-composer-provider.php';
}

class STLAI_Video_Job_Service {
    const CLIP_DURATION = 8.0;
    const FADE_DURATION = 0.4;

    public static function create_job( array $payload ) {
        $validated = self::validate_payload( $payload );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $existing_job = null;
        if ( ! empty( $validated['job_id'] ) ) {
            $existing_job = STLAI_Video_Storage::get_job( $validated['job_id'] );
        }

        if ( $existing_job ) {
            $job = STLAI_Video_Storage::update_job(
                $existing_job['job_id'],
                array_merge(
                    $validated,
                    array(
                        'status'               => 'queued',
                        'progress'             => max( 10, (int) ( $existing_job['progress'] ?? 10 ) ),
                        'message'              => 'Retomando geração do vídeo.',
                        'audio_url'            => $existing_job['audio_url'] ?? '',
                        'audio_path'           => $existing_job['audio_path'] ?? '',
                        'audio_provider'       => $existing_job['audio_provider'] ?? '',
                        'audio_model'          => $existing_job['audio_model'] ?? '',
                        'audio_voice_id'       => $existing_job['audio_voice_id'] ?? '',
                        'clips'                => self::normalize_clip_list( $existing_job['clips'] ?? array() ),
                        'partial_clips'        => self::normalize_clip_list( $existing_job['partial_clips'] ?? ( $existing_job['clips'] ?? array() ) ),
                        'composition_status'   => 'pending',
                        'final_video_url'      => '',
                        'final_video_path'     => '',
                        'final_video_duration' => 0,
                        'final_video_debug'    => '',
                        'composer_mode'        => '',
                        'composer_provider'    => '',
                        'composer_status'      => '',
                        'render_job_id'        => '',
                        'composed_at'          => '',
                        'failed_clip_index'    => 0,
                        'failed_clip_role'     => '',
                        'error_code'           => '',
                        'error_message'        => '',
                        'error_debug'          => '',
                    )
                )
            );
        } else {
            $create_data = $validated;
            unset( $create_data['job_id'] );
            $job = STLAI_Video_Storage::create_job( $create_data );
        }

        $has_audio = ! empty( $job['audio_url'] );
        if ( ! $has_audio ) {
            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_audio',
                    'progress' => 20,
                    'message'  => 'Gerando narracao profissional.',
                )
            );

            $audio = STLAI_ElevenLabs_Provider::generate_audio( $validated['script'], $validated['narration_type'] );
            if ( is_wp_error( $audio ) ) {
                $error_data = $audio->get_error_data();
                STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array(
                        'status'        => 'error',
                        'progress'      => 0,
                        'message'       => $audio->get_error_message(),
                        'error_code'    => $audio->get_error_code(),
                        'error_message' => $audio->get_error_message(),
                        'error_debug'   => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                    )
                );

                return $audio;
            }

            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'         => 'generating_audio',
                    'progress'       => 24,
                    'message'        => 'Narracao gerada com sucesso.',
                    'audio_url'      => $audio['audio_url'] ?? '',
                    'audio_path'     => $audio['audio_path'] ?? '',
                    'audio_provider' => $audio['provider'] ?? '',
                    'audio_model'    => $audio['model'] ?? '',
                    'audio_voice_id' => $audio['voice_id'] ?? '',
                )
            );
        } else {
            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_audio',
                    'progress' => max( 24, (int) ( $job['progress'] ?? 24 ) ),
                    'message'  => 'Narracao existente reutilizada.',
                )
            );
        }

        $clips = self::normalize_clip_list( $job['clips'] ?? array() );
        $clip_roles = self::clip_roles();
        $selected_images = array_slice( $validated['selected_images'], 0, 4 );

        foreach ( $clip_roles as $offset => $role ) {
            $clip_index = $offset + 1;
            if ( self::has_ready_clip( $clips, $clip_index ) ) {
                continue;
            }

            STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_clip_' . $clip_index,
                    'progress' => 24 + ( $clip_index * 14 ),
                    'message'  => 'Gerando clipe ' . $clip_index . ' de 4.',
                    'clips'    => $clips,
                )
            );

            $clip = STLAI_Veo_Provider::generate_clip(
                array(
                    'image_url'            => $selected_images[ $offset ]['url'] ?? '',
                    'format'               => $validated['format'],
                    'script'               => $validated['script'],
                    'product_name'         => $validated['product_name'],
                    'product_description'  => $validated['product_description'],
                    'index'                => $clip_index,
                    'role'                 => $role['role'],
                    'role_label'           => $role['label'],
                    'role_direction'       => $role['direction'],
                )
            );

            if ( is_wp_error( $clip ) ) {
                $error_data = $clip->get_error_data();
                $safe_debug = self::clip_error_debug( $error_data, $role, $validated['format'] );
                $message = 'Não foi possível gerar o clipe ' . $clip_index . '.';
                $partial_clips = self::normalize_clip_list( $clips );
                STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array(
                        'status'            => 'clips_partial_error',
                        'progress'          => 24 + ( $clip_index * 14 ),
                        'message'           => $message,
                        'clips'             => $partial_clips,
                        'partial_clips'     => $partial_clips,
                        'composition_status' => 'pending',
                        'failed_clip_index' => $clip_index,
                        'failed_clip_role'  => $role['role'],
                        'error_code'        => 'VEO_CLIP_' . $clip_index . '_ERROR',
                        'error_message'     => $message,
                        'error_debug'       => $safe_debug,
                    )
                );

                return new WP_Error(
                    'VEO_CLIP_' . $clip_index . '_ERROR',
                    $message,
                    array(
                        'debug'             => $safe_debug,
                        'job_id'            => $job['job_id'],
                        'status'            => 'clips_partial_error',
                        'audio_url'         => $job['audio_url'] ?? '',
                        'clips'             => $partial_clips,
                        'partial_clips'     => $partial_clips,
                        'failed_clip'       => $clip_index,
                        'failed_clip_index' => $clip_index,
                        'failed_clip_role'  => $role['role'],
                    )
                );
            }

            $clips[] = self::public_clip_data( $clip );
            STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_clip_' . $clip_index,
                    'progress' => 24 + ( $clip_index * 14 ),
                    'message'  => 'Clipe ' . $clip_index . ' gerado com sucesso.',
                    'clips'    => $clips,
                )
            );
        }

        $clips = self::normalize_clip_list( $clips );
        if ( count( $clips ) < 4 ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'ready_for_composition',
                    'progress'           => 82,
                    'message'            => 'Aguardando todos os clipes para compor o vídeo final.',
                    'clips'              => $clips,
                    'partial_clips'      => $clips,
                    'composition_status' => 'pending',
                )
            );
        }

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => 'clips_ready',
                'progress'           => 86,
                'message'            => '4 clipes gerados. Preparando composição final.',
                'clips'              => $clips,
                'partial_clips'      => array(),
                'composition_status' => 'pending',
                'final_video_url'    => '',
                'thumbnail_url'      => '',
            )
        );

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => 'composing_final_video',
                'progress'           => 88,
                'message'            => 'Compondo vídeo final...',
                'composition_status' => 'processing',
            )
        );

        $composer = STLAI_Video_Composer_Provider::start_composition( $job );
        if ( is_wp_error( $composer ) ) {
            $error_data = $composer->get_error_data();
            $pending_codes = array(
                'COMPOSER_ENDPOINT_MISSING',
                'COMPOSER_API_KEY_MISSING',
                'LOCAL_FFMPEG_UNAVAILABLE',
            );
            $is_pending_config = in_array( $composer->get_error_code(), $pending_codes, true );
            $fallback_status = $is_pending_config ? 'composition_pending' : 'composition_error';
            $fallback_composition_status = $is_pending_config ? 'pending' : 'error';
            $fallback_message = $is_pending_config
                ? 'Narração e clipes preparados. A composição final está pendente.'
                : $composer->get_error_message();

            STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => $fallback_status,
                    'progress'           => 88,
                    'message'            => $fallback_message,
                    'clips'              => $clips,
                    'partial_clips'      => array(),
                    'composition_status' => $fallback_composition_status,
                    'final_video_url'    => '',
                    'final_video_path'   => '',
                    'final_video_duration' => 0,
                    'composer_mode'      => '',
                    'composer_provider'  => '',
                    'composer_status'    => $fallback_composition_status,
                    'render_job_id'      => '',
                    'composed_at'        => '',
                    'error_code'         => $composer->get_error_code(),
                    'error_message'      => $composer->get_error_message(),
                    'error_debug'        => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                )
            );

            return new WP_Error(
                $composer->get_error_code(),
                $composer->get_error_message(),
                array(
                    'debug'              => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                    'job_id'             => $job['job_id'],
                    'status'             => $fallback_status,
                    'composition_status' => $fallback_composition_status,
                    'audio_url'          => $job['audio_url'] ?? '',
                    'clips'              => $clips,
                )
            );
        }

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'               => 'composition_queued',
                'progress'             => 90,
                'message'              => 'Composição final em andamento...',
                'clips'                => $clips,
                'composition_status'   => 'queued',
                'composer_status'      => $composer['status'] ?? 'queued',
                'render_job_id'        => $composer['render_job_id'] ?? '',
                'final_video_url'      => '',
                'final_video_path'     => '',
                'final_video_duration' => 0,
                'final_video_debug'    => $composer['debug'] ?? '',
                'composer_mode'        => $composer['composer_mode'] ?? '',
                'composer_provider'    => $composer['composer_provider'] ?? '',
                'composed_at'          => '',
                'thumbnail_url'        => '',
                'error_code'           => '',
                'error_message'        => '',
                'error_debug'          => '',
            )
        );
    }

    public static function get_status( $job_id ) {
        $job = STLAI_Video_Storage::get_job( $job_id );
        if ( ! $job ) {
            return new WP_Error( 'stlai_video_job_not_found', 'Job de video nao encontrado.' );
        }

        return self::maybe_refresh_composition_status( $job );
    }

    public static function get_result( $job_id ) {
        $job = STLAI_Video_Storage::get_job( $job_id );
        if ( ! $job ) {
            return new WP_Error( 'stlai_video_job_not_found', 'Job de video nao encontrado.' );
        }

        return self::maybe_refresh_composition_status( $job );
    }

    public static function generate_test_veo_clip( array $payload ) {
        $validated = self::validate_test_clip_payload( $payload );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        return STLAI_Veo_Provider::generate_test_clip( $validated );
    }

    private static function maybe_refresh_composition_status( array $job ) {
        $status = $job['status'] ?? '';
        if ( ! in_array( $status, array( 'composition_queued', 'composition_processing', 'composing_final_video' ), true ) ) {
            return $job;
        }

        $render_job_id = $job['render_job_id'] ?? '';
        if ( empty( $render_job_id ) ) {
            return $job;
        }

        $remote = STLAI_Video_Composer_Provider::get_composition_status( $render_job_id );
        if ( is_wp_error( $remote ) ) {
            $error_data = $remote->get_error_data();
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'composition_error',
                    'progress'           => max( 90, (int) ( $job['progress'] ?? 90 ) ),
                    'message'            => $remote->get_error_message(),
                    'composition_status' => 'error',
                    'composer_status'    => 'error',
                    'error_code'         => $remote->get_error_code(),
                    'error_message'      => $remote->get_error_message(),
                    'error_debug'        => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                )
            );
        }

        $remote_status = sanitize_key( $remote['status'] ?? 'processing' );
        if ( 'ready' === $remote_status ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'               => 'ready',
                    'progress'             => 100,
                    'message'              => $remote['message'] ?? 'Vídeo final composto com sucesso.',
                    'composition_status'   => 'complete',
                    'composer_status'      => 'ready',
                    'final_video_url'      => $remote['final_video_url'] ?? '',
                    'final_video_duration' => $remote['final_video_duration'] ?? 0,
                    'final_video_debug'    => $remote['debug'] ?? '',
                    'composer_mode'        => $remote['composer_mode'] ?? ( $job['composer_mode'] ?? '' ),
                    'composer_provider'    => $remote['composer_provider'] ?? ( $job['composer_provider'] ?? '' ),
                    'composed_at'          => $remote['composed_at'] ?? current_time( 'mysql' ),
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                )
            );
        }

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => 'queued' === $remote_status ? 'composition_queued' : 'composition_processing',
                'progress'           => max( 90, (int) ( $remote['progress'] ?? ( $job['progress'] ?? 90 ) ) ),
                'message'            => $remote['message'] ?? 'Composição final em andamento...',
                'composition_status' => 'queued' === $remote_status ? 'queued' : 'processing',
                'composer_status'    => $remote_status,
                'composer_mode'      => $remote['composer_mode'] ?? ( $job['composer_mode'] ?? '' ),
                'composer_provider'  => $remote['composer_provider'] ?? ( $job['composer_provider'] ?? '' ),
            )
        );
    }

    private static function compose_final_video( array $job ) {
        $ffmpeg = self::detect_ffmpeg_binary();
        if ( is_wp_error( $ffmpeg ) ) {
            return self::composition_error(
                'FFMPEG_NOT_AVAILABLE',
                'FFmpeg não está disponível para compor o vídeo final.',
                self::ffmpeg_error_debug( $ffmpeg )
            );
        }

        $audio_path = $job['audio_path'] ?? '';
        if ( empty( $audio_path ) || ! file_exists( $audio_path ) ) {
            return self::composition_error( 'FINAL_VIDEO_AUDIO_MISSING', 'Não foi possível localizar a narração para compor o vídeo final.', 'audio_path ausente.' );
        }

        $clips = array_values( array_filter( $job['clips'] ?? array(), 'is_array' ) );
        if ( count( $clips ) < 4 ) {
            return self::composition_error( 'FINAL_VIDEO_CLIPS_MISSING', 'Não foi possível localizar os 4 clipes para compor o vídeo final.', 'clips insuficientes.' );
        }

        $clip_paths = array();
        foreach ( array_slice( $clips, 0, 4 ) as $clip ) {
            $path = $clip['path'] ?? '';
            if ( empty( $path ) || ! file_exists( $path ) ) {
                return self::composition_error( 'FINAL_VIDEO_CLIPS_MISSING', 'Não foi possível localizar os clipes para compor o vídeo final.', 'clip path ausente.' );
            }
            $clip_paths[] = $path;
        }

        $audio_duration = self::probe_duration( $audio_path );
        if ( $audio_duration <= 0 ) {
            return self::composition_error( 'FINAL_VIDEO_AUDIO_DURATION_ERROR', 'Não foi possível medir a duração da narração.', 'ffprobe não retornou duração válida.' );
        }

        $sequence = self::clip_sequence_for_duration( $clip_paths, $audio_duration );
        $output = self::final_video_output_location();
        if ( is_wp_error( $output ) ) {
            return $output;
        }

        $format = sanitize_text_field( $job['format'] ?? '16:9' );
        list( $width, $height ) = self::dimensions_for_format( $format );
        $filter = self::build_composition_filter( count( $sequence ), $width, $height );
        $audio_index = count( $sequence );

        $cmd_parts = array( escapeshellarg( $ffmpeg['path'] ), '-y' );
        foreach ( $sequence as $clip_path ) {
            $cmd_parts[] = '-i';
            $cmd_parts[] = escapeshellarg( $clip_path );
        }
        $cmd_parts[] = '-i';
        $cmd_parts[] = escapeshellarg( $audio_path );
        $cmd_parts[] = '-filter_complex';
        $cmd_parts[] = escapeshellarg( $filter );
        $cmd_parts[] = '-map';
        $cmd_parts[] = escapeshellarg( '[vout]' );
        $cmd_parts[] = '-map';
        $cmd_parts[] = escapeshellarg( $audio_index . ':a:0' );
        $cmd_parts[] = '-t';
        $cmd_parts[] = escapeshellarg( self::format_seconds( $audio_duration ) );
        $cmd_parts[] = '-c:v';
        $cmd_parts[] = 'libx264';
        $cmd_parts[] = '-preset';
        $cmd_parts[] = 'veryfast';
        $cmd_parts[] = '-crf';
        $cmd_parts[] = '20';
        $cmd_parts[] = '-pix_fmt';
        $cmd_parts[] = 'yuv420p';
        $cmd_parts[] = '-c:a';
        $cmd_parts[] = 'aac';
        $cmd_parts[] = '-b:a';
        $cmd_parts[] = '192k';
        $cmd_parts[] = '-movflags';
        $cmd_parts[] = '+faststart';
        $cmd_parts[] = '-shortest';
        $cmd_parts[] = escapeshellarg( $output['path'] );
        $cmd_parts[] = '2>&1';

        $exec_result = self::run_shell_command( implode( ' ', $cmd_parts ) );
        if ( is_wp_error( $exec_result ) ) {
            return self::composition_error(
                'FINAL_VIDEO_COMPOSITION_ERROR',
                'Não foi possível compor o vídeo final.',
                self::ffmpeg_error_debug( $exec_result )
            );
        }

        $exec_output = $exec_result['output'];
        $exit_code = $exec_result['exit_code'];

        if ( 0 !== (int) $exit_code || ! file_exists( $output['path'] ) || filesize( $output['path'] ) <= 0 ) {
            $debug = 'FFmpeg exit code: ' . (int) $exit_code . '; ' . substr( implode( ' ', $exec_output ), -500 );
            return self::composition_error( 'FINAL_VIDEO_COMPOSITION_ERROR', 'Não foi possível compor o vídeo final.', $debug );
        }

        return array(
            'path'     => $output['path'],
            'url'      => $output['url'],
            'duration' => $audio_duration,
            'debug'    => 'ffmpeg=available; ffmpeg_path=' . $ffmpeg['path'] . '; clips=' . count( $sequence ) . '; fade=' . self::FADE_DURATION . '; audio_duration=' . self::format_seconds( $audio_duration ),
        );
    }

    private static function clip_sequence_for_duration( array $clip_paths, $target_duration ) {
        $sequence = $clip_paths;
        while ( self::sequence_duration( count( $sequence ) ) < $target_duration ) {
            foreach ( $clip_paths as $clip_path ) {
                $sequence[] = $clip_path;
                if ( self::sequence_duration( count( $sequence ) ) >= $target_duration ) {
                    break;
                }
            }
        }

        return $sequence;
    }

    private static function sequence_duration( $count ) {
        $count = max( 1, (int) $count );
        return ( $count * self::CLIP_DURATION ) - ( max( 0, $count - 1 ) * self::FADE_DURATION );
    }

    private static function build_composition_filter( $clip_count, $width, $height ) {
        $parts = array();
        for ( $i = 0; $i < $clip_count; $i++ ) {
            $parts[] = '[' . $i . ':v]scale=' . (int) $width . ':' . (int) $height . ':force_original_aspect_ratio=increase,crop=' . (int) $width . ':' . (int) $height . ',setsar=1,fps=30,format=yuv420p,trim=duration=' . self::format_seconds( self::CLIP_DURATION ) . ',setpts=PTS-STARTPTS[v' . $i . ']';
        }

        if ( 1 === (int) $clip_count ) {
            $parts[] = '[v0]copy[vout]';
            return implode( ';', $parts );
        }

        $previous = 'v0';
        for ( $i = 1; $i < $clip_count; $i++ ) {
            $out = ( $i === $clip_count - 1 ) ? 'vout' : 'x' . $i;
            $offset = self::format_seconds( $i * ( self::CLIP_DURATION - self::FADE_DURATION ) );
            $parts[] = '[' . $previous . '][v' . $i . ']xfade=transition=fade:duration=' . self::format_seconds( self::FADE_DURATION ) . ':offset=' . $offset . '[' . $out . ']';
            $previous = $out;
        }

        return implode( ';', $parts );
    }

    private static function probe_duration( $path ) {
        $ffprobe = self::find_binary( 'ffprobe' );
        if ( empty( $ffprobe ) || empty( $path ) || ! file_exists( $path ) ) {
            return 0.0;
        }

        $cmd = escapeshellarg( $ffprobe ) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg( $path ) . ' 2>/dev/null';
        $result = self::run_shell_command( $cmd );

        if ( is_wp_error( $result ) || 0 !== (int) $result['exit_code'] || empty( $result['output'][0] ) ) {
            return 0.0;
        }

        return max( 0.0, (float) $result['output'][0] );
    }

    private static function find_binary( $binary ) {
        if ( ! function_exists( 'escapeshellarg' ) || ! self::has_shell_runner() ) {
            return '';
        }

        $binary = preg_replace( '/[^a-z0-9_-]/i', '', (string) $binary );
        if ( empty( $binary ) ) {
            return '';
        }

        $result = self::run_shell_command( 'command -v ' . $binary . ' 2>/dev/null' );
        if ( is_wp_error( $result ) || 0 !== (int) $result['exit_code'] || empty( $result['output'][0] ) ) {
            return '';
        }

        return trim( (string) $result['output'][0] );
    }

    private static function detect_ffmpeg_binary() {
        if ( ! function_exists( 'escapeshellarg' ) || ! self::has_shell_runner() ) {
            return self::ffmpeg_detection_error( 'FFMPEG_EXEC_DISABLED', 'Funções PHP para executar comandos indisponíveis.', array() );
        }

        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $configured_path = trim( (string) ( is_array( $settings ) ? ( $settings['ffmpegPath'] ?? '' ) : '' ) );
        $tested = array();
        $candidates = array();

        if ( ! empty( $configured_path ) ) {
            $candidates[] = array(
                'label' => 'configured',
                'path'  => $configured_path,
            );
        }

        $candidates[] = array( 'label' => 'default', 'path' => '/usr/bin/ffmpeg' );
        $candidates[] = array( 'label' => 'default', 'path' => '/usr/local/bin/ffmpeg' );

        foreach ( $candidates as $candidate ) {
            $result = self::validate_ffmpeg_candidate( $candidate['path'], $candidate['label'] );
            $tested[] = $result['debug'];
            if ( ! empty( $result['ok'] ) ) {
                return array(
                    'path'  => $result['path'],
                    'debug' => implode( '; ', $tested ),
                );
            }
        }

        $command_result = self::run_shell_command( 'command -v ffmpeg 2>/dev/null' );
        if ( ! is_wp_error( $command_result ) && 0 === (int) $command_result['exit_code'] && ! empty( $command_result['output'][0] ) ) {
            $shell_path = trim( (string) $command_result['output'][0] );
            $result = self::validate_ffmpeg_candidate( $shell_path, 'shell' );
            $tested[] = $result['debug'];
            if ( ! empty( $result['ok'] ) ) {
                return array(
                    'path'  => $result['path'],
                    'debug' => implode( '; ', $tested ),
                );
            }
        } else {
            $tested[] = 'shell:ffmpeg=not_found';
        }

        $debug = $tested;
        if ( ! self::any_candidate_exists( $candidates ) && ( is_wp_error( $command_result ) || empty( $command_result['output'][0] ) ) ) {
            return self::ffmpeg_detection_error( 'FFMPEG_NOT_FOUND', 'FFmpeg não encontrado nos caminhos testados.', $debug );
        }

        return self::ffmpeg_detection_error( 'FFMPEG_NOT_EXECUTABLE', 'FFmpeg existe, mas não executou `ffmpeg -version`.', $debug );
    }

    private static function validate_ffmpeg_candidate( $path, $label ) {
        $path = trim( (string) $path );
        $safe_label = sanitize_key( (string) $label );
        if ( empty( $path ) ) {
            return array(
                'ok'    => false,
                'path'  => '',
                'debug' => $safe_label . ':empty',
            );
        }

        if ( ! file_exists( $path ) ) {
            return array(
                'ok'    => false,
                'path'  => $path,
                'debug' => $safe_label . ':' . $path . '=not_found',
            );
        }

        $result = self::run_shell_command( escapeshellarg( $path ) . ' -version 2>&1' );
        if ( is_wp_error( $result ) ) {
            return array(
                'ok'    => false,
                'path'  => $path,
                'debug' => $safe_label . ':' . $path . '=exec_disabled',
            );
        }

        if ( 0 !== (int) $result['exit_code'] ) {
            return array(
                'ok'    => false,
                'path'  => $path,
                'debug' => $safe_label . ':' . $path . '=not_executable(exit=' . (int) $result['exit_code'] . ')',
            );
        }

        $first_line = isset( $result['output'][0] ) ? sanitize_text_field( (string) $result['output'][0] ) : 'version_ok';

        return array(
            'ok'    => true,
            'path'  => $path,
            'debug' => $safe_label . ':' . $path . '=ok(' . substr( $first_line, 0, 120 ) . ')',
        );
    }

    private static function any_candidate_exists( array $candidates ) {
        foreach ( $candidates as $candidate ) {
            $path = $candidate['path'] ?? '';
            if ( ! empty( $path ) && file_exists( $path ) ) {
                return true;
            }
        }

        return false;
    }

    private static function ffmpeg_detection_error( $code, $message, array $tested ) {
        $debug_parts = array_merge(
            array(
                'exec=' . ( self::php_function_available( 'exec' ) ? 'available' : 'disabled' ),
                'proc_open=' . ( self::php_function_available( 'proc_open' ) ? 'available' : 'disabled' ),
                'shell_exec=' . ( self::php_function_available( 'shell_exec' ) ? 'available' : 'disabled' ),
            ),
            $tested
        );

        return new WP_Error(
            $code,
            $message,
            array(
                'debug' => sanitize_text_field( implode( '; ', array_filter( $debug_parts ) ) ),
            )
        );
    }

    private static function ffmpeg_error_debug( WP_Error $error ) {
        $data = $error->get_error_data();
        $debug = is_array( $data ) ? (string) ( $data['debug'] ?? '' ) : '';
        return $error->get_error_code() . '; ' . $debug;
    }

    private static function run_shell_command( $command ) {
        if ( ! self::has_shell_runner() ) {
            return new WP_Error( 'FFMPEG_EXEC_DISABLED', 'Funções PHP para executar comandos indisponíveis.', array( 'debug' => 'exec/proc_open/shell_exec disabled' ) );
        }

        if ( self::php_function_available( 'exec' ) ) {
            $output = array();
            $exit_code = 1;
            exec( $command, $output, $exit_code );
            return array(
                'output'    => $output,
                'exit_code' => (int) $exit_code,
            );
        }

        if ( self::php_function_available( 'proc_open' ) ) {
            $descriptors = array(
                0 => array( 'pipe', 'r' ),
                1 => array( 'pipe', 'w' ),
                2 => array( 'pipe', 'w' ),
            );
            $process = proc_open( $command, $descriptors, $pipes );
            if ( ! is_resource( $process ) ) {
                return new WP_Error( 'FFMPEG_EXEC_DISABLED', 'Não foi possível abrir processo para executar comandos.', array( 'debug' => 'proc_open returned no resource' ) );
            }
            fclose( $pipes[0] );
            $stdout = stream_get_contents( $pipes[1] );
            $stderr = stream_get_contents( $pipes[2] );
            fclose( $pipes[1] );
            fclose( $pipes[2] );
            $exit_code = proc_close( $process );
            $output = preg_split( '/\r\n|\r|\n/', trim( $stdout . "\n" . $stderr ) );
            return array(
                'output'    => array_values( array_filter( $output, 'strlen' ) ),
                'exit_code' => (int) $exit_code,
            );
        }

        $output = shell_exec( $command );
        return array(
            'output'    => preg_split( '/\r\n|\r|\n/', trim( (string) $output ) ),
            'exit_code' => empty( $output ) ? 1 : 0,
        );
    }

    private static function has_shell_runner() {
        return self::php_function_available( 'exec' ) || self::php_function_available( 'proc_open' ) || self::php_function_available( 'shell_exec' );
    }

    private static function php_function_available( $function ) {
        if ( ! function_exists( $function ) ) {
            return false;
        }

        $disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
        return ! in_array( $function, $disabled, true );
    }

    private static function final_video_output_location() {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return self::composition_error( 'FINAL_VIDEO_SAVE_ERROR', 'Não foi possível salvar o vídeo final.', sanitize_text_field( $uploads['error'] ) );
        }

        $relative = 'stlai-vision-video/';
        $dir = trailingslashit( $uploads['basedir'] ) . $relative;
        $url = trailingslashit( $uploads['baseurl'] ) . $relative;
        if ( ! wp_mkdir_p( $dir ) ) {
            return self::composition_error( 'FINAL_VIDEO_SAVE_ERROR', 'Não foi possível salvar o vídeo final.', 'Falha ao criar pasta de video.' );
        }

        $filename = 'stlai-final-video-' . wp_generate_uuid4() . '.mp4';
        return array(
            'path' => trailingslashit( $dir ) . $filename,
            'url'  => trailingslashit( $url ) . $filename,
        );
    }

    private static function dimensions_for_format( $format ) {
        if ( '9:16' === $format || '1:1' === $format ) {
            return array( 1080, 1920 );
        }

        return array( 1920, 1080 );
    }

    private static function format_seconds( $seconds ) {
        return rtrim( rtrim( number_format( (float) $seconds, 3, '.', '' ), '0' ), '.' );
    }

    private static function composition_error( $code, $message, $debug = '' ) {
        return new WP_Error(
            $code,
            $message,
            array(
                'debug' => sanitize_text_field( (string) $debug ),
            )
        );
    }

    private static function normalize_clip_list( $clips ) {
        if ( ! is_array( $clips ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $clips as $clip ) {
            if ( ! is_array( $clip ) ) {
                continue;
            }

            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index < 1 || $index > 4 || empty( $clip['url'] ) ) {
                continue;
            }

            $normalized[ $index ] = array(
                'index'    => $index,
                'role'     => sanitize_key( $clip['role'] ?? '' ),
                'label'    => sanitize_text_field( $clip['label'] ?? '' ),
                'url'      => esc_url_raw( $clip['url'] ?? '' ),
                'path'     => sanitize_text_field( $clip['path'] ?? '' ),
                'duration' => (int) ( $clip['duration'] ?? 8 ),
                'muted'    => true,
            );
        }

        ksort( $normalized );
        return array_values( $normalized );
    }

    private static function has_ready_clip( array $clips, $clip_index ) {
        foreach ( $clips as $clip ) {
            if ( (int) ( $clip['index'] ?? 0 ) === (int) $clip_index && ! empty( $clip['url'] ) ) {
                return true;
            }
        }

        return false;
    }

    private static function clip_error_debug( $error_data, array $role, $format ) {
        $debug = is_array( $error_data ) ? (string) ( $error_data['debug'] ?? '' ) : '';
        $parts = array_filter(
            array(
                'failed_clip_role=' . sanitize_key( $role['role'] ?? '' ),
                'format=' . sanitize_text_field( (string) $format ),
                $debug,
            )
        );

        return sanitize_text_field( implode( '; ', $parts ) );
    }

    private static function validate_payload( array $payload ) {
        $images = self::sanitize_images( $payload['selected_images'] ?? array() );
        $count = count( $images );

        if ( $count < 4 || $count > 8 ) {
            return new WP_Error( 'stlai_video_invalid_images', 'Selecione de 4 a 8 imagens para o video.' );
        }

        $narration_type = sanitize_key( $payload['narration_type'] ?? '' );
        if ( ! in_array( $narration_type, array( 'persuasiva', 'emocional' ), true ) ) {
            return new WP_Error( 'stlai_video_invalid_narration', 'Tipo de narracao invalido.' );
        }

        $format = sanitize_text_field( $payload['format'] ?? '' );
        if ( ! in_array( $format, array( '16:9', '9:16', '1:1' ), true ) ) {
            return new WP_Error( 'stlai_video_invalid_format', 'Formato de video invalido.' );
        }

        $script = wp_kses_post( wp_unslash( $payload['script'] ?? '' ) );
        if ( empty( trim( wp_strip_all_tags( $script ) ) ) ) {
            return new WP_Error( 'EMPTY_NARRATION_TEXT', 'O roteiro da narração está vazio.' );
        }

        if ( strlen( trim( wp_strip_all_tags( $script ) ) ) > STLAI_ElevenLabs_Provider::MAX_SCRIPT_LENGTH ) {
            return new WP_Error( 'NARRATION_TOO_LONG', 'O roteiro está muito longo. Reduza o texto da narração.' );
        }

        return array(
            'job_id'               => sanitize_text_field( wp_unslash( $payload['job_id'] ?? '' ) ),
            'selected_images'      => $images,
            'narration_type'       => $narration_type,
            'format'               => $format,
            'script'               => $script,
            'product_name'         => sanitize_text_field( wp_unslash( $payload['product_name'] ?? '' ) ),
            'product_description'  => wp_kses_post( wp_unslash( $payload['product_description'] ?? '' ) ),
            'status'               => 'queued',
            'progress'             => 10,
            'message'              => 'Job de video criado com sucesso.',
            'clips'                => array(),
            'composition_status'    => 'pending',
            'error_code'           => '',
            'error_message'        => '',
        );
    }

    private static function clip_roles() {
        return array(
            array(
                'role'      => 'apresentacao_geral',
                'label'     => 'Clipe 1 — Apresentação geral',
                'direction' => 'Use the selected image as-is. Slow zoom in, stable product presentation. Do not create a new scene.',
            ),
            array(
                'role'      => 'uso_contexto',
                'label'     => 'Clipe 2 — Uso / contexto',
                'direction' => 'Use the selected image as-is. Gentle camera drift showing the product in its existing context. Do not create a new use case.',
            ),
            array(
                'role'      => 'detalhe_acabamento',
                'label'     => 'Clipe 3 — Detalhe / acabamento',
                'direction' => 'Use the selected image as-is. Subtle close-up feel, emphasizing texture and finish, but do not change crop too aggressively.',
            ),
            array(
                'role'      => 'hero_fechamento',
                'label'     => 'Clipe 4 — Hero / fechamento',
                'direction' => 'Use the selected image as-is. Premium slow zoom out or slight parallax, elegant final product shot. Preserve the exact product presentation, support, base, hook, display stand, surface, attachment point and display position. Keep the product anchored exactly as shown. Do not detach, lift, pull, hang, place, attach, fit, remove or transform the product. Do not show any hand interaction unless a hand is already clearly present in the source image.',
            ),
        );
    }

    private static function public_clip_data( array $clip ) {
        return array(
            'index'    => (int) ( $clip['index'] ?? 0 ),
            'role'     => sanitize_key( $clip['role'] ?? '' ),
            'label'    => sanitize_text_field( $clip['label'] ?? '' ),
            'url'      => esc_url_raw( $clip['url'] ?? '' ),
            'path'     => sanitize_text_field( $clip['path'] ?? '' ),
            'duration' => (int) ( $clip['duration'] ?? 8 ),
            'muted'    => true,
        );
    }

    private static function validate_test_clip_payload( array $payload ) {
        $images = self::sanitize_images( $payload['selected_images'] ?? array() );
        if ( empty( $images ) ) {
            return new WP_Error( 'MISSING_SELECTED_IMAGE', 'Selecione ao menos uma imagem para o teste.' );
        }

        $format = sanitize_text_field( wp_unslash( $payload['format'] ?? '' ) );
        if ( ! in_array( $format, array( '16:9', '9:16', '1:1' ), true ) ) {
            return new WP_Error( 'INVALID_VIDEO_FORMAT', 'Formato de vídeo inválido para o teste.' );
        }

        return array(
            'image_url'            => $images[0]['url'] ?? '',
            'format'               => $format,
            'script'               => wp_kses_post( wp_unslash( $payload['script'] ?? '' ) ),
            'product_name'         => sanitize_text_field( wp_unslash( $payload['product_name'] ?? '' ) ),
            'product_description'  => wp_kses_post( wp_unslash( $payload['product_description'] ?? '' ) ),
        );
    }

    private static function sanitize_images( $images ) {
        if ( is_string( $images ) ) {
            $decoded = json_decode( wp_unslash( $images ), true );
            $images = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $images ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $images as $image ) {
            $url = '';
            $label = '';

            if ( is_array( $image ) ) {
                $url = $image['url'] ?? '';
                $label = $image['label'] ?? '';
            } elseif ( is_string( $image ) ) {
                $url = $image;
            }

            $url = self::sanitize_image_url( $url );
            if ( empty( $url ) ) {
                continue;
            }

            $sanitized[] = array(
                'url'   => $url,
                'label' => sanitize_text_field( $label ),
            );
        }

        return $sanitized;
    }

    private static function sanitize_image_url( $url ) {
        $url = trim( (string) $url );
        $safe_url = esc_url_raw( $url );

        if ( ! empty( $safe_url ) ) {
            return $safe_url;
        }

        if ( preg_match( '/^data:image\/(png|jpe?g|webp);base64,[A-Za-z0-9+\/=]+$/', $url ) ) {
            return $url;
        }

        return '';
    }
}
