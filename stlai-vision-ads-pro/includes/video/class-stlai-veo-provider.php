<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STLAI_Veo_Provider {
    const DEFAULT_MODEL = 'veo-3.1-lite-generate-preview';
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
            ),
        );

        $safe_debug = self::payload_debug( $config['model'], $aspect_ratio['value'], $prepared_frame, $payload );

        $operation = self::create_operation( $config, $body, $safe_debug );
        if ( is_wp_error( $operation ) ) {
            return $operation;
        }

        $operation_name = $operation['name'] ?? '';
        if ( empty( $operation_name ) ) {
            return self::error( 'VEO_INVALID_RESPONSE', 'O serviço de vídeo não retornou uma operação válida.', self::join_debug( $safe_debug, 'Campo name ausente na criação da operação.' ) );
        }

        $operation_debug = self::join_debug( $safe_debug, 'operation_id=' . $operation_name );

        $done = self::poll_operation( $config, $operation_name, $operation_debug );
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
            'prepared_frame_url' => $prepared_frame['prepared_frame_url'] ?? '',
            'prepared_frame_path' => $prepared_frame['prepared_frame_path'] ?? '',
            'prepared_frame_width' => (int) ( $prepared_frame['prepared_width'] ?? 0 ),
            'prepared_frame_height' => (int) ( $prepared_frame['prepared_height'] ?? 0 ),
            'aspect_ratio'   => $aspect_ratio['value'],
            'provider'       => 'veo',
            'model'          => $config['model'],
            'operation_id'   => $operation_name,
            'operation_name' => $operation_name,
            'status'         => 'ready',
            'debug'          => $debug,
        );
    }

    private static function validate_config( array $settings ) {
        $api_key = trim( (string) ( $settings['videoApiKey'] ?? '' ) );
        if ( empty( $api_key ) ) {
            return self::error( 'MISSING_VIDEO_API_KEY', 'Configure a Video API Key no painel.' );
        }

        $model = trim( (string) ( $settings['videoModel'] ?? '' ) );
        if ( empty( $model ) ) {
            return self::error( 'MISSING_VIDEO_MODEL', 'Configure o modelo de vídeo no painel.' );
        }

        $base_url = trim( (string) ( $settings['videoBaseUrl'] ?? '' ) );
        if ( empty( $base_url ) ) {
            return self::error( 'MISSING_VIDEO_BASE_URL', 'Configure a Video Base URL no painel.' );
        }

        $base_url = esc_url_raw( untrailingslashit( $base_url ) );
        if ( empty( $base_url ) || ! preg_match( '#^https://#i', $base_url ) ) {
            return self::error( 'MISSING_VIDEO_BASE_URL', 'Configure uma Video Base URL HTTPS válida no painel.' );
        }

        return array(
            'api_key'  => $api_key,
            'model'    => sanitize_text_field( $model ),
            'base_url' => $base_url,
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
        $format_direction = 'Keep the full product visible with safe space around all important edges. Stable camera, natural professional smartphone movement, no scene change, no internal fade.';

        if ( '16:9' === $format ) {
            $format_direction = 'For 16:9 horizontal output, use a wider framing. Center the product with safe space above, below and on both sides. If the product has a character, face, head, top ornament, cake topper shape, keychain ring, base or stand, keep the whole object inside the frame. Start with a medium or wide shot and use only a very gentle zoom in. Avoid close-ups that cut the head, face, top, base or important product details.';
        } elseif ( '9:16' === $format || '1:1' === $format ) {
            $format_direction = 'For 9:16 vertical output, keep the product centered vertically with safe space at the top and base. Use only a light natural zoom or slight parallax and never crop the product top, base, ring, support or display position.';
        }

        $product_line = $product_name ?: 'Decorative 3D printed keychain.';
        if ( ! empty( $product_description ) ) {
            $product_line .= ' Reference context: ' . $product_description;
        }

        $purpose_line = 'Used as a decorative keychain accessory for keys, bags or backpacks. If the product name or description says keychain, always treat it as a keychain only. If any term is ambiguous, prioritize decorative keychain accessory.';
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
                'Use the provided image as the exact full scene and exact product reference. The script is only marketing context. Do not create scenes from the script. Follow the reference image exactly. The selected image has priority over any text context.',
                'Clip role:',
                ( $role_label ?: 'Product visual clip' ) . ( $role_direction ? '. Direction: ' . $role_direction : '' ),
                'Visual direction:',
                'Create one continuous 8-second silent, visual-only product video from the same image. Single continuous shot. No scene changes. No cuts. No internal transitions. No fade inside the clip. No before/after. No montage. No new location. Do not create a new scene. Do not change the background. Do not change the composition. Do not cut to another shot. Do not fade to another scene. Do not transition inside the clip. Keep the product mostly still, like a realistic high-quality smartphone product recording. Only add subtle camera movement, gentle handheld feel, slow zoom in or slow zoom out, and slight natural parallax. Avoid exaggerated animation.',
                'Framing and safe area:',
                'Keep the full product visible during most of the clip. Do not crop the head, face, top, base, support, ring, hook, stand, surface or any important product detail. Use stable camera movement, light natural motion, and a professional smartphone product-recording feel. ' . $format_direction,
                'Product anchoring rules:',
                'Preserve the exact product presentation from the reference image. Do not detach the product from its support or display position. Do not show a hand picking it up, removing it, lifting it, hanging it, placing it, or transforming its usage. Keep the product anchored exactly as shown in the reference image. No interaction action unless already clearly present in the source image.',
                'Strict restrictions:',
                'Keep the exact object from the reference image. Preserve exact product identity, shape, color, material, texture and proportions. Preserve the exact product presentation from the reference image. Preserve the exact support, base, hook, display stand, surface, attachment point, and display position if present. Keep the product anchored exactly as shown in the reference image. Do not detach the product from its support or display position. Do not show a hand picking it up, removing it, lifting it, pulling it, hanging it, placing it, attaching it, fitting it, or transforming its usage. No interaction action unless already clearly present in the source image. Do not change the product. Do not change the product purpose. Do not introduce new objects. Do not create a new product. Do not transform the product. Do not invent any new function. If the reference image shows a keychain, it must remain only a decorative keychain. It is a keychain/accessory only. It is not a cake topper. It is not a bottle opener. It is not a tool. It is not a toy. It is not a figurine for decoration unless the original product context says so. Keep the keychain ring/hole as part of the keychain design only. No spinning product. No moving body parts. No changing pose. No changing expression. No cinematic transition. No fade to another shot. No logos, no captions, no text, no watermarks, no people, no hands unless already present in the reference image, no voiceover, no speech, no music, no sound effects.',
            )
        );
    }

    private static function prepare_frame_for_aspect_ratio( array $image, $aspect_ratio ) {
        if ( class_exists( 'Imagick' ) ) {
            return self::prepare_frame_with_imagick( $image, $aspect_ratio );
        }

        if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecreatetruecolor' ) ) {
            return self::prepare_frame_with_gd( $image, $aspect_ratio );
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

        $white = imagecolorallocate( $canvas, 255, 255, 255 );
        imagefilledrectangle( $canvas, 0, 0, $target_w, $target_h, $white );

        $safe_w = (int) floor( $target_w * 0.9 );
        $safe_h = (int) floor( $target_h * 0.9 );
        $scale = min( $safe_w / $src_w, $safe_h / $src_h );
        $draw_w = max( 1, (int) floor( $src_w * $scale ) );
        $draw_h = max( 1, (int) floor( $src_h * $scale ) );
        $draw_x = (int) floor( ( $target_w - $draw_w ) / 2 );
        $draw_y = (int) floor( ( $target_h - $draw_h ) / 2 );

        imagecopyresampled( $canvas, $source, $draw_x, $draw_y, 0, 0, $draw_w, $draw_h, $src_w, $src_h );

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

            $source->thumbnailImage( (int) floor( $target_w * 0.9 ), (int) floor( $target_h * 0.9 ), true );
            $source->setImageBackgroundColor( 'white' );
            $source->setImagePage( 0, 0, 0, 0 );
            $x = (int) floor( ( $target_w - $source->getImageWidth() ) / 2 );
            $y = (int) floor( ( $target_h - $source->getImageHeight() ) / 2 );
            $source->extentImage( $target_w, $target_h, -$x, -$y );
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

        return self::error( 'VEO_OPERATION_TIMEOUT', 'O clipe de teste ainda está em processamento. Tente novamente em alguns instantes.', self::join_debug( $safe_debug, $last_debug ) );
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

    private static function payload_debug( $model, $aspect_ratio, array $prepared_frame, array $payload = array() ) {
        return sprintf(
            'model=%s; role=%s; aspectRatio=%s; mimeType=%s; imagePayload=image.bytesBase64Encoded; prepared_frame_url=%s; prepared_frame_path=%s; prepared_width=%d; prepared_height=%d; prepared_processor=%s',
            sanitize_text_field( (string) $model ),
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
