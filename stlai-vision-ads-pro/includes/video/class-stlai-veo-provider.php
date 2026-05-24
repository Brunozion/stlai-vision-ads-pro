<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Veo_Provider {
    const DEFAULT_MODEL = 'veo-3.1-lite-generate-preview';
    const DEFAULT_PROVIDER = 'gemini_veo';
    const DEFAULT_OUTPUT_RESOLUTION = '720p';
    const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    const MAX_IMAGE_BYTES = 15728640;
    const POLL_ATTEMPTS = 10;
    const POLL_INTERVAL = 5;

    public static function generate_test_clip( array $payload ) {
        $clip = self::generate_clip(
            array_merge(
                $payload,
                array(
                    'index'          => 1,
                    'role'           => 'teste_visual',
                    'role_label'     => 'Clipe IA de teste',
                    'role_direction' => 'Produto parado, visual limpo, movimento de câmera muito suave e leve zoom.',
                )
            )
        );

        if ( is_wp_error( $clip ) ) {
            return $clip;
        }

        return array(
            'test_clip_url'  => $clip['url'],
            'test_clip_path' => $clip['path'],
            'provider'       => $clip['provider'],
            'model'          => $clip['model'],
            'operation_id'   => $clip['operation_id'],
            'operation_name' => $clip['operation_name'],
            'status'         => 'ready',
            'debug'          => $clip['debug'],
        );
    }

    public static function generate_clip( array $payload ) {
        $existing_operation_id = trim( sanitize_text_field( (string) ( $payload['operation_id'] ?? '' ) ) );
        if ( ! empty( $existing_operation_id ) ) {
            return self::poll_clip_operation( $existing_operation_id, $payload, true );
        }

        $operation = self::start_clip_operation( $payload );
        if ( is_wp_error( $operation ) ) {
            return $operation;
        }

        $payload = array_merge(
            $payload,
            array(
                'operation_id' => $operation['operation_id'] ?? '',
                'prepared_frame_url' => $operation['prepared_frame_url'] ?? '',
                'prepared_frame_path' => $operation['prepared_frame_path'] ?? '',
                'prepared_frame_width' => $operation['prepared_frame_width'] ?? 0,
                'prepared_frame_height' => $operation['prepared_frame_height'] ?? 0,
                'prepared_frame_aspect_ratio' => $operation['aspect_ratio'] ?? '',
                'operation_debug' => $operation['debug'] ?? '',
            )
        );

        return self::poll_clip_operation( $operation['operation_id'] ?? '', $payload, true );
    }

    public static function start_clip_operation( array $payload ) {
        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $config = self::validate_config( $settings );
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $format = sanitize_text_field( $payload['format'] ?? '' );
        $aspect_ratio = self::aspect_ratio_for_format( $format );
        if ( is_wp_error( $aspect_ratio ) ) {
            return $aspect_ratio;
        }

        $image = self::prepare_image( $payload['image_url'] ?? '' );
        if ( is_wp_error( $image ) ) {
            return $image;
        }

        $prepared_frame = self::prepare_frame_for_aspect_ratio( $image, $aspect_ratio['value'] );
        if ( is_wp_error( $prepared_frame ) ) {
            return $prepared_frame;
        }

        $prompt = self::build_prompt( $payload );
        $body = array(
            'instances'  => array(
                array(
                    'prompt' => $prompt,
                    'image'  => array(
                        'bytesBase64Encoded' => $prepared_frame['base64'],
                        'mimeType'           => $prepared_frame['mime_type'],
                    ),
                ),
            ),
            'parameters' => array(
                'durationSeconds' => 8,
                'aspectRatio'     => $aspect_ratio['value'],
                'resolution'      => $config['effective_resolution'],
            ),
        );

        $safe_debug = self::payload_debug( $config, $aspect_ratio['value'], $prepared_frame, $payload );

        $operation = self::create_operation( $config, $body, $safe_debug );
        if ( is_wp_error( $operation ) ) {
            return $operation;
        }

        $operation_name = $operation['name'] ?? '';
        if ( empty( $operation_name ) ) {
            return self::error( 'VEO_INVALID_RESPONSE', 'O serviço de vídeo não retornou uma operação válida.', self::join_debug( $safe_debug, 'Campo name ausente na criação da operação.' ) );
        }

        $operation_debug = self::join_debug( $safe_debug, 'operation_id=' . $operation_name );

        return array(
            'index'          => (int) ( $payload['index'] ?? 1 ),
            'role'           => sanitize_key( $payload['role'] ?? 'clip' ),
            'label'          => sanitize_text_field( $payload['role_label'] ?? 'Clipe' ),
            'prepared_frame_url' => $prepared_frame['prepared_frame_url'] ?? '',
            'prepared_frame_path' => $prepared_frame['prepared_frame_path'] ?? '',
            'prepared_frame_width' => (int) ( $prepared_frame['prepared_width'] ?? 0 ),
            'prepared_frame_height' => (int) ( $prepared_frame['prepared_height'] ?? 0 ),
            'aspect_ratio'   => $aspect_ratio['value'],
            'provider'       => $config['provider'],
            'model'          => $config['model'],
            'output_resolution' => $config['effective_resolution'],
            'requested_resolution' => $config['requested_resolution'],
            'effective_resolution' => $config['effective_resolution'],
            'resolution_fallback_reason' => $config['resolution_fallback_reason'],
            'operation_id'   => $operation_name,
            'operation_name' => $operation_name,
            'status'         => 'processing',
            'debug'          => $operation_debug,
        );
    }

    public static function poll_clip_operation( $operation_name, array $payload = array(), $blocking = false ) {
        $operation_name = trim( sanitize_text_field( (string) $operation_name ) );
        if ( empty( $operation_name ) ) {
            return self::error( 'VEO_OPERATION_MISSING', 'Operação de vídeo ausente.', 'operation_id vazio.' );
        }

        $settings = get_option( 'stlai_vision_ads_pro_settings', array() );
        $config = self::validate_config( $settings );
        if ( is_wp_error( $config ) ) {
            return $config;
        }

        $format = sanitize_text_field( $payload['format'] ?? '' );
        $aspect_ratio = self::aspect_ratio_for_format( $format );
        if ( is_wp_error( $aspect_ratio ) ) {
            return $aspect_ratio;
        }

        $operation_debug = trim( sanitize_text_field( (string) ( $payload['operation_debug'] ?? '' ) ) );
        if ( empty( $operation_debug ) ) {
            $operation_debug = 'operation_id=' . $operation_name;
        }

        $done = $blocking
            ? self::poll_operation( $config, $operation_name, $operation_debug )
            : self::poll_operation_once( $config, $operation_name, $operation_debug );
        if ( is_wp_error( $done ) ) {
            return $done;
        }

        $video_uri = self::extract_video_uri( $done );
        if ( empty( $video_uri ) ) {
            return self::error( 'VEO_INVALID_RESPONSE', 'O serviço de vídeo não retornou um vídeo válido.', self::join_debug( $operation_debug, 'URI do vídeo ausente na operação concluída.' ) );
        }

        $video_binary = self::download_video( $config, $video_uri );
        if ( is_wp_error( $video_binary ) ) {
            return self::append_error_debug( $video_binary, $operation_debug );
        }

        $saved = self::save_video_file( $video_binary );
        if ( is_wp_error( $saved ) ) {
            return self::append_error_debug( $saved, $operation_debug );
        }

        $audio_result = self::strip_audio_if_possible( $saved );
        $clip = is_wp_error( $audio_result ) ? $saved : $audio_result;

        $debug_parts = array_filter(
            array(
                $operation_debug,
                $aspect_ratio['debug'],
                is_wp_error( $audio_result ) ? $audio_result->get_error_code() : 'audio=removed_with_ffmpeg',
            )
        );
        $debug = trim( implode( '; ', $debug_parts ) );

        return array(
            'index'          => (int) ( $payload['index'] ?? 1 ),
            'role'           => sanitize_key( $payload['role'] ?? 'clip' ),
            'label'          => sanitize_text_field( $payload['role_label'] ?? 'Clipe' ),
            'url'            => $clip['url'],
            'path'           => $clip['path'],
            'duration'       => 8,
            'muted'          => true,
            'prepared_frame_url' => esc_url_raw( $payload['prepared_frame_url'] ?? '' ),
            'prepared_frame_path' => sanitize_text_field( $payload['prepared_frame_path'] ?? '' ),
            'prepared_frame_width' => (int) ( $payload['prepared_frame_width'] ?? 0 ),
            'prepared_frame_height' => (int) ( $payload['prepared_frame_height'] ?? 0 ),
            'aspect_ratio'   => $aspect_ratio['value'],
            'provider'       => $config['provider'],
            'model'          => $config['model'],
            'output_resolution' => $config['effective_resolution'],
            'requested_resolution' => $config['requested_resolution'],
            'effective_resolution' => $config['effective_resolution'],
            'resolution_fallback_reason' => $config['resolution_fallback_reason'],
            'operation_id'   => $operation_name,
            'operation_name' => $operation_name,
            'status'         => 'ready',
            'debug'          => $debug,
        );
    }

    private static function validate_config( array $settings ) {
        $provider = sanitize_key( (string) ( $settings['commercialVideoProvider'] ?? '' ) );
        if ( empty( $provider ) ) {
            $legacy_provider = sanitize_key( (string) ( $settings['videoProvider'] ?? '' ) );
            $provider = in_array( $legacy_provider, array( 'veo', 'gemini_veo' ), true ) ? self::DEFAULT_PROVIDER : $legacy_provider;
        }
        if ( empty( $provider ) ) {
            $provider = self::DEFAULT_PROVIDER;
        }

        if ( self::DEFAULT_PROVIDER !== $provider ) {
            return self::error( 'VIDEO_PROVIDER_NOT_IMPLEMENTED', 'Provider selecionado ainda não está implementado no MVP.', 'provider=' . $provider );
        }

        $api_key = trim( (string) ( $settings['videoApiKey'] ?? '' ) );
        if ( empty( $api_key ) ) {
            return self::error( 'MISSING_VIDEO_API_KEY', 'Configure a Video API Key no painel.' );
        }

        $model = trim( (string) ( $settings['commercialVideoModel'] ?? ( $settings['videoModel'] ?? '' ) ) );
        $allowed_models = self::allowed_models();
        if ( empty( $model ) || ! in_array( $model, $allowed_models, true ) ) {
            $model = self::DEFAULT_MODEL;
        }

        $requested_resolution = sanitize_key( (string) ( $settings['commercialVideoOutputResolution'] ?? self::DEFAULT_OUTPUT_RESOLUTION ) );
        $resolution_fallback_reason = '';
        if ( ! in_array( $requested_resolution, array( '720p', '1080p' ), true ) ) {
            $resolution_fallback_reason = 'invalid_resolution_fallback_to_720p';
            $requested_resolution = self::DEFAULT_OUTPUT_RESOLUTION;
        }
        $effective_resolution = $requested_resolution;

        $base_url = trim( (string) ( $settings['videoBaseUrl'] ?? self::DEFAULT_BASE_URL ) );
        if ( empty( $base_url ) ) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $base_url = esc_url_raw( untrailingslashit( $base_url ) );
        if ( empty( $base_url ) || ! preg_match( '#^https://#i', $base_url ) ) {
            return self::error( 'MISSING_VIDEO_BASE_URL', 'Configure uma Video Base URL HTTPS válida no painel.' );
        }

        return array(
            'api_key'                    => $api_key,
            'provider'                   => self::DEFAULT_PROVIDER,
            'model'                      => sanitize_text_field( $model ),
            'base_url'                   => $base_url,
            'requested_resolution'       => $requested_resolution,
            'effective_resolution'       => $effective_resolution,
            'resolution_fallback_reason' => $resolution_fallback_reason,
        );
    }

    private static function allowed_models() {
        return array(
            'veo-3.1-lite-generate-preview',
            'veo-3.1-fast-generate-preview',
            'veo-3.1-generate-preview',
            'veo-2.0-generate-001',
        );
    }

    private static function prepare_image( $url ) {
        $url = trim( (string) $url );
        if ( empty( $url ) ) {
            return self::error( 'MISSING_SELECTED_IMAGE', 'Selecione ao menos uma imagem para o teste.' );
        }

        if ( preg_match( '#^data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=]+)$#', $url, $matches ) ) {
            $mime_type = 'image/' . strtolower( $matches[1] );
            if ( 'image/jpg' === $mime_type ) {
                $mime_type = 'image/jpeg';
            }

            $binary = base64_decode( $matches[2], true );
            if ( false === $binary || empty( $binary ) ) {
                return self::error( 'IMAGE_FETCH_ERROR', 'Não foi possível ler a imagem selecionada.', 'Data URL inválida.' );
            }

            return self::image_data_response( $binary, $mime_type );
        }

        $safe_url = esc_url_raw( $url );
        if ( empty( $safe_url ) ) {
            return self::error( 'IMAGE_FETCH_ERROR', 'Não foi possível ler a imagem selecionada.', 'URL de imagem inválida.' );
        }

        $response = wp_remote_get(
            $safe_url,
            array(
                'timeout'     => 30,
                'redirection' => 3,
            )
        );

        if ( is_wp_error( $response ) ) {
            return self::error( 'IMAGE_FETCH_ERROR', 'Não foi possível baixar a imagem selecionada.', sanitize_text_field( $response->get_error_message() ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            return self::error( 'IMAGE_FETCH_ERROR', 'Não foi possível baixar a imagem selecionada.', 'HTTP ' . $status_code );
        }

        $binary = wp_remote_retrieve_body( $response );
        $mime_type = self::detect_mime_type( $binary, (string) wp_remote_retrieve_header( $response, 'content-type' ) );

        return self::image_data_response( $binary, $mime_type );
    }

    private static function image_data_response( $binary, $mime_type ) {
        $size = strlen( (string) $binary );
        if ( $size <= 0 || $size > self::MAX_IMAGE_BYTES ) {
            return self::error( 'IMAGE_FETCH_ERROR', 'A imagem selecionada não pôde ser usada no teste.', 'Tamanho inválido da imagem.' );
        }

        if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
            return self::error( 'INVALID_IMAGE_MIME_TYPE', 'A imagem selecionada precisa ser JPG, PNG ou WEBP.', 'MIME detectado: ' . sanitize_text_field( $mime_type ) );
        }

        return array(
            'binary'    => $binary,
            'mime_type' => $mime_type,
        );
    }

    private static function detect_mime_type( $binary, $content_type ) {
        $content_type = strtolower( trim( explode( ';', (string) $content_type )[0] ) );
        if ( in_array( $content_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
            return $content_type;
        }

        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            if ( $finfo ) {
                $mime_type = finfo_buffer( $finfo, $binary );
                finfo_close( $finfo );
                if ( is_string( $mime_type ) && ! empty( $mime_type ) ) {
                    return $mime_type;
                }
            }
        }

        return 'application/octet-stream';
    }

    private static function aspect_ratio_for_format( $format ) {
        if ( '16:9' === $format || '9:16' === $format ) {
            return array(
                'value' => $format,
                'debug' => '',
            );
        }

        if ( '1:1' === $format ) {
            return array(
                'value' => '9:16',
                'debug' => 'Formato 1:1 não faz parte do MVP de video; usado fallback 9:16.',
            );
        }

        return self::error( 'INVALID_VIDEO_FORMAT', 'Formato de vídeo inválido para o teste.' );
    }

    private static function build_prompt( array $payload ) {
        $product_name = trim( wp_strip_all_tags( (string) ( $payload['product_name'] ?? '' ) ) );
        $product_description = trim( wp_strip_all_tags( (string) ( $payload['product_description'] ?? '' ) ) );
        $script = trim( wp_strip_all_tags( (string) ( $payload['script'] ?? '' ) ) );

        $product_name = self::compact_text( $product_name, 140 );
        $product_description = self::compact_text( $product_description, 320 );
        $script_context = self::compact_text( $script, 260 );
        $role_label = self::compact_text( sanitize_text_field( $payload['role_label'] ?? '' ), 120 );
        $role_direction = self::compact_text( sanitize_text_field( $payload['role_direction'] ?? '' ), 520 );
        $format = sanitize_text_field( $payload['format'] ?? '' );
        $format_direction = 'Keep the full product visible with safe space around all important edges. Stable camera, natural professional smartphone movement, no scene change, no internal fade. Animate the whole scene with subtle realistic motion while the product remains unchanged.';

        if ( '16:9' === $format ) {
            $format_direction = 'Horizontal 16:9 commercial product video. Use the prepared 16:9 frame as the exact visual reference. Preserve the product exactly. Use a wider horizontal commercial composition with subtle cinematic parallax across the full scene. Center the product or place it cleanly on rule of thirds with safe space above, below and on both sides. Keep the product fully visible and stable. If the product has a character, face, head, hair, clothing, top ornament, cake topper shape, keychain ring, base or stand, keep the whole object inside the frame. Start with a medium or wide shot and use only a very gentle push-in, dolly or parallax. Avoid close-ups that cut the head, face, top, base or important product details. Do not add black bars. Do not create a blurred artificial canvas. Do not change the product to fill the frame. The background can have natural movement, but the product must not change.';
        } elseif ( '9:16' === $format || '1:1' === $format ) {
            $format_direction = 'Vertical 9:16 commercial product video. Use the prepared 9:16 frame as the exact visual reference. Preserve the product exactly. Keep the product centered vertically with safe space at the top and base. No black borders. No artificial frame inside frame. Animate the scene naturally with subtle background motion and gentle camera movement. Use only a light natural zoom, push-in or slight parallax and never crop the product top, base, ring, support or display position.';
        }

        $product_line = $product_name ?: 'Commercial product shown in the reference image.';
        if ( ! empty( $product_description ) ) {
            $product_line .= ' Reference context: ' . $product_description;
        }

        $purpose_line = 'Preserve the original product and its real purpose from the product name, description and reference image. If the purpose is ambiguous, do not invent a new function. Treat the script only as marketing context and keep the product usage visually neutral and faithful to the reference.';
        if ( ! empty( $script_context ) ) {
            $purpose_line .= ' Marketing context only: ' . $script_context;
        }

        return implode(
            "\n\n",
            array(
                'Product:',
                $product_line,
                'Purpose:',
                $purpose_line,
                'Reference priority:',
                'Use the provided formatted image as the exact first frame, full scene, aspect ratio and product reference. The script is only marketing context. Do not create scenes from the script. Follow the formatted reference image exactly. The selected formatted image has priority over any text context.',
                'Clip role:',
                ( $role_label ?: 'Product visual clip' ) . ( $role_direction ? '. Direction: ' . $role_direction : '' ),
                'Product identity lock:',
                'The product must remain exactly identical to the source image in every frame. The product from the input image is sacred. Do not modify the product shape, material, color, size, texture, details, face, clothing, hair, pose, structure, printed elements, accessories, base, support, stand, ring, hook, attachment point or identity. Do not add new product parts. Do not remove any product parts. Do not replace the product with a different object. Do not redesign the product. Do not stylize the product. Do not change the product into another version. Do not add props attached to the product. Do not invent product features. The product must look like the same physical item from the input image in every frame. For 3D printed personalized products, cake toppers, figurines, characters or wedding toppers: do not change faces, hair, clothing, body proportions, main pose, base, format, printed details or any personalized element. Do not transform it into a toy, tool, keychain, bottle opener, gadget, different figurine or another object.',
                'Scene animation:',
                'Animate the entire scene with subtle realistic motion. Keep the product stable and unchanged as the hero subject. Add gentle cinematic camera movement such as slow dolly, soft parallax, natural push-in or a very light handheld product-video feel. Background people may move subtly and naturally only if they are already present in the source image. Do not invent new crowds or make background people the main characters. Ambient lights can softly shimmer. Flowers, fabric, candles, reflections, depth, shadows or background details may have very subtle movement only if they exist in the source image. The environment should feel alive, premium and realistic, but never distracting. The main product must stay sharp, consistent and recognizable. No sudden scene changes. No object transformation. No product morphing. No new objects appearing. No fantasy animation.',
                'Visual direction:',
                'Create one continuous 8-second silent, visual-only product video from the provided formatted image. The first video frame must match the formatted image composition and aspect ratio. Maintain the exact same aspect ratio from the first frame to the last frame. Do not transition from a square image into a vertical or horizontal layout. Do not reveal square source framing. Do not place the subject inside a smaller centered square. Do not create blurred background framing. Do not reveal padding, canvas changes, reframing, format conversion or layout changes inside the clip. The full frame must feel natively composed for the selected aspect ratio. Single continuous shot. No scene changes. No cuts. No internal transitions. No fade inside the clip. No before/after. No montage. No new location. Do not create a new scene. Do not change the product, product presentation or product identity. Do not cut to another shot. Do not fade to another scene. Do not transition inside the clip. Keep the product visually stable and exactly consistent, like a clean commercial product video. Animate the whole scene around it with subtle realistic camera and background motion. Only add subtle camera movement, gentle handheld feel, slow zoom in or slow zoom out, and slight natural parallax. Avoid exaggerated animation. Do not add fake recording indicators, camera UI, phone interface, viewfinder graphics or any production overlay.',
                'Framing and safe area:',
                'Keep the full product visible during most of the clip. Do not crop the head, face, top, base, support, ring, hook, stand, surface or any important product detail. Use stable camera movement, light natural motion, and a professional smartphone product-recording feel. ' . $format_direction,
                'Product anchoring rules:',
                'Preserve the exact product presentation from the reference image. Do not detach the product from its support or display position. Do not show a hand picking it up, removing it, lifting it, hanging it, placing it, or transforming its usage. Keep the product anchored exactly as shown in the reference image. No interaction action unless already clearly present in the source image.',
                'Overlay restrictions:',
                'Clean commercial product video only. Realistic premium marketplace style. Product focused, natural lighting, neutral color balance, no overlays, no text, no visual effects. No glitter. No artificial glitter. No sparkles. No magical sparkles. No purple particles. No purple tint. No fantasy glow. No magic effects. No animated overlays. No REC icon. No recording overlay. No camera HUD. No viewfinder overlay. No timestamp. No watermark. No logo. No subtitles. No captions. No text. No labels. No badges. No stickers. No icons. No UI elements. No camera screen graphics. No phone camera interface. No fake recording indicators. No red dot. No frame counter. No focus box. No battery icon. No interface chrome. No glitter overlay. No sparkle overlay. No particles. No dust overlay. No snow overlay. No confetti overlay. No smoke. No neon effects. No color cast. No excessive lens flare. No artificial bokeh particles crossing the product. No excessive cinematic filter. No fake lens dirt. No fake film grain. No decorative overlay. No magical effects. No frame border. No black bars. Do not add any graphic overlay or visual effect of any kind.',
                'Strict restrictions:',
                'Keep the exact object from the reference image. Preserve exact product identity, shape, color, material, texture and proportions. Preserve the exact product presentation from the reference image. Preserve the exact support, base, hook, display stand, surface, attachment point, and display position if present. Keep the product anchored exactly as shown in the reference image. Do not detach the product from its support or display position. Do not show a hand picking it up, removing it, lifting it, pulling it, hanging it, placing it, attaching it, fitting it, removing it, opening it, using it as a tool, or transforming its usage. No interaction action unless already clearly present in the source image and explicitly needed. Do not change the product. Do not deform the product. Do not morph the product. Do not replace the product. Do not change the product purpose. Do not introduce new objects, extra accessories, extra props or invented functions. Do not create a new product. Do not transform the product. Do not hallucinate bottle opener, tool, hardware, toy, keychain, cake topper, figurine or other usage unless the original product context explicitly says so. No spinning product. No moving body parts. No changing pose. No changing expression. No cinematic transition. No fade to another shot. No logos, no captions, no text, no watermarks, no new people, no hands unless already present in the reference image, no voiceover, no speech, no music, no sound effects, no REC, no HUD, no camera overlay, no viewfinder, no timestamp, no UI, no glitter, no sparkles, no particles, no purple tint, no neon, no confetti, no smoke, no fake dust, no film grain, no lens dirt, no magical effects, no extra props, no extra accessories, no invented elements, no scene cuts inside the 8 second clip.',
            )
        );
    }

    private static function prepare_frame_for_aspect_ratio( array $image, $aspect_ratio ) {
        if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecreatetruecolor' ) ) {
            return self::prepare_frame_with_gd( $image, $aspect_ratio );
        }

        if ( class_exists( 'Imagick' ) ) {
            return self::prepare_frame_with_imagick( $image, $aspect_ratio );
        }

        return self::error( 'IMAGE_PREPROCESSOR_UNAVAILABLE', 'Não foi possível preparar a imagem no formato do vídeo.', 'GD/Imagick indisponível.' );
    }

    private static function frame_dimensions_for_aspect_ratio( $aspect_ratio ) {
        if ( '16:9' === $aspect_ratio ) {
            return array( 1920, 1080 );
        }

        return array( 1080, 1920 );
    }

    private static function prepare_frame_with_gd( array $image, $aspect_ratio ) {
        $source = imagecreatefromstring( $image['binary'] );
        if ( ! $source ) {
            return self::error( 'IMAGE_FETCH_ERROR', 'Não foi possível ler a imagem selecionada.', 'Falha ao decodificar imagem com GD.' );
        }

        $src_w = imagesx( $source );
        $src_h = imagesy( $source );
        list( $target_w, $target_h ) = self::frame_dimensions_for_aspect_ratio( $aspect_ratio );

        $canvas = imagecreatetruecolor( $target_w, $target_h );
        if ( ! $canvas ) {
            imagedestroy( $source );
            return self::error( 'IMAGE_PREPROCESSOR_UNAVAILABLE', 'Não foi possível preparar a imagem no formato do vídeo.', 'Falha ao criar canvas GD.' );
        }

        $crop = self::smart_crop_rect_gd( $source, $src_w, $src_h, $target_w / $target_h );
        imagecopyresampled( $canvas, $source, 0, 0, $crop['x'], $crop['y'], $target_w, $target_h, $crop['w'], $crop['h'] );

        $saved = self::save_prepared_frame_from_gd( $canvas, $aspect_ratio );

        imagedestroy( $source );
        imagedestroy( $canvas );

        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        $saved['processor'] = 'gd';
        return $saved;
    }

    private static function prepare_frame_with_imagick( array $image, $aspect_ratio ) {
        try {
            $source = new Imagick();
            $source->readImageBlob( $image['binary'] );
            $source->setImageColorspace( Imagick::COLORSPACE_RGB );
            $source->setImageAlphaChannel( Imagick::ALPHACHANNEL_REMOVE );
            $source->setImageBackgroundColor( 'white' );

            list( $target_w, $target_h ) = self::frame_dimensions_for_aspect_ratio( $aspect_ratio );

            $source->cropThumbnailImage( $target_w, $target_h );
            $source->setImageFormat( 'jpeg' );
            $source->setImageCompressionQuality( 92 );

            $saved = self::save_prepared_frame_binary( $source->getImagesBlob(), $aspect_ratio, $target_w, $target_h, 'imagick' );

            $source->clear();

            return $saved;
        } catch ( Exception $e ) {
            return self::error( 'IMAGE_PREPROCESSOR_UNAVAILABLE', 'Não foi possível preparar a imagem no formato do vídeo.', sanitize_text_field( $e->getMessage() ) );
        }
    }

    private static function save_prepared_frame_from_gd( $canvas, $aspect_ratio ) {
        list( $target_w, $target_h ) = self::frame_dimensions_for_aspect_ratio( $aspect_ratio );
        ob_start();
        imagejpeg( $canvas, null, 92 );
        $binary = ob_get_clean();

        if ( empty( $binary ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível salvar o frame preparado.', 'Falha ao codificar JPEG.' );
        }

        return self::save_prepared_frame_binary( $binary, $aspect_ratio, $target_w, $target_h, 'gd' );
    }

    private static function smart_crop_rect_gd( $source, $src_w, $src_h, $target_ratio ) {
        $src_ratio = $src_w / max( 1, $src_h );
        if ( $src_ratio > $target_ratio ) {
            $crop_h = $src_h;
            $crop_w = (int) floor( $src_h * $target_ratio );
        } else {
            $crop_w = $src_w;
            $crop_h = (int) floor( $src_w / $target_ratio );
        }

        $focus = self::detect_focus_box_gd( $source, $src_w, $src_h );
        $center_x = $src_w / 2;
        $center_y = $src_h / 2;

        if ( $focus ) {
            $center_x = ( $focus['x1'] + $focus['x2'] ) / 2;
            $center_y = ( $focus['y1'] + $focus['y2'] ) / 2;
            $focus_w = ( $focus['x2'] - $focus['x1'] ) * 1.08;
            $focus_h = ( $focus['y2'] - $focus['y1'] ) * 1.08;

            if ( $crop_w < $focus_w ) {
                $crop_w = min( $src_w, (int) ceil( $focus_w ) );
                $crop_h = (int) ceil( $crop_w / $target_ratio );
            }

            if ( $crop_h < $focus_h ) {
                $crop_h = min( $src_h, (int) ceil( $focus_h ) );
                $crop_w = (int) ceil( $crop_h * $target_ratio );
            }

            if ( $crop_w > $src_w ) {
                $crop_w = $src_w;
                $crop_h = (int) floor( $crop_w / $target_ratio );
            }

            if ( $crop_h > $src_h ) {
                $crop_h = $src_h;
                $crop_w = (int) floor( $crop_h * $target_ratio );
            }
        }

        $crop_w = max( 1, min( $src_w, (int) $crop_w ) );
        $crop_h = max( 1, min( $src_h, (int) $crop_h ) );
        $crop_x = (int) round( $center_x - ( $crop_w / 2 ) );
        $crop_y = (int) round( $center_y - ( $crop_h / 2 ) );
        $crop_x = max( 0, min( $crop_x, $src_w - $crop_w ) );
        $crop_y = max( 0, min( $crop_y, $src_h - $crop_h ) );

        return array(
            'x' => $crop_x,
            'y' => $crop_y,
            'w' => $crop_w,
            'h' => $crop_h,
        );
    }

    private static function detect_focus_box_gd( $source, $src_w, $src_h ) {
        $corner_points = array(
            array( 0, 0 ),
            array( max( 0, $src_w - 1 ), 0 ),
            array( 0, max( 0, $src_h - 1 ) ),
            array( max( 0, $src_w - 1 ), max( 0, $src_h - 1 ) ),
        );
        $bg = array( 'r' => 0, 'g' => 0, 'b' => 0 );
        foreach ( $corner_points as $point ) {
            $rgb = self::gd_pixel_rgba( $source, $point[0], $point[1] );
            $bg['r'] += $rgb['r'];
            $bg['g'] += $rgb['g'];
            $bg['b'] += $rgb['b'];
        }
        $bg['r'] /= 4;
        $bg['g'] /= 4;
        $bg['b'] /= 4;

        $step = max( 1, (int) floor( min( $src_w, $src_h ) / 140 ) );
        $x1 = $src_w;
        $y1 = $src_h;
        $x2 = 0;
        $y2 = 0;
        $hits = 0;
        $samples = 0;

        for ( $y = 0; $y < $src_h; $y += $step ) {
            for ( $x = 0; $x < $src_w; $x += $step ) {
                $samples++;
                $rgb = self::gd_pixel_rgba( $source, $x, $y );
                $distance = abs( $rgb['r'] - $bg['r'] ) + abs( $rgb['g'] - $bg['g'] ) + abs( $rgb['b'] - $bg['b'] );
                $opaque_subject = isset( $rgb['a'] ) && $rgb['a'] < 96 && $distance > 38;
                if ( $distance <= 58 && ! $opaque_subject ) {
                    continue;
                }

                $hits++;
                $x1 = min( $x1, $x );
                $y1 = min( $y1, $y );
                $x2 = max( $x2, $x );
                $y2 = max( $y2, $y );
            }
        }

        if ( $samples <= 0 || $hits < 8 || ( $hits / $samples ) > 0.82 ) {
            return null;
        }

        $pad = max( 8, (int) floor( min( $src_w, $src_h ) * 0.04 ) );
        return array(
            'x1' => max( 0, $x1 - $pad ),
            'y1' => max( 0, $y1 - $pad ),
            'x2' => min( $src_w, $x2 + $pad ),
            'y2' => min( $src_h, $y2 + $pad ),
        );
    }

    private static function gd_pixel_rgba( $source, $x, $y ) {
        $color = imagecolorat( $source, (int) $x, (int) $y );
        return array(
            'a' => ( $color >> 24 ) & 0x7F,
            'r' => ( $color >> 16 ) & 0xFF,
            'g' => ( $color >> 8 ) & 0xFF,
            'b' => $color & 0xFF,
        );
    }

    private static function save_prepared_frame_binary( $binary, $aspect_ratio, $width, $height, $processor ) {
        $location = self::video_upload_location( 'frames/' );
        if ( is_wp_error( $location ) ) {
            return $location;
        }

        $filename = 'stlai-veo-frame-' . wp_generate_uuid4() . '.jpg';
        $path = $location['dir'] . $filename;

        if ( false === file_put_contents( $path, $binary ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível salvar o frame preparado.', 'Falha ao gravar frame jpg.' );
        }

        return array(
            'base64'              => base64_encode( $binary ),
            'mime_type'           => 'image/jpeg',
            'prepared_frame_url'  => $location['url'] . $filename,
            'prepared_frame_path' => $path,
            'prepared_width'      => (int) $width,
            'prepared_height'     => (int) $height,
            'aspect_ratio'        => $aspect_ratio,
            'processor'           => $processor,
        );
    }

    private static function compact_text( $text, $limit ) {
        $text = preg_replace( '/\s+/', ' ', (string) $text );
        $text = trim( $text );

        if ( empty( $text ) ) {
            return '';
        }

        return substr( $text, 0, max( 0, (int) $limit ) );
    }

    private static function create_operation( array $config, array $body, $safe_debug = '' ) {
        $url = $config['base_url'] . '/models/' . rawurlencode( $config['model'] ) . ':predictLongRunning';
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'x-goog-api-key' => $config['api_key'],
                    'Content-Type'   => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return self::decode_json_response( $response, 'VEO_REQUEST_ERROR', 'VEO_HTTP_ERROR', 'VEO_INVALID_RESPONSE', 'Não foi possível iniciar a geração do clipe de teste.', $safe_debug );
    }

    private static function poll_operation( array $config, $operation_name, $safe_debug = '' ) {
        $url = $config['base_url'] . '/' . ltrim( $operation_name, '/' );
        $last_debug = '';

        for ( $i = 0; $i < self::POLL_ATTEMPTS; $i++ ) {
            if ( $i > 0 ) {
                sleep( self::POLL_INTERVAL );
            }

            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'x-goog-api-key' => $config['api_key'],
                    ),
                )
            );

            $decoded = self::decode_json_response( $response, 'VEO_REQUEST_ERROR', 'VEO_HTTP_ERROR', 'VEO_INVALID_RESPONSE', 'Não foi possível consultar o clipe de teste.', $safe_debug );
            if ( is_wp_error( $decoded ) ) {
                return $decoded;
            }

            if ( ! empty( $decoded['error'] ) ) {
                return self::error( 'VEO_HTTP_ERROR', 'O serviço de vídeo retornou erro na operação.', self::join_debug( $safe_debug, self::extract_provider_message( wp_json_encode( $decoded ) ) ) );
            }

            if ( ! empty( $decoded['done'] ) ) {
                return $decoded;
            }

            $last_debug = 'Operação ainda em processamento; tentativa ' . ( $i + 1 ) . ' de ' . self::POLL_ATTEMPTS . '.';
        }

        return self::processing_error( $operation_name, self::join_debug( $safe_debug, $last_debug ), self::POLL_ATTEMPTS );
    }

    private static function poll_operation_once( array $config, $operation_name, $safe_debug = '' ) {
        $url = $config['base_url'] . '/' . ltrim( $operation_name, '/' );
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'x-goog-api-key' => $config['api_key'],
                ),
            )
        );

        $decoded = self::decode_json_response( $response, 'VEO_REQUEST_ERROR', 'VEO_HTTP_ERROR', 'VEO_INVALID_RESPONSE', 'Não foi possível consultar o clipe.', $safe_debug );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        if ( ! empty( $decoded['error'] ) ) {
            return self::error( 'VEO_HTTP_ERROR', 'O serviço de vídeo retornou erro na operação.', self::join_debug( $safe_debug, self::extract_provider_message( wp_json_encode( $decoded ) ) ) );
        }

        if ( ! empty( $decoded['done'] ) ) {
            return $decoded;
        }

        return self::processing_error( $operation_name, self::join_debug( $safe_debug, 'Operação ainda em processamento; polling externo do job.' ), 1 );
    }

    private static function processing_error( $operation_name, $debug, $poll_count ) {
        return new WP_Error(
            'VEO_OPERATION_PROCESSING',
            'O clipe ainda está em processamento.',
            array(
                'debug' => sanitize_text_field( (string) $debug ),
                'operation_id' => sanitize_text_field( (string) $operation_name ),
                'operation_still_processing' => true,
                'operation_poll_count' => (int) $poll_count,
                'retryable' => true,
                'retry_reason' => 'operation_still_processing',
            )
        );
    }

    private static function download_video( array $config, $video_uri ) {
        $response = wp_remote_get(
            esc_url_raw( $video_uri ),
            array(
                'timeout'     => 120,
                'redirection' => 5,
                'headers'     => array(
                    'x-goog-api-key' => $config['api_key'],
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return self::error( 'VEO_REQUEST_ERROR', 'Não foi possível baixar o vídeo gerado.', sanitize_text_field( $response->get_error_message() ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

        if ( 200 !== $status_code ) {
            return self::error( 'VEO_HTTP_ERROR', 'O serviço de vídeo recusou o download do clipe.', 'HTTP ' . $status_code );
        }

        if ( empty( $body ) || false !== strpos( $content_type, 'application/json' ) ) {
            return self::error( 'VEO_INVALID_RESPONSE', 'O serviço de vídeo não retornou um arquivo válido.', 'Content-Type: ' . sanitize_text_field( $content_type ) );
        }

        return $body;
    }

    private static function save_video_file( $video_binary ) {
        $location = self::video_upload_location();
        if ( is_wp_error( $location ) ) {
            return $location;
        }

        $filename = 'stlai-test-clip-' . wp_generate_uuid4() . '.mp4';
        $path = $location['dir'] . $filename;

        if ( false === file_put_contents( $path, $video_binary ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível salvar o vídeo gerado.', 'Falha ao gravar arquivo mp4.' );
        }

        return array(
            'path' => $path,
            'url'  => $location['url'] . $filename,
        );
    }

    private static function strip_audio_if_possible( array $saved_video ) {
        $ffmpeg = self::find_ffmpeg_binary();
        if ( empty( $ffmpeg ) ) {
            return self::error( 'FFMPEG_UNAVAILABLE_AUDIO_NOT_STRIPPED', 'FFmpeg não está disponível para remover o áudio nativo do clipe.', 'Player frontend deve permanecer muted.' );
        }

        $source_path = $saved_video['path'] ?? '';
        if ( empty( $source_path ) || ! file_exists( $source_path ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível remover o áudio do clipe.', 'Arquivo de origem ausente.' );
        }

        $pathinfo = pathinfo( $source_path );
        $muted_path = trailingslashit( $pathinfo['dirname'] ) . $pathinfo['filename'] . '-muted.mp4';
        $cmd = escapeshellarg( $ffmpeg ) . ' -y -i ' . escapeshellarg( $source_path ) . ' -c:v copy -an ' . escapeshellarg( $muted_path ) . ' 2>&1';

        $output = array();
        $exit_code = 1;
        exec( $cmd, $output, $exit_code );

        if ( 0 !== (int) $exit_code || ! file_exists( $muted_path ) || filesize( $muted_path ) <= 0 ) {
            return self::error( 'FFMPEG_AUDIO_STRIP_ERROR', 'Não foi possível remover o áudio nativo do clipe.', 'FFmpeg exit code: ' . (int) $exit_code );
        }

        return array(
            'path' => $muted_path,
            'url'  => preg_replace( '/\.mp4$/', '-muted.mp4', $saved_video['url'] ),
        );
    }

    private static function find_ffmpeg_binary() {
        if ( ! function_exists( 'exec' ) || ! function_exists( 'escapeshellarg' ) ) {
            return '';
        }

        $output = array();
        $exit_code = 1;
        exec( 'command -v ffmpeg 2>/dev/null', $output, $exit_code );
        if ( 0 !== (int) $exit_code || empty( $output[0] ) ) {
            return '';
        }

        return trim( (string) $output[0] );
    }

    private static function video_upload_location( $subdir = '' ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível salvar o vídeo gerado.', sanitize_text_field( $uploads['error'] ) );
        }

        $relative = 'stlai-vision-video/' . ltrim( $subdir, '/' );
        $dir = trailingslashit( $uploads['basedir'] ) . $relative;
        $url = trailingslashit( $uploads['baseurl'] ) . $relative;

        if ( ! wp_mkdir_p( $dir ) ) {
            return self::error( 'VIDEO_SAVE_ERROR', 'Não foi possível salvar o vídeo gerado.', 'Falha ao criar pasta de video.' );
        }

        return array(
            'dir' => trailingslashit( $dir ),
            'url' => trailingslashit( $url ),
        );
    }

    private static function extract_video_uri( array $operation ) {
        $paths = array(
            array( 'response', 'generateVideoResponse', 'generatedSamples', 0, 'video', 'uri' ),
            array( 'response', 'generatedVideos', 0, 'video', 'uri' ),
            array( 'response', 'generated_videos', 0, 'video', 'uri' ),
            array( 'response', 'generateVideoResponse', 'generatedSamples', 0, 'video', 'gcsUri' ),
        );

        foreach ( $paths as $path ) {
            $value = self::array_get_path( $operation, $path );
            if ( is_string( $value ) && ! empty( $value ) ) {
                return $value;
            }
        }

        return '';
    }

    private static function array_get_path( array $data, array $path ) {
        $current = $data;
        foreach ( $path as $key ) {
            if ( is_array( $current ) && array_key_exists( $key, $current ) ) {
                $current = $current[ $key ];
            } else {
                return null;
            }
        }

        return $current;
    }

    private static function decode_json_response( $response, $request_code, $http_code, $invalid_code, $friendly_message, $safe_debug = '' ) {
        if ( is_wp_error( $response ) ) {
            return self::error( $request_code, $friendly_message, self::join_debug( $safe_debug, sanitize_text_field( $response->get_error_message() ) ) );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $debug = 'HTTP ' . $status_code;
            $provider_message = self::extract_provider_message( $body );
            if ( ! empty( $provider_message ) ) {
                $debug .= ' - ' . $provider_message;
            }

            return self::error( $http_code, $friendly_message, self::join_debug( $safe_debug, $debug ) );
        }

        $decoded = json_decode( (string) $body, true );
        if ( ! is_array( $decoded ) ) {
            return self::error( $invalid_code, $friendly_message, self::join_debug( $safe_debug, 'Resposta JSON inválida.' ) );
        }

        return $decoded;
    }

    private static function extract_provider_message( $body ) {
        $decoded = json_decode( (string) $body, true );
        if ( is_array( $decoded ) ) {
            $candidates = array(
                $decoded['error']['message'] ?? '',
                $decoded['message'] ?? '',
                $decoded['detail']['message'] ?? '',
                is_string( $decoded['detail'] ?? null ) ? $decoded['detail'] : '',
            );

            foreach ( $candidates as $candidate ) {
                if ( is_string( $candidate ) && ! empty( $candidate ) ) {
                    return sanitize_text_field( substr( $candidate, 0, 300 ) );
                }
            }
        }

        $body = trim( wp_strip_all_tags( (string) $body ) );
        return empty( $body ) ? '' : sanitize_text_field( substr( $body, 0, 300 ) );
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

    private static function append_error_debug( WP_Error $error, $extra_debug ) {
        $data = $error->get_error_data();
        $debug = is_array( $data ) ? (string) ( $data['debug'] ?? '' ) : '';

        return self::error(
            $error->get_error_code(),
            $error->get_error_message(),
            self::join_debug( $extra_debug, $debug )
        );
    }

    private static function payload_debug( array $config, $aspect_ratio, array $prepared_frame, array $payload = array() ) {
        return sprintf(
            'provider=%s; model=%s; requested_resolution=%s; effective_resolution=%s; resolution_fallback_reason=%s; role=%s; aspectRatio=%s; mimeType=%s; imagePayload=image.bytesBase64Encoded; prepared_frame_url=%s; prepared_frame_path=%s; prepared_width=%d; prepared_height=%d; prepared_processor=%s',
            sanitize_key( (string) ( $config['provider'] ?? self::DEFAULT_PROVIDER ) ),
            sanitize_text_field( (string) ( $config['model'] ?? self::DEFAULT_MODEL ) ),
            sanitize_key( (string) ( $config['requested_resolution'] ?? self::DEFAULT_OUTPUT_RESOLUTION ) ),
            sanitize_key( (string) ( $config['effective_resolution'] ?? self::DEFAULT_OUTPUT_RESOLUTION ) ),
            sanitize_key( (string) ( $config['resolution_fallback_reason'] ?? '' ) ),
            sanitize_key( (string) ( $payload['role'] ?? '' ) ),
            sanitize_text_field( (string) $aspect_ratio ),
            sanitize_text_field( (string) ( $prepared_frame['mime_type'] ?? '' ) ),
            esc_url_raw( (string) ( $prepared_frame['prepared_frame_url'] ?? '' ) ),
            sanitize_text_field( (string) ( $prepared_frame['prepared_frame_path'] ?? '' ) ),
            (int) ( $prepared_frame['prepared_width'] ?? 0 ),
            (int) ( $prepared_frame['prepared_height'] ?? 0 ),
            sanitize_text_field( (string) ( $prepared_frame['processor'] ?? '' ) )
        );
    }

    private static function join_debug( $safe_debug, $detail ) {
        $safe_debug = trim( sanitize_text_field( (string) $safe_debug ) );
        $detail = trim( sanitize_text_field( (string) $detail ) );

        if ( empty( $safe_debug ) ) {
            return $detail;
        }

        if ( empty( $detail ) ) {
            return $safe_debug;
        }

        return $safe_debug . '; ' . $detail;
    }
}
