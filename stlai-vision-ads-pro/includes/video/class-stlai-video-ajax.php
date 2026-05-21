<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Ajax {
    private static $client_ready_clips_received = 0;
    const CLIP_GENERATION_STALE_SECONDS = 75;

    public static function init() {
        add_action( 'wp_ajax_stlai_create_video_job', array( __CLASS__, 'create_video_job' ) );
        add_action( 'wp_ajax_nopriv_stlai_create_video_job', array( __CLASS__, 'create_video_job' ) );
        add_action( 'wp_ajax_stlai_check_video_status', array( __CLASS__, 'check_video_status' ) );
        add_action( 'wp_ajax_nopriv_stlai_check_video_status', array( __CLASS__, 'check_video_status' ) );
        add_action( 'wp_ajax_stlai_start_video_clip', array( __CLASS__, 'start_video_clip' ) );
        add_action( 'wp_ajax_nopriv_stlai_start_video_clip', array( __CLASS__, 'start_video_clip' ) );
        add_action( 'wp_ajax_stlai_get_video_result', array( __CLASS__, 'get_video_result' ) );
        add_action( 'wp_ajax_nopriv_stlai_get_video_result', array( __CLASS__, 'get_video_result' ) );
        add_action( 'wp_ajax_stlai_generate_test_veo_clip', array( __CLASS__, 'generate_test_veo_clip' ) );
        add_action( 'wp_ajax_nopriv_stlai_generate_test_veo_clip', array( __CLASS__, 'generate_test_veo_clip' ) );
    }

    public static function create_video_job() {
        $payload = array(
            'job_id'               => $_POST['job_id'] ?? '',
            'selected_images'      => $_POST['selected_images'] ?? array(),
            'narration_type'       => $_POST['narration_type'] ?? '',
            'format'               => $_POST['format'] ?? '',
            'script'               => $_POST['script'] ?? '',
            'product_name'         => $_POST['product_name'] ?? '',
            'product_description'  => $_POST['product_description'] ?? '',
        );

        $job = STLAI_Video_Job_Service::create_job( $payload );
        if ( is_wp_error( $job ) ) {
            wp_send_json_error( self::public_error_response( $job ) );
        }

        wp_send_json_success( self::public_job_response( $job ) );
    }

    public static function start_video_clip() {
        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
        $clip_index = (int) ( $_POST['clip_index'] ?? 0 );
        $job = STLAI_Video_Job_Service::start_clip_job( $job_id, $clip_index );

        if ( is_wp_error( $job ) ) {
            wp_send_json_error( self::public_error_response( $job ) );
        }

        wp_send_json_success( self::public_job_response( $job ) );
    }

    public static function check_video_status() {
        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
        $client_ready_clips = self::client_ready_clips_from_request();
        $job = STLAI_Video_Job_Service::get_status( $job_id, $client_ready_clips, self::$client_ready_clips_received );

        if ( is_wp_error( $job ) ) {
            wp_send_json_error( self::public_error_response( $job ) );
        }

        wp_send_json_success( self::public_job_response( $job ) );
    }

    public static function get_video_result() {
        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
        $job = STLAI_Video_Job_Service::get_result( $job_id );

        if ( is_wp_error( $job ) ) {
            wp_send_json_error( self::public_error_response( $job ) );
        }

        wp_send_json_success( self::public_job_response( $job ) );
    }

    public static function generate_test_veo_clip() {
        $payload = array(
            'selected_images'      => $_POST['selected_images'] ?? array(),
            'format'               => $_POST['format'] ?? '',
            'script'               => $_POST['script'] ?? '',
            'product_name'         => $_POST['product_name'] ?? '',
            'product_description'  => $_POST['product_description'] ?? '',
        );

        $clip = STLAI_Video_Job_Service::generate_test_veo_clip( $payload );
        if ( is_wp_error( $clip ) ) {
            wp_send_json_error( self::public_error_response( $clip ) );
        }

        wp_send_json_success(
            array(
                'status'         => $clip['status'] ?? 'ready',
                'message'        => 'Clipe de teste gerado com sucesso.',
                'test_clip_url'  => $clip['test_clip_url'] ?? '',
                'operation_id'   => $clip['operation_id'] ?? '',
                'provider'       => $clip['provider'] ?? 'veo',
                'model'          => $clip['model'] ?? '',
                'debug'          => $clip['debug'] ?? '',
            )
        );
    }

    private static function public_job_response( array $job ) {
        $diagnostics = self::composer_diagnostics( $job );
        $composition_start = self::composition_start_diagnostics( $job, $diagnostics );
        $composer_mode = self::composer_mode_for_response( $job, $diagnostics );
        $clip_summary = self::clip_jobs_summary( $job['clip_jobs'] ?? array() );
        $next_clip_action = self::next_clip_action( $job, $composition_start, $clip_summary );
        $normalized_ready_count = self::normalized_ready_count( $job );
        $normalized_missing_clips = self::normalized_missing_clips( $job );
        $backend_ready_count = (int) ( $job['backend_clips_ready_count'] ?? $normalized_ready_count );
        $client_ready_received = (int) ( $job['client_ready_clips_received'] ?? 0 );
        $client_ready_accepted = (int) ( $job['client_ready_clips_accepted'] ?? 0 );
        $reconciled_ready_count = (int) ( $job['reconciled_clips_ready_count'] ?? $normalized_ready_count );
        $reconciled_missing_clips = ! empty( $job['reconciled_missing_clips'] ) && is_array( $job['reconciled_missing_clips'] )
            ? array_values( array_map( 'intval', $job['reconciled_missing_clips'] ) )
            : $normalized_missing_clips;
        $reconciliation_used = ! empty( $job['reconciliation_used'] );
        $next_clip_index = (int) ( $job['next_clip_index'] ?? self::next_clip_index( $clip_summary ) );
        $next_clip_reason = sanitize_key( $job['next_clip_reason'] ?? ( $next_clip_index ? $next_clip_action : '' ) );
        $auto_clip_generation_triggered = ! empty( $job['auto_clip_generation_triggered'] );
        $auto_clip_generation_result = sanitize_key( $job['auto_clip_generation_result'] ?? '' );
        $skipped_reason = sanitize_key( $job['skipped_reason'] ?? '' );
        $active_generating_count = self::active_generating_count( $clip_summary );
        $max_concurrent_clip_generations = (int) ( $job['max_concurrent_clip_generations'] ?? 2 );
        $concurrency_blocked = ! empty( $job['concurrency_blocked'] ) || $active_generating_count >= $max_concurrent_clip_generations;
        $next_clip_indexes = self::public_index_list( $job['next_clip_indexes'] ?? array() );
        if ( empty( $next_clip_indexes ) ) {
            $next_clip_indexes = self::next_clip_indexes( $clip_summary, max( 1, $max_concurrent_clip_generations - $active_generating_count ) );
        }
        if ( empty( $next_clip_indexes ) && $next_clip_index > 0 ) {
            $next_clip_indexes = array( $next_clip_index );
        }
        $started_clip_indexes = self::public_index_list( $job['started_clip_indexes'] ?? array() );
        $stale_threshold_seconds = (int) ( $job['stale_threshold_seconds'] ?? self::CLIP_GENERATION_STALE_SECONDS );
        $summary_retryable = false;
        $summary_will_retry = false;
        $summary_retry_reason = '';
        foreach ( $clip_summary as $clip_item ) {
            if ( ! empty( $clip_item['retryable'] ) ) {
                $summary_retryable = true;
            }
            if ( ! empty( $clip_item['will_retry'] ) ) {
                $summary_will_retry = true;
                if ( empty( $summary_retry_reason ) ) {
                    $summary_retry_reason = sanitize_key( $clip_item['retry_reason'] ?? '' );
                }
            }
        }
        $retryable = ! empty( $job['retryable'] ) || $summary_retryable;
        $will_retry = ! empty( $job['will_retry'] ) || $summary_will_retry;
        $retry_reason = sanitize_key( $job['retry_reason'] ?? $summary_retry_reason );
        if ( empty( $retry_reason ) ) {
            $retry_reason = $summary_retry_reason;
        }
        $error_final_reason = sanitize_key( $job['error_final_reason'] ?? '' );
        $max_clip_attempts = (int) ( $job['max_clip_attempts'] ?? 3 );
        $safe_diagnostics = array(
            'job_id'                       => sanitize_text_field( $job['job_id'] ?? '' ),
            'status'                       => sanitize_key( $job['status'] ?? 'queued' ),
            'audio_url_exists'             => ! empty( $job['audio_url'] ),
            'clips_ready_count'            => $normalized_ready_count,
            'missing_clips'                => $normalized_missing_clips,
            'normalized_clips_ready_count' => $normalized_ready_count,
            'normalized_missing_clips'     => $normalized_missing_clips,
            'backend_clips_ready_count'     => $backend_ready_count,
            'client_ready_clips_received'   => $client_ready_received,
            'client_ready_clips_accepted'   => $client_ready_accepted,
            'reconciled_clips_ready_count'  => $reconciled_ready_count,
            'reconciled_missing_clips'      => $reconciled_missing_clips,
            'reconciliation_used'           => $reconciliation_used,
            'composition_status'           => sanitize_key( $job['composition_status'] ?? 'pending' ),
            'render_job_id'                => ! empty( $job['render_job_id'] ) ? sanitize_text_field( $job['render_job_id'] ) : null,
            'final_video_url_exists'       => ! empty( $job['final_video_url'] ),
            'composer_mode'                => $composer_mode,
            'composer_endpoint_configured' => ! empty( $diagnostics['composer_endpoint_configured'] ),
            'composer_endpoint_host'       => sanitize_text_field( $diagnostics['composer_endpoint_host'] ?? '' ),
            'composer_endpoint_path'       => sanitize_text_field( $diagnostics['composer_endpoint_path'] ?? '' ),
            'composer_started_at'          => sanitize_text_field( $job['composition_started_at'] ?? '' ),
            'composer_elapsed_seconds'     => self::composer_elapsed_seconds( $job ),
            'composer_poll_count'          => (int) ( $job['poll_count'] ?? 0 ),
            'last_composer_error_code'     => sanitize_text_field( $job['last_composer_error_code'] ?? ( $job['error_code'] ?? '' ) ),
            'last_composer_error_message'  => sanitize_text_field( $job['last_composer_error_message'] ?? ( $job['error_message'] ?? '' ) ),
            'can_start_composition'        => ! empty( $composition_start['can_start_composition'] ),
            'composition_start_blocker'    => sanitize_key( $composition_start['composition_start_blocker'] ?? '' ),
            'clip_jobs_summary'            => $clip_summary,
            'next_clip_action'             => $next_clip_action,
            'next_clip_index'              => $next_clip_index,
            'next_clip_indexes'            => $next_clip_indexes,
            'next_clip_reason'             => $next_clip_reason,
            'started_clip_indexes'         => $started_clip_indexes,
            'auto_clip_generation_triggered' => $auto_clip_generation_triggered,
            'auto_clip_generation_result'  => $auto_clip_generation_result,
            'skipped_reason'               => $skipped_reason,
            'stale_threshold_seconds'      => $stale_threshold_seconds,
            'active_generating_count'      => $active_generating_count,
            'max_concurrent_clip_generations' => $max_concurrent_clip_generations,
            'concurrency_blocked'          => $concurrency_blocked,
            'failed_clip_index'            => (int) ( $job['failed_clip_index'] ?? 0 ),
            'failed_clip_role'             => sanitize_key( $job['failed_clip_role'] ?? '' ),
            'current_clip_attempt'         => (int) ( $job['current_clip_attempt'] ?? 0 ),
            'max_clip_attempts'            => $max_clip_attempts,
            'retryable'                    => $retryable,
            'retry_reason'                 => $retry_reason,
            'will_retry'                   => $will_retry,
            'error_final_reason'           => $error_final_reason,
        );
        return array(
            'job_id'          => $job['job_id'] ?? '',
            'status'          => $job['status'] ?? 'queued',
            'progress'        => (int) ( $job['progress'] ?? 0 ),
            'progress_hint'   => (int) ( $job['progress_hint'] ?? ( $job['progress'] ?? 0 ) ),
            'message'         => $job['message'] ?? '',
            'script_public'   => wp_strip_all_tags( $job['script_public'] ?? ( $job['script'] ?? '' ) ),
            'audio_url'       => $job['audio_url'] ?? '',
            'clips'           => self::public_clips_response( $job['clips'] ?? array() ),
            'partial_clips'   => self::public_clips_response( $job['partial_clips'] ?? array() ),
            'video_frames'    => self::public_video_frames_response( $job['video_frames'] ?? array() ),
            'clip_jobs'       => self::public_clip_jobs_response( $job['clip_jobs'] ?? array() ),
            'clip_statuses'   => self::public_assoc_response( $job['clip_statuses'] ?? array() ),
            'clip_attempts'   => self::public_int_assoc_response( $job['clip_attempts'] ?? array() ),
            'clip_errors'     => self::public_assoc_response( $job['clip_errors'] ?? array() ),
	            'missing_clips'   => $normalized_missing_clips,
	            'clips_ready_count' => $normalized_ready_count,
	            'job_version'     => (int) ( $job['job_version'] ?? 0 ),
	            'updated_at'      => sanitize_text_field( $job['updated_at'] ?? '' ),
	            'final_video_url' => ! empty( $job['final_video_url'] ) ? $job['final_video_url'] : null,
            'final_video_duration' => (float) ( $job['final_video_duration'] ?? 0 ),
            'transition_used' => sanitize_key( $job['transition_used'] ?? '' ),
            'fallback_used'   => sanitize_key( $job['fallback_used'] ?? '' ),
            'render_time_seconds' => (float) ( $job['render_time_seconds'] ?? 0 ),
            'background_music_used' => ! empty( $job['background_music_used'] ),
            'background_music_volume' => (float) ( $job['background_music_volume'] ?? 0 ),
            'fast_compose'    => ! empty( $job['fast_compose'] ),
            'composer_mode'   => $composer_mode,
            'composer_provider' => sanitize_key( $job['composer_provider'] ?? '' ),
            'composer_status' => sanitize_key( $job['composer_status'] ?? '' ),
            'render_job_id'   => ! empty( $job['render_job_id'] ) ? sanitize_text_field( $job['render_job_id'] ) : null,
            'composed_at'      => sanitize_text_field( $job['composed_at'] ?? '' ),
            'thumbnail_url'   => $job['thumbnail_url'] ?? '',
            'composition_status' => $job['composition_status'] ?? 'pending',
            'composer_endpoint_configured' => ! empty( $diagnostics['composer_endpoint_configured'] ),
            'composer_endpoint_host' => sanitize_text_field( $diagnostics['composer_endpoint_host'] ?? '' ),
            'composer_endpoint_path' => sanitize_text_field( $diagnostics['composer_endpoint_path'] ?? '' ),
            'composer_started_at' => sanitize_text_field( $job['composition_started_at'] ?? '' ),
            'composer_updated_at' => sanitize_text_field( $job['updated_at'] ?? '' ),
            'composer_elapsed_seconds' => self::composer_elapsed_seconds( $job ),
            'composer_poll_count' => (int) ( $job['poll_count'] ?? 0 ),
            'audio_url_exists' => ! empty( $job['audio_url'] ),
            'final_video_url_exists' => ! empty( $job['final_video_url'] ),
            'can_start_composition' => ! empty( $composition_start['can_start_composition'] ),
            'composition_start_blocker' => sanitize_key( $composition_start['composition_start_blocker'] ?? '' ),
            'next_clip_action' => $next_clip_action,
            'next_clip_index' => $next_clip_index,
            'next_clip_indexes' => $next_clip_indexes,
            'next_clip_reason' => $next_clip_reason,
            'started_clip_indexes' => $started_clip_indexes,
            'auto_clip_generation_triggered' => $auto_clip_generation_triggered,
            'auto_clip_generation_result' => $auto_clip_generation_result,
            'skipped_reason' => $skipped_reason,
            'stale_threshold_seconds' => $stale_threshold_seconds,
            'active_generating_count' => $active_generating_count,
            'max_concurrent_clip_generations' => $max_concurrent_clip_generations,
            'concurrency_blocked' => $concurrency_blocked,
            'max_clip_attempts' => $max_clip_attempts,
            'retryable' => $retryable,
            'retry_reason' => $retry_reason,
            'will_retry' => $will_retry,
            'error_final_reason' => $error_final_reason,
            'normalized_clips_ready_count' => $normalized_ready_count,
            'normalized_missing_clips' => $normalized_missing_clips,
            'backend_clips_ready_count' => $backend_ready_count,
            'client_ready_clips_received' => $client_ready_received,
            'client_ready_clips_accepted' => $client_ready_accepted,
            'reconciled_clips_ready_count' => $reconciled_ready_count,
            'reconciled_missing_clips' => $reconciled_missing_clips,
            'reconciliation_used' => $reconciliation_used,
            'diagnostics'      => $safe_diagnostics,
            'has_audio'       => ! empty( $job['audio_url'] ),
            'current_clip_index' => (int) ( $job['current_clip_index'] ?? 0 ),
            'current_clip_attempt' => (int) ( $job['current_clip_attempt'] ?? 0 ),
            'clip_retry_count' => (int) ( $job['clip_retry_count'] ?? 0 ),
            'last_clip_error' => sanitize_text_field( $job['last_clip_error'] ?? '' ),
            'format'          => $job['format'] ?? '',
            'narration_type'  => $job['narration_type'] ?? '',
            'failed_clip_index' => (int) ( $job['failed_clip_index'] ?? 0 ),
            'failed_clip_role' => sanitize_key( $job['failed_clip_role'] ?? '' ),
            'last_composer_error_code' => sanitize_text_field( $job['last_composer_error_code'] ?? ( $job['error_code'] ?? '' ) ),
            'last_composer_error_message' => sanitize_text_field( $job['last_composer_error_message'] ?? ( $job['error_message'] ?? '' ) ),
            'error_code'      => $job['error_code'] ?? '',
            'error_message'   => $job['error_message'] ?? '',
            'debug'           => $job['error_debug'] ?? '',
        );
    }

    private static function public_clips_response( $clips ) {
        if ( ! is_array( $clips ) ) {
            return array();
        }

        $response = array();
        foreach ( $clips as $clip ) {
            if ( ! is_array( $clip ) ) {
                continue;
            }

            $response[] = array(
                'index'    => (int) ( $clip['index'] ?? 0 ),
                'role'     => sanitize_key( $clip['role'] ?? '' ),
                'label'    => sanitize_text_field( $clip['label'] ?? '' ),
                'url'      => esc_url_raw( $clip['url'] ?? '' ),
                'duration' => (int) ( $clip['duration'] ?? 8 ),
                'muted'    => true,
                'prepared_frame_url' => esc_url_raw( $clip['prepared_frame_url'] ?? '' ),
                'prepared_frame_width' => (int) ( $clip['prepared_frame_width'] ?? 0 ),
                'prepared_frame_height' => (int) ( $clip['prepared_frame_height'] ?? 0 ),
                'aspect_ratio' => sanitize_text_field( $clip['aspect_ratio'] ?? '' ),
            );
        }

        return $response;
    }

    private static function client_ready_clips_from_request() {
        $raw = $_POST['client_ready_clips'] ?? array();
        if ( is_string( $raw ) ) {
            $decoded = json_decode( wp_unslash( $raw ), true );
            $raw = is_array( $decoded ) ? $decoded : array();
        }

        if ( ! is_array( $raw ) ) {
            self::$client_ready_clips_received = 0;
            return array();
        }

        self::$client_ready_clips_received = count( $raw );
        $clips = array();
        foreach ( $raw as $clip ) {
            if ( ! is_array( $clip ) ) {
                continue;
            }

            $index = (int) ( $clip['index'] ?? 0 );
            $url = self::sanitize_client_upload_url( $clip['url'] ?? '', array( 'mp4', 'webm', 'mov', 'm4v' ) );
            if ( $index < 1 || $index > 4 || empty( $url ) ) {
                continue;
            }

            $prepared_frame_url = self::sanitize_client_upload_url( $clip['prepared_frame_url'] ?? '', array( 'jpg', 'jpeg', 'png', 'webp' ) );
            $clips[ $index ] = array(
                'index'                 => $index,
                'role'                  => sanitize_key( $clip['role'] ?? '' ),
                'label'                 => sanitize_text_field( $clip['label'] ?? ( 'Clipe ' . $index ) ),
                'url'                   => $url,
                'duration'              => (int) ( $clip['duration'] ?? 8 ),
                'muted'                 => true,
                'prepared_frame_url'    => $prepared_frame_url,
                'prepared_frame_width'  => (int) ( $clip['prepared_frame_width'] ?? ( $clip['width'] ?? 0 ) ),
                'prepared_frame_height' => (int) ( $clip['prepared_frame_height'] ?? ( $clip['height'] ?? 0 ) ),
                'aspect_ratio'          => sanitize_text_field( $clip['aspect_ratio'] ?? '' ),
            );
        }

        ksort( $clips );
        return array_values( $clips );
    }

    private static function sanitize_client_upload_url( $url, array $allowed_extensions ) {
        if ( ! is_scalar( $url ) ) {
            return '';
        }

        $url = esc_url_raw( str_replace( '\\/', '/', trim( (string) wp_unslash( $url ) ) ) );
        if ( empty( $url ) ) {
            return '';
        }

        $uploads = wp_upload_dir();
        $base_url = isset( $uploads['baseurl'] ) ? esc_url_raw( $uploads['baseurl'] ) : '';
        $url_parts = wp_parse_url( $url );
        $base_parts = wp_parse_url( $base_url );
        if ( empty( $url_parts['host'] ) || empty( $base_parts['host'] ) || strtolower( $url_parts['host'] ) !== strtolower( $base_parts['host'] ) ) {
            return '';
        }

        $path = $url_parts['path'] ?? '';
        $base_path = rtrim( $base_parts['path'] ?? '', '/' );
        if ( empty( $path ) || empty( $base_path ) || 0 !== strpos( $path, $base_path . '/' ) ) {
            return '';
        }

        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, $allowed_extensions, true ) ) {
            return '';
        }

        return $url;
    }

    private static function public_video_frames_response( $frames ) {
        if ( ! is_array( $frames ) ) {
            return array();
        }

        $response = array();
        foreach ( $frames as $frame ) {
            if ( ! is_array( $frame ) || empty( $frame['url'] ) ) {
                continue;
            }

            $response[] = array(
                'index'        => (int) ( $frame['index'] ?? 0 ),
                'url'          => esc_url_raw( $frame['url'] ?? '' ),
                'aspect_ratio' => sanitize_text_field( $frame['aspect_ratio'] ?? '' ),
                'label'        => sanitize_text_field( $frame['label'] ?? '' ),
                'width'        => (int) ( $frame['width'] ?? 0 ),
                'height'       => (int) ( $frame['height'] ?? 0 ),
            );
        }

        return $response;
    }

    private static function public_clip_jobs_response( $jobs ) {
        if ( ! is_array( $jobs ) ) {
            return array();
        }

        $response = array();
        foreach ( $jobs as $job ) {
            if ( ! is_array( $job ) ) {
                continue;
            }
            $error = sanitize_text_field( $job['error'] ?? '' );
            $attempt = (int) ( $job['attempt'] ?? 0 );
            $retryable = empty( $job['url'] ) && self::is_retryable_clip_error_summary( $error );

            $response[] = array(
                'index'       => (int) ( $job['index'] ?? 0 ),
                'status'      => sanitize_key( $job['status'] ?? 'pending' ),
                'attempt'     => $attempt,
                'max_attempts' => 3,
                'url'         => esc_url_raw( $job['url'] ?? '' ),
                'error'       => $error,
                'error_code'  => self::clip_error_code_from_summary( $error ),
                'retryable'   => $retryable,
                'will_retry'  => $retryable && $attempt > 0 && $attempt < 3,
                'retry_reason' => $retryable ? self::clip_retry_reason_from_summary( $error ) : '',
                'started_at'  => sanitize_text_field( $job['started_at'] ?? '' ),
                'finished_at' => sanitize_text_field( $job['finished_at'] ?? '' ),
            );
        }

        return $response;
    }

    private static function public_assoc_response( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $response = array();
        foreach ( $items as $key => $value ) {
            $response[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
        }

        return $response;
    }

    private static function public_int_assoc_response( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $response = array();
        foreach ( $items as $key => $value ) {
            $response[ sanitize_key( (string) $key ) ] = (int) $value;
        }

        return $response;
    }

    private static function composer_diagnostics( array $job ) {
        if ( class_exists( 'STLAI_Video_Composer_Provider' ) && method_exists( 'STLAI_Video_Composer_Provider', 'diagnostics' ) ) {
            return STLAI_Video_Composer_Provider::diagnostics();
        }

        return array(
            'composer_mode'                => sanitize_key( $job['composer_mode'] ?? '' ),
            'composer_endpoint_configured' => false,
            'composer_endpoint_host'       => '',
            'composer_endpoint_path'       => '',
        );
    }

    private static function composer_mode_for_response( array $job, array $diagnostics ) {
        $mode = sanitize_key( $job['composer_mode'] ?? '' );
        if ( ! empty( $mode ) ) {
            return $mode;
        }

        $mode = sanitize_key( $diagnostics['composer_mode'] ?? '' );
        return $mode ?: 'external_service';
    }

    private static function composition_start_diagnostics( array $job, array $diagnostics ) {
        $clips_ready = self::normalized_ready_count( $job );
        if ( ! empty( $job['final_video_url'] ) || 'ready' === sanitize_key( $job['status'] ?? '' ) ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'already_ready' );
        }
        if ( empty( $job['audio_url'] ) ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'missing_audio' );
        }
        if ( $clips_ready < 4 ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'missing_clips' );
        }
        if ( in_array( sanitize_key( $job['composition_status'] ?? '' ), array( 'error', 'timeout' ), true ) || 'composition_error' === sanitize_key( $job['status'] ?? '' ) ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'composition_error' );
        }
        if ( ! empty( $job['render_job_id'] ) ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'poll_render_job' );
        }
        if ( empty( $diagnostics['composer_endpoint_configured'] ) ) {
            return array( 'can_start_composition' => false, 'composition_start_blocker' => 'composer_endpoint' );
        }

        return array( 'can_start_composition' => true, 'composition_start_blocker' => '' );
    }

    private static function count_ready_clips( $clips ) {
        if ( ! is_array( $clips ) ) {
            return 0;
        }

        $ready = array();
        foreach ( $clips as $clip ) {
            if ( ! is_array( $clip ) || empty( $clip['url'] ) ) {
                continue;
            }
            $index = (int) ( $clip['index'] ?? 0 );
            if ( $index >= 1 && $index <= 4 ) {
                $ready[ $index ] = true;
            }
        }

        return count( $ready );
    }

    private static function normalized_ready_count( array $job ) {
        return 4 - count( self::normalized_missing_clips( $job ) );
    }

    private static function normalized_missing_clips( array $job ) {
        $ready = array();
        foreach ( array( 'clips', 'partial_clips' ) as $field ) {
            if ( empty( $job[ $field ] ) || ! is_array( $job[ $field ] ) ) {
                continue;
            }
            foreach ( $job[ $field ] as $clip ) {
                if ( ! is_array( $clip ) || empty( $clip['url'] ) ) {
                    continue;
                }
                $index = (int) ( $clip['index'] ?? 0 );
                if ( $index >= 1 && $index <= 4 ) {
                    $ready[ $index ] = true;
                }
            }
        }

        if ( ! empty( $job['clip_jobs'] ) && is_array( $job['clip_jobs'] ) ) {
            foreach ( $job['clip_jobs'] as $clip_job ) {
                if ( ! is_array( $clip_job ) || empty( $clip_job['url'] ) ) {
                    continue;
                }
                $index = (int) ( $clip_job['index'] ?? 0 );
                if ( $index >= 1 && $index <= 4 ) {
                    $ready[ $index ] = true;
                }
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

    private static function clip_jobs_summary( $clip_jobs ) {
        $summary = array();
        if ( ! is_array( $clip_jobs ) ) {
            $clip_jobs = array();
        }

        $by_index = array();
        foreach ( $clip_jobs as $clip_job ) {
            if ( ! is_array( $clip_job ) ) {
                continue;
            }
            $index = (int) ( $clip_job['index'] ?? 0 );
            if ( $index >= 1 && $index <= 4 ) {
                $by_index[ $index ] = $clip_job;
            }
        }

        for ( $index = 1; $index <= 4; $index++ ) {
            $clip_job = $by_index[ $index ] ?? array();
            $started_at = sanitize_text_field( $clip_job['started_at'] ?? '' );
            $age = self::clip_job_age_seconds( $started_at );
            $status = sanitize_key( $clip_job['status'] ?? 'pending' );
            $attempt = (int) ( $clip_job['attempt'] ?? 0 );
            $error = sanitize_text_field( $clip_job['error'] ?? '' );
            $error_code = self::clip_error_code_from_summary( $error );
            $retryable = empty( $clip_job['url'] ) && self::is_retryable_clip_error_summary( $error );
            $will_retry = $retryable && $attempt > 0 && $attempt < 3 && 'ready' !== $status;
            $summary[] = array(
                'index'       => $index,
                'status'      => $status ?: 'pending',
                'attempt'     => $attempt,
                'max_attempts' => 3,
                'has_url'     => ! empty( $clip_job['url'] ),
                'error_code'  => $error_code,
                'retryable'   => $retryable,
                'will_retry'  => $will_retry,
                'retry_reason' => $retryable ? self::clip_retry_reason_from_summary( $error ) : '',
                'error_final_reason' => 'error_final' === $status ? ( $will_retry ? '' : ( $retryable ? 'max_attempts_exhausted' : 'non_retryable_error' ) ) : '',
                'started_at'  => $started_at,
                'age_seconds' => $age,
                'is_stale'    => in_array( $status, array( 'pending', 'generating', 'retrying' ), true ) && empty( $clip_job['url'] ) && ! empty( $started_at ) && $age >= self::CLIP_GENERATION_STALE_SECONDS,
            );
        }
        return $summary;
    }

    private static function is_retryable_clip_error_summary( $summary ) {
        return (bool) preg_match( '/veo_invalid_response|o servi[cç]o de v[ií]deo n[aã]o retornou um v[ií]deo v[aá]lido|uri do v[ií]deo ausente|missing video uri|video uri missing|operation completed without video|opera[cç][aã]o conclu[ií]da sem v[ií]deo|empty video response|response without video|completed operation without generated video|generatedvideos vazio|video uri ausente|timeout|timed out|operation timeout|sem resposta|no response|curl|tempor[aá]ri|temporary|unavailable|reset|empty|vazia|json|408|409|429|500|502|503|504/i', (string) $summary );
    }

    private static function clip_error_code_from_summary( $summary ) {
        if ( preg_match( '/\b([A-Z0-9_]{3,})\s*:/', (string) $summary, $matches ) ) {
            return sanitize_key( $matches[1] );
        }
        if ( false !== stripos( (string) $summary, 'veo_invalid_response' ) ) {
            return 'veo_invalid_response';
        }
        return '';
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

    private static function next_clip_action( array $job, array $composition_start, array $clip_summary ) {
        if ( ! empty( $job['render_job_id'] ) && empty( $job['final_video_url'] ) ) {
            return 'poll_composition';
        }
        if ( ! empty( $composition_start['can_start_composition'] ) ) {
            return 'start_composition';
        }
        foreach ( $clip_summary as $item ) {
            if ( ! empty( $item['is_stale'] ) ) {
                return 'retry_clip';
            }
        }
        foreach ( $clip_summary as $item ) {
            if ( empty( $item['has_url'] ) && in_array( sanitize_key( $item['status'] ?? '' ), array( 'pending', 'queued' ), true ) ) {
                return 'generate_missing_clip';
            }
        }
        foreach ( $clip_summary as $item ) {
            if ( empty( $item['has_url'] ) && 'retrying' === sanitize_key( $item['status'] ?? '' ) ) {
                return 'retry_clip';
            }
        }
        return 'none';
    }

    private static function next_clip_index( array $clip_summary ) {
        foreach ( $clip_summary as $item ) {
            if ( ! empty( $item['is_stale'] ) ) {
                return (int) ( $item['index'] ?? 0 );
            }
        }
        foreach ( $clip_summary as $item ) {
            if ( empty( $item['has_url'] ) && in_array( sanitize_key( $item['status'] ?? '' ), array( 'pending', 'queued', 'retrying' ), true ) ) {
                return (int) ( $item['index'] ?? 0 );
            }
        }
        return 0;
    }

    private static function next_clip_indexes( array $clip_summary, $limit ) {
        $limit = max( 0, (int) $limit );
        if ( $limit <= 0 ) {
            return array();
        }

        $indexes = array();
        $add = function ( $item ) use ( &$indexes, $limit ) {
            if ( count( $indexes ) >= $limit || ! empty( $item['has_url'] ) ) {
                return;
            }
            $index = (int) ( $item['index'] ?? 0 );
            if ( $index >= 1 && $index <= 4 && ! in_array( $index, $indexes, true ) ) {
                $indexes[] = $index;
            }
        };

        foreach ( $clip_summary as $item ) {
            if ( ! empty( $item['is_stale'] ) ) {
                $add( $item );
            }
        }
        foreach ( $clip_summary as $item ) {
            if ( ! empty( $item['will_retry'] ) || ! empty( $item['retryable'] ) || 'retrying' === sanitize_key( $item['status'] ?? '' ) ) {
                $add( $item );
            }
        }
        foreach ( $clip_summary as $item ) {
            if ( in_array( sanitize_key( $item['status'] ?? '' ), array( 'pending', 'queued' ), true ) ) {
                $add( $item );
            }
        }

        return $indexes;
    }

    private static function active_generating_count( array $clip_summary ) {
        $count = 0;
        foreach ( $clip_summary as $item ) {
            if (
                'generating' === sanitize_key( $item['status'] ?? '' )
                && empty( $item['has_url'] )
                && empty( $item['is_stale'] )
            ) {
                $count++;
            }
        }
        return $count;
    }

    private static function public_index_list( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }
        $indexes = array();
        foreach ( $items as $item ) {
            $index = (int) $item;
            if ( $index >= 1 && $index <= 4 && ! in_array( $index, $indexes, true ) ) {
                $indexes[] = $index;
            }
        }
        sort( $indexes );
        return $indexes;
    }

    private static function clip_job_age_seconds( $started_at ) {
        $timestamp = strtotime( (string) $started_at );
        if ( ! $timestamp ) {
            return 0;
        }
        return max( 0, current_time( 'timestamp' ) - $timestamp );
    }

    private static function composer_elapsed_seconds( array $job ) {
        $timestamp = strtotime( (string) ( $job['composition_started_at'] ?? '' ) );
        if ( ! $timestamp ) {
            $timestamp = strtotime( (string) ( $job['updated_at'] ?? '' ) );
        }
        if ( ! $timestamp ) {
            return 0;
        }

        return max( 0, current_time( 'timestamp' ) - $timestamp );
    }

    private static function public_error_response( WP_Error $error ) {
        $data = $error->get_error_data();

        $response = array(
            'message' => $error->get_error_message() ?: 'Não foi possível preparar o vídeo agora.',
            'code'    => $error->get_error_code() ?: 'UNKNOWN_VIDEO_ERROR',
            'debug'   => is_array( $data ) ? sanitize_text_field( $data['debug'] ?? '' ) : '',
        );

        if ( is_array( $data ) ) {
            if ( ! empty( $data['job_id'] ) ) {
                $response['job_id'] = sanitize_text_field( $data['job_id'] );
            }

            if ( ! empty( $data['failed_clip'] ) ) {
                $response['failed_clip'] = (int) $data['failed_clip'];
            }

            if ( ! empty( $data['failed_clip_index'] ) ) {
                $response['failed_clip_index'] = (int) $data['failed_clip_index'];
            }

            if ( ! empty( $data['failed_clip_role'] ) ) {
                $response['failed_clip_role'] = sanitize_key( $data['failed_clip_role'] );
            }

            if ( ! empty( $data['current_clip_index'] ) ) {
                $response['current_clip_index'] = (int) $data['current_clip_index'];
            }

            if ( ! empty( $data['current_clip_attempt'] ) ) {
                $response['current_clip_attempt'] = (int) $data['current_clip_attempt'];
            }

            if ( isset( $data['clip_retry_count'] ) ) {
                $response['clip_retry_count'] = (int) $data['clip_retry_count'];
            }

            if ( isset( $data['max_clip_attempts'] ) ) {
                $response['max_clip_attempts'] = (int) $data['max_clip_attempts'];
            }

            if ( isset( $data['retryable'] ) ) {
                $response['retryable'] = ! empty( $data['retryable'] );
            }

            if ( ! empty( $data['retry_reason'] ) ) {
                $response['retry_reason'] = sanitize_key( $data['retry_reason'] );
            }

            if ( isset( $data['will_retry'] ) ) {
                $response['will_retry'] = ! empty( $data['will_retry'] );
            }

            if ( ! empty( $data['error_final_reason'] ) ) {
                $response['error_final_reason'] = sanitize_key( $data['error_final_reason'] );
            }

            if ( isset( $data['progress_hint'] ) ) {
                $response['progress_hint'] = (int) $data['progress_hint'];
            }

            if ( isset( $data['job_version'] ) ) {
                $response['job_version'] = (int) $data['job_version'];
            }

            if ( isset( $data['clips_ready_count'] ) ) {
                $response['clips_ready_count'] = (int) $data['clips_ready_count'];
            }

            if ( ! empty( $data['updated_at'] ) ) {
                $response['updated_at'] = sanitize_text_field( $data['updated_at'] );
            }

            if ( ! empty( $data['last_clip_error'] ) ) {
                $response['last_clip_error'] = sanitize_text_field( $data['last_clip_error'] );
            }

            if ( ! empty( $data['status'] ) ) {
                $response['status'] = sanitize_key( $data['status'] );
            }

            if ( ! empty( $data['composition_status'] ) ) {
                $response['composition_status'] = sanitize_key( $data['composition_status'] );
            }

            if ( ! empty( $data['composer_status'] ) ) {
                $response['composer_status'] = sanitize_key( $data['composer_status'] );
            }

            if ( ! empty( $data['render_job_id'] ) ) {
                $response['render_job_id'] = sanitize_text_field( $data['render_job_id'] );
            }

            if ( ! empty( $data['audio_url'] ) ) {
                $response['audio_url'] = esc_url_raw( $data['audio_url'] );
            }

            if ( ! empty( $data['clips'] ) ) {
                $response['clips'] = self::public_clips_response( $data['clips'] );
            }

            if ( ! empty( $data['partial_clips'] ) ) {
                $response['partial_clips'] = self::public_clips_response( $data['partial_clips'] );
            }

            if ( ! empty( $data['video_frames'] ) ) {
                $response['video_frames'] = self::public_video_frames_response( $data['video_frames'] );
            }

            if ( ! empty( $data['clip_jobs'] ) ) {
                $response['clip_jobs'] = self::public_clip_jobs_response( $data['clip_jobs'] );
            }

            if ( ! empty( $data['missing_clips'] ) && is_array( $data['missing_clips'] ) ) {
                $response['missing_clips'] = array_values( array_map( 'intval', $data['missing_clips'] ) );
            }
        }

        return $response;
    }
}

STLAI_Video_Ajax::init();
