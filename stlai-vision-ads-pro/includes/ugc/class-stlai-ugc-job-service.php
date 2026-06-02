<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'STLAI_MuAPI_UGC_Provider' ) ) {
    require_once __DIR__ . '/class-stlai-muapi-ugc-provider.php';
}
if ( ! class_exists( 'STLAI_Atlas_UGC_Provider' ) ) {
    require_once __DIR__ . '/class-stlai-atlas-ugc-provider.php';
}
if ( ! class_exists( 'STLAI_Fal_UGC_Provider' ) ) {
    require_once __DIR__ . '/class-stlai-fal-ugc-provider.php';
}
if ( ! class_exists( 'STLAI_Seedance_UGC_Provider' ) ) {
    require_once __DIR__ . '/class-stlai-seedance-ugc-provider.php';
}

class STLAI_UGC_Job_Service {
    const DEFAULT_DURATION = 9;
    const DEFAULT_RESOLUTION = '720p';
    const DEFAULT_ASPECT_RATIO = '9:16';

    public static function presets() {
        return array(
            'ugc'                => array( 'label' => 'UGC', 'prompt_key' => 'ugc_prompt_social', 'group' => 'ugc' ),
            'tutorial'           => array( 'label' => 'Tutorial', 'prompt_key' => 'ugc_prompt_tutorial', 'group' => 'ugc' ),
            'unboxing'           => array( 'label' => 'Unboxing', 'prompt_key' => 'ugc_prompt_unboxing', 'group' => 'ugc' ),
            'product_review'     => array( 'label' => 'Product Review', 'prompt_key' => 'ugc_prompt_product_review', 'group' => 'ugc' ),
            'ugc_virtual_try_on' => array( 'label' => 'UGC Virtual Try On', 'prompt_key' => 'ugc_prompt_virtual_try_on', 'group' => 'ugc' ),
            'hyper_motion'       => array( 'label' => 'Hyper Motion', 'prompt_key' => 'ugc_prompt_hyper_motion', 'group' => 'commercial' ),
            'tv_spot'            => array( 'label' => 'TV Spot', 'prompt_key' => 'ugc_prompt_tv_spot', 'group' => 'commercial' ),
            'wild_card'          => array( 'label' => 'Wild Card', 'prompt_key' => 'ugc_prompt_wild_card', 'group' => 'commercial' ),
            'pro_virtual_try_on' => array( 'label' => 'Pro Virtual Try On', 'prompt_key' => 'ugc_prompt_pro_virtual_try_on', 'group' => 'commercial' ),
        );
    }

    public static function start_job( array $payload ) {
        $validated = self::validate_start_payload( $payload );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $parent_job = self::parent_job( $validated['parent_job_id'], $validated );
        if ( is_wp_error( $parent_job ) ) {
            return $parent_job;
        }

        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $provider_key = self::provider_key_from_settings( $settings );
        $provider = self::provider_for_key( $provider_key );
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }

        if ( 'muapi' !== $provider_key && 0 === strpos( $validated['image_url'], 'data:image/' ) ) {
            return new WP_Error( 'UGC_PROVIDER_REQUIRES_IMAGE_URL', 'Este provider UGC precisa de uma URL pública da imagem. Use MuAPI para imagens em base64 ou selecione uma imagem com URL.' );
        }

        $prompt_final = self::build_prompt( $validated, $parent_job, $settings );
        $ugc_job = array(
            'ugc_job_id'          => 'stlai_ugc_' . wp_generate_uuid4(),
            'parent_job_id'       => $parent_job['job_id'],
            'preset'              => $validated['preset'],
            'label'               => $validated['label'],
            'provider'            => $provider_key,
            'provider_label'      => self::provider_label( $provider_key ),
            'model'               => self::model_for_provider( $provider_key, $settings ),
            'request_id'          => '',
            'operation_id'        => '',
            'status_url'          => '',
            'result_url'          => '',
            'endpoint_used'       => '',
            'provider_debug'      => array(),
            'status'              => 'queued',
            'image_url'           => $validated['image_url'],
            'selected_image_label' => $validated['selected_image_label'],
            'prompt_public'       => $validated['prompt_public'],
            'prompt_final'        => $prompt_final,
            'aspect_ratio'        => $validated['aspect_ratio'],
            'duration'            => $validated['duration'],
            'resolution'          => $validated['resolution'],
            'video_url'           => '',
            'error_message'       => '',
            'raw_status'          => '',
            'created_at'          => current_time( 'mysql' ),
            'updated_at'          => current_time( 'mysql' ),
        );

        $started = call_user_func(
            array( $provider['class'], 'start_job' ),
            array(
                'image_url'    => $validated['image_url'],
                'prompt'       => $prompt_final,
                'aspect_ratio' => $validated['aspect_ratio'],
                'duration'     => $validated['duration'],
                'resolution'   => $validated['resolution'],
            )
        );

        if ( is_wp_error( $started ) ) {
            $ugc_job['status'] = 'failed';
            $ugc_job['error_message'] = $started->get_error_message();
            self::save_ugc_job( $parent_job['job_id'], $ugc_job );
            return $started;
        }

        $operation_id = sanitize_text_field( $started['operation_id'] ?? ( $started['request_id'] ?? '' ) );
        $ugc_job['operation_id'] = $operation_id;
        $ugc_job['request_id'] = sanitize_text_field( $started['request_id'] ?? $operation_id );
        $ugc_job['status'] = sanitize_key( $started['status'] ?? 'processing' );
        $ugc_job['provider'] = sanitize_key( $started['provider'] ?? $provider_key );
        $ugc_job['provider_label'] = self::provider_label( $ugc_job['provider'] );
        $ugc_job['model'] = sanitize_text_field( $started['model'] ?? $ugc_job['model'] );
        $ugc_job['image_url'] = esc_url_raw( $started['image_url'] ?? $ugc_job['image_url'] );
        $ugc_job['video_url'] = esc_url_raw( $started['video_url'] ?? '' );
        $ugc_job['status_url'] = esc_url_raw( $started['status_url'] ?? '' );
        $ugc_job['result_url'] = esc_url_raw( $started['result_url'] ?? '' );
        $ugc_job['endpoint_used'] = sanitize_text_field( $started['endpoint_used'] ?? '' );
        $ugc_job['provider_debug'] = self::sanitize_provider_debug( $started['provider_debug'] ?? array() );
        $ugc_job['raw_status'] = sanitize_key( $started['raw_status'] ?? 'processing' );
        if ( ! empty( $ugc_job['video_url'] ) ) {
            $ugc_job['status'] = 'ready';
        }
        $ugc_job = self::save_ugc_job( $parent_job['job_id'], $ugc_job );

        return array(
            'parent_job_id' => $parent_job['job_id'],
            'ugc_job'       => $ugc_job,
            'ugc_jobs'      => self::get_jobs( $parent_job['job_id'] ),
            'status'        => $ugc_job['status'],
            'message'       => 'Vídeo UGC iniciado.',
        );
    }

    public static function poll_job( $parent_job_id, $ugc_job_id ) {
        $parent_job_id = sanitize_text_field( $parent_job_id );
        $ugc_job_id = sanitize_text_field( $ugc_job_id );
        $job = STLAI_Video_Storage::get_job( $parent_job_id );
        if ( ! $job ) {
            return new WP_Error( 'UGC_PARENT_JOB_NOT_FOUND', 'Job do anúncio não encontrado.' );
        }

        $ugc_job = self::find_ugc_job( $job, $ugc_job_id );
        if ( ! $ugc_job ) {
            return new WP_Error( 'UGC_JOB_NOT_FOUND', 'Job UGC não encontrado.' );
        }

        if ( 'ready' === ( $ugc_job['status'] ?? '' ) && ! empty( $ugc_job['video_url'] ) ) {
            return array(
                'parent_job_id' => $parent_job_id,
                'ugc_job'       => self::public_ugc_job( $ugc_job ),
                'ugc_jobs'      => self::get_jobs( $parent_job_id ),
                'status'        => 'ready',
                'video_url'     => $ugc_job['video_url'],
                'message'       => 'Vídeo UGC pronto.',
            );
        }

        $operation_id = sanitize_text_field( $ugc_job['operation_id'] ?? ( $ugc_job['request_id'] ?? '' ) );
        if ( empty( $operation_id ) ) {
            return new WP_Error( 'UGC_REQUEST_ID_MISSING', 'operation_id UGC ausente.' );
        }

        $provider_key = sanitize_key( $ugc_job['provider'] ?? 'muapi' );
        $provider = self::provider_for_key( $provider_key );
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }

        $polled = call_user_func( array( $provider['class'], 'poll_job' ), $operation_id, $ugc_job );
        if ( is_wp_error( $polled ) ) {
            $ugc_job['status'] = 'failed';
            $ugc_job['error_message'] = $polled->get_error_message();
        } else {
            $ugc_job['status'] = sanitize_key( $polled['status'] ?? 'processing' );
            $ugc_job['raw_status'] = sanitize_key( $polled['raw_status'] ?? '' );
            $ugc_job['video_url'] = esc_url_raw( $polled['video_url'] ?? ( $ugc_job['video_url'] ?? '' ) );
            $ugc_job['provider'] = sanitize_key( $polled['provider'] ?? ( $ugc_job['provider'] ?? $provider_key ) );
            $ugc_job['provider_label'] = self::provider_label( $ugc_job['provider'] );
            $ugc_job['model'] = sanitize_text_field( $polled['model'] ?? ( $ugc_job['model'] ?? '' ) );
            $ugc_job['operation_id'] = sanitize_text_field( $polled['operation_id'] ?? $operation_id );
            $ugc_job['request_id'] = sanitize_text_field( $polled['request_id'] ?? ( $ugc_job['request_id'] ?? $operation_id ) );
            $ugc_job['status_url'] = esc_url_raw( $polled['status_url'] ?? ( $ugc_job['status_url'] ?? '' ) );
            $ugc_job['result_url'] = esc_url_raw( $polled['result_url'] ?? ( $ugc_job['result_url'] ?? '' ) );
            $ugc_job['provider_debug'] = self::sanitize_provider_debug( $polled['provider_debug'] ?? ( $ugc_job['provider_debug'] ?? array() ) );
            $ugc_job['error_message'] = 'failed' === $ugc_job['status'] ? ( $polled['message'] ?? 'Falha ao gerar UGC.' ) : '';
        }
        $ugc_job['updated_at'] = current_time( 'mysql' );
        $ugc_job = self::save_ugc_job( $parent_job_id, $ugc_job );

        return array(
            'parent_job_id' => $parent_job_id,
            'ugc_job'       => $ugc_job,
            'ugc_jobs'      => self::get_jobs( $parent_job_id ),
            'status'        => $ugc_job['status'],
            'video_url'     => $ugc_job['video_url'] ?? '',
            'message'       => 'ready' === $ugc_job['status'] ? 'Vídeo UGC pronto.' : ( 'failed' === $ugc_job['status'] ? ( $ugc_job['error_message'] ?? 'Falha ao gerar UGC.' ) : 'Gerando vídeo UGC.' ),
        );
    }

    public static function get_jobs( $parent_job_id ) {
        $job = STLAI_Video_Storage::get_job( $parent_job_id );
        if ( ! $job ) {
            return array();
        }

        $items = is_array( $job['ugc_jobs'] ?? null ) ? $job['ugc_jobs'] : array();
        return array_values( array_map( array( __CLASS__, 'public_ugc_job' ), $items ) );
    }

    public static function ugc_config_status() {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $provider = sanitize_key( (string) ( $settings['ugcProvider'] ?? 'none' ) );
        $enabled = false;
        if ( 'muapi' === $provider ) {
            $enabled = ! empty( $settings['muApiKey'] ?? '' );
        } elseif ( 'atlas' === $provider ) {
            $enabled = ! empty( $settings['ugcAtlasApiKey'] ?? '' );
        } elseif ( 'fal' === $provider ) {
            $enabled = ! empty( $settings['ugcFalApiKey'] ?? '' );
        } elseif ( 'seedance' === $provider ) {
            $enabled = ! empty( $settings['seedanceApiKey'] ?? '' ) && ! empty( $settings['seedanceBaseUrl'] ?? '' ) && ! empty( $settings['seedanceEndpoint'] ?? '' ) && ! empty( $settings['seedancePollEndpoint'] ?? '' );
        }
        return array(
            'provider'      => $provider,
            'providerLabel' => self::provider_label( $provider ),
            'enabled'       => $enabled,
            'defaultAspect' => self::sanitize_aspect_ratio( $settings['ugcDefaultAspectRatio'] ?? self::DEFAULT_ASPECT_RATIO ),
            'defaultDuration' => self::sanitize_duration( $settings['ugcDefaultDuration'] ?? self::DEFAULT_DURATION ),
            'defaultResolution' => self::sanitize_resolution( $settings['ugcDefaultResolution'] ?? self::DEFAULT_RESOLUTION ),
        );
    }

    private static function validate_start_payload( array $payload ) {
        $presets = self::presets();
        $preset = sanitize_key( wp_unslash( $payload['preset'] ?? 'ugc' ) );
        if ( ! isset( $presets[ $preset ] ) ) {
            return new WP_Error( 'UGC_INVALID_PRESET', 'Preset UGC inválido.' );
        }

        $image_url = self::public_image_url_from_payload( $payload );
        if ( '' === $image_url ) {
            $has_data_image = self::payload_has_data_image_url( $payload );
            return new WP_Error(
                $has_data_image ? 'UGC_IMAGE_NOT_PUBLISHED' : 'UGC_IMAGE_REQUIRES_PUBLIC_URL',
                $has_data_image ? 'A imagem precisa ser publicada antes de gerar UGC.' : 'Selecione uma imagem publicada para gerar UGC.',
                array(
                    'debug' => wp_json_encode(
                        array(
                            'received_image_fields' => array_values( array_filter( array( 'image_url', 'reference_image_url', 'product_image_url', 'selected_image_url', 'url' ), function( $key ) use ( $payload ) {
                                return '' !== trim( (string) ( $payload[ $key ] ?? '' ) );
                            } ) ),
                            'payload_keys' => array_values( array_map( 'sanitize_key', array_keys( $payload ) ) ),
                        ),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                )
            );
        }

        $settings = self::ugc_config_status();
        return array(
            'parent_job_id'        => sanitize_text_field( wp_unslash( $payload['parent_job_id'] ?? '' ) ),
            'preset'               => $preset,
            'label'                => $presets[ $preset ]['label'],
            'prompt_key'           => $presets[ $preset ]['prompt_key'],
            'image_url'            => $image_url,
            'selected_image_label' => sanitize_text_field( wp_unslash( $payload['selected_image_label'] ?? 'Imagem selecionada' ) ),
            'aspect_ratio'         => self::sanitize_aspect_ratio( $payload['aspect_ratio'] ?? $settings['defaultAspect'] ),
            'duration'             => self::sanitize_duration( $payload['duration'] ?? $settings['defaultDuration'] ),
            'resolution'           => self::sanitize_resolution( $payload['resolution'] ?? $settings['defaultResolution'] ),
            'prompt_public'        => self::default_prompt_for_preset( $preset ),
        );
    }

    private static function public_image_url_from_payload( array $payload ) {
        foreach ( array( 'image_url', 'reference_image_url', 'product_image_url', 'selected_image_url', 'url' ) as $key ) {
            $value = trim( (string) wp_unslash( $payload[ $key ] ?? '' ) );
            if ( '' === $value ) {
                continue;
            }
            $url = esc_url_raw( $value );
            if ( preg_match( '#^https?://#i', $url ) ) {
                return $url;
            }
        }
        return '';
    }

    private static function payload_has_data_image_url( array $payload ) {
        foreach ( array( 'image_url', 'reference_image_url', 'product_image_url', 'selected_image_url', 'url' ) as $key ) {
            $value = trim( (string) wp_unslash( $payload[ $key ] ?? '' ) );
            if ( preg_match( '#^data:image/#i', $value ) ) {
                return true;
            }
        }
        return false;
    }

    private static function parent_job( $parent_job_id, array $validated ) {
        $parent_job_id = sanitize_text_field( $parent_job_id );
        if ( $parent_job_id ) {
            $job = STLAI_Video_Storage::get_job( $parent_job_id );
            if ( $job ) {
                return $job;
            }
        }

        return STLAI_Video_Storage::create_job(
            array(
                'status'              => 'ugc_only',
                'progress'            => 0,
                'progress_hint'       => 0,
                'message'             => 'Job criado para vídeos UGC.',
                'selected_images'     => array( $validated['image_url'] ),
                'product_name'        => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
                'product_description' => wp_kses_post( wp_unslash( $_POST['product_description'] ?? '' ) ),
                'ugc_jobs'            => array(),
            )
        );
    }

    private static function save_ugc_job( $parent_job_id, array $ugc_job ) {
        $job = STLAI_Video_Storage::get_job( $parent_job_id );
        if ( ! $job ) {
            return self::public_ugc_job( $ugc_job );
        }

        $jobs = is_array( $job['ugc_jobs'] ?? null ) ? $job['ugc_jobs'] : array();
        $by_id = array();
        foreach ( $jobs as $existing ) {
            if ( ! empty( $existing['ugc_job_id'] ) ) {
                $by_id[ $existing['ugc_job_id'] ] = $existing;
            }
        }

        $id = $ugc_job['ugc_job_id'];
        $existing = $by_id[ $id ] ?? array();
        $by_id[ $id ] = self::stronger_ugc_job( $existing, $ugc_job );

        STLAI_Video_Storage::update_job( $parent_job_id, array( 'ugc_jobs' => array_values( $by_id ) ) );
        return self::public_ugc_job( $by_id[ $id ] );
    }

    private static function stronger_ugc_job( array $existing, array $incoming ) {
        $job = array_merge( $existing, $incoming );
        if ( ! empty( $existing['video_url'] ) && empty( $incoming['video_url'] ) ) {
            $job['video_url'] = $existing['video_url'];
        }
        if ( ( $existing['status'] ?? '' ) === 'ready' && ( $incoming['status'] ?? '' ) !== 'ready' ) {
            $job['status'] = 'ready';
        }
        if ( empty( $incoming['created_at'] ) && ! empty( $existing['created_at'] ) ) {
            $job['created_at'] = $existing['created_at'];
        }
        $job['updated_at'] = current_time( 'mysql' );
        return $job;
    }

    private static function find_ugc_job( array $job, $ugc_job_id ) {
        foreach ( (array) ( $job['ugc_jobs'] ?? array() ) as $ugc_job ) {
            if ( ( $ugc_job['ugc_job_id'] ?? '' ) === $ugc_job_id ) {
                return $ugc_job;
            }
        }
        return null;
    }

    private static function build_prompt( array $validated, array $parent_job, array $settings ) {
        $template = (string) ( $settings[ $validated['prompt_key'] ] ?? '' );
        if ( '' === trim( $template ) ) {
            $template = self::default_prompt_for_preset( $validated['preset'] );
        }

        $replacements = array(
            '{{product_name}}'        => (string) ( $parent_job['product_name'] ?? 'produto' ),
            '{{product_description}}' => wp_strip_all_tags( (string) ( $parent_job['product_description'] ?? '' ) ),
            '{{product_context}}'     => wp_strip_all_tags( (string) ( $parent_job['product_description'] ?? '' ) ),
            '{{target_audience}}'     => '',
            '{{tone}}'                => sanitize_text_field( (string) ( $parent_job['narration_style'] ?? 'natural' ) ),
            '{{language}}'            => sanitize_text_field( (string) ( $parent_job['video_language'] ?? 'pt-BR' ) ),
            '{{aspect_ratio}}'        => $validated['aspect_ratio'],
            '{{duration}}'            => (string) $validated['duration'],
            '{{resolution}}'          => $validated['resolution'],
            '{{image_url}}'           => $validated['image_url'],
            '{{selected_image_label}}' => $validated['selected_image_label'],
            '{{benefits}}'            => '',
            '{{features}}'            => '',
            '{{marketplace_context}}' => 'marketplace, social media ads and ecommerce product presentation',
        );

        return trim( strtr( $template, $replacements ) . "\n\n" . self::base_prompt() . "\n\n" . self::safety_prompt() );
    }

    public static function base_prompt() {
        return 'Create a realistic UGC-style product video based on the provided product image. Keep the product visually consistent with the reference image. Do not change the product identity, shape, color, material, label, packaging, details or proportions. The video should feel like authentic social media content, naturally filmed, with believable lighting and motion. Do not add text overlays, watermarks, logos, subtitles or UI elements unless explicitly requested.';
    }

    public static function safety_prompt() {
        return "Preserve exact product identity.\nDo not change the product shape.\nDo not change product color.\nDo not change label, logo, packaging or material.\nDo not invent product features.\nDo not add text overlays.\nDo not add subtitles.\nDo not add watermarks.\nDo not add UI.\nDo not use celebrity likeness.\nDo not create unsafe or adult content.\nDo not create medical, legal or financial claims.\nUse only realistic product benefit language.\nAvoid unrealistic transformations.\nFor 3D printed or personalized products: do not change face, hair, clothing, base, main pose or finish.";
    }

    public static function default_prompt_for_preset( $preset ) {
        $defaults = array(
            'ugc'                => 'Create an authentic UGC-style social media video featuring {{product_name}}. The video should feel like a real creator casually filming the product in a natural setting. Show the product clearly, with handheld-style motion, realistic lighting, and a believable lifestyle context. Focus on why someone would want to use or buy it. Keep it natural, not overly polished.',
            'tutorial'           => 'Create a step-by-step tutorial-style video showing how {{product_name}} is used. Keep the product clear and visible. Use natural hand movement or product handling when appropriate, but do not change the product. The video should feel practical, helpful and easy to understand.',
            'unboxing'           => 'Create a realistic unboxing-style video for {{product_name}}. Show the product being revealed or presented as if someone just received it. Focus on first impression, packaging feel, product reveal and visual appeal. Keep the product consistent with the reference image.',
            'product_review'     => 'Create an authentic product review-style video for {{product_name}}. The video should feel like a creator showing the product to the camera, highlighting real benefits, details and usage impressions. Keep the tone natural and trustworthy.',
            'ugc_virtual_try_on' => 'Create a realistic virtual try-on or demonstration-style video for {{product_name}}. Show the product being experienced, worn, tested or demonstrated in context when appropriate. Preserve the product exactly. Make the scene feel like user-generated content.',
            'hyper_motion'       => 'Create a high-energy product video for {{product_name}} with dynamic but realistic movement. Highlight the product with fast visual rhythm, smooth camera motion and strong product focus. Preserve the product exactly. Avoid unrealistic effects.',
            'tv_spot'            => 'Create a polished commercial TV spot-style video for {{product_name}}. Use cinematic product framing, clear benefit-driven storytelling and premium movement. Keep the product visually consistent with the reference image.',
            'wild_card'          => 'Create a creative and unexpected product video for {{product_name}}. The concept should feel original and scroll-stopping, while keeping the product accurate and recognizable. Do not alter the product.',
            'pro_virtual_try_on' => 'Create a premium virtual try-on or product demonstration video for {{product_name}}. The product should be shown in a polished, professional, high-quality context. Preserve product details and make the result feel commercially usable.',
        );
        $preset = sanitize_key( $preset );
        return $defaults[ $preset ] ?? $defaults['ugc'];
    }

    private static function sanitize_aspect_ratio( $value ) {
        $value = sanitize_text_field( (string) $value );
        return in_array( $value, array( '9:16', '16:9', '1:1' ), true ) ? $value : self::DEFAULT_ASPECT_RATIO;
    }

    private static function sanitize_duration( $value ) {
        $value = (int) $value;
        return in_array( $value, array( 5, 8, 9, 10 ), true ) ? $value : self::DEFAULT_DURATION;
    }

    private static function sanitize_resolution( $value ) {
        $value = sanitize_key( (string) $value );
        return in_array( $value, array( '720p', '1080p' ), true ) ? $value : self::DEFAULT_RESOLUTION;
    }

    public static function public_ugc_job( array $job ) {
        return array(
            'ugc_job_id'          => sanitize_text_field( $job['ugc_job_id'] ?? '' ),
            'parent_job_id'       => sanitize_text_field( $job['parent_job_id'] ?? '' ),
            'preset'              => sanitize_key( $job['preset'] ?? '' ),
            'label'               => sanitize_text_field( $job['label'] ?? '' ),
            'provider'            => sanitize_key( $job['provider'] ?? 'muapi' ),
            'provider_label'      => sanitize_text_field( $job['provider_label'] ?? self::provider_label( $job['provider'] ?? 'muapi' ) ),
            'model'               => sanitize_text_field( $job['model'] ?? '' ),
            'request_id'          => sanitize_text_field( $job['request_id'] ?? '' ),
            'operation_id'        => sanitize_text_field( $job['operation_id'] ?? ( $job['request_id'] ?? '' ) ),
            'status_url'          => esc_url_raw( $job['status_url'] ?? '' ),
            'result_url'          => esc_url_raw( $job['result_url'] ?? '' ),
            'endpoint_used'       => sanitize_text_field( $job['endpoint_used'] ?? '' ),
            'status'              => sanitize_key( $job['status'] ?? 'processing' ),
            'image_url'           => esc_url_raw( $job['image_url'] ?? '' ),
            'selected_image_label' => sanitize_text_field( $job['selected_image_label'] ?? '' ),
            'prompt_public'       => sanitize_textarea_field( $job['prompt_public'] ?? '' ),
            'aspect_ratio'        => sanitize_text_field( $job['aspect_ratio'] ?? self::DEFAULT_ASPECT_RATIO ),
            'duration'            => (int) ( $job['duration'] ?? self::DEFAULT_DURATION ),
            'resolution'          => sanitize_key( $job['resolution'] ?? self::DEFAULT_RESOLUTION ),
            'video_url'           => esc_url_raw( $job['video_url'] ?? '' ),
            'error_message'       => sanitize_text_field( $job['error_message'] ?? '' ),
            'raw_status'          => sanitize_key( $job['raw_status'] ?? '' ),
            'created_at'          => sanitize_text_field( $job['created_at'] ?? '' ),
            'updated_at'          => sanitize_text_field( $job['updated_at'] ?? '' ),
        );
    }

    private static function sanitize_provider_debug( $debug ) {
        if ( ! is_array( $debug ) ) {
            return array();
        }
        $safe = array();
        foreach ( $debug as $key => $value ) {
            $safe_key = sanitize_key( $key );
            if ( in_array( $safe_key, array( 'api_key', 'x_api_key', 'authorization', 'secret', 'token' ), true ) ) {
                continue;
            }
            if ( is_bool( $value ) ) {
                $safe[ $safe_key ] = $value;
            } elseif ( is_array( $value ) ) {
                $safe[ $safe_key ] = array_values( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) );
            } else {
                $safe[ $safe_key ] = sanitize_text_field( (string) $value );
            }
        }
        return $safe;
    }

    private static function provider_key_from_settings( array $settings ) {
        return sanitize_key( (string) ( $settings['ugcProvider'] ?? 'none' ) );
    }

    private static function provider_for_key( $provider_key ) {
        $providers = array(
            'muapi'    => array( 'class' => 'STLAI_MuAPI_UGC_Provider' ),
            'atlas'    => array( 'class' => 'STLAI_Atlas_UGC_Provider' ),
            'fal'      => array( 'class' => 'STLAI_Fal_UGC_Provider' ),
            'seedance' => array( 'class' => 'STLAI_Seedance_UGC_Provider' ),
        );

        $provider_key = sanitize_key( $provider_key );
        if ( empty( $provider_key ) || 'none' === $provider_key ) {
            return new WP_Error( 'UGC_PROVIDER_NOT_CONFIGURED', 'Configure um provider UGC para gerar vídeos.' );
        }
        if ( empty( $providers[ $provider_key ] ) || ! class_exists( $providers[ $provider_key ]['class'] ) ) {
            return new WP_Error( 'UGC_PROVIDER_NOT_IMPLEMENTED', 'Provider UGC não implementado.' );
        }
        return $providers[ $provider_key ];
    }

    public static function provider_label( $provider_key ) {
        $labels = array(
            'muapi'    => 'MuAPI',
            'atlas'    => 'Atlas Cloud',
            'fal'      => 'Fal.ai',
            'seedance' => 'Seedance/BytePlus',
            'none'     => 'Desativado',
        );
        $provider_key = sanitize_key( $provider_key );
        return $labels[ $provider_key ] ?? 'UGC';
    }

    private static function model_for_provider( $provider_key, array $settings ) {
        $provider_key = sanitize_key( $provider_key );
        if ( 'atlas' === $provider_key ) {
            return sanitize_text_field( (string) ( $settings['ugcAtlasModel'] ?? STLAI_Atlas_UGC_Provider::DEFAULT_MODEL ) ) ?: STLAI_Atlas_UGC_Provider::DEFAULT_MODEL;
        }
        if ( 'fal' === $provider_key ) {
            return sanitize_text_field( (string) ( $settings['ugcFalModel'] ?? STLAI_Fal_UGC_Provider::DEFAULT_MODEL ) ) ?: STLAI_Fal_UGC_Provider::DEFAULT_MODEL;
        }
        if ( 'seedance' === $provider_key ) {
            return sanitize_text_field( (string) ( $settings['seedanceModel'] ?? STLAI_Seedance_UGC_Provider::DEFAULT_MODEL ) ) ?: STLAI_Seedance_UGC_Provider::DEFAULT_MODEL;
        }
        return STLAI_MuAPI_UGC_Provider::normalize_model(
            (string) ( $settings['muApiModel'] ?? '' ),
            (string) ( $settings['muApiBaseUrl'] ?? '' )
        );
    }

    public static function extract_provider_status( array $data ) {
        foreach ( array( 'status', 'state' ) as $key ) {
            if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
                return sanitize_key( (string) $data[ $key ] );
            }
        }
        foreach ( array( 'data', 'output', 'result', 'prediction' ) as $key ) {
            if ( ! empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                $status = self::extract_provider_status( $data[ $key ] );
                if ( $status ) {
                    return $status;
                }
            }
        }
        return '';
    }

    public static function extract_provider_operation_id( array $data ) {
        foreach ( array( 'operation_id', 'request_id', 'id', 'prediction_id' ) as $key ) {
            if ( ! empty( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
                return sanitize_text_field( (string) $data[ $key ] );
            }
        }
        foreach ( array( 'data', 'output', 'result', 'prediction' ) as $key ) {
            if ( ! empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                $id = self::extract_provider_operation_id( $data[ $key ] );
                if ( $id ) {
                    return $id;
                }
            }
        }
        return '';
    }

    public static function extract_provider_video_url( array $data ) {
        $candidates = array(
            $data['video_url'] ?? '',
            $data['url'] ?? '',
            $data['output']['url'] ?? '',
            $data['output']['video_url'] ?? '',
            $data['output']['video']['url'] ?? '',
            $data['data']['url'] ?? '',
            $data['data']['video_url'] ?? '',
            $data['data']['video']['url'] ?? '',
            $data['data']['output'] ?? '',
            $data['data']['outputs'][0] ?? '',
            $data['result']['url'] ?? '',
            $data['result']['video_url'] ?? '',
            $data['video']['url'] ?? '',
            $data['outputs'][0] ?? '',
        );

        foreach ( $candidates as $candidate ) {
            if ( is_array( $candidate ) ) {
                $nested = self::extract_provider_video_url( $candidate );
                if ( $nested ) {
                    return $nested;
                }
            } elseif ( is_string( $candidate ) && preg_match( '#^https?://#i', $candidate ) ) {
                return esc_url_raw( $candidate );
            }
        }

        return '';
    }

    public static function normalize_provider_status( $raw_status, $video_url = '' ) {
        if ( ! empty( $video_url ) ) {
            return 'ready';
        }
        $raw_status = sanitize_key( (string) $raw_status );
        if ( in_array( $raw_status, array( 'completed', 'succeeded', 'success', 'done', 'ready', 'finished' ), true ) ) {
            return 'failed';
        }
        if ( in_array( $raw_status, array( 'failed', 'error', 'canceled', 'cancelled' ), true ) ) {
            return 'failed';
        }
        return 'processing';
    }

    public static function message_for_provider_status( $status, $video_url = '' ) {
        if ( 'ready' === $status ) {
            return 'Vídeo UGC pronto.';
        }
        if ( 'failed' === $status ) {
            return empty( $video_url ) ? 'Provider concluiu sem retornar vídeo.' : 'Não foi possível gerar o vídeo UGC.';
        }
        return 'Gerando vídeo UGC.';
    }
}
