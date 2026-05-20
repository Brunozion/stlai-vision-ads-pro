<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function stlai_vision_fetch_mercadolivre($termo) {
    $url = 'https://api.mercadolibre.com/sites/MLB/search?q=' . urlencode($termo);
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return [];
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $produtos = [];
    
    if (!empty($data['results'])) {
        $count = 0;
        foreach ($data['results'] as $item) {
            if ($count >= 35) break;
            $produtos[] = [
                'origem' => 'Mercado Livre',
                'titulo' => $item['title'],
                'preco'  => (float) $item['price'],
                'link'   => $item['permalink'],
                'imagem' => $item['thumbnail']
            ];
            $count++;
        }
    }
    return $produtos;
}

function stlai_vision_fetch_serpapi($termo) {
    $options = get_option('stlai_vision_ads_pro_settings', array());
    $api_key = $options['serpapiKey'] ?? '';
    
    if (empty($api_key)) return [];
    
    $url = "https://serpapi.com/search.json?engine=google_shopping&q=" . urlencode($termo) . "&location=Brazil&hl=pt&gl=br&api_key=" . $api_key;
    $response = wp_remote_get($url, ['timeout' => 15]);
    
    if (is_wp_error($response)) return [];
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $produtos = [];
    
    if (!empty($data['shopping_results'])) {
        $count = 0;
        foreach ($data['shopping_results'] as $item) {
            if ($count >= 35) break;
            
            $preco_str = $item['extracted_price'] ?? ($item['price'] ?? '0');
            $preco_limpo = preg_replace('/[^0-9.,]/', '', (string)$preco_str);
            if (strpos($preco_limpo, ',') !== false && strpos($preco_limpo, '.') !== false) {
                $preco_limpo = str_replace('.', '', $preco_limpo);
                $preco_limpo = str_replace(',', '.', $preco_limpo);
            } else {
                $preco_limpo = str_replace(',', '.', $preco_limpo);
            }
            
            $produtos[] = [
                'origem' => $item['source'] ?? 'Google Shopping',
                'titulo' => $item['title'],
                'preco'  => (float) $preco_limpo,
                'link'   => $item['product_link'] ?? ($item['link'] ?? '#'),
                'imagem' => $item['thumbnail'] ?? ''
            ];
            $count++;
        }
    }
    return $produtos;
}

function stlai_vision_processar_dados_mercado($produtos_ml, $produtos_serp) {
    $todos = array_merge($produtos_ml, $produtos_serp);
    $filtrados = [];
    $precos = [];
    
    foreach ($todos as $p) {
        if ($p['preco'] >= 10.00) {
            $filtrados[] = $p;
            $precos[] = $p['preco'];
        }
    }
    
    if (empty($precos)) {
        return ['estatisticas' => ['min' => 0, 'max' => 0, 'medio' => 0], 'produtos' => []];
    }
    
    $min = min($precos);
    $max = max($precos);
    $medio = array_sum($precos) / count($precos);
    
    return [
        'estatisticas' => [
            'min' => round($min, 2),
            'max' => round($max, 2),
            'medio' => round($medio, 2)
        ],
        'produtos' => $filtrados
    ];
}

function stlai_vision_call_ai_php($prompt) {
    $opts = get_option('stlai_vision_ads_pro_settings', []);
    $api = $opts['txtApi'] ?? 'openai';
    if ($api === 'gemini') {
        $key = $opts['geminiKey'] ?? '';
        $model = $opts['geminiTextModel'] ?? 'gemini-1.5-flash';
        $url = ($opts['geminiUrl'] ?? 'https://generativelanguage.googleapis.com') . "/v1beta/models/{$model}:generateContent?key={$key}";
        $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
        $res = wp_remote_post($url, ['body' => $body, 'headers' => ['Content-Type' => 'application/json'], 'timeout' => 30]);
        if(is_wp_error($res)) return "{}";
        $data = json_decode(wp_remote_retrieve_body($res), true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? "{}";
        $text = preg_replace('/```json|```/i', '', $text);
        return trim($text);
    } else {
        $key = $opts['apiKey'] ?? '';
        $model = $opts['textModel'] ?? 'gpt-4o-mini';
        $url = ($opts['url'] ?? 'https://api.openai.com/v1') . "/chat/completions";
        $body = json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $res = wp_remote_post($url, ['body' => $body, 'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key], 'timeout' => 30]);
        if(is_wp_error($res)) return "{}";
        $data = json_decode(wp_remote_retrieve_body($res), true);
        $text = $data['choices'][0]['message']['content'] ?? "{}";
        $text = preg_replace('/```json|```/i', '', $text);
        return trim($text);
    }
}

add_action('wp_ajax_stlai_analisar_mercado', 'stlai_vision_analisar_mercado_ajax');
add_action('wp_ajax_nopriv_stlai_analisar_mercado', 'stlai_vision_analisar_mercado_ajax');

function stlai_vision_analisar_mercado_ajax() {
    $termo = sanitize_text_field($_POST['termo_busca'] ?? '');
    
    if (empty($termo)) {
        wp_send_json_error('Termo de busca vazio.');
    }
    
    $ml = stlai_vision_fetch_mercadolivre($termo);
    $serp = stlai_vision_fetch_serpapi($termo);
    
    $processado = stlai_vision_processar_dados_mercado($ml, $serp);
    
    $top10 = array_slice($processado['produtos'], 0, 10);
    $txt_top = "";
    foreach($top10 as $p) {
        $txt_top .= "- " . $p['titulo'] . " (R$ " . $p['preco'] . ")\n";
    }
    
    $prompt = "Analise esses dados de e-commerce:\n" . $txt_top . "\nRetorne um JSON com: \"estrategia\" (1 parágrafo curto orientando a precificação e diferenciação) e \"palavras_chave\" (array com 8 tags). Sem formatação markdown, apenas o JSON puro.";
    
    $ia_raw = stlai_vision_call_ai_php($prompt);
    $ia_json = json_decode($ia_raw, true);
    
    if (!$ia_json) {
        $ia_json = ['estrategia' => 'Os dados foram analisados, mas a IA não retornou uma estratégia em formato legível.', 'palavras_chave' => []];
    }
    
    wp_send_json_success([
        'estatisticas' => $processado['estatisticas'],
        'produtos' => $processado['produtos'],
        'ia' => $ia_json
    ]);
}
