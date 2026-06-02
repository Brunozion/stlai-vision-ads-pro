<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Storage {
    const TRANSIENT_PREFIX = 'stlai_video_job_';
    const TTL = DAY_IN_SECONDS;
    const CLIP_GENERATION_STALE_SECONDS = 75;
    const CLIP_STALE_SECONDS = 75;

    public static function create_job( array $data ) {
        $now = current_time( 'mysql' );
        $job_id = 'stlai_video_' . wp_generate_uuid4();

        $job = array_merge(
            array(
                'job_id'          => $job_id,
                'status'          => 'queued',
                'progress'        => 10,
                'progress_hint'   => 10,
                'selected_images' => array(),
                'narration_type'  => 'persuasiva',
                'format'          => '16:9',
                'video_provider'  => 'gemini_veo',
                'video_model'     => 'veo-3.1-lite-generate-preview',
                'output_resolution' => '720p',
                'requested_resolution' => '720p',
                'effective_resolution' => '720p',
                'resolution_fallback_reason' => '',
                'script'          => '',
                'script_public'   => '',
                'script_narration' => '',
                'product_name'    => '',
                'product_description' => '',
                'message'         => 'Job de video criado com sucesso.',
                'audio_url'       => '',
                'audio_path'      => '',
                'audio_provider'  => '',
                'audio_model'     => '',
                'audio_voice_id'  => '',
                'clips'           => array(),
                'partial_clips'   => array(),
                'video_frames'    => array(),
                'clip_jobs'       => array(),
                'clip_statuses'   => array(),
                'clip_attempts'   => array(),
                'clip_errors'     => array(),
                'clip_started_at' => array(),
                'clip_finished_at' => array(),
                'missing_clips'   => array( 1, 2, 3, 4 ),
                'ugc_jobs'        => array(),
                'final_video_url' => '',
                'final_video_path' => '',
                'final_video_duration' => 0,
                'final_video_debug' => '',
                'transition_used' => '',
                'fallback_used'   => '',
                'composer_mode'   => '',
                'composer_provider' => '',
                'composer_status' => '',
	                'render_job_id'   => '',
	                'composition_started_at' => '',
                'composed_at'     => '',
                'thumbnail_url'   => '',
                'composition_status' => 'pending',
                'current_clip_index' => 0,
                'current_clip_attempt' => 0,
                'max_clip_attempts' => 3,
                'max_concurrent_clip_generations' => 4,
                'clip_start_stagger_seconds' => 1,
                'clip_generation_mode' => 'staggered_parallel',
                'clip_retry_count' => 0,
                'last_clip_error' => '',
                'retryable'      => false,
                'retry_reason'   => '',
                'will_retry'     => false,
                'error_final_reason' => '',
                'failed_clip_index' => 0,
                'failed_clip_role' => '',
                'error_code'      => '',
                'error_message'   => '',
                'error_debug'     => '',
	                'poll_count'      => 0,
	                'job_version'     => 1,
	                'clips_ready_count' => 0,
	                'created_at'      => $now,
	                'updated_at'      => $now,
	            ),
            $data
        );

        set_transient( self::key( $job_id ), $job, self::TTL );

        return $job;
    }

    public static function get_job( $job_id ) {
        $job_id = sanitize_text_field( $job_id );
        if ( empty( $job_id ) ) {
            return null;
        }

        $job = get_transient( self::key( $job_id ) );

        return is_array( $job ) ? $job : null;
    }

    public static function update_job( $job_id, array $data ) {
        $job = self::get_job( $job_id );
        if ( ! $job ) {
            return null;
        }

	        $job = self::merge_job_data( $job, $data );
	        $job['updated_at'] = current_time( 'mysql' );
	        $job['job_version'] = max( 1, (int) ( $job['job_version'] ?? 1 ) ) + 1;
	        $job = self::refresh_derived_state( $job );

        set_transient( self::key( $job_id ), $job, self::TTL );

        return $job;
    }

	    private static function key( $job_id ) {
	        return self::TRANSIENT_PREFIX . sanitize_key( $job_id );
	    }

	    private static function merge_job_data( array $existing, array $incoming ) {
	        $data = $incoming;
	        $reset_composition = ! empty( $incoming['reset_composition'] );
	        $reset_clip_failures = ! empty( $incoming['reset_clip_failures'] );
	        unset( $data['reset_composition'], $data['reset_clip_failures'] );

	        $existing_clips = self::merge_clips(
	            self::normalize_clips( $existing['clips'] ?? array() ),
	            self::normalize_clips( $existing['partial_clips'] ?? array() )
	        );
	        $incoming_clips = self::merge_clips(
	            self::normalize_clips( $incoming['clips'] ?? array() ),
	            self::normalize_clips( $incoming['partial_clips'] ?? array() )
	        );
	        $merged_clips = self::merge_clips( $existing_clips, $incoming_clips );

	        if ( isset( $incoming['partial_clips'] ) ) {
	            $merged_clips = self::merge_clips( $merged_clips, self::normalize_clips( $incoming['partial_clips'] ) );
	        }

	        $existing_jobs = self::normalize_clip_jobs( $existing['clip_jobs'] ?? array(), $existing_clips );
	        if ( $reset_clip_failures ) {
	            $existing_jobs = self::reset_clip_failures( $existing_jobs );
	        }
	        $incoming_jobs = self::normalize_clip_jobs( $incoming['clip_jobs'] ?? array(), $incoming_clips );
	        $merged_jobs = self::merge_clip_jobs( $existing_jobs, $incoming_jobs, $merged_clips );
	        $merged_clips = self::merge_clips( $merged_clips, self::clips_from_clip_jobs( $merged_jobs ) );
	        $merged_jobs = self::normalize_clip_jobs( $merged_jobs, $merged_clips );
	        $merged_ugc_jobs = self::merge_ugc_jobs(
	            self::normalize_ugc_jobs( $existing['ugc_jobs'] ?? array() ),
	            self::normalize_ugc_jobs( $incoming['ugc_jobs'] ?? array() )
	        );

	        unset( $data['clips'], $data['partial_clips'], $data['clip_jobs'], $data['clip_statuses'], $data['clip_attempts'], $data['clip_errors'], $data['clip_started_at'], $data['clip_finished_at'], $data['missing_clips'], $data['ugc_jobs'] );

	        $job = array_merge( $existing, $data );
	        $job['clips'] = $merged_clips;
	        $job['partial_clips'] = $merged_clips;
	        $job['clip_jobs'] = $merged_jobs;
	        $job['ugc_jobs'] = $merged_ugc_jobs;

	        if ( ! $reset_composition && empty( $incoming['final_video_url'] ?? '' ) && ! empty( $existing['final_video_url'] ?? '' ) ) {
	            $job['final_video_url'] = $existing['final_video_url'];
	        }

	        if ( empty( $incoming['audio_url'] ?? '' ) && ! empty( $existing['audio_url'] ?? '' ) ) {
	            $job['audio_url'] = $existing['audio_url'];
	        }

	        if ( ! $reset_composition && empty( $incoming['render_job_id'] ?? '' ) && ! empty( $existing['render_job_id'] ?? '' ) ) {
	            $job['render_job_id'] = $existing['render_job_id'];
	        }

	        if ( ! $reset_composition && empty( $incoming['composition_started_at'] ?? '' ) && ! empty( $existing['composition_started_at'] ?? '' ) ) {
	            $job['composition_started_at'] = $existing['composition_started_at'];
	        }

	        $job['progress'] = max( (int) ( $existing['progress'] ?? 0 ), (int) ( $incoming['progress'] ?? 0 ) );
	        $job['progress_hint'] = max( (int) ( $existing['progress_hint'] ?? 0 ), (int) ( $incoming['progress_hint'] ?? 0 ), (int) $job['progress'] );

	        return $job;
	    }

	    private static function normalize_ugc_jobs( $jobs ) {
	        $normalized = array();
	        if ( ! is_array( $jobs ) ) {
	            return $normalized;
	        }

	        foreach ( $jobs as $job ) {
	            if ( ! is_array( $job ) || empty( $job['ugc_job_id'] ) ) {
	                continue;
	            }
	            $normalized[] = array(
	                'ugc_job_id'          => sanitize_text_field( $job['ugc_job_id'] ?? '' ),
	                'parent_job_id'       => sanitize_text_field( $job['parent_job_id'] ?? '' ),
	                'preset'              => sanitize_key( $job['preset'] ?? '' ),
	                'label'               => sanitize_text_field( $job['label'] ?? '' ),
	                'provider'            => sanitize_key( $job['provider'] ?? 'muapi' ),
	                'provider_label'      => sanitize_text_field( $job['provider_label'] ?? '' ),
	                'model'               => sanitize_text_field( $job['model'] ?? '' ),
	                'request_id'          => sanitize_text_field( $job['request_id'] ?? '' ),
	                'operation_id'        => sanitize_text_field( $job['operation_id'] ?? ( $job['request_id'] ?? '' ) ),
	                'status_url'          => esc_url_raw( $job['status_url'] ?? '' ),
	                'result_url'          => esc_url_raw( $job['result_url'] ?? '' ),
	                'endpoint_used'       => sanitize_text_field( $job['endpoint_used'] ?? '' ),
	                'status'              => sanitize_key( $job['status'] ?? 'processing' ),
	                'image_url'           => is_string( $job['image_url'] ?? '' ) ? (string) $job['image_url'] : '',
	                'selected_image_label' => sanitize_text_field( $job['selected_image_label'] ?? '' ),
	                'prompt_public'       => sanitize_textarea_field( $job['prompt_public'] ?? '' ),
	                'prompt_final'        => sanitize_textarea_field( $job['prompt_final'] ?? '' ),
	                'aspect_ratio'        => sanitize_text_field( $job['aspect_ratio'] ?? '9:16' ),
	                'duration'            => (int) ( $job['duration'] ?? 9 ),
	                'resolution'          => sanitize_key( $job['resolution'] ?? '720p' ),
	                'video_url'           => esc_url_raw( $job['video_url'] ?? '' ),
	                'error_message'       => sanitize_text_field( $job['error_message'] ?? '' ),
	                'raw_status'          => sanitize_key( $job['raw_status'] ?? '' ),
	                'created_at'          => sanitize_text_field( $job['created_at'] ?? '' ),
	                'updated_at'          => sanitize_text_field( $job['updated_at'] ?? '' ),
	            );
	        }

	        return $normalized;
	    }

	    private static function merge_ugc_jobs( array $existing, array $incoming ) {
	        $by_id = array();
	        foreach ( $existing as $job ) {
	            if ( ! empty( $job['ugc_job_id'] ) ) {
	                $by_id[ $job['ugc_job_id'] ] = $job;
	            }
	        }
	        foreach ( $incoming as $job ) {
	            if ( empty( $job['ugc_job_id'] ) ) {
	                continue;
	            }
	            $by_id[ $job['ugc_job_id'] ] = self::stronger_ugc_job( $by_id[ $job['ugc_job_id'] ] ?? array(), $job );
	        }
	        return array_values( $by_id );
	    }

	    private static function stronger_ugc_job( array $existing, array $incoming ) {
	        $job = array_merge( $existing, $incoming );
	        if ( ! empty( $existing['video_url'] ) && empty( $incoming['video_url'] ) ) {
	            $job['video_url'] = $existing['video_url'];
	        }
	        if ( 'ready' === ( $existing['status'] ?? '' ) && 'ready' !== ( $incoming['status'] ?? '' ) ) {
	            $job['status'] = 'ready';
	        }
	        if ( empty( $incoming['created_at'] ) && ! empty( $existing['created_at'] ) ) {
	            $job['created_at'] = $existing['created_at'];
	        }
	        return $job;
	    }

	    private static function refresh_derived_state( array $job ) {
	        $clips = self::merge_clips(
	            self::normalize_clips( $job['clips'] ?? array() ),
	            self::normalize_clips( $job['partial_clips'] ?? array() )
	        );
	        $clip_jobs = self::normalize_clip_jobs( $job['clip_jobs'] ?? array(), $clips );
	        $clips = self::merge_clips( $clips, self::clips_from_clip_jobs( $clip_jobs ) );
	        $clip_jobs = self::normalize_clip_jobs( $clip_jobs, $clips );
	        $statuses = array();
	        $attempts = array();
	        $errors = array();
	        $started = array();
	        $finished = array();
	        $missing = array();
	        $ready = 0;
	        $active_clip_operations = 0;
	        $scheduled_clip_indexes = array();
	        $retrying_clip_indexes = array();
	        $error_final_clip_indexes = array();

	        foreach ( $clip_jobs as $clip_job ) {
	            $index = (int) ( $clip_job['index'] ?? 0 );
	            $status = sanitize_key( $clip_job['status'] ?? 'pending' );
	            $statuses[ $index ] = $status;
	            $attempts[ $index ] = (int) ( $clip_job['attempt'] ?? 0 );
	            $errors[ $index ] = sanitize_text_field( $clip_job['error'] ?? '' );
	            $started[ $index ] = sanitize_text_field( $clip_job['started_at'] ?? '' );
	            $finished[ $index ] = sanitize_text_field( $clip_job['finished_at'] ?? '' );
	            if ( 'ready' === $status ) {
	                $ready++;
	            } else {
	                $missing[] = $index;
	            }
	            if ( ! empty( $clip_job['operation_id'] ) && empty( $clip_job['url'] ) && in_array( $status, array( 'pending', 'generating', 'retrying' ), true ) ) {
	                $active_clip_operations++;
	            }
	            if ( 'scheduled' === $status ) {
	                $scheduled_clip_indexes[] = $index;
	            }
	            if ( 'retrying' === $status ) {
	                $retrying_clip_indexes[] = $index;
	            }
	            if ( 'error_final' === $status && (int) ( $clip_job['attempt'] ?? 0 ) >= 3 && empty( $clip_job['operation_id'] ) ) {
	                $error_final_clip_indexes[] = $index;
	            }
	        }

	        $job['clips'] = $clips;
	        $job['partial_clips'] = $clips;
	        $job['clip_jobs'] = $clip_jobs;
	        $job['clip_statuses'] = $statuses;
	        $job['clip_attempts'] = $attempts;
	        $job['clip_errors'] = $errors;
	        $job['clip_started_at'] = $started;
	        $job['clip_finished_at'] = $finished;
	        $job['missing_clips'] = $missing;
	        $job['clips_ready_count'] = $ready;

	        $current_status = sanitize_key( $job['status'] ?? '' );
	        $composition_status = sanitize_key( $job['composition_status'] ?? '' );
	        $composer_status = sanitize_key( $job['composer_status'] ?? '' );

	        if ( ! empty( $job['final_video_url'] ) ) {
	            $job['status'] = 'ready';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), 100 );
	            $job['progress_hint'] = 100;
	        } elseif ( in_array( $composition_status, array( 'processing' ), true ) || in_array( $composer_status, array( 'processing' ), true ) || in_array( $current_status, array( 'composition_processing', 'composing_final_video' ), true ) ) {
	            $job['status'] = 'composition_processing';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), 82 );
	        } elseif ( in_array( $composition_status, array( 'queued' ), true ) || in_array( $composer_status, array( 'queued' ), true ) || 'composition_queued' === $current_status ) {
	            $job['status'] = 'composition_queued';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), 80 );
	        } elseif ( $ready >= 4 && ! empty( $job['audio_url'] ) && ! in_array( $current_status, array( 'composition_error', 'composition_pending' ), true ) ) {
	            $job['status'] = 'clips_ready';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), 78 );
	        } elseif ( $active_clip_operations > 0 || ! empty( $scheduled_clip_indexes ) || ! empty( $retrying_clip_indexes ) ) {
	            $job['status'] = 'generating_clips';
	            $job['message'] = 'Gerando clipes IA';
	            $job['failed_clip_index'] = 0;
	            $job['failed_clip_role'] = '';
	            $job['error_final_reason'] = '';
	            $job['error_code'] = '';
	            $job['error_message'] = '';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), self::clip_progress_for_ready_count( $ready ) );
	        } elseif ( $ready > 0 && $ready < 4 && ! in_array( $current_status, array( 'clip_generation_error', 'clips_partial_error' ), true ) ) {
	            $job['status'] = 'generating_clips';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), self::clip_progress_for_ready_count( $ready ) );
	        } elseif ( ! empty( $job['audio_url'] ) && $ready <= 0 && in_array( $current_status, array( 'generating_audio', 'generating_narration', 'queued' ), true ) ) {
	            $job['status'] = 'generating_clips';
	            $job['progress'] = max( (int) ( $job['progress'] ?? 0 ), 25 );
	        } elseif ( empty( $active_clip_operations ) && empty( $scheduled_clip_indexes ) && empty( $retrying_clip_indexes ) && ! empty( $error_final_clip_indexes ) ) {
	            $job['status'] = 'clip_generation_error';
	        }

	        return $job;
	    }

	    private static function merge_clips( array $existing, array $incoming ) {
	        $by_index = array();
	        foreach ( array_merge( $existing, $incoming ) as $clip ) {
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

	    private static function normalize_clips( $clips ) {
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
	            $normalized[ $index ] = array_merge( $clip, array( 'index' => $index, 'url' => $url ) );
	        }
	        ksort( $normalized );
	        return array_values( $normalized );
	    }

	    private static function normalize_clip_jobs( $clip_jobs, array $clips ) {
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
	                $by_index[ $index ] = array(
	                    'index'       => $index,
	                    'status'      => in_array( $status, array( 'pending', 'queued', 'scheduled', 'generating', 'retrying', 'ready', 'error', 'error_final' ), true ) ? $status : 'pending',
	                    'attempt'     => max( 0, (int) ( $clip_job['attempt'] ?? 0 ) ),
	                    'url'         => esc_url_raw( $clip_job['url'] ?? '' ),
	                    'error'       => sanitize_text_field( $clip_job['error'] ?? '' ),
	                    'scheduled_start_at' => sanitize_text_field( $clip_job['scheduled_start_at'] ?? '' ),
	                    'started_at'  => sanitize_text_field( $clip_job['started_at'] ?? '' ),
	                    'finished_at' => sanitize_text_field( $clip_job['finished_at'] ?? '' ),
	                    'operation_id' => sanitize_text_field( $clip_job['operation_id'] ?? '' ),
	                    'operation_poll_count' => max( 0, (int) ( $clip_job['operation_poll_count'] ?? 0 ) ),
	                    'operation_checked_at' => sanitize_text_field( $clip_job['operation_checked_at'] ?? '' ),
	                    'operation_still_processing' => ! empty( $clip_job['operation_still_processing'] ),
	                    'provider' => sanitize_key( $clip_job['provider'] ?? ( $clip_job['video_provider'] ?? '' ) ),
	                    'model' => sanitize_text_field( $clip_job['model'] ?? ( $clip_job['video_model'] ?? '' ) ),
	                    'output_resolution' => sanitize_key( $clip_job['output_resolution'] ?? '' ),
	                    'requested_resolution' => sanitize_key( $clip_job['requested_resolution'] ?? '' ),
	                    'effective_resolution' => sanitize_key( $clip_job['effective_resolution'] ?? ( $clip_job['output_resolution'] ?? '' ) ),
	                    'aspect_ratio' => sanitize_text_field( $clip_job['aspect_ratio'] ?? '' ),
	                    'prepared_frame_url' => esc_url_raw( $clip_job['prepared_frame_url'] ?? '' ),
	                );
	                $by_index[ $index ] = self::recover_premature_final_clip_job( $by_index[ $index ] );
	                $by_index[ $index ] = self::recover_stale_clip_job( $by_index[ $index ] );
	            }
	        }

	        foreach ( $clips as $clip ) {
	            $index = (int) ( $clip['index'] ?? 0 );
	            if ( $index < 1 || $index > 4 || empty( $clip['url'] ) ) {
	                continue;
	            }
	            $by_index[ $index ] = array_merge(
	                $by_index[ $index ] ?? array( 'index' => $index, 'attempt' => 1, 'started_at' => '', 'finished_at' => '' ),
	                array(
	                    'status'      => 'ready',
	                    'attempt'     => max( 1, (int) ( $by_index[ $index ]['attempt'] ?? 1 ) ),
	                    'url'         => esc_url_raw( $clip['url'] ?? '' ),
	                    'error'       => '',
	                    'scheduled_start_at' => sanitize_text_field( $by_index[ $index ]['scheduled_start_at'] ?? '' ),
	                    'finished_at' => sanitize_text_field( $by_index[ $index ]['finished_at'] ?? current_time( 'mysql' ) ),
	                    'operation_still_processing' => false,
	                    'provider' => sanitize_key( $clip['provider'] ?? ( $by_index[ $index ]['provider'] ?? '' ) ),
	                    'model' => sanitize_text_field( $clip['model'] ?? ( $by_index[ $index ]['model'] ?? '' ) ),
	                    'output_resolution' => sanitize_key( $clip['output_resolution'] ?? ( $by_index[ $index ]['output_resolution'] ?? '' ) ),
	                    'requested_resolution' => sanitize_key( $clip['requested_resolution'] ?? ( $by_index[ $index ]['requested_resolution'] ?? '' ) ),
	                    'effective_resolution' => sanitize_key( $clip['effective_resolution'] ?? ( $by_index[ $index ]['effective_resolution'] ?? '' ) ),
	                    'aspect_ratio' => sanitize_text_field( $clip['aspect_ratio'] ?? ( $by_index[ $index ]['aspect_ratio'] ?? '' ) ),
	                    'prepared_frame_url' => esc_url_raw( $clip['prepared_frame_url'] ?? ( $by_index[ $index ]['prepared_frame_url'] ?? '' ) ),
	                )
	            );
	        }

	        for ( $index = 1; $index <= 4; $index++ ) {
	            if ( empty( $by_index[ $index ] ) ) {
	                $by_index[ $index ] = array( 'index' => $index, 'status' => 'pending', 'attempt' => 0, 'url' => '', 'error' => '', 'scheduled_start_at' => '', 'started_at' => '', 'finished_at' => '', 'operation_id' => '', 'operation_poll_count' => 0, 'operation_checked_at' => '', 'operation_still_processing' => false );
	            }
	        }
	        ksort( $by_index );
	        return array_values( $by_index );
	    }

	    private static function recover_premature_final_clip_job( array $clip_job ) {
	        $status = sanitize_key( $clip_job['status'] ?? 'pending' );
	        $attempt = max( 0, (int) ( $clip_job['attempt'] ?? 0 ) );
	        $operation_id = sanitize_text_field( $clip_job['operation_id'] ?? '' );
	        if ( 'error_final' === $status && ! empty( $operation_id ) && empty( $clip_job['url'] ) ) {
	            $started_at = sanitize_text_field( $clip_job['started_at'] ?? '' );
	            $age = self::clip_job_age_seconds( $started_at );
	            $hard_timeout = '1080p' === sanitize_key( $clip_job['effective_resolution'] ?? ( $clip_job['output_resolution'] ?? '' ) ) ? 900 : 600;
	            if ( empty( $started_at ) || $age < $hard_timeout ) {
	                $clip_job['status'] = 'generating';
	                $clip_job['attempt'] = max( 1, $attempt );
	                $clip_job['error'] = '';
	                $clip_job['finished_at'] = '';
	                $clip_job['operation_still_processing'] = true;
	                return $clip_job;
	            }
	        }

	        if ( 'error_final' !== $status || ! empty( $clip_job['url'] ) || $attempt >= 3 ) {
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
	        if ( ! empty( $clip_job['operation_id'] ) ) {
	            $hard_timeout = '1080p' === sanitize_key( $clip_job['effective_resolution'] ?? ( $clip_job['output_resolution'] ?? '' ) ) ? 900 : 600;
	            if ( $age < $hard_timeout ) {
	                $clip_job['status'] = 'generating';
	                $clip_job['error'] = 'Operação Veo ainda em processamento.';
	                $clip_job['operation_still_processing'] = true;
	                return $clip_job;
	            }
	        }

	        if ( $attempt >= 3 ) {
	            $clip_job['status'] = 'error_final';
	            $clip_job['attempt'] = 3;
	            $clip_job['error'] = $clip_job['error'] ?: 'A geração do clipe demorou mais que o esperado.';
	            $clip_job['finished_at'] = current_time( 'mysql' );
	            $clip_job['operation_id'] = '';
	            return $clip_job;
	        }

	        $clip_job['status'] = 'pending';
	        $clip_job['attempt'] = $attempt;
	        $clip_job['error'] = $clip_job['error'] ?: 'Tentativa anterior ficou sem resposta.';
	        $clip_job['operation_id'] = '';
	        return $clip_job;
	    }

	    private static function clip_job_age_seconds( $started_at ) {
	        $timestamp = strtotime( (string) $started_at );
	        if ( ! $timestamp ) {
	            return 0;
	        }
	        return max( 0, current_time( 'timestamp' ) - $timestamp );
	    }

	    private static function is_retryable_clip_error_summary( $summary ) {
	        return (bool) preg_match( '/veo_invalid_response|o servi[cç]o de v[ií]deo n[aã]o retornou um v[ií]deo v[aá]lido|uri do v[ií]deo ausente|missing video uri|video uri missing|operation completed without video|opera[cç][aã]o conclu[ií]da sem v[ií]deo|empty video response|response without video|completed operation without generated video|generatedvideos vazio|video uri ausente|timeout|timed out|operation timeout|sem resposta|no response|curl|tempor[aá]ri|temporary|unavailable|reset|empty|vazia|json|408|409|429|500|502|503|504/i', (string) $summary );
	    }

	    private static function merge_clip_jobs( array $existing, array $incoming, array $clips ) {
	        $by_index = array();
	        foreach ( $existing as $job ) {
	            $by_index[ (int) $job['index'] ] = $job;
	        }
	        foreach ( $incoming as $job ) {
	            $index = (int) ( $job['index'] ?? 0 );
	            if ( $index < 1 || $index > 4 ) {
	                continue;
	            }
	            $by_index[ $index ] = self::stronger_clip_job( $by_index[ $index ] ?? array(), $job );
	        }
	        return self::normalize_clip_jobs( array_values( $by_index ), $clips );
	    }

	    private static function reset_clip_failures( array $clip_jobs ) {
	        foreach ( $clip_jobs as &$clip_job ) {
	            if ( ! is_array( $clip_job ) || ! empty( $clip_job['url'] ) || 'ready' === sanitize_key( $clip_job['status'] ?? '' ) ) {
	                continue;
	            }
	            if ( in_array( sanitize_key( $clip_job['status'] ?? '' ), array( 'error', 'error_final', 'generating', 'retrying', 'queued', 'scheduled' ), true ) ) {
	                $clip_job['status'] = 'pending';
	                $clip_job['attempt'] = 0;
	                $clip_job['error'] = '';
	                $clip_job['scheduled_start_at'] = '';
	                $clip_job['started_at'] = '';
	                $clip_job['finished_at'] = '';
	            }
	        }
	        unset( $clip_job );
	        return $clip_jobs;
	    }

	    private static function stronger_clip_job( array $existing, array $incoming ) {
	        $rank = array( 'pending' => 1, 'queued' => 2, 'scheduled' => 3, 'generating' => 4, 'retrying' => 5, 'error' => 6, 'error_final' => 7, 'ready' => 8 );
	        $existing_status = sanitize_key( $existing['status'] ?? 'pending' );
	        $incoming_status = sanitize_key( $incoming['status'] ?? 'pending' );
	        if ( ! empty( $existing['url'] ) ) {
	            $existing_status = 'ready';
	        }
	        if ( ! empty( $incoming['url'] ) ) {
	            $incoming_status = 'ready';
	        }
	        $winner = ( $rank[ $incoming_status ] ?? 1 ) >= ( $rank[ $existing_status ] ?? 1 ) ? $incoming : $existing;
	        if ( 'ready' === $existing_status && ! empty( $existing['url'] ) ) {
	            $winner = array_merge( $winner, array( 'status' => 'ready', 'url' => $existing['url'], 'error' => '' ) );
	        }
	        if ( 'ready' === $incoming_status && ! empty( $incoming['url'] ) ) {
	            $winner = array_merge( $winner, array( 'status' => 'ready', 'url' => $incoming['url'], 'error' => '' ) );
	        }
	        return $winner;
	    }

	    private static function clip_progress_for_ready_count( $ready ) {
	        $map = array( 0 => 25, 1 => 35, 2 => 50, 3 => 65, 4 => 78 );
	        return $map[ min( 4, max( 0, (int) $ready ) ) ];
	    }
	}
