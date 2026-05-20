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
            'message'         => $job['message'] ?? '',
            'audio_url'       => $job['audio_url'] ?? '',
            'clips'           => self::public_clips_response( $job['clips'] ?? array() ),
            'partial_clips'   => self::public_clips_response( $job['partial_clips'] ?? array() ),
            'final_video_url' => $job['final_video_url'] ?? '',
            'final_video_duration' => (float) ( $job['final_video_duration'] ?? 0 ),
            'composer_mode'   => sanitize_key( $job['composer_mode'] ?? '' ),
            'composer_provider' => sanitize_key( $job['composer_provider'] ?? '' ),
            'composer_status' => sanitize_key( $job['composer_status'] ?? '' ),
            'render_job_id'   => sanitize_text_field( $job['render_job_id'] ?? '' ),
            'composed_at'      => sanitize_text_field( $job['composed_at'] ?? '' ),
            'thumbnail_url'   => $job['thumbnail_url'] ?? '',
            'composition_status' => $job['composition_status'] ?? 'pending',
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
            );
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
        }

        return $response;
    }
}

STLAI_Video_Ajax::init();
