<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Ajax {
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
        $job = STLAI_Video_Job_Service::get_status( $job_id );

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
            'missing_clips'   => array_values( array_map( 'intval', $job['missing_clips'] ?? array() ) ),
            'final_video_url' => $job['final_video_url'] ?? '',
            'final_video_duration' => (float) ( $job['final_video_duration'] ?? 0 ),
            'transition_used' => sanitize_key( $job['transition_used'] ?? '' ),
            'fallback_used'   => sanitize_key( $job['fallback_used'] ?? '' ),
            'render_time_seconds' => (float) ( $job['render_time_seconds'] ?? 0 ),
            'background_music_used' => ! empty( $job['background_music_used'] ),
            'background_music_volume' => (float) ( $job['background_music_volume'] ?? 0 ),
            'fast_compose'    => ! empty( $job['fast_compose'] ),
            'composer_mode'   => sanitize_key( $job['composer_mode'] ?? '' ),
            'composer_provider' => sanitize_key( $job['composer_provider'] ?? '' ),
            'composer_status' => sanitize_key( $job['composer_status'] ?? '' ),
            'render_job_id'   => sanitize_text_field( $job['render_job_id'] ?? '' ),
            'composed_at'      => sanitize_text_field( $job['composed_at'] ?? '' ),
            'thumbnail_url'   => $job['thumbnail_url'] ?? '',
            'composition_status' => $job['composition_status'] ?? 'pending',
            'current_clip_index' => (int) ( $job['current_clip_index'] ?? 0 ),
            'current_clip_attempt' => (int) ( $job['current_clip_attempt'] ?? 0 ),
            'clip_retry_count' => (int) ( $job['clip_retry_count'] ?? 0 ),
            'last_clip_error' => sanitize_text_field( $job['last_clip_error'] ?? '' ),
            'format'          => $job['format'] ?? '',
            'narration_type'  => $job['narration_type'] ?? '',
            'failed_clip_index' => (int) ( $job['failed_clip_index'] ?? 0 ),
            'failed_clip_role' => sanitize_key( $job['failed_clip_role'] ?? '' ),
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

            $response[] = array(
                'index'       => (int) ( $job['index'] ?? 0 ),
                'status'      => sanitize_key( $job['status'] ?? 'pending' ),
                'attempt'     => (int) ( $job['attempt'] ?? 0 ),
                'url'         => esc_url_raw( $job['url'] ?? '' ),
                'error'       => sanitize_text_field( $job['error'] ?? '' ),
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

            if ( isset( $data['progress_hint'] ) ) {
                $response['progress_hint'] = (int) $data['progress_hint'];
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
