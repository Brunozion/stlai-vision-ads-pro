<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'stlai_vision_ads_pro_admin_menu' );
add_action( 'admin_init', 'stlai_vision_ads_pro_settings_init' );

function stlai_vision_ads_pro_admin_menu() {
    // Menu Principal (Dashboard)
    add_menu_page(
        'STLAI Vision Ads',
        'STLAI Vision Ads',
        'manage_options',
        'stlai_vision_ads_pro',
        'stlai_dashboard_page',
        'dashicons-visibility',
        30
    );
    
    // Submenus
    add_submenu_page('stlai_vision_ads_pro', 'Dashboard', 'Dashboard', 'manage_options', 'stlai_vision_ads_pro', 'stlai_dashboard_page');
    add_submenu_page('stlai_vision_ads_pro', 'Config. de IA', 'Config. de IA', 'manage_options', 'stlai_config_ia', 'stlai_config_ia_page');
    add_submenu_page('stlai_vision_ads_pro', 'Prompts', 'Prompts', 'manage_options', 'stlai_prompts', 'stlai_prompts_page');
    add_submenu_page('stlai_vision_ads_pro', 'Planos', 'Planos', 'manage_options', 'stlai_planos', 'stlai_planos_page');
    add_submenu_page('stlai_vision_ads_pro', 'Teste de Geração', 'Teste de Geração', 'manage_options', 'stlai_teste', 'stlai_teste_page');
    add_submenu_page('stlai_vision_ads_pro', 'Logs', 'Logs', 'manage_options', 'stlai_logs', 'stlai_logs_page');
}

function stlai_default_video_clip_generation_prompt() {
    return "Create an 8-second commercial AI video clip from the selected product image.\n\nProduct: {{product_name}}\nContext: {{product_context}}\nAspect ratio: {{aspect_ratio}}\nClip role: {{clip_label}} ({{clip_role}})\nNarration style: {{narration_style}}\nTone: {{tone}}\nTarget audience: {{target_audience}}\nImage notes: {{image_description}}\n\nPreserve the product exactly as shown. Animate the whole environment naturally with subtle realistic motion. The clip must look like a real commercial product recording, not a static photo with only camera movement.\n\nNegative prompt:\n{{negative_prompt}}";
}

function stlai_default_video_clip_generation_prompt_vertical() {
    return "Create a vertical 9:16 commercial video clip from the selected source image.\n\nProduct: {{product_name}}\nContext: {{product_context}}\nClip {{clip_index}}: {{clip_label}} ({{clip_role}})\nDirection: {{image_description}}\nNarration style: {{narration_style}}\nTone: {{tone}}\n\nUse the uploaded source image as the strict visual reference for the product. Preserve the product exactly as shown: same colors, complete face, eyes, hair, clothing, pose, proportions, material, base and identity. The first frame must show the full product clearly, with no cropped head, body or base. Animate the full environment with subtle realistic motion so the scene feels like a real product filming, while the product remains stable, sharp and unchanged. Use natural eye-level or slight three-quarter camera movement; avoid top-down motion and aggressive zoom.\n\nNegative prompt:\n{{negative_prompt}}";
}

function stlai_default_video_clip_generation_prompt_horizontal() {
    return "Create a horizontal 16:9 commercial video from the provided square source image.\n\nProduct: {{product_name}}\nContext: {{product_context}}\nClip {{clip_index}}: {{clip_label}} ({{clip_role}})\nDirection: {{image_description}}\nNarration style: {{narration_style}}\nTone: {{tone}}\n\nThe uploaded image is the strict visual reference for the product. Preserve the exact product identity with absolute fidelity. The product must remain exactly the same as the source image in all frames: same complete face, same eyes, same hair color, same hairstyle, same facial features, same skin tone, same clothing, same pose, same proportions, same base, same cake, same materials, same colors and same overall look.\n\nDo not redesign or reinterpret the product. Do not create a different version of the couple, character, figurine or item. Do not alter the character design in any way. Do not remove eyes, simplify the face, distort facial features or make the characters look unfinished.\n\nStart with a composition wide enough to show the product clearly and fully. Do not crop the top, body, base or important details in the opening frame. If necessary, pull the camera back to keep the full product visible before any motion begins.\n\nUse a realistic product-filming style, as if a person is naturally filming the product in a real scene. Prefer eye-level or slight three-quarter product angles. Avoid top-down camera moves, strong overhead angles, aggressive push-ins at the start and synthetic zoom-only motion. Use subtle handheld-like or dolly-like camera motion with realistic environmental movement.\n\nKeep the wedding atmosphere elegant, soft and believable. Avoid overloading the scene with excessive candles or unnecessary decorative props. Use subtle, tasteful wedding context only when visually coherent.\n\nNegative prompt:\n{{negative_prompt}}";
}

function stlai_default_ugc_prompt( $key ) {
    $defaults = array(
        'ugc_prompt_social' => 'Create an authentic UGC-style social media video featuring {{product_name}}. The video should feel like a real creator casually filming the product in a natural setting. Show the product clearly, with handheld-style motion, realistic lighting, and a believable lifestyle context. Focus on why someone would want to use or buy it. Keep it natural, not overly polished.',
        'ugc_prompt_tutorial' => 'Create a step-by-step tutorial-style video showing how {{product_name}} is used. Keep the product clear and visible. Use natural hand movement or product handling when appropriate, but do not change the product. The video should feel practical, helpful and easy to understand.',
        'ugc_prompt_unboxing' => 'Create a realistic unboxing-style video for {{product_name}}. Show the product being revealed or presented as if someone just received it. Focus on first impression, packaging feel, product reveal and visual appeal. Keep the product consistent with the reference image.',
        'ugc_prompt_product_review' => 'Create an authentic product review-style video for {{product_name}}. The video should feel like a creator showing the product to the camera, highlighting real benefits, details and usage impressions. Keep the tone natural and trustworthy.',
        'ugc_prompt_virtual_try_on' => 'Create a realistic virtual try-on or demonstration-style video for {{product_name}}. Show the product being experienced, worn, tested or demonstrated in context when appropriate. Preserve the product exactly. Make the scene feel like user-generated content.',
        'ugc_prompt_hyper_motion' => 'Create a high-energy product video for {{product_name}} with dynamic but realistic movement. Highlight the product with fast visual rhythm, smooth camera motion and strong product focus. Preserve the product exactly. Avoid unrealistic effects.',
        'ugc_prompt_tv_spot' => 'Create a polished commercial TV spot-style video for {{product_name}}. Use cinematic product framing, clear benefit-driven storytelling and premium movement. Keep the product visually consistent with the reference image.',
        'ugc_prompt_wild_card' => 'Create a creative and unexpected product video for {{product_name}}. The concept should feel original and scroll-stopping, while keeping the product accurate and recognizable. Do not alter the product.',
        'ugc_prompt_pro_virtual_try_on' => 'Create a premium virtual try-on or product demonstration video for {{product_name}}. The product should be shown in a polished, professional, high-quality context. Preserve product details and make the result feel commercially usable.',
    );
    return $defaults[ $key ] ?? $defaults['ugc_prompt_social'];
}

function stlai_vision_ads_pro_settings_init() {
    // Registra a configuração com callback de merge para evitar perda de dados em formulários parciais (páginas diferentes)
    register_setting( 'stlai_settings_group', 'stlai_vision_ads_pro_settings', array('sanitize_callback' => 'stlai_vision_ads_pro_sanitize_settings') );

    // ======== PÁGINA: CONFIG DE IA ========
    add_settings_section('stlai_config_api_section', 'Configurações de API e Modelos', '__return_empty_string', 'stlai_config_ia_page');
    
    add_settings_field('txtApi', 'IA para Textos', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'txtApi', 'options' => array('openai' => 'OpenAI', 'gemini' => 'Google Gemini')));
    add_settings_field('imgApi', 'IA para Imagens', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'imgApi', 'options' => array('openai' => 'OpenAI', 'gemini' => 'Google Gemini')));
    
    add_settings_field('apiKey', 'OpenAI API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'apiKey'));
    add_settings_field('url', 'OpenAI Endpoint URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'url'));
    add_settings_field('textModel', 'OpenAI Modelo Texto', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'textModel'));
    add_settings_field('imageModel', 'OpenAI Modelo Imagem', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'imageModel'));
    add_settings_field('imageQuality', 'Quality', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'imageQuality', 'default' => 'auto', 'options' => array('auto' => 'Auto', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa')));
    add_settings_field('imgQuality', 'Qualidade de Imagem OpenAI', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'imgQuality'));
    add_settings_field('imgResolution', 'Resolução de Imagem (OpenAI/Gemini)', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'imgResolution', 'placeholder' => 'ex: 1024x1024, 1k, 2k'));

    add_settings_field('geminiKey', 'Gemini API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'geminiKey'));
    add_settings_field('geminiUrl', 'Gemini Endpoint URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'geminiUrl'));
    add_settings_field('geminiTextModel', 'Gemini Modelo Texto', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'geminiTextModel'));
    add_settings_field('geminiImageModel', 'Gemini Modelo Imagem', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_api_section', array('id' => 'geminiImageModel'));
    
    // ======== PÁGINA: CONFIG DE IA (APIs DE MERCADO) ========
    add_settings_section('stlai_config_market_section', 'APIs de Inteligência de Mercado', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('serpapiKey', 'SerpApi Key (Google Shopping)', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_market_section', array('id' => 'serpapiKey'));
    add_settings_field('mlAppId', 'Mercado Livre App ID', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_market_section', array('id' => 'mlAppId'));
    add_settings_field('mlSecretKey', 'Mercado Livre Secret Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_market_section', array('id' => 'mlSecretKey'));

    // ======== PAGINA: CONFIG DE IA (VIDEO COMERCIAL) ========
    add_settings_section('stlai_config_video_section', 'Configuracoes de Video Comercial', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('commercialVideoOutputResolution', 'Resolução de saída dos vídeos comerciais', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'commercialVideoOutputResolution', 'default' => '720p', 'options' => array('720p' => '720p', '1080p' => '1080p'), 'description' => '720p é mais rápido e econômico. 1080p melhora qualidade, mas pode demorar mais e custar mais.'));
    add_settings_field('videoApiKey', 'Video API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoApiKey'));
    add_settings_field('videoBaseUrl', 'Video Base URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoBaseUrl', 'placeholder' => 'Endpoint/base URL da API de video'));
    add_settings_field('ffmpegPath', 'Caminho do FFmpeg', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'ffmpegPath', 'placeholder' => 'Auto detectar, /usr/bin/ffmpeg ou /usr/local/bin/ffmpeg', 'description' => 'Necessário para compor o vídeo final com clipes, fade e narração. Se vazio, o plugin tentará detectar automaticamente.'));

    add_settings_section('stlai_config_commercial_video_provider_section', 'Provider de Vídeo Comercial', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('commercialVideoProvider', 'Provider', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_commercial_video_provider_section', array('id' => 'commercialVideoProvider', 'default' => 'gemini_veo', 'options' => array('gemini_veo' => 'Gemini Veo', 'fal_ai' => 'Fal.ai', 'atlas_cloud' => 'Atlas Cloud', 'muapi' => 'MuAPI', 'custom' => 'Custom'), 'description' => 'No MVP, Gemini Veo está funcional. Os demais providers ficam salvos para uso futuro.'));
    add_settings_field('commercialVideoModel', 'Modelo', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_commercial_video_provider_section', array('id' => 'commercialVideoModel', 'default' => 'veo-3.1-lite-generate-preview', 'options' => array('veo-3.1-lite-generate-preview' => 'veo-3.1-lite-generate-preview (Lite)', 'veo-3.1-fast-generate-preview' => 'veo-3.1-fast-generate-preview (Fast)', 'veo-3.1-generate-preview' => 'veo-3.1-generate-preview', 'veo-2.0-generate-001' => 'veo-2.0-generate-001'), 'description' => 'Lite é mais barato/rápido; Fast é equilíbrio; Generate tende a maior qualidade/custo.'));
    add_settings_field('commercialVideoCustomEndpoint', 'Custom Endpoint', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_commercial_video_provider_section', array('id' => 'commercialVideoCustomEndpoint', 'placeholder' => 'https://api.seuprovedor.com/video'));
    add_settings_field('commercialVideoCustomApiKey', 'Custom API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_commercial_video_provider_section', array('id' => 'commercialVideoCustomApiKey', 'description' => 'Usado apenas quando o provider customizado estiver implementado.'));
    add_settings_field('commercialVideoCustomModel', 'Custom Model', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_commercial_video_provider_section', array('id' => 'commercialVideoCustomModel', 'placeholder' => 'modelo-custom'));

    // ======== PAGINA: CONFIG DE IA (COMPOSICAO DE VIDEO) ========
    add_settings_section('stlai_config_video_composer_section', 'Configuração de Composição de Vídeo', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('videoComposerMode', 'Modo de Composição', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_video_composer_section', array('id' => 'videoComposerMode', 'default' => 'external_service', 'options' => array('external_service' => 'Serviço externo', 'local_ffmpeg' => 'FFmpeg local')));
    add_settings_field('videoComposerEndpoint', 'Endpoint do Serviço de Composição', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_composer_section', array('id' => 'videoComposerEndpoint', 'placeholder' => 'https://video-render.seudominio.com/render'));
    add_settings_field('videoComposerApiKey', 'API Key do Serviço de Composição', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_video_composer_section', array('id' => 'videoComposerApiKey'));
    add_settings_field('videoComposerTimeout', 'Timeout da Composição', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_composer_section', array('id' => 'videoComposerTimeout', 'default' => '300'));

    // ======== PAGINA: CONFIG DE IA (NARRACAO / ELEVENLABS) ========
    add_settings_section('stlai_config_audio_section', 'Configuracoes de Narracao / ElevenLabs', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('audioProvider', 'Provider de Audio', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'audioProvider', 'default' => 'elevenlabs', 'options' => array('elevenlabs' => 'ElevenLabs')));
    add_settings_field('elevenLabsApiKey', 'ElevenLabs API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'elevenLabsApiKey'));
    add_settings_field('elevenLabsVoiceEmotional', 'Voice ID Emocional', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'elevenLabsVoiceEmotional', 'description' => 'Voice ID para narracao emocional'));
    add_settings_field('elevenLabsVoicePersuasive', 'Voice ID Persuasiva', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'elevenLabsVoicePersuasive', 'description' => 'Voice ID para narracao persuasiva'));
    add_settings_field('elevenLabsModel', 'Modelo ElevenLabs', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'elevenLabsModel', 'default' => 'eleven_multilingual_v2'));
    add_settings_field('elevenLabsDefaultLanguage', 'Idioma Padrao ElevenLabs', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_audio_section', array('id' => 'elevenLabsDefaultLanguage', 'default' => 'pt-BR'));

    // ======== PAGINA: CONFIG DE IA (UGC FUTURO) ========
    add_settings_section('stlai_config_ugc_section', 'Configuracoes Futuras de UGC', '__return_empty_string', 'stlai_config_ia_page');
    add_settings_field('ugcProvider', 'Provider UGC', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'ugcProvider', 'default' => 'none', 'options' => array('none' => 'Desativado', 'seedance' => 'Seedance 2.0 / BytePlus ModelArk', 'muapi' => 'MuAPI')));
    add_settings_field('seedanceApiKey', 'Seedance API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'seedanceApiKey'));
    add_settings_field('seedanceBaseUrl', 'Seedance Base URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'seedanceBaseUrl'));
    add_settings_field('seedanceModel', 'Seedance Model', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'seedanceModel'));
    add_settings_field('muApiKey', 'MuAPI Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'muApiKey'));
    add_settings_field('muApiBaseUrl', 'MuAPI Base URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'muApiBaseUrl'));
    add_settings_field('muApiModel', 'MuAPI Model', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'muApiModel', 'default' => 'seedance-2.0-image-to-video'));
    add_settings_field('ugcDefaultDuration', 'UGC Default Duration', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'ugcDefaultDuration', 'default' => '9', 'options' => array('5' => '5s', '8' => '8s', '9' => '9s', '10' => '10s')));
    add_settings_field('ugcDefaultResolution', 'UGC Default Resolution', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'ugcDefaultResolution', 'default' => '720p', 'options' => array('720p' => '720p', '1080p' => '1080p')));
    add_settings_field('ugcDefaultAspectRatio', 'UGC Default Aspect Ratio', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_ugc_section', array('id' => 'ugcDefaultAspectRatio', 'default' => '9:16', 'options' => array('9:16' => '9:16', '16:9' => '16:9', '1:1' => '1:1')));

    // ======== PÁGINA: PROMPTS ========
    add_settings_section('stlai_prompts_base_section', 'Prompts Base', '__return_empty_string', 'stlai_prompts_page');
    add_settings_field('textPrompt', 'Prompt Base de Textos', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_base_section', array('id' => 'textPrompt'));
    add_settings_field('imagePrompt', 'Prompt Base de Imagens', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_base_section', array('id' => 'imagePrompt'));
    
    add_settings_section('stlai_prompts_adv_section', 'Prompts Avançados (Sistema)', '__return_empty_string', 'stlai_prompts_page');
    add_settings_field('promptVision', 'Vision (Leitura Produto)', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptVision'));
    add_settings_field('promptMarket', 'Análise Concorrência', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptMarket'));
    add_settings_field('promptDims', 'Imagem de Medidas', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptDims'));
    add_settings_field('promptBatch1', 'Lote 1 (Básico)', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptBatch1'));
    add_settings_field('promptBatch2', 'Lote 2 (Premium)', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptBatch2'));
    add_settings_field('promptBatchMore', 'Lote Extra (+4)', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptBatchMore'));
    add_settings_field('promptFallback', 'Fallback Anti-Bloqueio', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_prompts_adv_section', array('id' => 'promptFallback'));
    add_settings_field(
        'video_clip_generation_prompt_vertical',
        'Vídeo — Clipes IA 9:16',
        'stlai_render_textarea_field',
        'stlai_prompts_page',
        'stlai_prompts_adv_section',
        array(
            'id' => 'video_clip_generation_prompt_vertical',
            'rows' => 12,
            'default' => stlai_default_video_clip_generation_prompt_vertical(),
            'description' => 'Prompt usado para gerar clipes comerciais verticais 9:16 a partir da imagem escolhida. Cada clipe usa a imagem escolhida como referência visual obrigatória. Placeholders: {{clip_index}}, {{product_name}}, {{product_context}}, {{aspect_ratio}}, {{clip_role}}, {{clip_label}}, {{narration_style}}, {{tone}}, {{target_audience}}, {{image_description}}, {{negative_prompt}}.',
        )
    );
    add_settings_field(
        'video_clip_generation_prompt_horizontal',
        'Vídeo — Clipes IA 16:9',
        'stlai_render_textarea_field',
        'stlai_prompts_page',
        'stlai_prompts_adv_section',
        array(
            'id' => 'video_clip_generation_prompt_horizontal',
            'rows' => 14,
            'default' => stlai_default_video_clip_generation_prompt_horizontal(),
            'description' => 'Prompt usado para gerar clipes comerciais horizontais 16:9 a partir da imagem escolhida. Mais rígido para impedir alteração de rosto, cabelo, cor, acabamento, proporções e identidade do produto. Placeholders: {{clip_index}}, {{product_name}}, {{product_context}}, {{aspect_ratio}}, {{clip_role}}, {{clip_label}}, {{narration_style}}, {{tone}}, {{target_audience}}, {{image_description}}, {{negative_prompt}}.',
        )
    );
    add_settings_field(
        'video_clip_generation_prompt',
        'Vídeo — Clipes IA',
        'stlai_render_textarea_field',
        'stlai_prompts_page',
        'stlai_prompts_adv_section',
        array(
            'id' => 'video_clip_generation_prompt',
            'rows' => 12,
            'default' => stlai_default_video_clip_generation_prompt(),
            'description' => 'Prompt usado para transformar imagens selecionadas em clipes comerciais com IA. Placeholders: {{product_name}}, {{product_context}}, {{aspect_ratio}}, {{clip_role}}, {{clip_label}}, {{narration_style}}, {{tone}}, {{target_audience}}, {{image_description}}, {{negative_prompt}}.',
        )
    );

    add_settings_section('stlai_prompts_ugc_section', 'UGC — Vídeos', '__return_empty_string', 'stlai_prompts_page');
    $ugc_prompt_fields = array(
        'ugc_prompt_social' => array('UGC — Vídeo Social', 'Prompt para vídeo realista estilo criador de conteúdo.'),
        'ugc_prompt_tutorial' => array('UGC — Tutorial', 'Prompt para vídeo demonstrativo passo a passo.'),
        'ugc_prompt_unboxing' => array('UGC — Unboxing', 'Prompt para abertura e primeira impressão do produto.'),
        'ugc_prompt_product_review' => array('UGC — Product Review', 'Prompt para review autêntico do produto.'),
        'ugc_prompt_virtual_try_on' => array('UGC — Virtual Try On', 'Prompt para demonstração estilo provador virtual.'),
        'ugc_prompt_hyper_motion' => array('Comercial — Hyper Motion', 'Prompt para vídeo com movimento forte e foco no produto.'),
        'ugc_prompt_tv_spot' => array('Comercial — TV Spot', 'Prompt para anúncio comercial narrativo.'),
        'ugc_prompt_wild_card' => array('Comercial — Wild Card', 'Prompt para ideia criativa e inesperada.'),
        'ugc_prompt_pro_virtual_try_on' => array('Comercial — Pro Virtual Try On', 'Prompt para demonstração premium do produto.'),
    );
    foreach ( $ugc_prompt_fields as $field_id => $field ) {
        add_settings_field(
            $field_id,
            $field[0],
            'stlai_render_textarea_field',
            'stlai_prompts_page',
            'stlai_prompts_ugc_section',
            array(
                'id' => $field_id,
                'rows' => 5,
                'default' => stlai_default_ugc_prompt( $field_id ),
                'description' => $field[1] . ' Placeholders: {{product_name}}, {{product_description}}, {{product_context}}, {{target_audience}}, {{tone}}, {{language}}, {{aspect_ratio}}, {{duration}}, {{resolution}}, {{image_url}}, {{selected_image_label}}, {{benefits}}, {{features}}, {{marketplace_context}}.',
            )
        );
    }

    add_settings_section('stlai_scenes_section', 'Configurações de Cenas Individuais', '__return_empty_string', 'stlai_prompts_page');
    add_settings_field('sceneCapa', 'Capa', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneCapa', 'rows' => 3));
    add_settings_field('sceneAmb1', 'Ambientada 1', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneAmb1', 'rows' => 3));
    add_settings_field('sceneAmb2', 'Ambientada 2', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneAmb2', 'rows' => 3));
    add_settings_field('sceneAmb3', 'Ambientada 3', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneAmb3', 'rows' => 3));
    add_settings_field('sceneAmb4', 'Ambientada 4', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneAmb4', 'rows' => 3));
    add_settings_field('sceneDetail', 'Detalhe', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneDetail', 'rows' => 3));
    add_settings_field('sceneFeature', 'Benefício', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneFeature', 'rows' => 3));
    add_settings_field('sceneHero', 'Hero', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneHero', 'rows' => 3));
    add_settings_field('sceneDims', 'Cena Medidas', 'stlai_render_textarea_field', 'stlai_prompts_page', 'stlai_scenes_section', array('id' => 'sceneDims', 'rows' => 3));
}

// Callback para merge do array (evita deletar configurações não presentes na página atual ao salvar)
function stlai_vision_ads_pro_sanitize_settings($input) {
    $existing = get_option('stlai_vision_ads_pro_settings', array());
    if (!is_array($existing)) {
        $existing = array();
    }
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            if ('imageQuality' === $key) {
                $allowed = array('auto', 'high', 'medium', 'low');
                $value = sanitize_key($value);
                if (!in_array($value, $allowed, true)) {
                    $value = 'auto';
                }
                $existing['imageQuality'] = $value;
                $existing['imgQuality'] = $value;
                continue;
            }
            if ('commercialVideoOutputResolution' === $key) {
                $value = sanitize_key($value);
                $existing[$key] = in_array($value, array('720p', '1080p'), true) ? $value : '720p';
                continue;
            }
            if ('commercialVideoProvider' === $key) {
                $value = sanitize_key($value);
                $existing[$key] = in_array($value, array('gemini_veo', 'fal_ai', 'atlas_cloud', 'muapi', 'custom'), true) ? $value : 'gemini_veo';
                continue;
            }
            if ('commercialVideoModel' === $key) {
                $allowed = array('veo-3.1-lite-generate-preview', 'veo-3.1-fast-generate-preview', 'veo-3.1-generate-preview', 'veo-2.0-generate-001');
                $value = sanitize_text_field($value);
                $existing[$key] = in_array($value, $allowed, true) ? $value : 'veo-3.1-lite-generate-preview';
                $existing['videoModel'] = $existing[$key];
                continue;
            }
            if ('commercialVideoCustomEndpoint' === $key) {
                $existing[$key] = esc_url_raw($value);
                continue;
            }
            if (in_array($key, array('commercialVideoCustomApiKey', 'commercialVideoCustomModel'), true)) {
                $existing[$key] = sanitize_text_field($value);
                continue;
            }
            if (in_array($key, array('video_clip_generation_prompt', 'video_clip_generation_prompt_vertical', 'video_clip_generation_prompt_horizontal'), true)) {
                $existing[$key] = sanitize_textarea_field($value);
                continue;
            }
            if (in_array($key, array('ugc_prompt_social', 'ugc_prompt_tutorial', 'ugc_prompt_unboxing', 'ugc_prompt_product_review', 'ugc_prompt_virtual_try_on', 'ugc_prompt_hyper_motion', 'ugc_prompt_tv_spot', 'ugc_prompt_wild_card', 'ugc_prompt_pro_virtual_try_on'), true)) {
                $existing[$key] = sanitize_textarea_field($value);
                continue;
            }
            if ('ugcProvider' === $key) {
                $value = sanitize_key($value);
                $existing[$key] = in_array($value, array('none', 'seedance', 'muapi'), true) ? $value : 'none';
                continue;
            }
            if ('muApiBaseUrl' === $key) {
                $existing[$key] = esc_url_raw($value);
                continue;
            }
            if ('muApiModel' === $key) {
                $existing[$key] = sanitize_text_field($value);
                continue;
            }
            if ('ugcDefaultDuration' === $key) {
                $value = sanitize_key($value);
                $existing[$key] = in_array($value, array('5', '8', '9', '10'), true) ? $value : '9';
                continue;
            }
            if ('ugcDefaultResolution' === $key) {
                $value = sanitize_key($value);
                $existing[$key] = in_array($value, array('720p', '1080p'), true) ? $value : '720p';
                continue;
            }
            if ('ugcDefaultAspectRatio' === $key) {
                $value = sanitize_text_field($value);
                $existing[$key] = in_array($value, array('9:16', '16:9', '1:1'), true) ? $value : '9:16';
                continue;
            }
            $existing[$key] = $value;
        }
    }
    return $existing;
}

// Campos Render Functions
function stlai_render_text_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : ($args['default'] ?? '');
    $placeholder = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';
    echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" class="regular-text" style="width:100%; max-width:500px;" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $val ) . '"' . $placeholder . '>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function stlai_render_password_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : '';
    $placeholder = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';
    echo '<div class="stlai-secret-field">';
    echo '<input type="password" id="' . esc_attr( $args['id'] ) . '" class="regular-text stlai-secret-input" autocomplete="off" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $val ) . '"' . $placeholder . '>';
    echo '<button type="button" class="stlai-secret-toggle" data-stlai-secret-toggle aria-label="Mostrar chave" aria-pressed="false">';
    echo '<svg class="stlai-secret-toggle__icon stlai-secret-toggle__icon--show" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5c5.1 0 8.7 4.1 10 7-1.3 2.9-4.9 7-10 7S3.3 14.9 2 12c1.3-2.9 4.9-7 10-7Zm0 2C8.3 7 5.5 9.6 4.3 12c1.2 2.4 4 5 7.7 5s6.5-2.6 7.7-5C18.5 9.6 15.7 7 12 7Zm0 2.5A2.5 2.5 0 1 1 12 14a2.5 2.5 0 0 1 0-5Z"/></svg>';
    echo '<svg class="stlai-secret-toggle__icon stlai-secret-toggle__icon--hide" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="m3.3 2 18.7 18.7-1.3 1.3-3.1-3.1A10.6 10.6 0 0 1 12 20C6.9 20 3.3 15.9 2 13c.7-1.6 2.2-3.5 4.2-4.9L2 3.3 3.3 2Zm4.4 7.6A11.1 11.1 0 0 0 4.3 13c1.2 2.4 4 5 7.7 5 1.5 0 2.9-.4 4-1.1l-2.1-2.1A3.5 3.5 0 0 1 10.2 11L7.7 9.6ZM12 6c5.1 0 8.7 4.1 10 7a12 12 0 0 1-2.8 3.9l-1.4-1.4A10.7 10.7 0 0 0 19.7 13c-1.2-2.4-4-5-7.7-5-.8 0-1.6.1-2.3.4L8.1 6.8A10.3 10.3 0 0 1 12 6Zm0 3.5A3.5 3.5 0 0 1 15.5 13v.2L11.8 9.5h.2Z"/></svg>';
    echo '</button>';
    echo '</div>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function stlai_render_textarea_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) && '' !== (string) $options[$args['id']] ? $options[$args['id']] : ($args['default'] ?? '');
    $rows = isset($args['rows']) ? $args['rows'] : 8;
    echo '<textarea id="' . esc_attr( $args['id'] ) . '" class="large-text" style="width:100%; max-width:800px;" rows="' . esc_attr($rows) . '" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']">' . esc_textarea( $val ) . '</textarea>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function stlai_render_select_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : ($args['default'] ?? '');
    echo '<select id="' . esc_attr( $args['id'] ) . '" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']">';
    foreach ( $args['options'] as $k => $v ) {
        echo '<option value="' . esc_attr($k) . '" ' . selected( $val, $k, false ) . '>' . esc_html($v) . '</option>';
    }
    echo '</select>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

// ==========================================
// PÁGINAS DO MENU ADMIN
// ==========================================

function stlai_dashboard_page() {
    ?>
    <div class="wrap">
        <h1>STLAI Vision Ads Pro - Dashboard</h1>
        
        <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.04); max-width:800px; margin-top:20px;">
            <h2 style="margin-top:0; color:#1d2327;">Bem-vindo ao STLAI Vision Ads Pro 🚀</h2>
            <p style="font-size:15px; color:#50575e; line-height:1.6;">O seu plugin está ativo e pronto para gerar anúncios incriveis e imagens comerciais inteligentes para e-commerce usando IA de ponta.</p>
            
            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e4e7;">
            
            <h3 style="color:#1d2327; font-size:18px;">Como usar o aplicativo?</h3>
            <p style="font-size:14px; color:#50575e;">Para exibir o gerador de anúncios no front-end, basta copiar e colar o shortcode abaixo em qualquer página ou post do seu WordPress. Você pode usar no editor clássico, Gutenberg ou <strong>Elementor</strong>!</p>
            
            <div style="background:#f0f0f1; padding:20px; text-align:center; display:block; border-radius:6px; font-family:monospace; font-size:22px; font-weight:bold; color:#d63638; border:1px dashed #c3c4c7; margin:20px 0;">
                [stlai_vision_ads_pro]
            </div>
            
            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e4e7;">

            <h3 style="color:#1d2327; font-size:16px;">Próximos Passos:</h3>
            <ul style="font-size:14px; color:#50575e; line-height:1.8;">
                <li><strong style="color:#2271b1;">1. Config. de IA:</strong> Adicione suas chaves de API do ChatGPT ou Gemini para o sistema funcionar.</li>
                <li><strong style="color:#2271b1;">2. Prompts:</strong> Ajuste as diretrizes e como a IA deve conversar e criar as imagens e os textos de acordo com sua loja.</li>
                <li><strong style="color:#2271b1;">3. Elementor:</strong> Crie uma página "App" e arraste um widget "Shortcode" colando a tag acima.</li>
            </ul>
        </div>
    </div>
    <?php
}

function stlai_admin_settings_card( $title, $description, $rows_callback ) {
    echo '<section class="stlai-admin-card">';
    echo '<div class="stlai-admin-card__head"><h2>' . esc_html( $title ) . '</h2>';
    if ( ! empty( $description ) ) {
        echo '<p>' . esc_html( $description ) . '</p>';
    }
    echo '</div><table class="form-table" role="presentation"><tbody>';
    call_user_func( $rows_callback );
    echo '</tbody></table></section>';
}

function stlai_admin_field_row( $label, $callback, $args, $class = '', $note = '' ) {
    echo '<tr' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
    echo '<th scope="row"><label for="' . esc_attr( $args['id'] ?? '' ) . '">' . esc_html( $label ) . '</label></th>';
    echo '<td>';
    call_user_func( $callback, $args );
    if ( ! empty( $note ) ) {
        echo '<p class="description">' . esc_html( $note ) . '</p>';
    }
    echo '</td></tr>';
}

function stlai_admin_notice_row( $message, $class = 'notice-info', $provider_class = '' ) {
    echo '<tr' . ( $provider_class ? ' class="' . esc_attr( $provider_class ) . '"' : '' ) . '><th scope="row"></th><td>';
    echo '<div class="notice ' . esc_attr( $class ) . ' inline stlai-provider-notice"><p>' . esc_html( $message ) . '</p></div>';
    echo '</td></tr>';
}

function stlai_config_ia_page() {
    ?>
    <div class="wrap">
        <h1>Configurações de Inteligência Artificial</h1>
        <p>Informe suas credenciais e modelos por área. Gemini Veo é o provider de vídeo funcional no MVP.</p>
        <style>
            .stlai-admin-settings{max-width:1120px;margin-top:18px}
            .stlai-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;margin:0 0 18px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
            .stlai-admin-card__head{padding:18px 20px;border-bottom:1px solid #f0f0f1}
            .stlai-admin-card__head h2{margin:0;color:#1d2327;font-size:18px}
            .stlai-admin-card__head p{margin:6px 0 0;color:#646970;max-width:760px}
            .stlai-admin-card .form-table{margin:0}
            .stlai-admin-card .form-table th{padding-left:20px;width:260px}
            .stlai-admin-card .form-table td{padding-right:20px}
            .stlai-provider-notice{margin:0;max-width:720px}
            .stlai-admin-provider-hidden{display:none}
            .stlai-secret-field{display:flex;align-items:center;gap:8px;max-width:620px}
            .stlai-secret-field input{flex:1;min-width:0}
            .stlai-secret-toggle{height:36px;min-width:40px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #8c8f94;background:#fff;border-radius:4px;color:#3c434a;cursor:pointer}
            .stlai-secret-toggle:hover{border-color:#5166E6;color:#5166E6}
            .stlai-secret-toggle.is-visible{border-color:#9B51E6;color:#9B51E6;box-shadow:0 0 0 1px rgba(155,81,230,.12)}
            .stlai-secret-toggle__icon{display:block}
            .stlai-secret-toggle__icon--hide{display:none}
            .stlai-secret-toggle.is-visible .stlai-secret-toggle__icon--show{display:none}
            .stlai-secret-toggle.is-visible .stlai-secret-toggle__icon--hide{display:block}
        </style>
        <form action="options.php" method="post" class="stlai-admin-settings">
            <?php
            settings_fields( 'stlai_settings_group' );
            stlai_admin_settings_card( 'Provedores de Texto', 'Modelos e credenciais usados para títulos, descrição e leitura textual.', function() {
                stlai_admin_field_row( 'IA para Textos', 'stlai_render_select_field', array( 'id' => 'txtApi', 'options' => array( 'openai' => 'OpenAI', 'gemini' => 'Google Gemini' ) ) );
                stlai_admin_field_row( 'OpenAI API Key', 'stlai_render_password_field', array( 'id' => 'apiKey' ) );
                stlai_admin_field_row( 'OpenAI Endpoint URL', 'stlai_render_text_field', array( 'id' => 'url' ) );
                stlai_admin_field_row( 'OpenAI Modelo Texto', 'stlai_render_text_field', array( 'id' => 'textModel' ) );
                stlai_admin_field_row( 'Gemini API Key', 'stlai_render_password_field', array( 'id' => 'geminiKey' ) );
                stlai_admin_field_row( 'Gemini Endpoint URL', 'stlai_render_text_field', array( 'id' => 'geminiUrl' ) );
                stlai_admin_field_row( 'Gemini Modelo Texto', 'stlai_render_text_field', array( 'id' => 'geminiTextModel' ) );
            } );

            stlai_admin_settings_card( 'Provedores de Imagem', 'Configurações usadas na geração da galeria de imagens comerciais.', function() {
                stlai_admin_field_row( 'IA para Imagens', 'stlai_render_select_field', array( 'id' => 'imgApi', 'options' => array( 'openai' => 'OpenAI', 'gemini' => 'Google Gemini' ) ) );
                stlai_admin_notice_row( 'Ao usar Google Gemini para imagens, preencha o campo Gemini API Key no card Provedores de Texto/Credenciais acima. A geração de imagem usa essa mesma chave, lida diretamente do WordPress.', 'notice-info' );
                stlai_admin_field_row( 'OpenAI Modelo Imagem', 'stlai_render_text_field', array( 'id' => 'imageModel' ) );
                stlai_admin_field_row( 'Gemini Modelo Imagem', 'stlai_render_text_field', array( 'id' => 'geminiImageModel' ) );
                stlai_admin_field_row( 'Quality', 'stlai_render_select_field', array( 'id' => 'imageQuality', 'default' => 'auto', 'options' => array( 'auto' => 'Auto', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa' ) ) );
                stlai_admin_field_row( 'Qualidade de Imagem OpenAI', 'stlai_render_text_field', array( 'id' => 'imgQuality' ) );
                stlai_admin_field_row( 'Resolução de Imagem', 'stlai_render_text_field', array( 'id' => 'imgResolution', 'placeholder' => 'ex: 1024x1024, 1k, 2k' ) );
            } );

            stlai_admin_settings_card( 'Inteligência de Mercado', 'Credenciais para pesquisa competitiva e dados de marketplace.', function() {
                stlai_admin_field_row( 'SerpAPI Key', 'stlai_render_password_field', array( 'id' => 'serpapiKey' ) );
                stlai_admin_field_row( 'Mercado Livre App ID', 'stlai_render_text_field', array( 'id' => 'mlAppId' ) );
                stlai_admin_field_row( 'Mercado Livre Secret Key', 'stlai_render_password_field', array( 'id' => 'mlSecretKey' ) );
            } );

            stlai_admin_settings_card( 'Vídeo Comercial', 'Provider, modelo e resolução para os 4 clipes comerciais. No MVP, Gemini Veo é o fluxo ativo.', function() {
                stlai_admin_field_row( 'Provider de vídeo', 'stlai_render_select_field', array( 'id' => 'commercialVideoProvider', 'default' => 'gemini_veo', 'options' => array( 'gemini_veo' => 'Gemini Veo (ativo no MVP)', 'fal_ai' => 'Fal.ai (em breve)', 'atlas_cloud' => 'Atlas Cloud (em breve)', 'muapi' => 'MuAPI (em breve)', 'custom' => 'Custom' ), 'description' => 'Providers futuros podem ser salvos, mas o fluxo principal atual usa Gemini Veo.' ) );
                stlai_admin_notice_row( 'Gemini Veo está ativo no MVP e será usado para gerar os clipes comerciais.', 'notice-info', 'stlai-provider-row stlai-provider-gemini_veo' );
                stlai_admin_field_row( 'Video API Key', 'stlai_render_password_field', array( 'id' => 'videoApiKey' ), 'stlai-provider-row stlai-provider-gemini_veo' );
                stlai_admin_field_row( 'Video Base URL', 'stlai_render_text_field', array( 'id' => 'videoBaseUrl', 'placeholder' => 'Endpoint/base URL da API de vídeo' ), 'stlai-provider-row stlai-provider-gemini_veo' );
                stlai_admin_field_row( 'Modelo Gemini/Veo', 'stlai_render_select_field', array( 'id' => 'commercialVideoModel', 'default' => 'veo-3.1-lite-generate-preview', 'options' => array( 'veo-3.1-lite-generate-preview' => 'veo-3.1-lite-generate-preview (Lite)', 'veo-3.1-fast-generate-preview' => 'veo-3.1-fast-generate-preview (Fast)', 'veo-3.1-generate-preview' => 'veo-3.1-generate-preview', 'veo-2.0-generate-001' => 'veo-2.0-generate-001' ), 'description' => 'Lite é mais barato/rápido; Fast é equilíbrio; Generate tende a maior qualidade/custo.' ), 'stlai-provider-row stlai-provider-gemini_veo' );
                stlai_admin_field_row( 'Resolução de saída', 'stlai_render_select_field', array( 'id' => 'commercialVideoOutputResolution', 'default' => '720p', 'options' => array( '720p' => '720p', '1080p' => '1080p' ), 'description' => '720p é mais rápido e econômico. 1080p melhora qualidade, mas pode demorar mais.' ), 'stlai-provider-row stlai-provider-gemini_veo stlai-provider-custom' );

                stlai_admin_notice_row( 'Fal.ai está preparado para uso futuro. O fluxo principal atual usa Gemini Veo.', 'notice-warning', 'stlai-provider-row stlai-provider-fal_ai' );
                stlai_admin_field_row( 'Fal.ai API Key', 'stlai_render_password_field', array( 'id' => 'falAiApiKey' ), 'stlai-provider-row stlai-provider-fal_ai' );
                stlai_admin_field_row( 'Fal.ai Base URL', 'stlai_render_text_field', array( 'id' => 'falAiBaseUrl' ), 'stlai-provider-row stlai-provider-fal_ai' );
                stlai_admin_field_row( 'Fal.ai Model', 'stlai_render_text_field', array( 'id' => 'falAiModel', 'placeholder' => 'fal-ai/pixverse/v6/image-to-video' ), 'stlai-provider-row stlai-provider-fal_ai' );

                stlai_admin_notice_row( 'Atlas Cloud está preparado para uso futuro e ainda não está ativo no MVP.', 'notice-warning', 'stlai-provider-row stlai-provider-atlas_cloud' );
                stlai_admin_field_row( 'Atlas API Key', 'stlai_render_password_field', array( 'id' => 'atlasCloudApiKey' ), 'stlai-provider-row stlai-provider-atlas_cloud' );
                stlai_admin_field_row( 'Atlas Base URL', 'stlai_render_text_field', array( 'id' => 'atlasCloudBaseUrl' ), 'stlai-provider-row stlai-provider-atlas_cloud' );
                stlai_admin_field_row( 'Atlas Model', 'stlai_render_text_field', array( 'id' => 'atlasCloudModel' ), 'stlai-provider-row stlai-provider-atlas_cloud' );

                stlai_admin_notice_row( 'MuAPI está preparado para uso futuro e ainda não está ativo no MVP.', 'notice-warning', 'stlai-provider-row stlai-provider-muapi' );
                stlai_admin_field_row( 'MuAPI Key', 'stlai_render_password_field', array( 'id' => 'commercialMuApiKey' ), 'stlai-provider-row stlai-provider-muapi' );
                stlai_admin_field_row( 'MuAPI Base URL', 'stlai_render_text_field', array( 'id' => 'commercialMuApiBaseUrl' ), 'stlai-provider-row stlai-provider-muapi' );
                stlai_admin_field_row( 'MuAPI Model', 'stlai_render_text_field', array( 'id' => 'commercialMuApiModel' ), 'stlai-provider-row stlai-provider-muapi' );

                stlai_admin_notice_row( 'Provider customizado requer implementação compatível com o contrato de vídeo do plugin.', 'notice-warning', 'stlai-provider-row stlai-provider-custom' );
                stlai_admin_field_row( 'Custom Endpoint', 'stlai_render_text_field', array( 'id' => 'commercialVideoCustomEndpoint', 'placeholder' => 'https://api.seuprovedor.com/video' ), 'stlai-provider-row stlai-provider-custom' );
                stlai_admin_field_row( 'Custom API Key', 'stlai_render_password_field', array( 'id' => 'commercialVideoCustomApiKey' ), 'stlai-provider-row stlai-provider-custom' );
                stlai_admin_field_row( 'Custom Model', 'stlai_render_text_field', array( 'id' => 'commercialVideoCustomModel', 'placeholder' => 'modelo-custom' ), 'stlai-provider-row stlai-provider-custom' );
            } );

            stlai_admin_settings_card( 'Composição Final', 'Configurações do serviço que monta os clipes, narração e vídeo final.', function() {
                stlai_admin_field_row( 'Modo de composição', 'stlai_render_select_field', array( 'id' => 'videoComposerMode', 'default' => 'external_service', 'options' => array( 'external_service' => 'Serviço externo', 'local_ffmpeg' => 'FFmpeg local' ) ) );
                stlai_admin_field_row( 'Endpoint do serviço', 'stlai_render_text_field', array( 'id' => 'videoComposerEndpoint', 'placeholder' => 'https://video-render.seudominio.com/render' ) );
                stlai_admin_field_row( 'API Key do serviço', 'stlai_render_password_field', array( 'id' => 'videoComposerApiKey' ) );
                stlai_admin_field_row( 'Timeout da composição', 'stlai_render_text_field', array( 'id' => 'videoComposerTimeout', 'default' => '300' ) );
                stlai_admin_field_row( 'Caminho do FFmpeg', 'stlai_render_text_field', array( 'id' => 'ffmpegPath', 'placeholder' => 'Auto detectar, /usr/bin/ffmpeg ou /usr/local/bin/ffmpeg' ) );
            } );

            stlai_admin_settings_card( 'Narração / ElevenLabs', 'Configurações de voz usadas na narração do anúncio.', function() {
                stlai_admin_field_row( 'Provider de áudio', 'stlai_render_select_field', array( 'id' => 'audioProvider', 'default' => 'elevenlabs', 'options' => array( 'elevenlabs' => 'ElevenLabs' ) ) );
                stlai_admin_field_row( 'ElevenLabs API Key', 'stlai_render_password_field', array( 'id' => 'elevenLabsApiKey' ) );
                stlai_admin_field_row( 'Voice ID Emocional', 'stlai_render_text_field', array( 'id' => 'elevenLabsVoiceEmotional' ) );
                stlai_admin_field_row( 'Voice ID Persuasiva', 'stlai_render_text_field', array( 'id' => 'elevenLabsVoicePersuasive' ) );
                stlai_admin_field_row( 'Modelo ElevenLabs', 'stlai_render_text_field', array( 'id' => 'elevenLabsModel', 'default' => 'eleven_multilingual_v2' ) );
                stlai_admin_field_row( 'Idioma padrão', 'stlai_render_text_field', array( 'id' => 'elevenLabsDefaultLanguage', 'default' => 'pt-BR' ) );
            } );

            stlai_admin_settings_card( 'UGC — Vídeos', 'Configurações para vídeos estilo criador de conteúdo. MuAPI é o provider inicial.', function() {
                stlai_admin_field_row( 'Provider UGC', 'stlai_render_select_field', array( 'id' => 'ugcProvider', 'default' => 'none', 'options' => array( 'none' => 'Desativado', 'seedance' => 'Seedance 2.0 / BytePlus ModelArk', 'muapi' => 'MuAPI' ) ) );
                stlai_admin_field_row( 'Seedance API Key', 'stlai_render_password_field', array( 'id' => 'seedanceApiKey' ) );
                stlai_admin_field_row( 'Seedance Base URL', 'stlai_render_text_field', array( 'id' => 'seedanceBaseUrl' ) );
                stlai_admin_field_row( 'Seedance Model', 'stlai_render_text_field', array( 'id' => 'seedanceModel' ) );
                stlai_admin_field_row( 'MuAPI Key', 'stlai_render_password_field', array( 'id' => 'muApiKey' ) );
                stlai_admin_field_row( 'MuAPI Base URL', 'stlai_render_text_field', array( 'id' => 'muApiBaseUrl', 'default' => 'https://api.muapi.ai' ) );
                stlai_admin_field_row( 'MuAPI Model/Endpoint', 'stlai_render_text_field', array( 'id' => 'muApiModel', 'default' => 'seedance-2.0-image-to-video', 'description' => 'Endpoint enviado para /api/v1/{modelo}. Ajuste conforme o modelo liberado na sua conta MuAPI.' ) );
                stlai_admin_field_row( 'Duração padrão UGC', 'stlai_render_select_field', array( 'id' => 'ugcDefaultDuration', 'default' => '9', 'options' => array( '5' => '5s', '8' => '8s', '9' => '9s', '10' => '10s' ) ) );
                stlai_admin_field_row( 'Resolução padrão UGC', 'stlai_render_select_field', array( 'id' => 'ugcDefaultResolution', 'default' => '720p', 'options' => array( '720p' => '720p', '1080p' => '1080p' ) ) );
                stlai_admin_field_row( 'Formato padrão UGC', 'stlai_render_select_field', array( 'id' => 'ugcDefaultAspectRatio', 'default' => '9:16', 'options' => array( '9:16' => '9:16', '16:9' => '16:9', '1:1' => '1:1' ) ) );
            } );
            submit_button('Salvar Configurações');
            ?>
        </form>
        <script>
        (function(){
            const provider=document.getElementById("commercialVideoProvider");
            const rows=[].slice.call(document.querySelectorAll(".stlai-provider-row"));
            function syncProviderRows(){
                const value=provider ? provider.value : "gemini_veo";
                rows.forEach(row=>{
                    const show=row.classList.contains("stlai-provider-" + value);
                    row.classList.toggle("stlai-admin-provider-hidden", !show);
                });
            }
            if(provider){
                provider.addEventListener("change", syncProviderRows);
                syncProviderRows();
            }
            document.addEventListener("click", function(event){
                const toggle=event.target.closest("[data-stlai-secret-toggle]");
                if(!toggle){ return; }
                const wrap=toggle.closest(".stlai-secret-field");
                const input=wrap ? wrap.querySelector(".stlai-secret-input") : null;
                if(!input){ return; }
                const visible=input.type === "text";
                input.type=visible ? "password" : "text";
                toggle.classList.toggle("is-visible", !visible);
                toggle.setAttribute("aria-label", visible ? "Mostrar chave" : "Ocultar chave");
                toggle.setAttribute("aria-pressed", visible ? "false" : "true");
            });
        })();
        </script>
    </div>
    <?php
}

function stlai_prompts_page() {
    ?>
    <div class="wrap">
        <h1>Gerenciamento de Prompts</h1>
        <p>Ajuste os prompts base, parâmetros de IA em lote e instruções específicas de cada cena. A IA respeitará essas regras para gerar o conteúdo.</p>
        <form action="options.php" method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:15px;">
            <?php
            settings_fields( 'stlai_settings_group' );
            do_settings_sections( 'stlai_prompts_page' );
            submit_button('Salvar Prompts', 'primary', 'submit', false);
            echo '&nbsp;&nbsp;<button type="button" class="button" onclick="if(confirm(\'Isto irá limpar todos os prompts abaixo e restaurar para o padrão ao salvar. Continuar?\')) { const f=this.closest(\'form\'); f.querySelectorAll(\'textarea\').forEach(t=>t.value=\'\'); f.submit(); }">Restaurar Padrão</button>';
            ?>
        </form>
    </div>
    <?php
}

function stlai_planos_page() {
    ?>
    <div class="wrap">
        <h1>Planos & Créditos</h1>
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:15px;">
            <p><strong>Status:</strong> <span style="color:#d63638">Em Breve</span></p>
            <p>Aqui você poderá configurar a quantidade de créditos que cada usuário ganha ao assinar, custo das operações na sua ferramenta, e habilitar restrições por plano.</p>
        </div>
    </div>
    <?php
}

function stlai_teste_page() {
    ?>
    <div class="wrap">
        <h1>Teste de Geração</h1>
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:15px;">
            <p><strong>Status:</strong> <span style="color:#d63638">Em Breve</span></p>
            <p>Nesta tela você poderá simular e debugar uma chamada de IA utilizando as configurações salvas antes de liberar o aplicativo no site, testando se a API Key está correta e como as respostas estão chegando.</p>
        </div>
    </div>
    <?php
}

function stlai_logs_page() {
    ?>
    <div class="wrap">
        <h1>Logs de IA</h1>
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:15px;">
            <p><strong>Status:</strong> <span style="color:#d63638">Em Breve</span></p>
            <p>Monitore requisições falhas, consumo de tokens, rejeições por copyright (Safety) ou possíveis gargalos no servidor da OpenAI/Gemini.</p>
        </div>
    </div>
    <?php
}
