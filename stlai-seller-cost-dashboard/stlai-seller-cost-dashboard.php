<?php
/**
 * Plugin Name: STLAI Seller Cost Dashboard
 * Description: Dashboard financeiro para custos de geração de imagens, vídeos e áudios do Seller.
 * Version: 1.0.19
 * Author: NDB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'STLAI_SELLER_COST_VERSION', '1.0.19' );
define( 'STLAI_SELLER_COST_DIR', plugin_dir_path( __FILE__ ) );
define( 'STLAI_SELLER_COST_URL', plugin_dir_url( __FILE__ ) );

add_action( 'admin_menu', 'stlai_seller_cost_admin_menu' );
add_action( 'admin_init', 'stlai_seller_cost_register_settings' );
add_action( 'stlai_seller_cost_log_generation', 'stlai_seller_cost_log_generation' );
add_shortcode( 'stlai_seller_cost_dashboard', 'stlai_seller_cost_render_shortcode' );

function stlai_seller_cost_default_models() {
    return array(
        array( 'id' => 'openai-gpt-image-2-low-1024', 'provider' => 'OpenAI', 'category' => 'Imagem', 'model' => 'GPT Image 2', 'variant' => 'Low 1024x1024', 'unit' => 'image', 'usd' => 0.006, 'active' => true ),
        array( 'id' => 'openai-gpt-image-2-medium-1024', 'provider' => 'OpenAI', 'category' => 'Imagem', 'model' => 'GPT Image 2', 'variant' => 'Medium 1024x1024', 'unit' => 'image', 'usd' => 0.053, 'active' => true ),
        array( 'id' => 'openai-gpt-image-2-high-1024', 'provider' => 'OpenAI', 'category' => 'Imagem', 'model' => 'GPT Image 2', 'variant' => 'High 1024x1024', 'unit' => 'image', 'usd' => 0.211, 'active' => true ),
        array( 'id' => 'openai-gpt-image-1-5-high-1024', 'provider' => 'OpenAI', 'category' => 'Imagem', 'model' => 'GPT Image 1.5', 'variant' => 'High 1024x1024', 'unit' => 'image', 'usd' => 0.133, 'active' => true ),
        array( 'id' => 'gemini-3-1-flash-image-1k', 'provider' => 'Google Gemini', 'category' => 'Imagem', 'model' => 'Gemini 3.1 Flash Image', 'variant' => '1K', 'unit' => 'image', 'usd' => 0.067, 'active' => true ),
        array( 'id' => 'gemini-3-1-flash-image-2k', 'provider' => 'Google Gemini', 'category' => 'Imagem', 'model' => 'Gemini 3.1 Flash Image', 'variant' => '2K', 'unit' => 'image', 'usd' => 0.101, 'active' => true ),
        array( 'id' => 'gemini-3-1-flash-image-4k', 'provider' => 'Google Gemini', 'category' => 'Imagem', 'model' => 'Gemini 3.1 Flash Image', 'variant' => '4K', 'unit' => 'image', 'usd' => 0.151, 'active' => true ),
        array( 'id' => 'veo-3-1-lite-720', 'provider' => 'Google Gemini', 'category' => 'Video', 'model' => 'Veo 3.1 Lite', 'variant' => '720p', 'unit' => 'second', 'usd' => 0.05, 'active' => true ),
        array( 'id' => 'veo-3-1-lite-1080', 'provider' => 'Google Gemini', 'category' => 'Video', 'model' => 'Veo 3.1 Lite', 'variant' => '1080p', 'unit' => 'second', 'usd' => 0.08, 'active' => true ),
        array( 'id' => 'veo-3-1-fast-720', 'provider' => 'Google Gemini', 'category' => 'Video', 'model' => 'Veo 3.1 Fast', 'variant' => '720p', 'unit' => 'second', 'usd' => 0.10, 'active' => true ),
        array( 'id' => 'veo-3-1-fast-1080', 'provider' => 'Google Gemini', 'category' => 'Video', 'model' => 'Veo 3.1 Fast', 'variant' => '1080p', 'unit' => 'second', 'usd' => 0.12, 'active' => true ),
        array( 'id' => 'veo-3-1-standard-720', 'provider' => 'Google Gemini', 'category' => 'Video', 'model' => 'Veo 3.1', 'variant' => '720p/1080p', 'unit' => 'second', 'usd' => 0.40, 'active' => true ),
        array( 'id' => 'fal-seedance-2-image-video-standard', 'provider' => 'Fal.ai', 'category' => 'Video', 'model' => 'Seedance 2.0 Image to Video', 'variant' => 'Standard 1080p', 'unit' => 'second', 'usd' => 0.30, 'active' => true ),
        array( 'id' => 'fal-seedance-2-image-video-fast', 'provider' => 'Fal.ai', 'category' => 'Video', 'model' => 'Seedance 2.0 Image to Video', 'variant' => 'Fast 1080p', 'unit' => 'second', 'usd' => 0.24, 'active' => true ),
        array( 'id' => 'fal-seedance-2-reference-standard', 'provider' => 'Fal.ai', 'category' => 'Video', 'model' => 'Seedance 2.0 Reference to Video', 'variant' => 'Standard sem vídeo input', 'unit' => 'second', 'usd' => 0.84, 'active' => true ),
        array( 'id' => 'fal-seedance-2-reference-fast', 'provider' => 'Fal.ai', 'category' => 'Video', 'model' => 'Seedance 2.0 Reference to Video', 'variant' => 'Fast sem vídeo input', 'unit' => 'second', 'usd' => 0.45, 'active' => true ),
        array( 'id' => 'ugc-seedance-2-image-video-standard', 'provider' => 'Fal.ai', 'category' => 'UGC', 'model' => 'Seedance 2.0 Image to Video', 'variant' => 'Standard 1080p', 'unit' => 'second', 'usd' => 0.30, 'active' => true ),
        array( 'id' => 'elevenlabs-creative-creator', 'provider' => 'ElevenLabs', 'category' => 'Audio', 'model' => 'ElevenCreative Creator', 'variant' => 'Multilingue/Flash extra', 'unit' => 'minute', 'usd' => 0.18, 'active' => true ),
        array( 'id' => 'elevenlabs-creative-pro', 'provider' => 'ElevenLabs', 'category' => 'Audio', 'model' => 'ElevenCreative Pro', 'variant' => 'Minutos extras', 'unit' => 'minute', 'usd' => 0.17, 'active' => true ),
        array( 'id' => 'seller-text-generation', 'provider' => 'OpenAI/Gemini', 'category' => 'Texto', 'model' => 'Títulos, descrição e análise', 'variant' => 'Custo médio editável', 'unit' => 'generation', 'usd' => 0.01, 'active' => true ),
    );
}

function stlai_seller_cost_default_settings() {
    return array(
        'usd_brl' => 5.00,
        'default_currency' => 'USD',
        'flow_order' => array( 'volume', 'text', 'image', 'video', 'audio', 'ugc', 'composer' ),
        'default_flow' => array(
            'name' => 'Seller Premium',
            'text_model' => 'seller-text-generation',
            'text_generations' => 1,
            'image_model' => 'openai-gpt-image-2-high-1024',
            'image_quantity' => 2,
            'video_model' => 'veo-3-1-lite-720',
            'video_clips' => 4,
            'seconds_per_clip' => 8,
            'audio_model' => 'elevenlabs-creative-creator',
            'audio_seconds' => 32,
            'ugc_enabled' => 0,
            'ugc_model' => 'ugc-seedance-2-image-video-standard',
            'ugc_clips' => 0,
            'ugc_seconds_per_clip' => 8,
            'composer_usd' => 0,
            'platform_margin_percent' => 0,
            'generations_per_day' => 10,
            'days_per_month' => 22,
            'suggested_multiplier' => 3,
        ),
        'models' => stlai_seller_cost_default_models(),
        'generations' => array(
            array( 'date' => gmdate( 'Y-m-d' ), 'flow' => 'Seller Premium', 'quantity' => 1, 'text_model' => 'seller-text-generation', 'text_generations' => 1, 'image_model' => 'openai-gpt-image-2-high-1024', 'image_quantity' => 2, 'video_model' => 'veo-3-1-lite-720', 'video_clips' => 4, 'seconds_per_clip' => 8, 'audio_model' => 'elevenlabs-creative-creator', 'audio_seconds' => 32, 'composer_usd' => 0 ),
        ),
    );
}

function stlai_seller_cost_get_settings() {
    $defaults = stlai_seller_cost_default_settings();
    $saved = get_option( 'stlai_seller_cost_settings', array() );
    $settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
    $settings['default_flow'] = wp_parse_args( $settings['default_flow'] ?? array(), $defaults['default_flow'] );
    $settings['flow_order'] = stlai_seller_cost_normalize_flow_order( $settings['flow_order'] ?? $defaults['flow_order'] );
    $settings['models'] = stlai_seller_cost_merge_default_models( $settings['models'] ?? array(), $defaults['models'] );
    return $settings;
}

function stlai_seller_cost_merge_default_models( $saved_models, $default_models ) {
    if ( ! is_array( $saved_models ) ) {
        $saved_models = array();
    }
    $ids = array();
    foreach ( $saved_models as $model ) {
        if ( is_array( $model ) && ! empty( $model['id'] ) ) {
            $ids[] = $model['id'];
        }
    }
    foreach ( $default_models as $model ) {
        if ( ! in_array( $model['id'], $ids, true ) ) {
            $saved_models[] = $model;
        }
    }
    return $saved_models;
}

function stlai_seller_cost_normalize_flow_order( $order ) {
    $allowed = array( 'volume', 'text', 'image', 'video', 'audio', 'ugc', 'composer' );
    if ( is_string( $order ) ) {
        $decoded = json_decode( wp_unslash( $order ), true );
        $order = is_array( $decoded ) ? $decoded : explode( ',', $order );
    }
    if ( ! is_array( $order ) ) {
        $order = array();
    }
    $order = array_values( array_intersect( array_map( 'sanitize_key', $order ), $allowed ) );
    foreach ( $allowed as $key ) {
        if ( ! in_array( $key, $order, true ) ) {
            if ( 'ugc' === $key && in_array( 'composer', $order, true ) ) {
                array_splice( $order, array_search( 'composer', $order, true ), 0, $key );
            } else {
                $order[] = $key;
            }
        }
    }
    return $order;
}

function stlai_seller_cost_register_settings() {
    register_setting( 'stlai_seller_cost_group', 'stlai_seller_cost_settings', array( 'sanitize_callback' => 'stlai_seller_cost_sanitize_settings' ) );
}

function stlai_seller_cost_sanitize_settings( $input ) {
    $existing = stlai_seller_cost_get_settings();
    $output = $existing;
    $output['usd_brl'] = isset( $input['usd_brl'] ) ? max( 0, (float) str_replace( ',', '.', $input['usd_brl'] ) ) : $existing['usd_brl'];
    if ( isset( $input['default_currency'] ) ) {
        $currency = strtoupper( sanitize_key( $input['default_currency'] ) );
        $output['default_currency'] = in_array( $currency, array( 'USD', 'BRL' ), true ) ? $currency : 'USD';
    }
    if ( isset( $input['flow_order'] ) ) {
        $output['flow_order'] = stlai_seller_cost_normalize_flow_order( $input['flow_order'] );
    }

    foreach ( array( 'name', 'text_model', 'image_model', 'video_model', 'audio_model', 'ugc_model' ) as $key ) {
        if ( isset( $input['default_flow'][ $key ] ) ) {
            $output['default_flow'][ $key ] = sanitize_text_field( $input['default_flow'][ $key ] );
        }
    }
    foreach ( array( 'text_generations', 'image_quantity', 'video_clips', 'seconds_per_clip', 'audio_seconds', 'ugc_enabled', 'ugc_clips', 'ugc_seconds_per_clip', 'composer_usd', 'platform_margin_percent', 'generations_per_day', 'days_per_month', 'suggested_multiplier' ) as $key ) {
        if ( isset( $input['default_flow'][ $key ] ) ) {
            $output['default_flow'][ $key ] = max( 0, (float) str_replace( ',', '.', $input['default_flow'][ $key ] ) );
        }
    }

    if ( isset( $input['models_json'] ) ) {
        $models = json_decode( wp_unslash( $input['models_json'] ), true );
        if ( is_array( $models ) ) {
            $output['models'] = stlai_seller_cost_sanitize_rows( $models, 'model' );
        }
    }

    if ( isset( $input['generations_json'] ) ) {
        $generations = json_decode( wp_unslash( $input['generations_json'] ), true );
        if ( is_array( $generations ) ) {
            $output['generations'] = stlai_seller_cost_sanitize_rows( $generations, 'generation' );
        }
    }

    return $output;
}

function stlai_seller_cost_sanitize_rows( $rows, $type ) {
    $clean = array();
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        if ( 'model' === $type ) {
            $id = sanitize_key( $row['id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }
            $clean[] = array(
                'id' => $id,
                'provider' => sanitize_text_field( $row['provider'] ?? '' ),
                'category' => sanitize_text_field( $row['category'] ?? '' ),
                'model' => sanitize_text_field( $row['model'] ?? '' ),
                'variant' => sanitize_text_field( $row['variant'] ?? '' ),
                'unit' => sanitize_key( $row['unit'] ?? 'generation' ),
                'usd' => max( 0, (float) ( $row['usd'] ?? 0 ) ),
                'active' => ! empty( $row['active'] ),
            );
        } else {
            $clean[] = array(
                'date' => sanitize_text_field( $row['date'] ?? gmdate( 'Y-m-d' ) ),
                'flow' => sanitize_text_field( $row['flow'] ?? 'Seller' ),
                'quantity' => max( 0, (float) ( $row['quantity'] ?? 1 ) ),
                'text_model' => sanitize_key( $row['text_model'] ?? '' ),
                'text_generations' => max( 0, (float) ( $row['text_generations'] ?? 0 ) ),
                'image_model' => sanitize_key( $row['image_model'] ?? '' ),
                'image_quantity' => max( 0, (float) ( $row['image_quantity'] ?? 0 ) ),
                'video_model' => sanitize_key( $row['video_model'] ?? '' ),
                'video_clips' => max( 0, (float) ( $row['video_clips'] ?? 0 ) ),
                'seconds_per_clip' => max( 0, (float) ( $row['seconds_per_clip'] ?? 0 ) ),
                'audio_model' => sanitize_key( $row['audio_model'] ?? '' ),
                'audio_seconds' => max( 0, (float) ( $row['audio_seconds'] ?? 0 ) ),
                'composer_usd' => max( 0, (float) ( $row['composer_usd'] ?? 0 ) ),
            );
        }
    }
    return $clean;
}

function stlai_seller_cost_log_generation( $generation ) {
    if ( ! is_array( $generation ) ) {
        return;
    }

    $settings = stlai_seller_cost_get_settings();
    $rows = stlai_seller_cost_sanitize_rows( array( $generation ), 'generation' );
    if ( empty( $rows ) ) {
        return;
    }

    $settings['generations'][] = $rows[0];
    update_option( 'stlai_seller_cost_settings', $settings, false );
}

function stlai_seller_cost_admin_menu() {
    add_menu_page(
        'STLAI Seller Custos',
        'Seller Custos',
        'manage_options',
        'stlai_seller_costs',
        'stlai_seller_cost_admin_page',
        'dashicons-chart-area',
        31
    );
}

function stlai_seller_cost_admin_page() {
    $settings = stlai_seller_cost_get_settings();
    ?>
    <div class="wrap stlai-cost-admin">
        <h1>STLAI Seller Custos</h1>
        <p>Configure os modelos e o fluxo padrão de projeção que aparecem no shortcode <strong>[stlai_seller_cost_dashboard]</strong>.</p>
        <style>
            .stlai-cost-admin-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:18px;max-width:1180px;margin-top:18px}
            .stlai-cost-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
            .stlai-cost-card h2{margin:0 0 8px;font-size:18px}
            .stlai-cost-card p{color:#646970;margin-top:0}
            .stlai-cost-fields{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px}
            .stlai-cost-fields label{display:flex;flex-direction:column;gap:6px;font-weight:600;color:#1d2327}
            .stlai-cost-fields input,.stlai-cost-fields select{width:100%;max-width:100%}
            .stlai-cost-order-list{display:grid;gap:8px;max-width:520px;margin-top:12px}
            .stlai-cost-order-item{align-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;cursor:grab;display:flex;justify-content:space-between;padding:10px 12px;font-weight:700}
            .stlai-cost-order-item span{color:#646970;font-size:12px;font-weight:600}
            .stlai-cost-order-item.is-dragging{opacity:.55}
            .stlai-cost-json{width:100%;min-height:260px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px}
            .stlai-cost-shortcode{display:inline-block;background:#f0f0f1;border:1px dashed #c3c4c7;border-radius:6px;padding:12px 16px;font-family:monospace;font-size:18px;color:#9B51E6;font-weight:700}
            @media(max-width:900px){.stlai-cost-fields{grid-template-columns:1fr 1fr}}
            @media(max-width:560px){.stlai-cost-fields{grid-template-columns:1fr}}
        </style>
        <form action="options.php" method="post" class="stlai-cost-admin-grid">
            <?php settings_fields( 'stlai_seller_cost_group' ); ?>
            <section class="stlai-cost-card">
                <h2>Publicação</h2>
                <p>Crie uma página nova no WordPress e use este shortcode. O financeiro fica separado do gerador de anúncios.</p>
                <span class="stlai-cost-shortcode">[stlai_seller_cost_dashboard]</span>
            </section>
            <section class="stlai-cost-card">
                <h2>Fluxo padrão</h2>
                <p>Esses valores entram como base na simulação e no resumo executivo.</p>
                <div class="stlai-cost-fields">
                    <?php stlai_seller_cost_input( 'Cotação USD/BRL', 'usd_brl', $settings['usd_brl'], 'number', '0.01' ); ?>
                    <?php stlai_seller_cost_currency_select( 'Moeda padrão', $settings['default_currency'] ?? 'USD' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Nome do fluxo', 'name', $settings['default_flow']['name'] ); ?>
                    <?php stlai_seller_cost_model_select( 'Texto', 'text_model', $settings ); ?>
                    <?php stlai_seller_cost_flow_input( 'Gerações de texto', 'text_generations', $settings['default_flow']['text_generations'], 'number', '1' ); ?>
                    <?php stlai_seller_cost_model_select( 'Modelo de imagem', 'image_model', $settings ); ?>
                    <?php stlai_seller_cost_flow_input( 'Quantidade de imagens', 'image_quantity', $settings['default_flow']['image_quantity'], 'number', '1' ); ?>
                    <?php stlai_seller_cost_model_select( 'Modelo de vídeo', 'video_model', $settings ); ?>
                    <?php stlai_seller_cost_flow_input( 'Clipes de vídeo', 'video_clips', $settings['default_flow']['video_clips'], 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Segundos por clipe', 'seconds_per_clip', $settings['default_flow']['seconds_per_clip'], 'number', '1' ); ?>
                    <?php stlai_seller_cost_model_select( 'Modelo de áudio', 'audio_model', $settings ); ?>
                    <?php stlai_seller_cost_flow_input( 'Áudio total em segundos', 'audio_seconds', $settings['default_flow']['audio_seconds'], 'number', '1' ); ?>
                    <?php stlai_seller_cost_model_select( 'Modelo UGC', 'ugc_model', $settings ); ?>
                    <?php stlai_seller_cost_flow_input( 'UGC ativo', 'ugc_enabled', $settings['default_flow']['ugc_enabled'] ?? 0, 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Vídeos UGC', 'ugc_clips', $settings['default_flow']['ugc_clips'] ?? 0, 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Segundos por UGC', 'ugc_seconds_per_clip', $settings['default_flow']['ugc_seconds_per_clip'] ?? 8, 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Composição/infra USD', 'composer_usd', $settings['default_flow']['composer_usd'], 'number', '0.01' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Margem operacional %', 'platform_margin_percent', $settings['default_flow']['platform_margin_percent'], 'number', '0.1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Gerações por dia', 'generations_per_day', $settings['default_flow']['generations_per_day'] ?? 10, 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Dias por mês', 'days_per_month', $settings['default_flow']['days_per_month'] ?? 22, 'number', '1' ); ?>
                    <?php stlai_seller_cost_flow_input( 'Multiplicador sugerido', 'suggested_multiplier', $settings['default_flow']['suggested_multiplier'] ?? 3, 'number', '0.1' ); ?>
                </div>
            </section>
            <section class="stlai-cost-card">
                <h2>Ordem do fluxo no dashboard</h2>
                <p>Arraste para mudar a ordem dos blocos que aparecem no painel de fluxo do front.</p>
                <input type="hidden" data-stlai-flow-order-input name="stlai_seller_cost_settings[flow_order]" value="<?php echo esc_attr( wp_json_encode( $settings['flow_order'] ) ); ?>">
                <div class="stlai-cost-order-list" data-stlai-flow-order-list>
                    <?php $flow_order_labels = stlai_seller_cost_flow_order_labels(); ?>
                    <?php foreach ( $settings['flow_order'] as $key ) : ?>
                        <div class="stlai-cost-order-item" draggable="true" data-flow-key="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $flow_order_labels[ $key ] ?? $key ); ?><span>arrastar</span></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="stlai-cost-card">
                <h2>Tabela de modelos e preços</h2>
                <p>Edite quando os provedores mudarem preço. Unidades aceitas: image, second, minute, generation.</p>
                <textarea class="stlai-cost-json" name="stlai_seller_cost_settings[models_json]"><?php echo esc_textarea( wp_json_encode( $settings['models'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
            </section>
            <?php submit_button( 'Salvar dashboard financeiro' ); ?>
        </form>
        <script>
        (function(){
            const list=document.querySelector("[data-stlai-flow-order-list]");
            const input=document.querySelector("[data-stlai-flow-order-input]");
            if(!list||!input){return;}
            let dragging=null;
            function sync(){
                input.value=JSON.stringify(Array.from(list.querySelectorAll("[data-flow-key]")).map(item=>item.dataset.flowKey));
            }
            list.addEventListener("dragstart", function(event){
                dragging=event.target.closest("[data-flow-key]");
                if(dragging){dragging.classList.add("is-dragging");}
            });
            list.addEventListener("dragend", function(){
                if(dragging){dragging.classList.remove("is-dragging");}
                dragging=null;
                sync();
            });
            list.addEventListener("dragover", function(event){
                event.preventDefault();
                const target=event.target.closest("[data-flow-key]");
                if(!dragging||!target||target===dragging){return;}
                const rect=target.getBoundingClientRect();
                const after=event.clientY > rect.top + rect.height / 2;
                list.insertBefore(dragging, after ? target.nextSibling : target);
            });
            sync();
        })();
        </script>
    </div>
    <?php
}

function stlai_seller_cost_flow_order_labels() {
    return array(
        'volume' => 'Volume e preço sugerido',
        'text' => 'Geração de texto',
        'image' => 'Geração de imagens',
        'video' => 'Geração de vídeo',
        'audio' => 'Narração / áudio',
        'ugc' => 'UGC / Seedance',
        'composer' => 'Composição final',
    );
}

function stlai_seller_cost_currency_select( $label, $value ) {
    echo '<label>' . esc_html( $label ) . '<select name="stlai_seller_cost_settings[default_currency]">';
    echo '<option value="USD" ' . selected( $value, 'USD', false ) . '>USD</option>';
    echo '<option value="BRL" ' . selected( $value, 'BRL', false ) . '>BRL</option>';
    echo '</select></label>';
}

function stlai_seller_cost_help_tip( $image ) {
    $url = STLAI_SELLER_COST_URL . 'assets/help/' . ltrim( $image, '/' );
    echo '<span class="stlai-help-tip" tabindex="0" aria-label="Ver exemplo">?<span><img src="' . esc_url( $url ) . '" alt=""></span></span>';
}

function stlai_seller_cost_input( $label, $key, $value, $type = 'text', $step = '' ) {
    echo '<label>' . esc_html( $label ) . '<input type="' . esc_attr( $type ) . '" ' . ( $step ? 'step="' . esc_attr( $step ) . '"' : '' ) . ' name="stlai_seller_cost_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label>';
}

function stlai_seller_cost_flow_input( $label, $key, $value, $type = 'text', $step = '' ) {
    echo '<label>' . esc_html( $label ) . '<input type="' . esc_attr( $type ) . '" ' . ( $step ? 'step="' . esc_attr( $step ) . '"' : '' ) . ' name="stlai_seller_cost_settings[default_flow][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label>';
}

function stlai_seller_cost_model_select( $label, $key, $settings ) {
    echo '<label>' . esc_html( $label ) . '<select name="stlai_seller_cost_settings[default_flow][' . esc_attr( $key ) . ']">';
    foreach ( $settings['models'] as $model ) {
        $display_categories = array( 'Video' => 'Vídeo', 'Audio' => 'Áudio' );
        $category = $display_categories[ $model['category'] ] ?? $model['category'];
        $text = trim( $category . ' - ' . $model['provider'] . ' - ' . $model['model'] . ' ' . $model['variant'] );
        echo '<option value="' . esc_attr( $model['id'] ) . '" ' . selected( $settings['default_flow'][ $key ], $model['id'], false ) . '>' . esc_html( $text ) . '</option>';
    }
    echo '</select></label>';
}

function stlai_seller_cost_render_shortcode() {
    $settings = stlai_seller_cost_get_settings();
    wp_enqueue_style( 'stlai-seller-cost-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', array(), null );
    wp_enqueue_style( 'stlai-seller-cost-dashboard', STLAI_SELLER_COST_URL . 'assets/css/dashboard.css', array(), STLAI_SELLER_COST_VERSION );
    wp_enqueue_script( 'stlai-seller-cost-dashboard', STLAI_SELLER_COST_URL . 'assets/js/dashboard.js', array(), STLAI_SELLER_COST_VERSION, true );
    wp_localize_script( 'stlai-seller-cost-dashboard', 'stlaiSellerCostData', $settings );

    ob_start();
    ?>
    <div class="stlai-cost-dashboard" data-stlai-cost-dashboard style="visibility:hidden;opacity:0;">
        <header class="stlai-cost-topbar">
            <div class="stlai-cost-brand">
                <img src="https://stlflix.negociosdobruno.com.br/wp-content/uploads/2026/04/stl-ai-logo-1.png" alt="STLAI">
                <span>Seller</span>
                <em>Custos de IA</em>
            </div>
            <div class="stlai-cost-actions">
                <span class="stlai-cost-date"><?php echo esc_html( wp_date( 'd/m/Y' ) ); ?></span>
                <button type="button" class="stlai-cost-btn stlai-cost-btn-secondary" data-export-csv>Exportar XLS</button>
                <button type="button" class="stlai-cost-btn stlai-cost-btn-primary" data-export-pdf>Relatório PDF</button>
            </div>
        </header>

        <section class="stlai-cost-title-row">
            <h1>Custos de gera&ccedil;&atilde;o</h1>
            <div class="stlai-cost-config">
                <label>Moeda<select data-currency><option value="USD">USD</option><option value="BRL">BRL</option></select></label>
                <label>Câmbio R$<input type="number" min="0" step="0.01" data-usd-brl></label>
            </div>
        </section>

        <section class="stlai-cost-kpis">
            <article><span>Custo / gera&ccedil;&atilde;o</span><strong data-kpi-generation>R$ 0,00</strong><small>configura&ccedil;&atilde;o atual</small></article>
            <article><span>Imagens</span><strong data-kpi-images-cost>R$ 0,00</strong><small data-kpi-images-sub>0 imagens</small></article>
            <article><span>Vídeo</span><strong data-kpi-video-cost>R$ 0,00</strong><small data-kpi-video-sub>0s totais</small></article>
            <article><span>Áudio + composição</span><strong data-kpi-audio-cost>R$ 0,00</strong><small data-kpi-audio-sub>áudio e infra</small></article>
            <article><span>Sugerido ao usuário</span><strong data-kpi-suggested>R$ 0,00</strong><small data-kpi-margin>multiplicador salvo</small></article>
        </section>

        <section class="stlai-cost-tabs" data-cost-tabs>
            <button type="button" class="is-active" data-tab-target="projection">Proje&ccedil;&atilde;o</button>
            <button type="button" data-tab-target="plan">Criar Plano</button>
        </section>

        <section class="stlai-cost-panel stlai-plan-panel" data-tab-panel="plan" hidden>
            <div class="stlai-cost-panel-head">
                <div><h2>Criar Plano</h2></div>
                <small>monte um pacote e compare o preço final</small>
            </div>
            <div class="stlai-plan-layout">
                <div class="stlai-plan-column">
                    <div class="stlai-plan-builder" data-plan-builder="basic">
                        <h3>B&aacute;sico</h3>
                        <label><span>Nome do plano</span><input type="text" value="B&aacute;sico" data-plan-field="name"></label>
                        <label class="stlai-plan-field-text"><span>Textos gerados <?php stlai_seller_cost_help_tip( 'textos-gerados.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="textGenerations"></label>
                        <label class="stlai-plan-field-image"><span>Imagens individuais <?php stlai_seller_cost_help_tip( 'imagens-individuais.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="images"></label>
                        <label class="stlai-plan-field-image"><span>Combos 2x2 <?php stlai_seller_cost_help_tip( 'combos-2x2.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="combos"></label>
                        <div class="stlai-plan-inline stlai-plan-field-video"><label><span>Vídeos <?php stlai_seller_cost_help_tip( 'videos.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="videos"></label><label><span>Segundos</span><input type="number" min="0" step="1" value="8" data-plan-field="videoSeconds"></label></div>
                        <div class="stlai-plan-inline stlai-plan-field-ugc"><label><span>UGC Vídeo Realista <?php stlai_seller_cost_help_tip( 'ugc-video.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="ugcVideos"></label><label><span>Segundos</span><input type="number" min="0" step="1" value="8" data-plan-field="ugcSeconds"></label></div>
                        <label><span>Gera&ccedil;&otilde;es / mês</span><input type="number" min="0" step="1" value="0" data-plan-field="monthlyGenerations"></label>
                        <label><span>Multiplicador final</span><input type="number" min="0" step="0.1" value="3" data-plan-field="multiplier"></label>
                    </div>
                    <article class="stlai-plan-card" data-plan-card="basic">
                        <span>B&aacute;sico</span>
                        <strong data-plan-output="name">B&aacute;sico</strong>
                        <ul data-plan-output="list"></ul>
                        <em data-plan-output="price">$0.00 sugerido</em>
                        <label class="stlai-plan-card-toggle"><input type="checkbox" checked data-plan-card-toggle="basic"> Mostrar valor sugerido</label>
                    </article>
                </div>
                <div class="stlai-plan-column">
                    <div class="stlai-plan-builder" data-plan-builder="premium">
                        <h3>Premium</h3>
                        <label><span>Nome do plano</span><input type="text" value="Premium" data-plan-field="name"></label>
                        <label class="stlai-plan-field-text"><span>Textos gerados <?php stlai_seller_cost_help_tip( 'textos-gerados.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="textGenerations"></label>
                        <label class="stlai-plan-field-image"><span>Imagens individuais <?php stlai_seller_cost_help_tip( 'imagens-individuais.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="images"></label>
                        <label class="stlai-plan-field-image"><span>Combos 2x2 <?php stlai_seller_cost_help_tip( 'combos-2x2.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="combos"></label>
                        <div class="stlai-plan-inline stlai-plan-field-video"><label><span>Vídeos <?php stlai_seller_cost_help_tip( 'videos.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="videos"></label><label><span>Segundos</span><input type="number" min="0" step="1" value="8" data-plan-field="videoSeconds"></label></div>
                        <div class="stlai-plan-inline stlai-plan-field-ugc"><label><span>UGC Vídeo Realista <?php stlai_seller_cost_help_tip( 'ugc-video.png' ); ?></span><input type="number" min="0" step="1" value="0" data-plan-field="ugcVideos"></label><label><span>Segundos</span><input type="number" min="0" step="1" value="8" data-plan-field="ugcSeconds"></label></div>
                        <label><span>Gera&ccedil;&otilde;es / mês</span><input type="number" min="0" step="1" value="0" data-plan-field="monthlyGenerations"></label>
                        <label><span>Multiplicador final</span><input type="number" min="0" step="0.1" value="3" data-plan-field="multiplier"></label>
                    </div>
                    <article class="stlai-plan-card stlai-plan-card-custom" data-plan-card="premium">
                        <span>Premium</span>
                        <strong data-plan-output="name">Premium</strong>
                        <ul data-plan-output="list"></ul>
                        <em data-plan-output="price">$0.00 sugerido</em>
                        <label class="stlai-plan-card-toggle"><input type="checkbox" checked data-plan-card-toggle="premium"> Mostrar valor sugerido</label>
                    </article>
                </div>
                <div class="stlai-plan-summary">
                    <div><span>B&aacute;sico / gera&ccedil;&atilde;o</span><strong data-plan-summary="basicGeneration">$0.00</strong></div>
                    <div><span>Premium / gera&ccedil;&atilde;o</span><strong data-plan-summary="premiumGeneration">$0.00</strong></div>
                    <div><span>Diferen&ccedil;a entre planos</span><strong data-plan-summary="difference">$0.00</strong></div>
                </div>
                <div class="stlai-plan-comparison">
                    <div><span>B&aacute;sico mensal</span><strong data-plan-summary="basicMonth">$0.00</strong></div>
                    <div><span>Premium mensal</span><strong data-plan-summary="premiumMonth">$0.00</strong></div>
                    <div><span>Premium vs B&aacute;sico</span><strong data-plan-summary="comparison">0%</strong></div>
                </div>
            </div>
        </section>

        <div data-tab-panel="projection">
        <main class="stlai-cost-main">
            <section class="stlai-cost-panel stlai-cost-simulator">
                <div class="stlai-cost-panel-head">
                    <div><h2>Fluxo de gera&ccedil;&atilde;o</h2></div>
                    <button type="button" class="stlai-cost-btn stlai-cost-btn-secondary" data-reset-flow>Restaurar fluxo salvo</button>
                </div>
                <div class="stlai-cost-flow-grid" data-flow-grid>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-volume" data-flow-section="volume">
                        <h3>Volume</h3>
                        <label>Gera&ccedil;&otilde;es / dia<input type="number" min="0" step="1" data-flow-generations-day></label>
                        <label>Dias / mês<input type="number" min="0" step="1" data-flow-days-month></label>
                        <label>Preço sugerido x<input type="number" min="0" step="0.1" data-flow-suggested-multiplier></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-text" data-flow-section="text">
                        <h3>Texto</h3>
                        <label>Modelo<select data-flow-text-model></select></label>
                        <label>Gerações texto<input type="number" min="0" step="1" data-flow-text-generations></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-image" data-flow-section="image">
                        <h3>Imagem</h3>
                        <label>Modelo<select data-flow-image-model></select></label>
                        <label>Qtd. imagens<input type="number" min="0" step="1" data-flow-image-quantity></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-video" data-flow-section="video">
                        <h3>Vídeo</h3>
                        <label>Modelo<select data-flow-video-model></select></label>
                        <label>Clipes<input type="number" min="0" step="1" data-flow-video-clips></label>
                        <label>Segundos por clipe<input type="number" min="0" step="1" data-flow-seconds-per-clip></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-audio" data-flow-section="audio">
                        <h3>Áudio</h3>
                        <label>Modelo<select data-flow-audio-model></select></label>
                        <label>Áudio total seg.<input type="number" min="0" step="1" data-flow-audio-seconds></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-ugc" data-flow-section="ugc">
                        <h3>UGC</h3>
                        <label class="stlai-toggle-row"><input type="checkbox" data-flow-ugc-enabled><span>Habilitar UGC</span></label>
                        <label>Modelo<select data-flow-ugc-model></select></label>
                        <label>Vídeos UGC<input type="number" min="0" step="1" data-flow-ugc-clips></label>
                        <label>Segundos por UGC<input type="number" min="0" step="1" data-flow-ugc-seconds-per-clip></label>
                    </div>
                    <div class="stlai-cost-flow-section stlai-cost-flow-section-composer" data-flow-section="composer">
                        <h3>Composição</h3>
                        <label>Composição USD<input type="number" min="0" step="0.01" data-flow-composer-usd></label>
                    </div>
                </div>
            </section>

            <aside class="stlai-cost-panel stlai-cost-chart-panel">
                <div class="stlai-cost-chart-head">
                    <div>
                        <span>Proje&ccedil;&atilde;o mensal</span>
                        <strong data-chart-month-total>R$ 0,00</strong>
                        <em data-chart-subtitle>30 dias</em>
                    </div>
                    <div>
                        <span>Preço sugerido / gera&ccedil;&atilde;o</span>
                        <strong data-total-suggested>R$ 0,00</strong>
                    </div>
                </div>
                <div class="stlai-cost-chart" data-projection-chart></div>
                <div class="stlai-cost-chart-foot">
                    <span data-chart-first-day>Dia 1</span>
                    <span data-chart-last-day>Dia 30</span>
                </div>
                <div class="stlai-cost-chart-tooltip" data-chart-tooltip></div>
            </aside>
        </main>

        <section class="stlai-cost-panel">
            <div class="stlai-cost-panel-head"><div><h2>Detalhamento de custos</h2></div></div>
            <div class="stlai-cost-table-wrap">
                <table class="stlai-cost-table">
                    <thead><tr><th>Etapa</th><th>Tipo</th><th>Modelo ativo</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th>Proporção</th></tr></thead>
                    <tbody data-cost-table></tbody>
                </table>
            </div>
        </section>

        <section class="stlai-cost-split">
            <div class="stlai-cost-panel">
                <div class="stlai-cost-panel-head"><div><h2>Projeção de volume</h2></div></div>
                <div class="stlai-cost-projection">
                    <div><span>Custo diário estimado</span><strong data-projection-day>R$ 0,00</strong></div>
                    <div><span>Custo mensal estimado</span><strong data-projection-month>R$ 0,00</strong></div>
                    <div><span>Receita sugerida / mês</span><strong data-projection-revenue>R$ 0,00</strong></div>
                </div>
            </div>
            <div class="stlai-cost-panel">
                <div class="stlai-cost-panel-head"><div><h2>Mix por categoria</h2></div></div>
                <div class="stlai-cost-bars" data-category-bars></div>
            </div>
        </section>

        <section class="stlai-cost-panel">
            <div class="stlai-cost-panel-head"><div><h2>Comparativo de modelos - Imagem</h2></div><small data-image-comparison-note>baseado nas imagens do fluxo</small></div>
            <div class="stlai-cost-table-wrap">
                <table class="stlai-cost-table">
                    <thead><tr><th>Modelo</th><th>Provedor</th><th>Unitário</th><th>Total</th><th>Vs. atual</th></tr></thead>
                    <tbody data-image-comparison></tbody>
                </table>
            </div>
        </section>

        <section class="stlai-cost-panel">
            <div class="stlai-cost-panel-head"><div><h2>Referência de preços - Vídeo</h2></div><small>custo = $/s x duração x clipes</small></div>
            <div class="stlai-cost-table-wrap">
                <table class="stlai-cost-table">
                    <thead><tr><th>Modelo</th><th>Provedor</th><th>$/segundo</th><th>4s</th><th>8s</th><th>10s</th><th>15s</th></tr></thead>
                    <tbody data-video-reference></tbody>
                </table>
            </div>
        </section>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
