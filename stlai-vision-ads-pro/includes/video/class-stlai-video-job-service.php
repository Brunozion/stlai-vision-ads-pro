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
    const CLIP_MAX_ATTEMPTS = 3;
    const CLIP_MAX_CONCURRENT = 2;
    const CLIP_GENERATION_STALE_SECONDS = 75;
    const CLIP_STALE_SECONDS = 75;
    const HARD_COMPOSER_TIMEOUT_SECONDS = 1200;
    const DEFAULT_VIDEO_PROVIDER = 'gemini_veo';
    const DEFAULT_VIDEO_MODEL = 'veo-3.1-lite-generate-preview';
    const DEFAULT_OUTPUT_RESOLUTION = '720p';

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
            $script_pair = self::build_script_pair( $validated['script'], $validated['narration_type'], $validated['video_language'] );
            $job = STLAI_Video_Storage::update_job(
                $existing_job['job_id'],
                array_merge(
                    $validated,
                    array(
                        'status'               => 'queued',
                        'progress'             => max( 10, (int) ( $existing_job['progress'] ?? 10 ) ),
                        'progress_hint'        => max( 10, (int) ( $existing_job['progress_hint'] ?? ( $existing_job['progress'] ?? 10 ) ) ),
                        'message'              => 'Retomando geração do vídeo.',
                        'video_language'       => $validated['video_language'],
                        'narration_language'   => $validated['narration_language'],
                        'narration_style'      => $validated['narration_style'],
                        'video_provider'        => $validated['video_provider'],
                        'video_model'           => $validated['video_model'],
                        'output_resolution'     => $validated['output_resolution'],
                        'requested_resolution'  => $validated['requested_resolution'],
                        'effective_resolution'  => $validated['effective_resolution'],
                        'resolution_fallback_reason' => $validated['resolution_fallback_reason'],
                        'script_public'        => $script_pair['script_public'],
                        'script_tts'           => $script_pair['script_tts'],
                        'script_narration'     => $script_pair['script_tts'],
                        'audio_url'            => $existing_job['audio_url'] ?? '',
                        'audio_path'           => $existing_job['audio_path'] ?? '',
                        'audio_provider'       => $existing_job['audio_provider'] ?? '',
                        'audio_model'          => $existing_job['audio_model'] ?? '',
                        'audio_voice_id'       => $existing_job['audio_voice_id'] ?? '',
                        'clips'                => self::normalize_clip_list( $existing_job['clips'] ?? array() ),
                        'partial_clips'        => self::normalize_clip_list( $existing_job['partial_clips'] ?? ( $existing_job['clips'] ?? array() ) ),
                        'video_frames'         => self::normalize_video_frames( $existing_job['video_frames'] ?? self::video_frames_from_clips( $existing_job['clips'] ?? array() ) ),
                        'clip_jobs'            => $existing_job['clip_jobs'] ?? array(),
                        'clip_statuses'        => $existing_job['clip_statuses'] ?? array(),
                        'clip_attempts'        => $existing_job['clip_attempts'] ?? array(),
                        'clip_errors'          => array(),
                        'clip_started_at'      => $existing_job['clip_started_at'] ?? array(),
                        'clip_finished_at'     => $existing_job['clip_finished_at'] ?? array(),
                        'missing_clips'        => $existing_job['missing_clips'] ?? array( 1, 2, 3, 4 ),
                        'reset_composition'    => true,
                        'reset_clip_failures'  => true,
                        'composition_status'   => 'pending',
                        'current_clip_index'   => 0,
                        'current_clip_attempt' => 0,
                        'clip_retry_count'     => 0,
                        'last_clip_error'      => '',
                        'final_video_url'      => '',
                        'final_video_path'     => '',
                        'final_video_duration' => 0,
                        'final_video_debug'    => '',
                        'composer_mode'        => '',
                        'composer_provider'    => '',
                        'composer_status'      => '',
                        'render_job_id'        => '',
                        'composition_started_at' => '',
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
            $script_pair = self::build_script_pair( $validated['script'], $validated['narration_type'], $validated['video_language'] );
            $create_data['script_public'] = $script_pair['script_public'];
            $create_data['script_tts'] = $script_pair['script_tts'];
            $create_data['script_narration'] = $create_data['script_tts'];
            $job = STLAI_Video_Storage::create_job( $create_data );
        }

        $script_pair = self::build_script_pair( $validated['script'], $validated['narration_type'], $validated['video_language'] );
        $script_public = $script_pair['script_public'];
        $script_narration = $script_pair['script_tts'];
        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'script'             => $script_public,
                'script_public'      => $script_public,
                'script_tts'         => $script_narration,
                'script_narration'   => $script_narration,
                'script_public_exists' => ! empty( $script_public ),
                'script_tts_exists'  => ! empty( $script_narration ),
                'video_language'     => $validated['video_language'],
                'narration_language' => $validated['narration_language'],
                'narration_style'    => $validated['narration_style'],
                'video_provider'      => $validated['video_provider'],
                'video_model'         => $validated['video_model'],
                'output_resolution'   => $validated['output_resolution'],
                'requested_resolution' => $validated['requested_resolution'],
                'effective_resolution' => $validated['effective_resolution'],
                'resolution_fallback_reason' => $validated['resolution_fallback_reason'],
            )
        );

        $has_audio = ! empty( $job['audio_url'] );
        if ( ! $has_audio ) {
            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_audio',
                    'progress' => 18,
                    'progress_hint' => 18,
                    'message'  => 'Gerando narracao profissional.',
                )
            );

            $script_tts_used_for_tts = ! empty( $script_narration );
            $audio = STLAI_ElevenLabs_Provider::generate_audio( $script_tts_used_for_tts ? $script_narration : $script_public, $validated['narration_type'] );
            if ( is_wp_error( $audio ) && $script_narration !== $script_public && self::should_retry_audio_without_directions( $audio ) ) {
                $script_tts_used_for_tts = false;
                $audio = STLAI_ElevenLabs_Provider::generate_audio( $script_public, $validated['narration_type'] );
            }
            if ( is_wp_error( $audio ) ) {
                $error_data = $audio->get_error_data();
                STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array(
                        'status'        => 'error',
                        'progress'      => 0,
                        'progress_hint' => 0,
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
                    'progress'       => 22,
                    'progress_hint'  => 24,
                    'message'        => 'Narracao gerada com sucesso.',
                    'audio_url'      => $audio['audio_url'] ?? '',
                    'audio_path'     => $audio['audio_path'] ?? '',
                    'audio_provider' => $audio['provider'] ?? '',
                    'audio_model'    => $audio['model'] ?? '',
                    'audio_voice_id' => $audio['voice_id'] ?? '',
                    'script_tts_used_for_tts' => $script_tts_used_for_tts,
                    'script_public_exists' => ! empty( $script_public ),
                    'script_tts_exists' => ! empty( $script_narration ),
                )
            );
        } else {
            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'   => 'generating_audio',
                    'progress' => max( 22, (int) ( $job['progress'] ?? 22 ) ),
                    'progress_hint' => 24,
                    'message'  => 'Narracao existente reutilizada.',
                    'script_tts_used_for_tts' => ! empty( $script_narration ),
                    'script_public_exists' => ! empty( $script_public ),
                    'script_tts_exists' => ! empty( $script_narration ),
                )
            );
        }

        $job = self::prepare_clip_jobs_for_job( $job, $validated, true );

        if ( self::count_ready_clips( $job['clips'] ?? array() ) >= 4 ) {
            return self::start_composition_if_ready( $job );
        }

        $clip_progress = self::clip_jobs_progress( $job['clip_jobs'] ?? array() );
        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $job['clip_jobs'] ?? array() ),
                array(
                    'status'             => 'generating_clips',
                    'progress'           => max( 25, $clip_progress ),
                    'progress_hint'      => max( 25, $clip_progress ),
                    'message'            => 'Criando os clipes comerciais a partir das imagens no formato escolhido.',
                    'composition_status' => 'pending',
                    'composer_status'    => '',
                    'final_video_url'    => '',
                    'thumbnail_url'      => '',
                    'error_code'         => '',
                    'error_message'      => '',
                    'error_debug'        => '',
                )
            )
        );
    }

    public static function get_status( $job_id, array $client_ready_clips = array(), $client_ready_clips_received = null ) {
        $job = STLAI_Video_Storage::get_job( $job_id );
        if ( ! $job ) {
            return new WP_Error( 'stlai_video_job_not_found', 'Job de video nao encontrado.' );
        }

        $job = self::ensure_script_fields( $job );
        $job = self::reconcile_client_ready_clips( $job, $client_ready_clips, $client_ready_clips_received );
        $job = self::maybe_start_or_poll_composition( $job );
        if ( is_wp_error( $job ) ) {
            return $job;
        }
        if ( in_array( $job['status'] ?? '', array( 'ready', 'composition_error', 'composition_pending' ), true ) ) {
            return $job;
        }

        $job = self::prepare_clip_jobs_for_job( $job, array(), false );
        if ( self::count_ready_clips( $job['clips'] ?? array() ) >= 4 ) {
            return self::start_composition_if_ready( $job );
        }

        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $job['clips'] ?? array(), false );
        if ( self::has_processable_clip_jobs( $clip_jobs ) && ! self::has_final_clip_errors( $clip_jobs ) && in_array( sanitize_key( $job['status'] ?? '' ), array( 'clip_generation_error', 'clips_partial_error' ), true ) ) {
            $updated_job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'        => 'generating_clips',
                        'message'       => 'Retomando geração dos clipes pendentes.',
                        'error_code'    => '',
                        'error_message' => '',
                        'error_debug'   => '',
                    )
                )
            );
            $job = $updated_job ?: $job;
        }

        return self::maybe_process_clip_pipeline( $job );
    }

    private static function reconcile_client_ready_clips( array $job, array $client_ready_clips, $client_ready_clips_received = null ) {
        $received = null === $client_ready_clips_received ? count( $client_ready_clips ) : max( 0, (int) $client_ready_clips_received );
        $backend_ready_count = self::count_ready_clips(
            self::merge_clip_lists(
                self::normalize_clip_list( $job['clips'] ?? array() ),
                self::normalize_clip_list( $job['partial_clips'] ?? array() )
            )
        );

        if ( empty( $client_ready_clips ) ) {
            return $job;
        }

        $incoming = self::normalize_clip_list( $client_ready_clips );
        $accepted = count( $incoming );
        if ( 0 === $accepted ) {
            $backend_clips = self::merge_clip_lists(
                self::normalize_clip_list( $job['clips'] ?? array() ),
                self::normalize_clip_list( $job['partial_clips'] ?? array() )
            );
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'backend_clips_ready_count'    => $backend_ready_count,
                    'client_ready_clips_received'  => $received,
                    'client_ready_clips_accepted'  => 0,
                    'reconciled_clips_ready_count' => $backend_ready_count,
                    'reconciled_missing_clips'     => self::missing_clip_indexes( $backend_clips, $job['clip_jobs'] ?? array() ),
                    'reconciliation_used'          => false,
                )
            ) ?: $job;
        }

        $latest = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
        $clips = self::merge_clip_lists(
            self::normalize_clip_list( $latest['clips'] ?? array() ),
            self::normalize_clip_list( $latest['partial_clips'] ?? array() )
        );
        $clip_jobs = self::normalize_clip_jobs( $latest['clip_jobs'] ?? array(), $clips, false );
        $clips = self::merge_clip_lists( $clips, $incoming );

        foreach ( $incoming as $clip ) {
            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index < 1 || $index > 4 || empty( $clip['url'] ) ) {
                continue;
            }

            $clip_jobs = self::replace_clip_job(
                $clip_jobs,
                $index,
                array(
                    'status'      => 'ready',
                    'attempt'     => max( 1, (int) ( self::clip_job_by_index( $clip_jobs, $index )['attempt'] ?? 1 ) ),
                    'url'         => esc_url_raw( $clip['url'] ),
                    'error'       => '',
                    'finished_at' => current_time( 'mysql' ),
                )
            );
        }

        $clip_jobs = self::normalize_clip_jobs( $clip_jobs, $clips, false );
        $reconciled_ready_count = self::count_ready_clips( $clips );
        $reconciled_missing = self::missing_clip_indexes( $clips, $clip_jobs );

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $clip_jobs ),
                array(
                    'status'                         => $reconciled_ready_count >= 4 && ! empty( $latest['audio_url'] ) ? 'clips_ready' : ( $latest['status'] ?? 'generating_clips' ),
                    'progress'                       => self::clip_jobs_progress( $clip_jobs ),
                    'progress_hint'                  => self::clip_jobs_progress( $clip_jobs ),
                    'message'                        => $reconciled_ready_count >= 4 ? '4 clipes prontos. Preparando composição final.' : ( $latest['message'] ?? 'Clipes reconciliados com segurança.' ),
                    'clips'                          => $clips,
                    'partial_clips'                  => $clips,
                    'video_frames'                   => self::video_frames_from_clips( $clips ),
                    'backend_clips_ready_count'      => $backend_ready_count,
                    'client_ready_clips_received'    => $received,
                    'client_ready_clips_accepted'    => $accepted,
                    'reconciled_clips_ready_count'   => $reconciled_ready_count,
                    'reconciled_missing_clips'       => $reconciled_missing,
                    'reconciliation_used'            => $accepted > 0,
                    'auto_clip_generation_triggered' => false,
                    'auto_clip_generation_result'    => 'reconciled',
                    'skipped_reason'                 => '',
                )
            )
        ) ?: $latest;

        if ( $reconciled_ready_count >= 4 && ! empty( $job['audio_url'] ) && empty( $job['final_video_url'] ) ) {
            return self::maybe_start_or_poll_composition( $job );
        }

        return $job;
    }

    private static function ensure_script_fields( array $job ) {
        $language = self::sanitize_video_language( $job['video_language'] ?? ( $job['narration_language'] ?? 'pt-BR' ) );
        $voice_style = sanitize_key( $job['narration_style'] ?? ( $job['narration_type'] ?? 'persuasiva' ) );
        if ( ! in_array( $voice_style, array( 'persuasiva', 'emocional' ), true ) ) {
            $voice_style = 'persuasiva';
        }

        $source = $job['script_public'] ?? ( $job['script'] ?? '' );
        $script_pair = self::build_script_pair( $source, $voice_style, $language );
        $current_public = self::normalize_script_terms( $source, $language );
        $current_tts = trim( (string) ( $job['script_tts'] ?? ( $job['script_narration'] ?? '' ) ) );

        $updates = array();
        if ( empty( $current_public ) || $current_public !== ( $job['script_public'] ?? '' ) ) {
            $updates['script'] = $script_pair['script_public'];
            $updates['script_public'] = $script_pair['script_public'];
        }
        if ( empty( $current_tts ) ) {
            $updates['script_tts'] = $script_pair['script_tts'];
            $updates['script_narration'] = $script_pair['script_tts'];
        }
        if ( empty( $job['video_language'] ) ) {
            $updates['video_language'] = $language;
        }
        if ( empty( $job['narration_language'] ) ) {
            $updates['narration_language'] = $language;
        }
        if ( empty( $job['narration_style'] ) ) {
            $updates['narration_style'] = $voice_style;
        }
        $public_exists = ! empty( $script_pair['script_public'] );
        $tts_exists = ! empty( $current_tts ) || ! empty( $script_pair['script_tts'] );
        if ( (bool) ( $job['script_public_exists'] ?? false ) !== $public_exists ) {
            $updates['script_public_exists'] = $public_exists;
        }
        if ( (bool) ( $job['script_tts_exists'] ?? false ) !== $tts_exists ) {
            $updates['script_tts_exists'] = $tts_exists;
        }
        if ( $tts_exists && ! empty( $job['audio_url'] ) && ! isset( $job['script_tts_used_for_tts'] ) ) {
            $updates['script_tts_used_for_tts'] = true;
        }

        if ( empty( $updates ) ) {
            return $job;
        }

        return STLAI_Video_Storage::update_job( $job['job_id'], $updates ) ?: $job;
    }

    public static function get_result( $job_id ) {
        $job = STLAI_Video_Storage::get_job( $job_id );
        if ( ! $job ) {
            return new WP_Error( 'stlai_video_job_not_found', 'Job de video nao encontrado.' );
        }

        $job = self::ensure_script_fields( $job );
        $job = self::prepare_clip_jobs_for_job( $job, array(), false );
        return self::maybe_start_or_poll_composition( $job );
    }

    public static function generate_test_veo_clip( array $payload ) {
        $validated = self::validate_test_clip_payload( $payload );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        return STLAI_Veo_Provider::generate_test_clip( $validated );
    }

    public static function start_clip_job( $job_id, $clip_index ) {
        $job = STLAI_Video_Storage::get_job( $job_id );
        if ( ! $job ) {
            return new WP_Error( 'stlai_video_job_not_found', 'Job de video nao encontrado.' );
        }

        $job = self::ensure_script_fields( $job );
        $clip_index = (int) $clip_index;
        if ( $clip_index < 1 || $clip_index > 4 ) {
            return new WP_Error( 'stlai_video_invalid_clip_index', 'Clipe invalido para geração.' );
        }

        $job = self::maybe_start_or_poll_composition( $job );
        if ( is_wp_error( $job ) ) {
            return $job;
        }

        if ( ! empty( $job['final_video_url'] ) || 'ready' === ( $job['status'] ?? '' ) ) {
            return $job;
        }

        if ( empty( $job['audio_url'] ) ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'        => 'generating_audio',
                    'progress'      => max( 18, (int) ( $job['progress'] ?? 18 ) ),
                    'progress_hint' => 24,
                    'message'       => 'Aguardando narração antes de gerar os clipes.',
                )
            );
        }

        $job = self::prepare_clip_jobs_for_job( $job, array(), false );
        $clips = self::normalize_clip_list( $job['clips'] ?? array() );
        if ( self::has_ready_clip( $clips, $clip_index ) ) {
            if ( self::count_ready_clips( $clips ) >= 4 ) {
                return self::start_composition_if_ready( $job );
            }

            return $job;
        }

        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $clips, false );
        $active_generating_count = self::active_generating_count( $clip_jobs );
        $requested_job = self::clip_job_by_index( $clip_jobs, $clip_index );
        if ( $active_generating_count >= self::CLIP_MAX_CONCURRENT && ! self::is_active_generating_clip_job( is_array( $requested_job ) ? $requested_job : array() ) ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'        => 'generating_clips',
                        'message'       => 'Aguardando o clipe em geração terminar antes de iniciar o próximo.',
                        'active_generating_count' => $active_generating_count,
                        'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                        'concurrency_blocked' => true,
                        'next_clip_indexes' => array(),
                        'started_clip_indexes' => array(),
                        'auto_clip_generation_triggered' => false,
                        'auto_clip_generation_result' => 'skipped',
                        'skipped_reason' => 'clip_already_generating_not_stale',
                    )
                )
            );
        }
        foreach ( $clip_jobs as $clip_job ) {
            if ( (int) ( $clip_job['index'] ?? 0 ) !== $clip_index ) {
                continue;
            }

            $status = sanitize_key( $clip_job['status'] ?? 'pending' );
            if ( in_array( $status, array( 'generating', 'retrying' ), true ) && ! self::is_stale_retryable_clip_job( $clip_job ) ) {
                return $job;
            }
            break;
        }

        return self::process_single_clip_job( $job, $clip_index );
    }

    private static function maybe_process_clip_pipeline( array $job ) {
        $status = sanitize_key( $job['status'] ?? '' );
        if ( in_array( $status, array( 'ready', 'composition_queued', 'composition_processing', 'composing_final_video', 'composition_pending', 'composition_error' ), true ) ) {
            return $job;
        }

        if ( empty( $job['audio_url'] ) ) {
            return $job;
        }

        $job = self::prepare_clip_jobs_for_job( $job, array(), false );
        if ( self::count_ready_clips( $job['clips'] ?? array() ) >= 4 ) {
            return self::start_composition_if_ready( $job );
        }

        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $job['clips'] ?? array(), false );
        $active_generating_count = self::active_generating_count( $clip_jobs );
        if ( $active_generating_count >= self::CLIP_MAX_CONCURRENT ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'        => 'generating_clips',
                        'progress'      => self::clip_jobs_progress( $clip_jobs ),
                        'progress_hint' => self::clip_jobs_progress( $clip_jobs ),
                        'message'       => 'Aguardando o clipe em geração terminar antes de iniciar o próximo.',
                        'next_clip_index' => 0,
                        'next_clip_indexes' => array(),
                        'started_clip_indexes' => array(),
                        'next_clip_reason' => '',
                        'auto_clip_generation_triggered' => false,
                        'auto_clip_generation_result' => 'skipped',
                        'skipped_reason' => 'clip_already_generating_not_stale',
                        'active_generating_count' => $active_generating_count,
                        'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                        'concurrency_blocked' => true,
                        'stale_threshold_seconds' => self::CLIP_GENERATION_STALE_SECONDS,
                    )
                )
            );
        }
        if ( self::has_final_clip_errors( $clip_jobs ) ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'        => 'clip_generation_error',
                        'message'       => 'Um dos clipes falhou após as tentativas automáticas.',
                        'next_clip_index' => 0,
                        'next_clip_indexes' => array(),
                        'started_clip_indexes' => array(),
                        'next_clip_reason' => '',
                        'auto_clip_generation_triggered' => false,
                        'auto_clip_generation_result' => 'skipped',
                        'skipped_reason' => 'clip_error_final',
                    )
                )
            );
        }

        $next = self::next_processable_clip_job( $clip_jobs );
        if ( ! $next ) {
            $has_error = false;
            $active_generating_count = 0;
            foreach ( $clip_jobs as $clip_job ) {
                if ( in_array( sanitize_key( $clip_job['status'] ?? '' ), array( 'error', 'error_final' ), true ) ) {
                    $has_error = true;
                }
                if ( self::is_active_generating_clip_job( $clip_job ) ) {
                    $active_generating_count++;
                }
            }

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'        => $has_error ? 'clip_generation_error' : 'generating_clips',
                        'progress'      => self::clip_jobs_progress( $clip_jobs ),
                        'progress_hint' => self::clip_jobs_progress( $clip_jobs ),
                        'message'       => $has_error ? 'Um dos clipes falhou após as tentativas automáticas.' : 'Aguardando a próxima etapa de geração dos clipes.',
                        'next_clip_index' => 0,
                        'next_clip_reason' => '',
                        'auto_clip_generation_triggered' => false,
                        'auto_clip_generation_result' => 'skipped',
                        'skipped_reason' => $has_error ? 'clip_error_final' : ( $active_generating_count > 0 ? 'clip_already_generating_not_stale' : 'no_processable_clip' ),
                        'active_generating_count' => $active_generating_count,
                        'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                        'stale_threshold_seconds' => self::CLIP_GENERATION_STALE_SECONDS,
                    )
                )
            );
        }

        $next_index = (int) ( $next['index'] ?? 0 );
        $next_reason = self::next_clip_reason_for_job( $next );
        $next_will_retry = in_array( $next_reason, array( 'pending_stale_retry', 'pending_retryable_error', 'retry_clip' ), true );
        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'next_clip_index' => $next_index,
                'next_clip_indexes' => array( $next_index ),
                'started_clip_indexes' => array( $next_index ),
                'next_clip_reason' => $next_reason,
                'auto_clip_generation_triggered' => true,
                'auto_clip_generation_result' => 'processing',
                'skipped_reason' => '',
                'active_generating_count' => self::active_generating_count( $clip_jobs ),
                'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                'concurrency_blocked' => false,
                'retryable' => $next_will_retry,
                'will_retry' => $next_will_retry,
                'retry_reason' => $next_will_retry ? self::clip_retry_reason_from_summary( $next['error'] ?? '' ) : '',
                'error_final_reason' => '',
                'stale_threshold_seconds' => self::CLIP_GENERATION_STALE_SECONDS,
            )
        ) ?: $job;

        $result = self::process_single_clip_job( $job, $next_index );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $result_clips = self::normalize_clip_list( $result['clips'] ?? array() );
        $result_jobs = self::normalize_clip_jobs( $result['clip_jobs'] ?? array(), $result_clips, false );
        $result_status = self::has_ready_clip( $result_clips, $next_index ) ? 'ready' : 'skipped';
        foreach ( $result_jobs as $result_job ) {
            if ( (int) ( $result_job['index'] ?? 0 ) === $next_index && in_array( sanitize_key( $result_job['status'] ?? '' ), array( 'error', 'error_final' ), true ) ) {
                $result_status = 'error';
                break;
            }
        }

        return STLAI_Video_Storage::update_job(
            $result['job_id'],
            array(
                'next_clip_index' => $next_index,
                'next_clip_indexes' => array( $next_index ),
                'started_clip_indexes' => array( $next_index ),
                'next_clip_reason' => $next_reason,
                'auto_clip_generation_triggered' => true,
                'auto_clip_generation_result' => $result_status,
                'skipped_reason' => '',
                'active_generating_count' => self::active_generating_count( $result_jobs ),
                'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                'concurrency_blocked' => false,
                'stale_threshold_seconds' => self::CLIP_GENERATION_STALE_SECONDS,
            )
        ) ?: $result;
    }

    private static function prepare_clip_jobs_for_job( array $job, array $validated = array(), $reset_failed = false ) {
        $clips = self::merge_clip_lists(
            self::normalize_clip_list( $job['clips'] ?? array() ),
            self::normalize_clip_list( $job['partial_clips'] ?? array() )
        );
        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $clips, $reset_failed );
        $clips = self::merge_clip_lists( $clips, self::clips_from_clip_jobs( $clip_jobs ) );
        $clip_jobs = self::normalize_clip_jobs( $clip_jobs, $clips, $reset_failed );
        $video_frames = self::normalize_video_frames( $job['video_frames'] ?? self::video_frames_from_clips( $clips ) );

        $language = $validated['video_language'] ?? ( $job['video_language'] ?? 'pt-BR' );
        $script_pair = self::build_script_pair(
            $validated['script'] ?? ( $job['script_public'] ?? ( $job['script'] ?? '' ) ),
            $validated['narration_type'] ?? ( $job['narration_type'] ?? 'persuasiva' ),
            $language
        );
        $existing_tts = trim( (string) ( $validated['script_tts'] ?? '' ) );
        if ( empty( $existing_tts ) ) {
            $existing_tts = trim( (string) ( $job['script_tts'] ?? ( $job['script_narration'] ?? '' ) ) );
        }
        if ( empty( $existing_tts ) ) {
            $existing_tts = $script_pair['script_tts'];
        }

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $clip_jobs ),
                array(
                    'clips'          => $clips,
                    'partial_clips'  => $clips,
                    'video_frames'   => $video_frames,
                    'selected_images' => ! empty( $validated['selected_images'] ) ? $validated['selected_images'] : ( $job['selected_images'] ?? array() ),
                    'format'         => $validated['format'] ?? ( $job['format'] ?? '16:9' ),
                    'script'         => $script_pair['script_public'],
                    'script_public'  => $script_pair['script_public'],
                    'script_tts'     => $existing_tts,
                    'script_narration' => $existing_tts,
                    'script_public_exists' => ! empty( $script_pair['script_public'] ),
                    'script_tts_exists' => ! empty( $existing_tts ),
                    'video_language' => $validated['video_language'] ?? ( $job['video_language'] ?? 'pt-BR' ),
                    'narration_language' => $validated['narration_language'] ?? ( $job['narration_language'] ?? ( $job['video_language'] ?? 'pt-BR' ) ),
                    'narration_style' => $validated['narration_style'] ?? ( $job['narration_style'] ?? ( $job['narration_type'] ?? 'persuasiva' ) ),
                    'product_name'   => $validated['product_name'] ?? ( $job['product_name'] ?? '' ),
                    'product_description' => $validated['product_description'] ?? ( $job['product_description'] ?? '' ),
                )
            )
        );
    }

    private static function normalize_clip_jobs( $clip_jobs, array $clips = array(), $reset_failed = false ) {
        $by_index = array();
        if ( is_array( $clip_jobs ) ) {
            foreach ( $clip_jobs as $clip_job ) {
                if ( ! is_array( $clip_job ) ) {
                    continue;
                }

                $index = (int) ( $clip_job['index'] ?? 0 );
                if ( $index < 1 || $index > 4 ) {
                    continue;
                }

                $status = sanitize_key( $clip_job['status'] ?? 'pending' );
                if ( $reset_failed && in_array( $status, array( 'error', 'error_final', 'retrying', 'generating', 'queued' ), true ) ) {
                    $status = 'pending';
                }

                $by_index[ $index ] = array(
                    'index'       => $index,
                    'status'      => in_array( $status, array( 'pending', 'queued', 'generating', 'retrying', 'ready', 'error', 'error_final' ), true ) ? $status : 'pending',
                    'attempt'     => max( 0, (int) ( $clip_job['attempt'] ?? 0 ) ),
                    'url'         => esc_url_raw( $clip_job['url'] ?? '' ),
                    'error'       => $reset_failed ? '' : sanitize_text_field( $clip_job['error'] ?? '' ),
                    'started_at'  => sanitize_text_field( $clip_job['started_at'] ?? '' ),
                    'finished_at' => sanitize_text_field( $clip_job['finished_at'] ?? '' ),
                );
                $by_index[ $index ] = self::recover_premature_final_clip_job( $by_index[ $index ] );
                $by_index[ $index ] = self::recover_stale_clip_job( $by_index[ $index ] );
            }
        }

        foreach ( self::normalize_clip_list( $clips ) as $clip ) {
            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index < 1 || $index > 4 ) {
                continue;
            }

            $by_index[ $index ] = array(
                'index'       => $index,
                'status'      => 'ready',
                'attempt'     => max( 1, (int) ( $by_index[ $index ]['attempt'] ?? 1 ) ),
                'url'         => esc_url_raw( $clip['url'] ?? '' ),
                'error'       => '',
                'started_at'  => sanitize_text_field( $by_index[ $index ]['started_at'] ?? '' ),
                'finished_at' => sanitize_text_field( $by_index[ $index ]['finished_at'] ?? current_time( 'mysql' ) ),
            );
        }

        for ( $index = 1; $index <= 4; $index++ ) {
            if ( empty( $by_index[ $index ] ) ) {
                $by_index[ $index ] = array(
                    'index'       => $index,
                    'status'      => 'pending',
                    'attempt'     => 0,
                    'url'         => '',
                    'error'       => '',
                    'started_at'  => '',
                    'finished_at' => '',
                );
            }
        }

        ksort( $by_index );
        return array_values( $by_index );
    }

    private static function recover_premature_final_clip_job( array $clip_job ) {
        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        $attempt = max( 0, (int) ( $clip_job['attempt'] ?? 0 ) );
        if ( 'error_final' !== $status || ! empty( $clip_job['url'] ) || $attempt >= self::CLIP_MAX_ATTEMPTS ) {
            return $clip_job;
        }

        if ( ! self::is_retryable_clip_error_summary( $clip_job['error'] ?? '' ) ) {
            return $clip_job;
        }

        $clip_job['status'] = 'pending';
        $clip_job['attempt'] = max( 1, $attempt );
        $clip_job['finished_at'] = '';
        return $clip_job;
    }

    private static function clip_job_state_fields( array $clip_jobs ) {
        $clip_jobs = self::normalize_clip_jobs( $clip_jobs, array(), false );
        $statuses = array();
        $attempts = array();
        $errors = array();
        $started = array();
        $finished = array();
        $missing = array();

        foreach ( $clip_jobs as $clip_job ) {
            $index = (int) ( $clip_job['index'] ?? 0 );
            if ( $index < 1 || $index > 4 ) {
                continue;
            }

            $statuses[ $index ] = sanitize_key( $clip_job['status'] ?? 'pending' );
            $attempts[ $index ] = (int) ( $clip_job['attempt'] ?? 0 );
            $errors[ $index ] = sanitize_text_field( $clip_job['error'] ?? '' );
            $started[ $index ] = sanitize_text_field( $clip_job['started_at'] ?? '' );
            $finished[ $index ] = sanitize_text_field( $clip_job['finished_at'] ?? '' );
            if ( 'ready' !== $statuses[ $index ] ) {
                $missing[] = $index;
            }
        }

        return array(
            'clip_jobs'       => $clip_jobs,
            'clip_statuses'   => $statuses,
            'clip_attempts'   => $attempts,
            'clip_errors'     => $errors,
            'clip_started_at' => $started,
            'clip_finished_at' => $finished,
            'missing_clips'   => $missing,
        );
    }

    private static function recover_stale_clip_job( array $clip_job ) {
        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        if ( ! in_array( $status, array( 'pending', 'generating', 'retrying' ), true ) || ! empty( $clip_job['url'] ) ) {
            return $clip_job;
        }

        $age = self::clip_job_age_seconds( $clip_job['started_at'] ?? '' );
        if ( empty( $clip_job['started_at'] ) || $age < self::CLIP_GENERATION_STALE_SECONDS ) {
            return $clip_job;
        }

        $attempt = max( 1, (int) ( $clip_job['attempt'] ?? 1 ) );
        if ( $attempt >= self::CLIP_MAX_ATTEMPTS ) {
            $clip_job['status'] = 'error_final';
            $clip_job['attempt'] = self::CLIP_MAX_ATTEMPTS;
            $clip_job['error'] = $clip_job['error'] ?: 'A geração do clipe demorou mais que o esperado.';
            $clip_job['finished_at'] = current_time( 'mysql' );
            return $clip_job;
        }

        $clip_job['status'] = 'pending';
        $clip_job['attempt'] = $attempt;
        $clip_job['error'] = $clip_job['error'] ?: 'Tentativa anterior ficou sem resposta.';
        return $clip_job;
    }

    private static function clip_job_age_seconds( $started_at ) {
        $timestamp = strtotime( (string) $started_at );
        if ( ! $timestamp ) {
            return 0;
        }

        return max( 0, current_time( 'timestamp' ) - $timestamp );
    }

    private static function clips_from_clip_jobs( array $clip_jobs ) {
        $clips = array();
        foreach ( $clip_jobs as $clip_job ) {
            if ( ! is_array( $clip_job ) || empty( $clip_job['url'] ) ) {
                continue;
            }
            $index = (int) ( $clip_job['index'] ?? 0 );
            if ( $index < 1 || $index > 4 ) {
                continue;
            }
            $clips[] = array(
                'index'    => $index,
                'role'     => sanitize_key( $clip_job['role'] ?? '' ),
                'label'    => sanitize_text_field( $clip_job['label'] ?? ( 'Clipe ' . $index ) ),
                'url'      => esc_url_raw( $clip_job['url'] ),
                'duration' => 8,
                'muted'    => true,
            );
        }

        return $clips;
    }

    private static function next_processable_clip_job( array $clip_jobs ) {
        foreach ( $clip_jobs as $clip_job ) {
            if ( self::is_stale_retryable_clip_job( $clip_job ) ) {
                return $clip_job;
            }
        }

        foreach ( $clip_jobs as $clip_job ) {
            if ( self::is_pending_retryable_clip_job( $clip_job ) ) {
                return $clip_job;
            }
        }

        foreach ( $clip_jobs as $clip_job ) {
            $status = sanitize_key( $clip_job['status'] ?? 'pending' );
            $attempt = (int) ( $clip_job['attempt'] ?? 0 );
            if ( empty( $clip_job['url'] ) && in_array( $status, array( 'pending', 'queued' ), true ) && 0 === $attempt ) {
                return $clip_job;
            }
        }

        foreach ( $clip_jobs as $clip_job ) {
            $status = sanitize_key( $clip_job['status'] ?? 'pending' );
            if ( empty( $clip_job['url'] ) && in_array( $status, array( 'pending', 'queued', 'retrying' ), true ) ) {
                return $clip_job;
            }
        }

        return null;
    }

    private static function has_processable_clip_jobs( array $clip_jobs ) {
        foreach ( $clip_jobs as $clip_job ) {
            if ( empty( $clip_job['url'] ) && in_array( sanitize_key( $clip_job['status'] ?? '' ), array( 'pending', 'queued', 'retrying' ), true ) ) {
                return true;
            }
        }

        return false;
    }

    private static function is_stale_retryable_clip_job( array $clip_job ) {
        if ( ! empty( $clip_job['url'] ) ) {
            return false;
        }

        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        if ( ! in_array( $status, array( 'pending', 'generating', 'retrying' ), true ) ) {
            return false;
        }

        $started_at = sanitize_text_field( $clip_job['started_at'] ?? '' );
        if ( empty( $started_at ) ) {
            return false;
        }

        return self::clip_job_age_seconds( $started_at ) >= self::CLIP_GENERATION_STALE_SECONDS
            && (int) ( $clip_job['attempt'] ?? 0 ) < self::CLIP_MAX_ATTEMPTS;
    }

    private static function is_pending_retryable_clip_job( array $clip_job ) {
        if ( ! empty( $clip_job['url'] ) ) {
            return false;
        }

        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        $attempt = (int) ( $clip_job['attempt'] ?? 0 );
        $error = strtolower( sanitize_text_field( $clip_job['error'] ?? '' ) );

        return 'pending' === $status
            && $attempt > 0
            && $attempt < self::CLIP_MAX_ATTEMPTS
            && ( empty( $error ) || false !== strpos( $error, 'tentativa anterior ficou sem resposta' ) || self::is_retryable_clip_error_summary( $error ) );
    }

    private static function is_retryable_clip_error_summary( $summary ) {
        return self::is_retryable_clip_error_parts( '', (string) $summary, (string) $summary );
    }

    private static function is_retryable_clip_error_parts( $code, $message, $debug = '' ) {
        $code = sanitize_key( (string) $code );
        $summary = strtolower( trim( (string) $code . ' ' . (string) $message . ' ' . (string) $debug ) );

        if ( in_array( $code, array( 'video_provider_not_implemented', 'missing_video_api_key', 'missing_video_model', 'missing_video_base_url', 'missing_selected_image', 'image_fetch_error', 'invalid_image_mime_type', 'invalid_video_format', 'image_preprocessor_unavailable', 'video_save_error' ), true ) ) {
            return false;
        }

        if ( preg_match( '/quota|billing|pagamento|payment|required|api key|chave.*inv[aá]lida|invalid.*api|invalid.*model|modelo.*inv[aá]lido|payload.*inv[aá]lido|invalid.*image|imagem.*inv[aá]lida|content policy|policy violation|safety|blocked|bloquead|prohibited|violat/i', $summary ) ) {
            return false;
        }

        if ( 'veo_invalid_response' === $code || false !== strpos( $summary, 'veo_invalid_response' ) ) {
            return true;
        }

        if ( preg_match( '/o servi[cç]o de v[ií]deo n[aã]o retornou um v[ií]deo v[aá]lido|uri do v[ií]deo ausente|missing video uri|video uri missing|operation completed without video|opera[cç][aã]o conclu[ií]da sem v[ií]deo|empty video response|response without video|completed operation without generated video|generatedvideos vazio|video uri ausente|timeout|timed out|operation timeout|sem resposta|no response|curl|tempor[aá]ri|temporary|unavailable|reset|empty|vazia|json|408|409|429|500|502|503|504/i', $summary ) ) {
            return true;
        }

        return true;
    }

    private static function next_clip_reason_for_job( array $clip_job ) {
        if ( self::is_stale_retryable_clip_job( $clip_job ) ) {
            return 'pending_stale_retry';
        }
        if ( self::is_pending_retryable_clip_job( $clip_job ) ) {
            return 'pending_retryable_error';
        }
        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        if ( 'retrying' === $status ) {
            return 'retry_clip';
        }
        if ( in_array( $status, array( 'pending', 'queued' ), true ) ) {
            return 'generate_missing_clip';
        }
        return 'generate_missing_clip';
    }

    private static function is_active_generating_clip_job( array $clip_job ) {
        if ( ! empty( $clip_job['url'] ) ) {
            return false;
        }

        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
        if ( 'generating' !== $status ) {
            return false;
        }

        $started_at = sanitize_text_field( $clip_job['started_at'] ?? '' );
        return ! empty( $started_at ) && self::clip_job_age_seconds( $started_at ) < self::CLIP_GENERATION_STALE_SECONDS;
    }

    private static function active_generating_count( array $clip_jobs ) {
        $count = 0;
        foreach ( $clip_jobs as $clip_job ) {
            if ( self::is_active_generating_clip_job( $clip_job ) ) {
                $count++;
            }
        }
        return $count;
    }

    private static function has_final_clip_errors( array $clip_jobs ) {
        foreach ( $clip_jobs as $clip_job ) {
            if ( 'error_final' === sanitize_key( $clip_job['status'] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    private static function process_single_clip_job( array $job, $clip_index ) {
        $clip_index = (int) $clip_index;
        if ( $clip_index < 1 || $clip_index > 4 ) {
            return $job;
        }

        $latest_for_start = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
        $clips = self::normalize_clip_list( $latest_for_start['clips'] ?? array() );
        if ( self::has_ready_clip( $clips, $clip_index ) ) {
            return self::prepare_clip_jobs_for_job( $latest_for_start, array(), false );
        }

        $clip_roles = self::clip_roles();
        $role = $clip_roles[ $clip_index - 1 ] ?? array();
        $selected_images = array_slice( self::sanitize_images( $latest_for_start['selected_images'] ?? array() ), 0, 4 );
        $clip_jobs = self::normalize_clip_jobs( $latest_for_start['clip_jobs'] ?? array(), $clips, false );
        $previous_attempt = 0;
        foreach ( $clip_jobs as $clip_job ) {
            if ( (int) ( $clip_job['index'] ?? 0 ) !== $clip_index ) {
                continue;
            }
            $previous_attempt = max( 0, (int) ( $clip_job['attempt'] ?? 0 ) );
            if ( 'error_final' === sanitize_key( $clip_job['status'] ?? '' ) ) {
                return STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array_merge(
                        self::clip_job_state_fields( $clip_jobs ),
                        array(
                            'status'               => 'clip_generation_error',
                            'message'              => 'O clipe ' . $clip_index . ' falhou após as tentativas automáticas. Você pode tentar novamente.',
                            'current_clip_index'   => $clip_index,
                            'current_clip_attempt' => min( self::CLIP_MAX_ATTEMPTS, max( 1, (int) ( $clip_job['attempt'] ?? self::CLIP_MAX_ATTEMPTS ) ) ),
                            'clip_retry_count'     => self::CLIP_MAX_ATTEMPTS - 1,
                            'last_clip_error'      => sanitize_text_field( $clip_job['error'] ?? '' ),
                            'failed_clip_index'    => $clip_index,
                            'error_code'           => 'VEO_CLIP_' . $clip_index . '_ERROR',
                            'error_message'        => 'Não foi possível gerar o clipe ' . $clip_index . '.',
                        )
                    )
                );
            }
        }
        $start_attempt = max( 1, min( self::CLIP_MAX_ATTEMPTS, $previous_attempt > 0 ? $previous_attempt + 1 : 1 ) );
        $clip_jobs = self::replace_clip_job(
            $clip_jobs,
            $clip_index,
            array(
                'status'     => 'generating',
                'attempt'    => $start_attempt,
                'error'      => '',
                'started_at' => current_time( 'mysql' ),
            )
        );

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $clip_jobs ),
                array(
                    'status'               => 'generating_clip_' . $clip_index,
                    'progress'             => self::clip_jobs_progress( $clip_jobs ),
                    'progress_hint'        => self::clip_progress_hint( $clip_index ),
                    'message'              => 'Gerando clipe ' . $clip_index . ' de 4.',
                    'current_clip_index'   => $clip_index,
                    'current_clip_attempt' => $start_attempt,
                    'clip_retry_count'     => max( 0, $start_attempt - 1 ),
                    'last_clip_error'      => '',
                    'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                    'max_concurrent_clip_generations' => self::CLIP_MAX_CONCURRENT,
                    'started_clip_indexes' => array( $clip_index ),
                    'retryable'            => false,
                    'retry_reason'         => '',
                    'will_retry'           => false,
                    'error_final_reason'   => '',
                    'failed_clip_index'    => 0,
                    'failed_clip_role'     => '',
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                )
            )
        );

        $clip = self::generate_clip_with_retries(
            $job,
            $clips,
            array(
                'image_url'            => $selected_images[ $clip_index - 1 ]['url'] ?? '',
                'format'               => $job['format'] ?? '16:9',
                'script'               => $job['script_public'] ?? ( $job['script'] ?? '' ),
                'product_name'         => $job['product_name'] ?? '',
                'product_description'  => $job['product_description'] ?? '',
                'index'                => $clip_index,
                'role'                 => $role['role'] ?? '',
                'role_label'           => $role['label'] ?? '',
                'role_direction'       => $role['direction'] ?? '',
                'start_attempt'        => $start_attempt,
                'video_provider'       => $job['video_provider'] ?? self::DEFAULT_VIDEO_PROVIDER,
                'video_model'          => $job['video_model'] ?? self::DEFAULT_VIDEO_MODEL,
                'output_resolution'    => $job['output_resolution'] ?? self::DEFAULT_OUTPUT_RESOLUTION,
            )
        );

        $latest = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
        $clips = self::normalize_clip_list( $latest['clips'] ?? $clips );
        $clip_jobs = self::normalize_clip_jobs( $latest['clip_jobs'] ?? array(), $clips, false );
        $attempt = max( 1, (int) ( $latest['current_clip_attempt'] ?? 1 ) );

        if ( is_wp_error( $clip ) ) {
            $error_data = $clip->get_error_data();
            $safe_debug = is_array( $error_data ) ? ( $error_data['debug'] ?? self::clip_error_debug( $error_data, $role, $job['format'] ?? '' ) ) : '';
            $last_error = is_array( $error_data ) ? ( $error_data['last_error_summary'] ?? $clip->get_error_message() ) : $clip->get_error_message();
            $failed_attempt = is_array( $error_data ) ? (int) ( $error_data['current_clip_attempt'] ?? $attempt ) : $attempt;
            $failed_attempt = max( 1, min( self::CLIP_MAX_ATTEMPTS, $failed_attempt ) );
            $retryable = self::is_retryable_clip_error( $clip );
            $retry_reason = is_array( $error_data ) ? sanitize_key( $error_data['retry_reason'] ?? self::clip_retry_reason( $clip ) ) : self::clip_retry_reason( $clip );
            if ( $retryable && $failed_attempt < self::CLIP_MAX_ATTEMPTS ) {
                $clip_jobs = self::replace_clip_job(
                    $clip_jobs,
                    $clip_index,
                    array(
                        'status'      => 'pending',
                        'attempt'     => $failed_attempt,
                        'error'       => $last_error,
                        'finished_at' => '',
                    )
                );

                return STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array_merge(
                        self::clip_job_state_fields( $clip_jobs ),
                        array(
                            'status'               => 'generating_clips',
                            'progress'             => self::clip_jobs_progress( $clip_jobs ),
                            'progress_hint'        => self::clip_progress_hint( $clip_index ),
                            'message'              => 'Ajustando clipe IA automaticamente. Refazendo o clipe ' . $clip_index . '. Tentativa ' . ( $failed_attempt + 1 ) . ' de ' . self::CLIP_MAX_ATTEMPTS . '.',
                            'clips'                => $clips,
                            'partial_clips'        => $clips,
                            'video_frames'         => self::video_frames_from_clips( $clips ),
                            'composition_status'   => 'pending',
                            'current_clip_index'   => $clip_index,
                            'current_clip_attempt' => $failed_attempt,
                            'clip_retry_count'     => max( 0, $failed_attempt - 1 ),
                            'last_clip_error'      => $last_error,
                            'failed_clip_index'    => 0,
                            'failed_clip_role'     => '',
                            'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                            'retryable'            => true,
                            'retry_reason'         => $retry_reason,
                            'will_retry'           => true,
                            'error_final_reason'   => '',
                            'next_clip_action'     => 'retry_clip',
                            'next_clip_index'      => $clip_index,
                            'next_clip_reason'     => $retry_reason,
                            'error_code'           => '',
                            'error_message'        => '',
                            'error_debug'          => self::clip_retry_debug( $clip, $clip_index, $failed_attempt, true ),
                        )
                    )
                );
            }
            $clip_jobs = self::replace_clip_job(
                $clip_jobs,
                $clip_index,
                array(
                    'status'      => 'error_final',
                    'attempt'     => $failed_attempt,
                    'error'       => $last_error,
                    'finished_at' => current_time( 'mysql' ),
                )
            );

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                        'status'               => 'clip_generation_error',
                        'progress'             => self::clip_jobs_progress( $clip_jobs ),
                        'progress_hint'        => self::clip_progress_hint( $clip_index ),
                        'message'              => 'Não foi possível gerar o clipe ' . $clip_index . ' após ' . self::CLIP_MAX_ATTEMPTS . ' tentativas.',
                        'clips'                => $clips,
                        'partial_clips'        => $clips,
                        'video_frames'         => self::video_frames_from_clips( $clips ),
                        'composition_status'   => 'pending',
                        'current_clip_index'   => $clip_index,
                        'current_clip_attempt' => $failed_attempt,
                        'clip_retry_count'     => max( 0, $failed_attempt - 1 ),
                        'last_clip_error'      => $last_error,
                        'failed_clip_index'    => $clip_index,
                        'failed_clip_role'     => $role['role'] ?? '',
                        'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                        'retryable'            => $retryable,
                        'retry_reason'         => $retry_reason,
                        'will_retry'           => false,
                        'error_final_reason'   => $retryable ? 'max_attempts_exhausted' : 'non_retryable_error',
                        'next_clip_action'     => '',
                        'next_clip_index'      => 0,
                        'error_code'           => 'VEO_CLIP_' . $clip_index . '_ERROR',
                        'error_message'        => 'Não foi possível gerar o clipe ' . $clip_index . ' após ' . self::CLIP_MAX_ATTEMPTS . ' tentativas.',
                        'error_debug'          => $safe_debug,
                    )
                )
            );
        }

        $latest = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
        $clips = self::normalize_clip_list( $latest['clips'] ?? array() );
        $clip_jobs = self::normalize_clip_jobs( $latest['clip_jobs'] ?? array(), $clips, false );
        $attempt = max( 1, (int) ( $latest['current_clip_attempt'] ?? $attempt ) );
        $clips[] = self::public_clip_data( $clip );
        $clips = self::normalize_clip_list( $clips );
        $clip_jobs = self::replace_clip_job(
            $clip_jobs,
            $clip_index,
            array(
                'status'      => 'ready',
                'attempt'     => $attempt,
                'url'         => esc_url_raw( $clip['url'] ?? '' ),
                'error'       => '',
                'finished_at' => current_time( 'mysql' ),
            )
        );

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $clip_jobs ),
                array(
                    'status'               => self::count_ready_clips( $clips ) >= 4 ? 'clips_ready' : 'generating_clips',
                    'progress'             => self::clip_jobs_progress( $clip_jobs ),
                    'progress_hint'        => self::clip_jobs_progress( $clip_jobs ),
                    'message'              => 'Clipe ' . $clip_index . ' gerado com sucesso.',
                    'clips'                => $clips,
                    'partial_clips'        => $clips,
                    'video_frames'         => self::video_frames_from_clips( $clips ),
                    'current_clip_index'   => 0,
                    'current_clip_attempt' => 0,
                    'clip_retry_count'     => 0,
                    'last_clip_error'      => '',
                    'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                    'retryable'            => false,
                    'retry_reason'         => '',
                    'will_retry'           => false,
                    'error_final_reason'   => '',
                    'failed_clip_index'    => 0,
                    'failed_clip_role'     => '',
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                )
            )
        );

        if ( self::count_ready_clips( $clips ) >= 4 ) {
            return self::maybe_start_or_poll_composition( $job );
        }

        return $job;
    }

    private static function replace_clip_job( array $clip_jobs, $clip_index, array $changes ) {
        $clip_index = (int) $clip_index;
        foreach ( $clip_jobs as &$clip_job ) {
            if ( (int) ( $clip_job['index'] ?? 0 ) === $clip_index ) {
                $clip_job = array_merge( $clip_job, $changes );
                return $clip_jobs;
            }
        }
        unset( $clip_job );

        $clip_jobs[] = array_merge(
            array(
                'index'       => $clip_index,
                'status'      => 'pending',
                'attempt'     => 0,
                'url'         => '',
                'error'       => '',
                'started_at'  => '',
                'finished_at' => '',
            ),
            $changes
        );

        return self::normalize_clip_jobs( $clip_jobs, array(), false );
    }

    private static function maybe_start_or_poll_composition( array $job ) {
        if ( 'ready' === sanitize_key( $job['status'] ?? '' ) ) {
            return $job;
        }

        if ( ! empty( $job['final_video_url'] ) ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'ready',
                    'composition_status' => 'complete',
                    'composer_status'    => 'ready',
                    'progress'           => 100,
                    'progress_hint'      => 100,
                )
            ) ?: $job;
        }

        if ( ! empty( $job['render_job_id'] ) && empty( $job['final_video_url'] ) && ( 'timeout' === sanitize_key( $job['composition_status'] ?? '' ) || ( 'composition_error' === sanitize_key( $job['status'] ?? '' ) && 'COMPOSER_TIMEOUT' === strtoupper( (string) ( $job['error_code'] ?? $job['last_composer_error_code'] ?? '' ) ) ) ) ) {
            $job = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'composition_waiting',
                    'composition_status' => 'waiting',
                    'composer_status'    => 'waiting',
                    'message'            => 'Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos.',
                    'progress'           => max( 92, min( 98, (int) ( $job['progress'] ?? 92 ) ) ),
                    'progress_hint'      => max( 92, min( 98, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 92 ) ) ) ),
                    'error_code'         => '',
                    'error_message'      => '',
                    'error_debug'        => '',
                )
            ) ?: $job;
            return self::maybe_refresh_composition_status( $job );
        }

        if ( in_array( sanitize_key( $job['composition_status'] ?? '' ), array( 'error', 'timeout' ), true ) || 'composition_error' === sanitize_key( $job['status'] ?? '' ) ) {
            return $job;
        }

        if ( ! empty( $job['render_job_id'] ) || in_array( sanitize_key( $job['status'] ?? '' ), array( 'composition_queued', 'composition_processing', 'composition_waiting', 'composing_final_video' ), true ) ) {
            return self::maybe_refresh_composition_status( $job );
        }

        if ( empty( $job['audio_url'] ) || self::count_ready_clips( $job['clips'] ?? array() ) < 4 ) {
            return $job;
        }

        return self::start_composition_if_ready( $job );
    }

	    private static function start_composition_if_ready( array $job ) {
	        $latest_job = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
	        if ( ! empty( $latest_job['render_job_id'] ) || in_array( $latest_job['composition_status'] ?? '', array( 'queued', 'processing', 'waiting' ), true ) ) {
	            return self::maybe_refresh_composition_status( $latest_job );
	        }

	        $job = $latest_job;
	        $clips = self::normalize_clip_list( $job['clips'] ?? array() );
	        self::log_composition(
	            'start_check',
	            array(
	                'job_id'              => $job['job_id'] ?? '',
	                'clips_count'         => count( $clips ),
	                'audio_url_exists'    => ! empty( $job['audio_url'] ),
	                'endpoint_configured' => self::composer_endpoint_configured(),
	                'composer_mode'       => self::composer_mode_setting(),
	            )
	        );
        if ( count( $clips ) < 4 ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'generating_clips',
                    'progress'           => self::clip_jobs_progress( $job['clip_jobs'] ?? array() ),
                    'progress_hint'      => self::clip_jobs_progress( $job['clip_jobs'] ?? array() ),
                    'message'            => 'Aguardando todos os clipes para compor o vídeo final.',
                    'clips'              => $clips,
                    'partial_clips'      => $clips,
                    'video_frames'       => self::video_frames_from_clips( $clips ),
                    'composition_status' => 'pending',
                )
            );
        }

        if ( ! empty( $job['render_job_id'] ) && in_array( $job['status'] ?? '', array( 'composition_queued', 'composition_processing', 'composing_final_video' ), true ) ) {
            return self::maybe_refresh_composition_status( $job );
        }

        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $clips, false );
        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array_merge(
                self::clip_job_state_fields( $clip_jobs ),
                array(
                    'status'             => 'clips_ready',
                    'progress'           => 79,
                    'progress_hint'      => 79,
                    'message'            => '4 clipes gerados. Preparando composição final.',
                    'clips'              => $clips,
                    'partial_clips'      => array(),
                    'video_frames'       => self::video_frames_from_clips( $clips ),
                    'composition_status' => 'pending',
                    'current_clip_index' => 0,
                    'current_clip_attempt' => 0,
                    'clip_retry_count'   => 0,
                    'last_clip_error'    => '',
                    'final_video_url'    => '',
                    'thumbnail_url'      => '',
                )
            )
        );

        $job = STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => 'composing_final_video',
                'progress'           => 82,
                'progress_hint'      => 82,
                'message'            => 'Compondo vídeo final...',
	                'composition_status' => 'processing',
	                'composition_started_at' => current_time( 'mysql' ),
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

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => $fallback_status,
                    'progress'           => 82,
                    'progress_hint'      => 82,
                    'message'            => $fallback_message,
                    'clips'              => $clips,
                    'partial_clips'      => array(),
                    'video_frames'       => self::video_frames_from_clips( $clips ),
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
        }

        if ( 'ready' === sanitize_key( $composer['status'] ?? '' ) ) {
            self::log_composition(
                'start_ready',
                array(
                    'job_id'                 => $job['job_id'] ?? '',
                    'render_job_id'          => $composer['render_job_id'] ?? '',
                    'final_video_url_exists' => ! empty( $composer['final_video_url'] ),
                )
            );

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'               => 'ready',
                    'progress'             => 100,
                    'progress_hint'        => 100,
                    'message'              => $composer['message'] ?? 'Vídeo final composto com sucesso.',
                    'clips'                => $clips,
                    'video_frames'         => self::video_frames_from_clips( $clips ),
                    'composition_status'   => 'complete',
                    'composer_status'      => 'ready',
                    'render_job_id'        => $composer['render_job_id'] ?? '',
                    'final_video_url'      => $composer['final_video_url'] ?? '',
                    'final_video_duration' => $composer['final_video_duration'] ?? 0,
                    'final_video_debug'    => $composer['debug'] ?? '',
                    'transition_used'      => $composer['transition_used'] ?? '',
                    'fallback_used'        => $composer['fallback_used'] ?? '',
                    'render_time_seconds'  => $composer['render_time_seconds'] ?? 0,
                    'background_music_used' => ! empty( $composer['background_music_used'] ),
                    'background_music_volume' => $composer['background_music_volume'] ?? 0,
                    'fast_compose'         => ! empty( $composer['fast_compose'] ),
                    'composer_mode'        => $composer['composer_mode'] ?? '',
                    'composer_provider'    => $composer['composer_provider'] ?? '',
                    'composed_at'          => current_time( 'mysql' ),
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                )
            );
        }

        $composer_status = sanitize_key( $composer['status'] ?? 'queued' );
        self::log_composition(
            'start_accepted',
            array(
                'job_id'                 => $job['job_id'] ?? '',
                'render_job_id'          => $composer['render_job_id'] ?? '',
                'status'                 => $composer_status,
                'final_video_url_exists' => ! empty( $composer['final_video_url'] ?? '' ),
            )
        );

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'               => 'processing' === $composer_status ? 'composition_processing' : 'composition_queued',
                'progress'             => max( 82, min( 84, (int) ( $composer['progress'] ?? 82 ) ) ),
                'progress_hint'        => 82,
                'message'              => 'Composição final em andamento...',
                'clips'                => $clips,
                'video_frames'         => self::video_frames_from_clips( $clips ),
                'composition_status'   => 'processing' === $composer_status ? 'processing' : 'queued',
                'composer_status'      => $composer_status,
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

    private static function maybe_refresh_composition_status( array $job ) {
        $status = $job['status'] ?? '';
        if ( ! in_array( $status, array( 'composition_queued', 'composition_processing', 'composition_waiting', 'composing_final_video' ), true ) ) {
            return $job;
        }

        $render_job_id = $job['render_job_id'] ?? '';
        if ( empty( $render_job_id ) ) {
            if ( self::count_ready_clips( $job['clips'] ?? array() ) < 4 || empty( $job['audio_url'] ) ) {
                if ( self::composition_elapsed_seconds( $job ) > 90 ) {
                    return self::composition_timeout_error(
                        $job,
                        'COMPOSER_TIMEOUT',
                        'composition_queued sem render_job_id por mais de 90s.'
                    );
                }
                return $job;
            }

            self::log_composition(
                'recover_queued_without_render_job',
                array(
                    'job_id'      => $job['job_id'] ?? '',
                    'clips_count' => self::count_ready_clips( $job['clips'] ?? array() ),
                    'has_audio'   => ! empty( $job['audio_url'] ),
                )
            );
            $recovered = STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'clips_ready',
                    'composition_status' => 'pending',
                    'composer_status'    => '',
                    'render_job_id'      => '',
                    'composition_started_at' => '',
                )
            );
            return self::start_composition_if_ready( $recovered ?: $job );
        }

        $elapsed = self::composition_elapsed_seconds( $job );
        $soft_timeout = self::soft_composer_timeout_seconds();
        $hard_timeout = self::hard_composer_timeout_seconds();
        $soft_reached = $elapsed > $soft_timeout;
        if ( $elapsed > $hard_timeout ) {
            return self::composition_timeout_error(
                $job,
                'COMPOSER_TIMEOUT',
                'composition_processing excedeu HARD_COMPOSER_TIMEOUT_SECONDS.'
            );
        }

        $remote = STLAI_Video_Composer_Provider::get_composition_status( $render_job_id );
        if ( is_wp_error( $remote ) ) {
            $error_data = $remote->get_error_data();
            self::log_composition(
                'status_error',
                array(
                    'job_id'        => $job['job_id'] ?? '',
                    'render_job_id' => $render_job_id,
                    'code'          => $remote->get_error_code(),
                    'message'       => $remote->get_error_message(),
                )
            );
            if ( 'COMPOSER_STATUS_ERROR' === $remote->get_error_code() && $elapsed <= $hard_timeout ) {
                return STLAI_Video_Storage::update_job(
                    $job['job_id'],
                    array(
                        'status'             => $soft_reached ? 'composition_waiting' : 'composition_processing',
                        'progress'           => $soft_reached ? max( 92, min( 98, (int) ( $job['progress'] ?? 92 ) ) ) : max( 82, (int) ( $job['progress'] ?? 82 ) ),
                        'progress_hint'      => $soft_reached ? max( 92, min( 98, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 92 ) ) ) ) : max( 82, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 82 ) ) ),
                        'message'            => $soft_reached ? 'Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos.' : 'Composição final em andamento...',
                        'composition_status' => $soft_reached ? 'waiting' : 'processing',
                        'composer_status'    => $soft_reached ? 'waiting' : 'processing',
                        'poll_count'         => (int) ( $job['poll_count'] ?? 0 ) + 1,
                        'last_composer_error_code' => $remote->get_error_code(),
                        'last_composer_error_message' => $remote->get_error_message(),
                        'soft_timeout_seconds' => $soft_timeout,
                        'soft_timeout_reached' => $soft_reached,
                        'hard_timeout_seconds' => $hard_timeout,
                        'hard_timeout_reached' => false,
                        'next_poll_seconds' => self::next_composer_poll_seconds( $elapsed ),
                        'external_render_status' => 'status_request_error',
                        'external_render_checked_at' => current_time( 'mysql' ),
                        'error_code'         => '',
                        'error_message'      => '',
                        'error_debug'        => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                    )
                );
            }

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'composition_error',
                    'progress'           => max( 80, (int) ( $job['progress'] ?? 80 ) ),
                    'progress_hint'      => max( 80, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 80 ) ) ),
                    'message'            => $remote->get_error_message(),
                    'composition_status' => 'error',
                    'composer_status'    => 'error',
                    'poll_count'         => (int) ( $job['poll_count'] ?? 0 ) + 1,
                    'last_composer_error_code' => $remote->get_error_code(),
                    'last_composer_error_message' => $remote->get_error_message(),
                    'error_code'         => $remote->get_error_code(),
                    'error_message'      => $remote->get_error_message(),
                    'error_debug'        => is_array( $error_data ) ? ( $error_data['debug'] ?? '' ) : '',
                )
            );
        }

        $remote_status = sanitize_key( $remote['status'] ?? 'processing' );
        $is_waiting = $soft_reached && in_array( $remote_status, array( 'queued', 'processing' ), true );
        self::log_composition(
            'status_update',
            array(
                'job_id'                 => $job['job_id'] ?? '',
                'render_job_id'          => $render_job_id,
                'status'                 => $remote_status,
                'final_video_url_exists' => ! empty( $remote['final_video_url'] ?? '' ),
            )
        );
        if ( 'ready' === $remote_status ) {
            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'               => 'ready',
                    'progress'             => 100,
                    'progress_hint'        => 100,
                    'message'              => $remote['message'] ?? 'Vídeo final composto com sucesso.',
                    'composition_status'   => 'complete',
                    'composer_status'      => 'ready',
                    'poll_count'           => (int) ( $job['poll_count'] ?? 0 ) + 1,
                    'final_video_url'      => $remote['final_video_url'] ?? '',
                    'final_video_duration' => $remote['final_video_duration'] ?? 0,
                    'final_video_debug'    => $remote['debug'] ?? '',
                    'transition_used'      => $remote['transition_used'] ?? '',
                    'fallback_used'        => $remote['fallback_used'] ?? '',
                    'render_time_seconds'  => $remote['render_time_seconds'] ?? 0,
                    'background_music_used' => ! empty( $remote['background_music_used'] ),
                    'background_music_volume' => $remote['background_music_volume'] ?? 0,
                    'fast_compose'         => ! empty( $remote['fast_compose'] ),
                    'composer_mode'        => $remote['composer_mode'] ?? ( $job['composer_mode'] ?? '' ),
                    'composer_provider'    => $remote['composer_provider'] ?? ( $job['composer_provider'] ?? '' ),
                    'composed_at'          => $remote['composed_at'] ?? current_time( 'mysql' ),
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                    'last_composer_error_code' => '',
                    'last_composer_error_message' => '',
                    'soft_timeout_seconds' => $soft_timeout,
                    'soft_timeout_reached' => $soft_reached,
                    'hard_timeout_seconds' => $hard_timeout,
                    'hard_timeout_reached' => false,
                    'next_poll_seconds'    => 0,
                    'external_render_status' => 'ready',
                    'external_render_checked_at' => current_time( 'mysql' ),
                )
            );
        }

        if ( 'error' === $remote_status ) {
            $remote_error_code = ! empty( $remote['code'] ) ? sanitize_text_field( $remote['code'] ) : 'COMPOSER_RENDER_ERROR';
            $remote_error_message = ! empty( $remote['message'] ) ? sanitize_text_field( $remote['message'] ) : 'Não foi possível concluir o vídeo final.';

            return STLAI_Video_Storage::update_job(
                $job['job_id'],
                array(
                    'status'             => 'composition_error',
                    'progress'           => max( 82, (int) ( $job['progress'] ?? 82 ) ),
                    'progress_hint'      => max( 82, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 82 ) ) ),
                    'message'            => 'Não foi possível concluir o vídeo final. Os clipes e a narração foram preservados. Você pode tentar novamente.',
                    'composition_status' => 'error',
                    'composer_status'    => 'error',
                    'poll_count'         => (int) ( $job['poll_count'] ?? 0 ) + 1,
                    'last_composer_error_code' => $remote_error_code,
                    'last_composer_error_message' => $remote_error_message,
                    'soft_timeout_seconds' => $soft_timeout,
                    'soft_timeout_reached' => $soft_reached,
                    'hard_timeout_seconds' => $hard_timeout,
                    'hard_timeout_reached' => false,
                    'next_poll_seconds' => 0,
                    'external_render_status' => 'error',
                    'external_render_checked_at' => current_time( 'mysql' ),
                    'error_code'         => $remote_error_code,
                    'error_message'      => $remote_error_message,
                    'error_debug'        => $remote['debug'] ?? '',
                )
            );
        }

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => $is_waiting ? 'composition_waiting' : ( 'queued' === $remote_status ? 'composition_queued' : 'composition_processing' ),
                'progress'           => $is_waiting ? max( 92, min( 98, self::composition_progress( $remote_status, $remote['progress'] ?? 0, $job['progress'] ?? 92 ) ) ) : self::composition_progress( $remote_status, $remote['progress'] ?? 0, $job['progress'] ?? 80 ),
                'progress_hint'      => $is_waiting ? max( 92, min( 98, self::composition_progress( $remote_status, $remote['progress'] ?? 0, $job['progress_hint'] ?? ( $job['progress'] ?? 92 ) ) ) ) : self::composition_progress( $remote_status, $remote['progress'] ?? 0, $job['progress_hint'] ?? ( $job['progress'] ?? 80 ) ),
                'message'            => $is_waiting ? 'Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos.' : ( $remote['message'] ?? 'Composição final em andamento...' ),
                'composition_status' => $is_waiting ? 'waiting' : ( 'queued' === $remote_status ? 'queued' : 'processing' ),
                'composer_status'    => $remote_status,
                'poll_count'         => (int) ( $job['poll_count'] ?? 0 ) + 1,
                'composer_mode'      => $remote['composer_mode'] ?? ( $job['composer_mode'] ?? '' ),
                'composer_provider'  => $remote['composer_provider'] ?? ( $job['composer_provider'] ?? '' ),
                'soft_timeout_seconds' => $soft_timeout,
                'soft_timeout_reached' => $soft_reached,
                'hard_timeout_seconds' => $hard_timeout,
                'hard_timeout_reached' => false,
                'next_poll_seconds' => self::next_composer_poll_seconds( $elapsed ),
                'external_render_status' => $remote_status,
                'external_render_checked_at' => current_time( 'mysql' ),
            )
        );
    }

    private static function composition_timeout_error( array $job, $code, $debug ) {
        $message = 'A composição demorou mais que o esperado. Os clipes e a narração foram preservados. Tente novamente.';

        self::log_composition(
            'timeout',
            array(
                'job_id'        => $job['job_id'] ?? '',
                'render_job_id' => $job['render_job_id'] ?? '',
                'status'        => $job['status'] ?? '',
                'elapsed'       => self::composition_elapsed_seconds( $job ),
                'debug'         => $debug,
            )
        );

        return STLAI_Video_Storage::update_job(
            $job['job_id'],
            array(
                'status'             => 'composition_error',
                'progress'           => max( 82, (int) ( $job['progress'] ?? 82 ) ),
                'progress_hint'      => max( 82, (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 82 ) ) ),
                'message'            => $message,
                'composition_status' => 'timeout',
                'composer_status'    => 'timeout',
                'last_composer_error_code' => strtoupper( preg_replace( '/[^A-Z0-9_]/i', '_', (string) $code ) ),
                'last_composer_error_message' => $message,
                'soft_timeout_seconds' => self::soft_composer_timeout_seconds(),
                'soft_timeout_reached' => true,
                'hard_timeout_seconds' => self::hard_composer_timeout_seconds(),
                'hard_timeout_reached' => true,
                'next_poll_seconds' => 0,
                'external_render_status' => 'timeout',
                'external_render_checked_at' => current_time( 'mysql' ),
                'error_code'         => strtoupper( preg_replace( '/[^A-Z0-9_]/i', '_', (string) $code ) ),
                'error_message'      => $message,
                'error_debug'        => sanitize_text_field( $debug ),
            )
        );
    }

    private static function composition_elapsed_seconds( array $job ) {
        $timestamp = strtotime( (string) ( $job['composition_started_at'] ?? '' ) );
        if ( ! $timestamp ) {
            $timestamp = strtotime( (string) ( $job['updated_at'] ?? '' ) );
        }
        if ( ! $timestamp ) {
            return 0;
        }

        return max( 0, current_time( 'timestamp' ) - $timestamp );
    }

    private static function soft_composer_timeout_seconds() {
        return STLAI_Video_Composer_Provider::configured_timeout();
    }

    private static function hard_composer_timeout_seconds() {
        return self::HARD_COMPOSER_TIMEOUT_SECONDS;
    }

    private static function next_composer_poll_seconds( $elapsed ) {
        $elapsed = max( 0, (int) $elapsed );
        if ( $elapsed >= 300 ) {
            return 10;
        }
        return 5;
    }

    private static function composer_endpoint_configured() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        return is_array( $settings ) && ( ! empty( $settings['videoComposerEndpoint'] ) || ! empty( $settings['video_composer_endpoint'] ) );
    }

    private static function composer_mode_setting() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $mode = is_array( $settings ) ? sanitize_key( $settings['videoComposerMode'] ?? ( $settings['video_composer_mode'] ?? '' ) ) : '';
        return in_array( $mode, array( 'external_service', 'local_ffmpeg' ), true ) ? $mode : 'external_service';
    }

    private static function log_composition( $event, array $context = array() ) {
        $safe = array();
        foreach ( $context as $key => $value ) {
            if ( preg_match( '/api|key|token|authorization/i', (string) $key ) ) {
                continue;
            }
            if ( is_bool( $value ) || is_numeric( $value ) ) {
                $safe[ $key ] = $value;
            } else {
                $safe[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
            }
        }
        error_log( '[STLAI video job composition] ' . sanitize_key( $event ) . ' ' . wp_json_encode( $safe ) );
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
                'prepared_frame_url' => esc_url_raw( $clip['prepared_frame_url'] ?? '' ),
                'prepared_frame_width' => (int) ( $clip['prepared_frame_width'] ?? 0 ),
                'prepared_frame_height' => (int) ( $clip['prepared_frame_height'] ?? 0 ),
                'aspect_ratio' => sanitize_text_field( $clip['aspect_ratio'] ?? '' ),
            );
        }

        ksort( $normalized );
        return array_values( $normalized );
    }

    private static function merge_clip_lists( array $existing, array $incoming ) {
        $by_index = array();
        foreach ( array_merge( self::normalize_clip_list( $existing ), self::normalize_clip_list( $incoming ) ) as $clip ) {
            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index < 1 || $index > 4 || empty( $clip['url'] ) ) {
                continue;
            }
            if ( ! empty( $by_index[ $index ]['url'] ) ) {
                $by_index[ $index ] = array_merge( $clip, $by_index[ $index ] );
                continue;
            }
            $by_index[ $index ] = $clip;
        }
        ksort( $by_index );
        return array_values( $by_index );
    }

    private static function has_ready_clip( array $clips, $clip_index ) {
        foreach ( $clips as $clip ) {
            if ( (int) ( $clip['index'] ?? 0 ) === (int) $clip_index && ! empty( $clip['url'] ) ) {
                return true;
            }
        }

        return false;
    }

    private static function count_ready_clips( $clips ) {
        return count( self::normalize_clip_list( is_array( $clips ) ? $clips : array() ) );
    }

    private static function missing_clip_indexes( $clips, $clip_jobs = array() ) {
        $ready = array();
        foreach ( self::normalize_clip_list( is_array( $clips ) ? $clips : array() ) as $clip ) {
            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index >= 1 && $index <= 4 && ! empty( $clip['url'] ) ) {
                $ready[ $index ] = true;
            }
        }

        foreach ( self::normalize_clip_jobs( is_array( $clip_jobs ) ? $clip_jobs : array(), $clips, false ) as $clip_job ) {
            $index = (int) ( $clip_job['index'] ?? 0 );
            if ( $index >= 1 && $index <= 4 && ! empty( $clip_job['url'] ) ) {
                $ready[ $index ] = true;
            }
        }

        $missing = array();
        for ( $index = 1; $index <= 4; $index++ ) {
            if ( empty( $ready[ $index ] ) ) {
                $missing[] = $index;
            }
        }

        return $missing;
    }

    private static function clip_job_by_index( array $clip_jobs, $clip_index ) {
        foreach ( $clip_jobs as $clip_job ) {
            if ( (int) ( $clip_job['index'] ?? 0 ) === (int) $clip_index ) {
                return $clip_job;
            }
        }

        return array();
    }

    private static function clip_jobs_progress( $clip_jobs ) {
        $clip_jobs = self::normalize_clip_jobs( is_array( $clip_jobs ) ? $clip_jobs : array(), array(), false );
        $ready = 0;
        foreach ( $clip_jobs as $clip_job ) {
            if ( 'ready' === ( $clip_job['status'] ?? '' ) ) {
                $ready++;
            }
        }

        $map = array(
            0 => 25,
            1 => 35,
            2 => 50,
            3 => 65,
            4 => 78,
        );

        return $map[ min( 4, max( 0, $ready ) ) ];
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

    private static function strip_narration_directions( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( '/\[[^\]\r\n]{1,80}\]\s*/u', '', $text );
        $text = preg_replace( '/\b(thoughtful|warmly|short pause|gentle pause|delighted|excited|softly|amazed|chuckles|sighs|confident|impressed)\b\s*/iu', '', $text );
        $text = preg_replace( '/\s+([,.!?;:])/', '$1', $text );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( (string) $text );
    }

    private static function normalize_script_terms( $text, $language = 'pt-BR' ) {
        $clean = self::strip_narration_directions( $text );
        $language = self::sanitize_video_language( $language );

        if ( 'pt-BR' === $language ) {
            $replacements = array(
                '/\bwedding topper\b/iu'       => 'topo de bolo de casamento',
                '/\bcake topper\b/iu'          => 'topo de bolo',
                '/\bpersonalized topper\b/iu'  => 'topo de bolo personalizado',
                '/\bcustom topper\b/iu'        => 'topo personalizado',
                '/\btopper personalizado\b/iu' => 'topo de bolo personalizado',
                '/\btopper\b/iu'               => 'topo de bolo',
            );
        } elseif ( 'es-ES' === $language ) {
            $replacements = array(
                '/\bwedding topper\b/iu'      => 'decoración para pastel de boda',
                '/\bcake topper\b/iu'         => 'decoración para pastel',
                '/\bpersonalized topper\b/iu' => 'decoración personalizada para pastel',
                '/\bcustom topper\b/iu'       => 'decoración personalizada para pastel',
                '/\btopper\b/iu'              => 'decoración para pastel',
            );
        } elseif ( 'fr-FR' === $language ) {
            $replacements = array(
                '/\bwedding topper\b/iu'      => 'décoration de gâteau de mariage',
                '/\bcake topper\b/iu'         => 'décoration de gâteau',
                '/\bpersonalized topper\b/iu' => 'décoration de gâteau personnalisée',
                '/\bcustom topper\b/iu'       => 'décoration de gâteau personnalisée',
                '/\btopper\b/iu'              => 'décoration de gâteau',
            );
        } else {
            $replacements = array();
        }

        foreach ( $replacements as $pattern => $replacement ) {
            $clean = preg_replace( $pattern, $replacement, $clean );
        }

        return trim( preg_replace( '/\s+/', ' ', (string) $clean ) );
    }

    private static function sanitize_video_language( $language ) {
        $language = sanitize_text_field( (string) $language );
        return in_array( $language, array( 'pt-BR', 'en-US', 'es-ES', 'fr-FR' ), true ) ? $language : 'pt-BR';
    }

    private static function build_script_pair( $public_script, $voice_style = 'persuasiva', $language = 'pt-BR' ) {
        $language = self::sanitize_video_language( $language );
        $script_public = self::normalize_script_terms( $public_script, $language );
        if ( empty( $script_public ) ) {
            $script_public = self::fallback_public_script( $language );
        }

        $script_tts = self::build_narration_script( $script_public, $voice_style, $language );
        if ( empty( $script_tts ) ) {
            $script_tts = $script_public;
        }

        return array(
            'script_public' => $script_public,
            'script_tts'    => $script_tts,
        );
    }

    private static function fallback_public_script( $language = 'pt-BR' ) {
        $language = self::sanitize_video_language( $language );
        if ( 'en-US' === $language ) {
            return 'I was looking for a detail that felt personal and useful, something that could make the moment more memorable. This product stood out because it brings care, presence, and a thoughtful finish without changing what it was made for.';
        }
        if ( 'es-ES' === $language ) {
            return 'Estaba buscando un detalle especial, algo que hiciera el momento más bonito y memorable. Este producto me llamó la atención por su presencia, su cuidado en los detalles y esa sensación de regalo pensado con cariño.';
        }
        if ( 'fr-FR' === $language ) {
            return "Je cherchais un détail spécial, quelque chose qui rende le moment plus beau et plus mémorable. Ce produit m'a plu par sa présence, son soin dans les détails et cette impression d'un cadeau choisi avec attention.";
        }
        return 'Eu estava procurando um detalhe especial, daqueles que fazem a pessoa sorrir antes mesmo de usar. Foi isso que me chamou atenção neste produto: presença, cuidado nos detalhes e uma sensação de presente pensado com carinho.';
    }

    private static function build_narration_script( $public_script, $voice_style = 'persuasiva', $language = 'pt-BR' ) {
        $clean = self::normalize_script_terms( $public_script, $language );
        if ( empty( $clean ) ) {
            return '';
        }

        $sentences = preg_split( '/(?<=[.!?])\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! is_array( $sentences ) || empty( $sentences ) ) {
            return $clean;
        }

        $parts = array();
        $emotional = 'emocional' === sanitize_key( $voice_style );
        foreach ( array_values( $sentences ) as $index => $sentence ) {
            $sentence = trim( (string) $sentence );
            if ( '' === $sentence ) {
                continue;
            }

            if ( 0 === $index ) {
                $parts[] = ( $emotional ? '[thoughtful] ' : '[confident] ' ) . $sentence;
                continue;
            }

            if ( 1 === $index ) {
                $parts[] = '[short pause] ' . ( $emotional ? '[warmly] ' : '[excited] ' ) . $sentence;
                continue;
            }

            $parts[] = $index === count( $sentences ) - 1 ? ( $emotional ? '[softly] ' : '[warmly] ' ) . $sentence : $sentence;
        }

        $script = trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $parts ) ) );
        if ( strlen( self::strip_narration_directions( $script ) ) > STLAI_ElevenLabs_Provider::MAX_SCRIPT_LENGTH ) {
            return $clean;
        }

        return $script;
    }

    private static function should_retry_audio_without_directions( WP_Error $error ) {
        $code = $error->get_error_code();
        if ( in_array( $code, array( 'MISSING_ELEVENLABS_API_KEY', 'MISSING_ELEVENLABS_VOICE', 'EMPTY_NARRATION_TEXT' ), true ) ) {
            return false;
        }

        return true;
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

        $video_language = self::sanitize_video_language( wp_unslash( $payload['video_language'] ?? ( $payload['narration_language'] ?? 'pt-BR' ) ) );
        $narration_language = self::sanitize_video_language( wp_unslash( $payload['narration_language'] ?? $video_language ) );
        $narration_style = sanitize_key( $payload['narration_style'] ?? $narration_type );
        if ( ! in_array( $narration_style, array( 'persuasiva', 'emocional' ), true ) ) {
            $narration_style = $narration_type;
        }

        $script = wp_kses_post( wp_unslash( $payload['script_public'] ?? ( $payload['script'] ?? '' ) ) );
        $script = self::normalize_script_terms( $script, $video_language );
        if ( empty( trim( wp_strip_all_tags( $script ) ) ) ) {
            return new WP_Error( 'EMPTY_NARRATION_TEXT', 'O roteiro da narração está vazio.' );
        }

        if ( strlen( trim( wp_strip_all_tags( $script ) ) ) > STLAI_ElevenLabs_Provider::MAX_SCRIPT_LENGTH ) {
            return new WP_Error( 'NARRATION_TOO_LONG', 'O roteiro está muito longo. Reduza o texto da narração.' );
        }

        $video_config = self::commercial_video_config();

        return array(
            'job_id'               => sanitize_text_field( wp_unslash( $payload['job_id'] ?? '' ) ),
            'selected_images'      => $images,
            'narration_type'       => $narration_type,
            'format'               => $format,
            'script'               => $script,
            'script_tts'           => wp_kses_post( wp_unslash( $payload['script_tts'] ?? '' ) ),
            'video_language'       => $video_language,
            'narration_language'   => $narration_language,
            'narration_style'      => $narration_style,
            'video_provider'        => $video_config['provider'],
            'video_model'           => $video_config['model'],
            'output_resolution'     => $video_config['effective_resolution'],
            'requested_resolution'  => $video_config['requested_resolution'],
            'effective_resolution'  => $video_config['effective_resolution'],
            'resolution_fallback_reason' => $video_config['resolution_fallback_reason'],
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

    private static function commercial_video_config() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $provider = sanitize_key( (string) ( $settings['commercialVideoProvider'] ?? '' ) );
        if ( empty( $provider ) ) {
            $legacy_provider = sanitize_key( (string) ( $settings['videoProvider'] ?? '' ) );
            $provider = in_array( $legacy_provider, array( 'veo', 'gemini_veo' ), true ) ? self::DEFAULT_VIDEO_PROVIDER : $legacy_provider;
        }
        if ( ! in_array( $provider, array( 'gemini_veo', 'fal_ai', 'atlas_cloud', 'muapi', 'custom' ), true ) ) {
            $provider = self::DEFAULT_VIDEO_PROVIDER;
        }

        $model = sanitize_text_field( (string) ( $settings['commercialVideoModel'] ?? ( $settings['videoModel'] ?? '' ) ) );
        $allowed_models = array(
            'veo-3.1-lite-generate-preview',
            'veo-3.1-fast-generate-preview',
            'veo-3.1-generate-preview',
            'veo-2.0-generate-001',
        );
        if ( ! in_array( $model, $allowed_models, true ) ) {
            $model = self::DEFAULT_VIDEO_MODEL;
        }

        $requested_resolution = sanitize_key( (string) ( $settings['commercialVideoOutputResolution'] ?? self::DEFAULT_OUTPUT_RESOLUTION ) );
        $fallback_reason = '';
        if ( ! in_array( $requested_resolution, array( '720p', '1080p' ), true ) ) {
            $requested_resolution = self::DEFAULT_OUTPUT_RESOLUTION;
            $fallback_reason = 'invalid_resolution_fallback_to_720p';
        }

        return array(
            'provider'                   => $provider,
            'model'                      => $model,
            'requested_resolution'       => $requested_resolution,
            'effective_resolution'       => $requested_resolution,
            'resolution_fallback_reason' => $fallback_reason,
        );
    }

    private static function clip_roles() {
        return array(
            array(
                'role'      => 'apresentacao_geral',
                'label'     => 'Clipe 1 — Apresentação geral',
                'direction' => 'Use the selected image as-is. Start with the full product visible, stable product presentation, medium/wide framing, slow gentle zoom in. Do not create a new scene. Do not crop the product top, base, face, ring, support or display stand.',
            ),
            array(
                'role'      => 'uso_contexto',
                'label'     => 'Clipe 2 — Uso / contexto',
                'direction' => 'Use the selected image as-is. Gentle camera drift showing the product in its existing context with the whole product safely inside frame. Do not create a new use case or tighter crop.',
            ),
            array(
                'role'      => 'detalhe_acabamento',
                'label'     => 'Clipe 3 — Detalhe / acabamento',
                'direction' => 'Use the selected image as-is. Subtle detail emphasis, but keep the full product or all important product parts visible. Avoid aggressive close-up and do not cut head, top, base, ring, support or finish details.',
            ),
            array(
                'role'      => 'hero_fechamento',
                'label'     => 'Clipe 4 — Hero / fechamento',
                'direction' => 'Use the selected image as-is. Premium slow zoom out or slight parallax, elegant final product shot with the entire product visible. Preserve the exact product presentation, support, base, hook, display stand, surface, attachment point and display position. Keep the product anchored exactly as shown. Do not detach, lift, pull, hang, place, attach, fit, remove or transform the product. Do not show any hand interaction unless a hand is already clearly present in the source image.',
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
            'prepared_frame_url' => esc_url_raw( $clip['prepared_frame_url'] ?? '' ),
            'prepared_frame_width' => (int) ( $clip['prepared_frame_width'] ?? 0 ),
            'prepared_frame_height' => (int) ( $clip['prepared_frame_height'] ?? 0 ),
            'aspect_ratio' => sanitize_text_field( $clip['aspect_ratio'] ?? '' ),
        );
    }

    private static function normalize_video_frames( $frames ) {
        if ( ! is_array( $frames ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $frames as $frame ) {
            if ( ! is_array( $frame ) || empty( $frame['url'] ) ) {
                continue;
            }

            $public_frame = self::public_video_frame_data( $frame );
            $index = (int) ( $public_frame['index'] ?? 0 );
            if ( $index < 1 || $index > 4 ) {
                continue;
            }
            $normalized[ $index ] = $public_frame;
        }

        usort(
            $normalized,
            function ( $a, $b ) {
                return (int) ( $a['index'] ?? 0 ) <=> (int) ( $b['index'] ?? 0 );
            }
        );

        return array_values( $normalized );
    }

    private static function video_frames_from_clips( $clips ) {
        $frames = array();
        foreach ( self::normalize_clip_list( $clips ) as $clip ) {
            if ( empty( $clip['prepared_frame_url'] ) ) {
                continue;
            }

            $frames[] = self::public_video_frame_data(
                array(
                    'index'        => (int) ( $clip['index'] ?? 0 ),
                    'url'          => $clip['prepared_frame_url'],
                    'aspect_ratio' => $clip['aspect_ratio'] ?? '',
                    'label'        => 'Imagem ' . (int) ( $clip['index'] ?? 0 ),
                    'width'        => (int) ( $clip['prepared_frame_width'] ?? 0 ),
                    'height'       => (int) ( $clip['prepared_frame_height'] ?? 0 ),
                )
            );
        }

        return self::normalize_video_frames( $frames );
    }

    private static function public_video_frame_data( array $frame ) {
        $index = (int) ( $frame['index'] ?? 0 );
        return array(
            'index'        => $index,
            'url'          => esc_url_raw( $frame['url'] ?? '' ),
            'aspect_ratio' => sanitize_text_field( $frame['aspect_ratio'] ?? '' ),
            'label'        => sanitize_text_field( $frame['label'] ?? ( 'Imagem ' . $index ) ),
            'width'        => (int) ( $frame['width'] ?? 0 ),
            'height'       => (int) ( $frame['height'] ?? 0 ),
        );
    }

    private static function generate_clip_with_retries( array $job, array $clips, array $payload ) {
        $clip_index = (int) ( $payload['index'] ?? 0 );
        $role = array(
            'role'  => sanitize_key( $payload['role'] ?? '' ),
            'label' => sanitize_text_field( $payload['role_label'] ?? '' ),
        );
        $last_error = null;
        $start_attempt = max( 1, min( self::CLIP_MAX_ATTEMPTS, (int) ( $payload['start_attempt'] ?? 1 ) ) );

        for ( $attempt = $start_attempt; $attempt <= self::CLIP_MAX_ATTEMPTS; $attempt++ ) {
            $retry_count = max( 0, $attempt - 1 );
            $is_retry = $attempt > $start_attempt || $attempt > 1;

            if ( $is_retry ) {
                sleep( 2 === $attempt ? 2 : 5 );
            }

            $latest_for_attempt = STLAI_Video_Storage::get_job( $job['job_id'] ) ?: $job;
            $attempt_clips = self::normalize_clip_list( $latest_for_attempt['clips'] ?? $clips );
            $clip_jobs = self::replace_clip_job(
                self::normalize_clip_jobs( $latest_for_attempt['clip_jobs'] ?? array(), $attempt_clips, false ),
                $clip_index,
                array(
                    'status'     => $is_retry ? 'retrying' : 'generating',
                    'attempt'    => $attempt,
                    'error'      => $last_error ? self::safe_error_summary( $last_error ) : '',
                    'started_at' => current_time( 'mysql' ),
                )
            );

            STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                    'status'               => ( $is_retry ? 'retrying_clip_' : 'generating_clip_' ) . $clip_index,
                    'progress'             => self::clip_progress( $clip_index ),
                    'progress_hint'        => self::clip_progress_hint( $clip_index ),
                    'message'              => $is_retry
                        ? 'Ajustando geração do clipe ' . $clip_index . '. Tentativa ' . $attempt . ' de ' . self::CLIP_MAX_ATTEMPTS . '.'
                        : 'Gerando clipe ' . $clip_index . ' de 4.',
                    'current_clip_index'   => $clip_index,
                    'current_clip_attempt' => $attempt,
                    'clip_retry_count'     => $retry_count,
                    'last_clip_error'      => $last_error ? self::safe_error_summary( $last_error ) : '',
                    'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                    'retryable'            => false,
                    'retry_reason'         => '',
                    'will_retry'           => false,
                    'error_final_reason'   => '',
                    'failed_clip_index'    => 0,
                    'failed_clip_role'     => '',
                    'error_code'           => '',
                    'error_message'        => '',
                    'error_debug'          => '',
                    )
                )
            );

            $clip = STLAI_Veo_Provider::generate_clip( $payload );
            if ( ! is_wp_error( $clip ) ) {
                return $clip;
            }

            $last_error = $clip;
            $retryable = self::is_retryable_clip_error( $clip );
            $retry_reason = self::clip_retry_reason( $clip );
            if ( ! $retryable || $attempt >= self::CLIP_MAX_ATTEMPTS ) {
                return self::clip_generation_error( $clip, $clip_index, $role, $attempt, $retryable );
            }

            $clip_jobs = self::replace_clip_job(
                $clip_jobs,
                $clip_index,
                array(
                    'status'  => 'retrying',
                    'attempt' => $attempt,
                    'error'   => self::safe_error_summary( $clip ),
                )
            );

            STLAI_Video_Storage::update_job(
                $job['job_id'],
                array_merge(
                    self::clip_job_state_fields( $clip_jobs ),
                    array(
                    'status'               => 'retrying_clip_' . $clip_index,
                    'progress'             => self::clip_progress( $clip_index ),
                    'progress_hint'        => self::clip_progress_hint( $clip_index ),
                    'message'              => 'Ajustando clipe IA automaticamente. Refazendo o clipe ' . $clip_index . '. Tentativa ' . ( $attempt + 1 ) . ' de ' . self::CLIP_MAX_ATTEMPTS . '.',
                    'current_clip_index'   => $clip_index,
                    'current_clip_attempt' => $attempt,
                    'clip_retry_count'     => max( 0, $attempt - 1 ),
                    'last_clip_error'      => self::safe_error_summary( $clip ),
                    'error_debug'          => self::clip_retry_debug( $clip, $clip_index, $attempt, true ),
                    'max_clip_attempts'    => self::CLIP_MAX_ATTEMPTS,
                    'retryable'            => true,
                    'retry_reason'         => $retry_reason,
                    'will_retry'           => true,
                    'error_final_reason'   => '',
                    'next_clip_action'     => 'retry_clip',
                    'next_clip_index'      => $clip_index,
                    )
                )
            );
        }

        return self::clip_generation_error( $last_error, $clip_index, $role, self::CLIP_MAX_ATTEMPTS, true );
    }

    private static function clip_generation_error( $error, $clip_index, array $role, $attempt, $retryable ) {
        if ( ! is_wp_error( $error ) ) {
            return self::composition_error( 'VEO_CLIP_' . (int) $clip_index . '_ERROR', 'Não foi possível gerar o clipe ' . (int) $clip_index . '.', '' );
        }

        $data = $error->get_error_data();
        $base_debug = self::clip_error_debug( is_array( $data ) ? $data : array(), $role, '' );
        $retry_debug = self::clip_retry_debug( $error, $clip_index, $attempt, $retryable );
        $retry_reason = self::clip_retry_reason( $error );

        return new WP_Error(
            'VEO_CLIP_' . (int) $clip_index . '_ERROR',
            'Não foi possível gerar o clipe ' . (int) $clip_index . ' após ' . self::CLIP_MAX_ATTEMPTS . ' tentativas.',
            array(
                'debug'              => trim( $base_debug . '; ' . $retry_debug, '; ' ),
                'failed_clip_index'  => (int) $clip_index,
                'failed_clip_role'   => sanitize_key( $role['role'] ?? '' ),
                'current_clip_index' => (int) $clip_index,
                'current_clip_attempt' => (int) $attempt,
                'clip_retry_count'   => max( 0, (int) $attempt - 1 ),
                'max_clip_attempts'  => self::CLIP_MAX_ATTEMPTS,
                'retryable'          => (bool) $retryable,
                'retry_reason'       => $retry_reason,
                'will_retry'         => false,
                'error_final_reason' => $retryable ? 'max_attempts_exhausted' : 'non_retryable_error',
                'last_error_code'    => $error->get_error_code(),
                'last_error_summary' => self::safe_error_summary( $error ),
            )
        );
    }

    private static function is_retryable_clip_error( WP_Error $error ) {
        $code = $error->get_error_code();
        if ( 'VEO_OPERATION_TIMEOUT' === $code ) {
            return true;
        }
        $data = $error->get_error_data();
        $debug = is_array( $data ) ? (string) ( $data['debug'] ?? '' ) : '';
        return self::is_retryable_clip_error_parts( $code, self::safe_error_summary( $error ), $debug );
    }

    private static function clip_retry_reason( WP_Error $error ) {
        $summary = strtolower( self::safe_error_summary( $error ) );
        if ( 'VEO_INVALID_RESPONSE' === $error->get_error_code() && preg_match( '/uri do v[ií]deo ausente|video uri missing|missing video uri|operation completed without video|opera[cç][aã]o conclu[ií]da sem v[ií]deo|empty video response|response without video|completed operation without generated video|generatedvideos vazio|video uri ausente|sem uri/i', $summary ) ) {
            return 'veo_completed_without_video_uri';
        }
        if ( preg_match( '/timeout|timed out|operation timeout/i', $summary ) ) {
            return 'timeout';
        }
        if ( preg_match( '/http\s+(408|409|429|500|502|503|504)\b/i', $summary, $matches ) ) {
            return 'http_' . $matches[1];
        }
        if ( 'VEO_INVALID_RESPONSE' === $error->get_error_code() ) {
            return 'veo_invalid_response';
        }
        if ( 'VEO_REQUEST_ERROR' === $error->get_error_code() ) {
            return 'veo_request_error';
        }
        return 'temporary_or_unknown';
    }

    private static function clip_retry_reason_from_summary( $summary ) {
        $summary = strtolower( (string) $summary );
        if ( preg_match( '/uri do v[ií]deo ausente|video uri missing|missing video uri|operation completed without video|opera[cç][aã]o conclu[ií]da sem v[ií]deo|empty video response|response without video|completed operation without generated video|generatedvideos vazio|video uri ausente|sem uri/i', $summary ) ) {
            return 'veo_completed_without_video_uri';
        }
        if ( false !== strpos( $summary, 'veo_invalid_response' ) || preg_match( '/o servi[cç]o de v[ií]deo n[aã]o retornou um v[ií]deo v[aá]lido/i', $summary ) ) {
            return 'veo_invalid_response';
        }
        if ( preg_match( '/timeout|timed out|operation timeout/i', $summary ) ) {
            return 'timeout';
        }
        if ( preg_match( '/408|409|429|500|502|503|504/', $summary, $matches ) ) {
            return 'http_' . $matches[0];
        }
        return 'temporary_or_unknown';
    }

    private static function safe_error_summary( WP_Error $error ) {
        $data = $error->get_error_data();
        $debug = is_array( $data ) ? (string) ( $data['debug'] ?? '' ) : '';
        $summary = trim( $error->get_error_code() . ': ' . $error->get_error_message() . ( $debug ? ' - ' . $debug : '' ) );
        $summary = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $summary );
        $summary = preg_replace( '/(api[_-]?key|token|authorization)\s*[:=]\s*[^;\s]+/i', '$1=[redacted]', $summary );
        $summary = preg_replace( '/\s+/', ' ', $summary );

        return sanitize_text_field( substr( $summary, 0, 500 ) );
    }

    private static function clip_retry_debug( WP_Error $error, $clip_index, $attempt, $retryable ) {
        return implode(
            '; ',
            array(
                'failed_clip_index=' . (int) $clip_index,
                'clip_attempt=' . (int) $attempt,
                'max_attempts=' . self::CLIP_MAX_ATTEMPTS,
                'retryable=' . ( $retryable ? 'true' : 'false' ),
                'retry_reason=' . self::clip_retry_reason( $error ),
                'last_error_code=' . sanitize_key( $error->get_error_code() ),
                'last_error_summary=' . self::safe_error_summary( $error ),
            )
        );
    }

    private static function clip_progress( $clip_index ) {
        $map = array(
            1 => 31,
            2 => 44,
            3 => 58,
            4 => 72,
        );

        $clip_index = (int) $clip_index;

        return $map[ $clip_index ] ?? 30;
    }

    private static function clip_progress_hint( $clip_index, $complete = false ) {
        $ranges = array(
            1 => array( 25, 37 ),
            2 => array( 38, 51 ),
            3 => array( 52, 65 ),
            4 => array( 66, 78 ),
        );

        $clip_index = (int) $clip_index;
        if ( empty( $ranges[ $clip_index ] ) ) {
            return 25;
        }

        return $complete ? $ranges[ $clip_index ][1] : $ranges[ $clip_index ][0];
    }

    private static function composition_progress( $remote_status, $remote_progress, $fallback = 80 ) {
        $remote_status = sanitize_key( $remote_status );

        if ( 'queued' === $remote_status ) {
            return 80;
        }

        if ( 'ready' === $remote_status ) {
            return 100;
        }

        $remote_progress = max( 0, min( 100, (int) $remote_progress ) );
        if ( $remote_progress <= 0 ) {
            return max( 82, min( 96, (int) $fallback ) );
        }

        return max( 82, min( 96, 82 + (int) floor( $remote_progress * 0.14 ) ) );
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
