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
    add_settings_field('videoProvider', 'Provider de Video', 'stlai_render_select_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoProvider', 'default' => 'veo', 'options' => array('veo' => 'Veo 3.1 Lite')));
    add_settings_field('videoModel', 'Modelo de Video', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoModel', 'default' => 'veo-3.1-lite-generate-preview'));
    add_settings_field('videoApiKey', 'Video API Key', 'stlai_render_password_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoApiKey'));
    add_settings_field('videoBaseUrl', 'Video Base URL', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'videoBaseUrl', 'placeholder' => 'Endpoint/base URL da API de video'));
    add_settings_field('ffmpegPath', 'Caminho do FFmpeg', 'stlai_render_text_field', 'stlai_config_ia_page', 'stlai_config_video_section', array('id' => 'ffmpegPath', 'placeholder' => 'Auto detectar, /usr/bin/ffmpeg ou /usr/local/bin/ffmpeg', 'description' => 'Necessário para compor o vídeo final com clipes, fade e narração. Se vazio, o plugin tentará detectar automaticamente.'));

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
    echo '<input type="text" class="regular-text" style="width:100%; max-width:500px;" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $val ) . '"' . $placeholder . '>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function stlai_render_password_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : '';
    $placeholder = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';
    echo '<input type="password" class="regular-text" style="width:100%; max-width:500px;" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $val ) . '"' . $placeholder . '>';
    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function stlai_render_textarea_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : '';
    $rows = isset($args['rows']) ? $args['rows'] : 8;
    echo '<textarea class="large-text" style="width:100%; max-width:800px;" rows="' . esc_attr($rows) . '" name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']">' . esc_textarea( $val ) . '</textarea>';
}

function stlai_render_select_field( $args ) {
    $options = get_option( 'stlai_vision_ads_pro_settings' );
    $val = isset($options[$args['id']]) ? $options[$args['id']] : ($args['default'] ?? '');
    echo '<select name="stlai_vision_ads_pro_settings[' . esc_attr( $args['id'] ) . ']">';
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

function stlai_config_ia_page() {
    ?>
    <div class="wrap">
        <h1>Configurações de Inteligência Artificial</h1>
        <p>Informe suas credenciais e os modelos desejados.</p>
        <form action="options.php" method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:15px;">
            <?php
            settings_fields( 'stlai_settings_group' );
            do_settings_sections( 'stlai_config_ia_page' );
            submit_button('Salvar Configurações');
            ?>
        </form>
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
