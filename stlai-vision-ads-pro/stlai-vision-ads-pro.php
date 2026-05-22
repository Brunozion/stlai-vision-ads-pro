<?php
/**
 * Plugin Name: STLAI Vision Ads Pro
 * Description: Plugin para geração de anúncios e inteligência de imagens 3D via IA.
 * Version: 1.3.12
 * Author: NDB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'STLAI_VISION_ADS_PRO_VERSION', '1.3.12' );
define( 'STLAI_VISION_ADS_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'STLAI_VISION_ADS_PRO_URL', plugin_dir_url( __FILE__ ) );

// Include admin settings
if ( is_admin() ) {
    require_once STLAI_VISION_ADS_PRO_DIR . 'admin/settings-page.php';
}

require_once STLAI_VISION_ADS_PRO_DIR . 'includes/market-analysis.php';
require_once STLAI_VISION_ADS_PRO_DIR . 'includes/video/class-stlai-video-storage.php';
require_once STLAI_VISION_ADS_PRO_DIR . 'includes/video/class-stlai-elevenlabs-provider.php';
require_once STLAI_VISION_ADS_PRO_DIR . 'includes/video/class-stlai-veo-provider.php';
require_once STLAI_VISION_ADS_PRO_DIR . 'includes/video/class-stlai-video-job-service.php';
require_once STLAI_VISION_ADS_PRO_DIR . 'includes/video/class-stlai-video-ajax.php';

// Register Shortcode
add_shortcode( 'stlai_vision_ads_pro', 'stlai_vision_ads_pro_render_shortcode' );

function stlai_vision_ads_pro_render_shortcode( $atts ) {
    // Enqueue CSS
    wp_enqueue_style( 'stlai-vision-ads-pro-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', array(), null );
    wp_enqueue_style( 'stlai-vision-ads-pro-style', STLAI_VISION_ADS_PRO_URL . 'assets/css/style.css', array(), STLAI_VISION_ADS_PRO_VERSION );

    // Enqueue JS
    wp_enqueue_script( 'jszip', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', array(), null, true );
    wp_enqueue_script( 'stlai-vision-ads-pro-script', STLAI_VISION_ADS_PRO_URL . 'assets/js/app.js', array('jszip'), STLAI_VISION_ADS_PRO_VERSION, true );

    // Get settings
    $options = get_option( 'stlai_vision_ads_pro_settings', array() );
    
    // Pass settings to JavaScript
    wp_localize_script( 'stlai-vision-ads-pro-script', 'stlaiConfig', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'txtApi' => $options['txtApi'] ?? 'openai',
        'imgApi' => $options['imgApi'] ?? 'openai',
        'apiKey' => $options['apiKey'] ?? '',
        'geminiKey' => $options['geminiKey'] ?? '',
        'serpapiKey' => $options['serpapiKey'] ?? '',
        'url' => $options['url'] ?? 'https://api.openai.com/v1',
        'geminiUrl' => $options['geminiUrl'] ?? 'https://generativelanguage.googleapis.com',
        'textModel' => $options['textModel'] ?? 'gpt-4o-mini',
        'imageModel' => $options['imageModel'] ?? 'chatgpt-image-latest',
        'geminiTextModel' => $options['geminiTextModel'] ?? 'gemini-3.1-pro-preview',
        'geminiImageModel' => $options['geminiImageModel'] ?? 'gemini-3.1-flash-image-preview',
        'imgQuality' => $options['imgQuality'] ?? 'medium',
        'imgResolution' => $options['imgResolution'] ?? '1024x1024',
        'textPrompt' => $options['textPrompt'] ?? "Você é um vendedor experiente de marketplace, especialista em anúncios de produtos fabricados via IMPRESSÃO 3D.\nAnalise as imagens e o contexto do produto e retorne SOMENTE JSON válido, sem markdown, neste formato:\n{\n  \"titles\": [\n    \"Título 1\",\n    \"Título 2\",\n    \"Título 3\",\n    \"Título 4\"\n  ],\n  \"description\": \"Descrição\"\n}\n\nINSTRUÇÃO CRÍTICA DE IDIOMA:\nO IDIOMA DE DESTINO É: {{languageLabel}}.\nTODOS os textos gerados DEVEM estar obrigatoriamente em {{languageLabel}}.\n\nCrie exatamente 4 títulos curtos e de altíssimo impacto. REGRA ABSOLUTA E INQUEBRÁVEL DE SEO: NENHUM TÍTULO PODE TER MAIS DE 55 CARACTERES NO TOTAL (incluindo espaços). CRIE FRASES COMPLÉTAS, SEMPRE FINALIZE O SENTIDO E NUNCA USE RETICÊNCIAS (...) NO FINAL DO TÍTULO:\n1. Estratégia SEO: Marca + Modelo + Categoria + atributos essenciais.\n2. Estratégia Informativa: destaque material, cor, tamanho.\n3. Estratégia Benefício: destaque utilidade e inovação.\n4. Estratégia Diferencial: destaque design exclusivo.\n\nRegras da descrição:\n- Crie um parágrafo inicial curto e persuasivo\n- Abaixo do parágrafo, crie OBRIGATORIAMENTE uma lista de bullet points (um abaixo do outro) resumindo as principais características de forma direta (ex: - Impressão 3D\\n- Peça em PLA\\n- Acabamento texturizado)\n- sem emojis\n- pronta para marketplace\n\nContexto:\nNome do produto: {{name}}\nDescrição/contexto: {{desc}}\nDimensões: {{dims}}\nPeso: {{weight}}\nVoltagem: {{volt}}\nCaracterísticas: {{feat}}\nTom: {{tone}}\nPúblico-alvo: {{audience}}",
        'imagePrompt' => $options['imagePrompt'] ?? '',
        'promptVision' => $options['promptVision'] ?? "Analise as imagens deste produto fabricado via IMPRESSÃO 3D com máxima atenção aos detalhes e retorne APENAS JSON válido sem markdown:\n{\n  \"name\": \"nome provável do produto em português (seja específico e atrativo)\",\n  \"description\": \"descrição detalhada sobre o produto impresso em 3D, seu propósito, como ele resolve um problema ou adiciona valor, e sugestões de uso prático\",\n  \"material\": \"material provável ou aparente (ex: PLA, PETG, ABS, Resina) considerando o acabamento da impressão\",\n  \"color\": \"cores predominantes e detalhes de cor\",\n  \"weight\": \"estimativa de peso em gramas\",\n  \"voltage\": \"voltagem se for um eletrônico, senão N/A\",\n  \"features\": \"liste características visuais, diferenciais de design, textura, qualidade de acabamento da impressão 3D, e pontos fortes comerciais. CUIDADO: Descreva APENAS o que é 100% visível na imagem de forma objetiva. NUNCA invente que o produto é articulado, possui movimento, ou funções eletrônicas a menos que esteja explicitamente visível e óbvio na imagem. Se for uma estatueta/cabeça estática, descreva apenas como escultura/peça decorativa estática.\"\n}",
        'promptMarket' => $options['promptMarket'] ?? '',
        'promptDims' => $options['promptDims'] ?? "Technical photography of the exact same {{name}} on a pure white background, keeping its exact shape, material, colors, and 3D printed appearance. Add very clear, distinct, and professional dimension lines (thin arrows with text) representing the measurements. Place the Height measurement on the right side vertically. Place the Width measurement on the front bottom horizontally. Place the Depth measurement on the left side, showing a dimension line with an arrow pointing backwards to clearly indicate depth in perspective. The total size to indicate is: {{dimsTxt}}. Ensure the dimension lines do not cover the product. DO NOT split into a grid. Generate a single square image 1:1.",
        'promptBatch1' => $options['promptBatch1'] ?? "CRITICAL INSTRUCTION: You MUST generate exactly ONE large square image divided evenly into a 2x2 grid (4 quadrants) like a collage. DO NOT generate a single scene.\nIMPORTANT: The product shown in ALL quadrants MUST be the EXACT SAME product from the reference image, keeping its exact shape, material, colors, and 3D printed appearance. You can change the camera angle and background, but NOT the product itself.\n- Top-Left Quadrant: The exact same {{name}} isolated on a pure white background, studio lighting.\n{{q2}}\n- Bottom-Left Quadrant: The exact same {{name}} in a second completely different realistic environment where a customer would naturally place or use it.\n- Bottom-Right Quadrant: The exact same {{name}} in a third beautiful, contextual lifestyle scene relevant to its function.",
        'promptBatch2' => $options['promptBatch2'] ?? "CRITICAL INSTRUCTION: You MUST generate exactly ONE large square image divided evenly into a 2x2 grid (4 quadrants) like a collage. DO NOT generate a single scene.\nABSOLUTE RULE: The product shown in ALL quadrants MUST be EXACTLY IDENTICAL to the reference image in shape, material, texture, scale, and color. DO NOT MODIFY, REDESIGN OR ALTER THE PRODUCT ITSELF in any way. Keep the exact same original 3D printed object from the reference. Only change the surrounding environment/background.\n- Top-Left Quadrant: The exact same {{name}} in a fourth unique lifestyle ambient setting.\n- Top-Right Quadrant: A different angle of the exact same {{name}} showing its original details. Do not change the shape.\n- Bottom-Left Quadrant: The exact same {{name}} placed in a commercial lifestyle setting.\n- Bottom-Right Quadrant: A highly sophisticated and premium hero shot of the exact same {{name}} in its ideal environment.",
        'promptBatchMore' => $options['promptBatchMore'] ?? "CRITICAL INSTRUCTION: You MUST generate exactly ONE large square image divided evenly into a 2x2 grid (4 quadrants) like a collage. DO NOT generate a single scene.\nIMPORTANT: The product shown in ALL quadrants MUST be the EXACT SAME product from the reference image, keeping its exact shape, material, colors, and 3D printed appearance.\nCreate 4 completely NEW and DIFFERENT lifestyle settings or use cases that have not been generated before. Focus on highly creative, unexplored environments where a customer might place this {{name}}.\n- Top-Left Quadrant: New creative lifestyle setting A.\n- Top-Right Quadrant: New creative lifestyle setting B.\n- Bottom-Left Quadrant: New creative lifestyle setting C.\n- Bottom-Right Quadrant: New creative lifestyle setting D.",
        'promptFallback' => $options['promptFallback'] ?? "Crie uma imagem de produto para e-commerce com linguagem visual segura e comercial.\nProduto: {{name}}.\nContexto: {{desc}}.\nCaracterísticas: {{feat}}.\nSem pessoas, sem logos, sem marcas registradas, sem personagens famosos, sem texto e sem marca d'água.\nMantenha composição limpa, fundo apropriado para marketplace e aspecto fotográfico realista.\nDireção da cena: {{originalPrompt}}",
        'sceneCapa' => $options['sceneCapa'] ?? "crie uma foto de produto em fundo branco puro, luz de estúdio, centralizada, com aparência premium para marketplace, proporção 1:1",
        'sceneAmb1' => $options['sceneAmb1'] ?? "mostre o produto em um ambiente realista de uso, sofisticado, com contexto cotidiano que valorize a utilidade do item, proporção 1:1",
        'sceneAmb2' => $options['sceneAmb2'] ?? "mostre o produto em uma segunda cena de uso diferente, com enquadramento comercial e atmosfera refinada, proporção 1:1",
        'sceneAmb3' => $options['sceneAmb3'] ?? "mostre o produto em uma terceira cena premium, destacando acabamento e desejo de compra, proporção 1:1",
        'sceneAmb4' => $options['sceneAmb4'] ?? "mostre o produto em uma quarta cena lifestyle com proposta aspiracional e composição mais editorial, proporção 1:1",
        'sceneDetail' => $options['sceneDetail'] ?? "crie um close premium do produto destacando textura, acabamento e qualidade visual, com profundidade de campo elegante, proporção 1:1",
        'sceneFeature' => $options['sceneFeature'] ?? "CRITICAL: The product must be EXACTLY IDENTICAL to the reference image in shape, material, texture, and color. DO NOT MODIFY THE PRODUCT ITSELF in any way. Only change the surrounding environment/background to illustrate its main benefit or functionality in a commercial setting, 1:1 proportion.",
        'sceneHero' => $options['sceneHero'] ?? "crie uma hero shot premium do produto em contexto de uso sofisticado, com impacto visual forte e linguagem comercial de alto nível, proporção 1:1",
        'sceneDims' => $options['sceneDims'] ?? "crie uma foto técnica do produto em fundo branco puro, contendo linhas de dimensão sutis e profissionais indicando o tamanho: {{dimsTxt}}. Proporção 1:1",
    ) );

    ob_start();
    require STLAI_VISION_ADS_PRO_DIR . 'frontend/shortcode.php';
    return ob_get_clean();
}
