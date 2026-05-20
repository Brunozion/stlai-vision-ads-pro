<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Video_Storage {
    const TRANSIENT_PREFIX = 'stlai_video_job_';
    const TTL = DAY_IN_SECONDS;

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
                'script'          => '',
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
                'composed_at'     => '',
                'thumbnail_url'   => '',
                'composition_status' => 'pending',
                'current_clip_index' => 0,
                'current_clip_attempt' => 0,
                'clip_retry_count' => 0,
                'last_clip_error' => '',
                'failed_clip_index' => 0,
                'failed_clip_role' => '',
                'error_code'      => '',
                'error_message'   => '',
                'error_debug'     => '',
                'poll_count'      => 0,
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

        $job = array_merge( $job, $data );
        $job['updated_at'] = current_time( 'mysql' );

        set_transient( self::key( $job_id ), $job, self::TTL );

        return $job;
    }

    private static function key( $job_id ) {
        return self::TRANSIENT_PREFIX . sanitize_key( $job_id );
    }
}
