<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="stlai-vision-ads-pro-wrapper">
<canvas id="offscreen-canvas"></canvas>
<div id="tw"></div>

<div id="vision-overlay">
  <div class="vision-spinner">
    <div class="vs-ring"></div>
    <div class="vs-ring"></div>
    <div class="vs-ring"></div>
    <div class="vs-eye">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
    </div>
  </div>
  <div class="vision-overlay-title">⚡ Mapeando imagem</div>
  <div class="vision-overlay-text" id="vo-text">A inteligência artificial está decodificando todos os atributos e materiais do seu produto em 3D...</div>
</div>

<div class="stlai-drawer-backdrop" id="stlai-drawer-backdrop"></div>
<div class="stlai-drawer" id="stlai-drawer">
  <div class="drawer-header" style="display:flex; flex-direction:column; gap:10px; width:100%; align-items:stretch;">
    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
      <h2>Análise Competitiva Profissional</h2>
      <button class="drawer-close" onclick="closeMarketDrawer()">&times;</button>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <input type="text" id="input-nova-busca" class="fi" style="background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); color:#fff; flex:1; padding:8px 12px; font-size:13px;" placeholder="Refinar busca de mercado...">
      <button class="btn bp" id="btn-refazer-busca" onclick="refazerBuscaMercado()" style="padding:8px 16px; flex-shrink:0;">Refazer Busca</button>
    </div>
  </div>
  <div class="drawer-content" style="display:flex; flex-direction:column; position:relative; flex:1;">
    <div id="drawer-loading" style="display:none; text-align:center; padding: 60px 20px; flex:1; flex-direction:column; justify-content:center; align-items:center; min-height: 50vh;">
       <div class="vision-spinner" style="margin: 0 auto 30px;">
         <div class="vs-ring"></div>
         <div class="vs-ring"></div>
         <div class="vs-ring"></div>
         <div class="vs-eye">
           <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
         </div>
       </div>
       <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:8px;color:#fff">⚡ Analisando Mercado</div>
       <p style="color:var(--tx2); font-size:13px;" id="drawer-loading-msg">Consultando Mercado Livre e Google Shopping...</p>
    </div>
    <div id="drawer-results" style="display:none; flex:1;">
       <!-- ABAS DO OFF-CANVAS -->
       <div class="drawer-tabs" style="display:flex; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:24px;">
          <button class="d-tab active" id="tab-calc" onclick="switchDrawerTab('calc')">Calculadora e Análise de Concorrentes</button>
          <button class="d-tab" id="tab-strat" onclick="switchDrawerTab('strat')">🚀 Estratégia de Vendas</button>
       </div>
       
       <!-- CONTEÚDO: ABA CALCULADORA -->
       <div id="drawer-tab-calc">
           <div class="stats-cards">
              <div class="stat-card">
                 <span class="stat-label">Preço Mínimo</span>
                 <span class="stat-val" id="calc-min"></span>
              </div>
              <div class="stat-card">
                 <span class="stat-label">Preço Médio</span>
                 <span class="stat-val" id="calc-medio"></span>
              </div>
              <div class="stat-card">
                 <span class="stat-label">Preço Máximo</span>
                 <span class="stat-val" id="calc-max"></span>
              </div>
           </div>
           
           <div class="calc-margin">
             <div class="calc-group">
               <label class="calc-label">Custo Produção (R$)</label>
               <input type="number" id="calc-custo" class="calc-input" placeholder="0.00" value="0">
             </div>
             <div class="calc-group">
               <label class="calc-label">Embalagem (R$)</label>
               <input type="number" id="calc-emb" class="calc-input" placeholder="0.00" value="0">
             </div>
             <div class="calc-group">
               <label class="calc-label">Frete Fixo (R$)</label>
               <input type="number" id="calc-frete" class="calc-input" placeholder="0.00" value="0">
             </div>
             <div class="calc-group">
               <label class="calc-label">Taxa Marketplace (%)</label>
               <input type="number" id="calc-taxa" class="calc-input" placeholder="0" value="18">
             </div>
             <div class="calc-group">
               <label class="calc-label">Impostos (Ex: Simples) (%)</label>
               <input type="number" id="calc-impostos" class="calc-input" placeholder="0" value="0">
             </div>
             <div class="calc-group" style="flex: 1.5; min-width: 150px;">
               <label class="calc-label">Preço de Venda Simulado (R$)</label>
               <div style="display:flex; gap:5px; margin-bottom:6px;">
                 <button class="btn bg bsm" style="padding:2px 6px; font-size:10px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#9898b8;" onclick="setCalcSimulado('min')">Usar Mínimo</button>
                 <button class="btn bg bsm" style="padding:2px 6px; font-size:10px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#9898b8;" onclick="setCalcSimulado('med')">Usar Médio</button>
                 <button class="btn bg bsm" style="padding:2px 6px; font-size:10px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#9898b8;" onclick="setCalcSimulado('max')">Usar Máximo</button>
               </div>
               <input type="number" id="calc-simulado" class="calc-input" style="font-weight:800; font-size:18px; color:var(--acc-h); border-color:var(--acc-h)" placeholder="0.00" value="0">
             </div>
             <div class="calc-group">
               <label class="calc-label" style="color:#2dd4bf">Lucro Líquido</label>
               <div class="calc-result positive" id="calc-lucro">R$ 0,00</div>
             </div>
             <div class="calc-group">
               <label class="calc-label" style="color:var(--gold)">Margem (%)</label>
               <div class="calc-result" id="calc-margem" style="color:var(--gold)">0,0%</div>
             </div>
           </div>
           
           <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
             <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin:0; color:var(--tx)">Concorrentes Encontrados</h3>
           </div>
           <div class="filters-container" id="market-filters"></div>
           <div class="grid-produtos" id="grid-produtos"></div>
       </div>

       <!-- CONTEÚDO: ABA ESTRATÉGIA -->
       <div id="drawer-tab-strat" style="display:none;">
           <div class="ia-analysis">
             <h3>Estratégia Sugerida pela IA</h3>
             <p id="ia-estrategia"></p>
             <div id="ia-tags" class="mk-tags" style="margin-top:16px;"></div>
           </div>
           
           <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin:0 0 16px; color:var(--tx)">Estratégia de Preço por Fase</h3>
           <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:30px;">
               <!-- Card Lançamento -->
               <div style="background:rgba(45,212,191,0.05); border:1px solid rgba(45,212,191,0.2); border-radius:12px; padding:20px; display:flex; gap:20px; align-items:center;">
                   <div style="flex:1;">
                       <h4 style="font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:var(--mint); margin:0 0 4px; text-transform:uppercase; letter-spacing:1px;">Lançamento</h4>
                       <p style="font-size:13px; color:var(--tx2); margin:0;">Para as primeiras 10 vendas. Ganhe avaliações e posicione o anúncio.</p>
                   </div>
                   <div style="text-align:right;">
                       <div style="font-family:'Syne',sans-serif; font-size:24px; font-weight:800; color:var(--tx);" id="strat-preco-min">R$ 0,00</div>
                   </div>
               </div>
               
               <!-- Card Crescimento -->
               <div style="background:rgba(108,71,255,0.05); border:1px solid rgba(108,71,255,0.2); border-radius:12px; padding:20px; display:flex; gap:20px; align-items:center;">
                   <div style="flex:1;">
                       <h4 style="font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:var(--acc-h); margin:0 0 4px; text-transform:uppercase; letter-spacing:1px;">Crescimento</h4>
                       <p style="font-size:13px; color:var(--tx2); margin:0;">Equilibre volume de vendas e margem de lucro.</p>
                   </div>
                   <div style="text-align:right;">
                       <div style="font-family:'Syne',sans-serif; font-size:24px; font-weight:800; color:var(--tx);" id="strat-preco-med">R$ 0,00</div>
                   </div>
               </div>
               
               <!-- Card Consolidação -->
               <div style="background:rgba(155,127,255,0.05); border:1px solid rgba(155,127,255,0.2); border-radius:12px; padding:20px; display:flex; gap:20px; align-items:center;">
                   <div style="flex:1;">
                       <h4 style="font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:#c4b5fd; margin:0 0 4px; text-transform:uppercase; letter-spacing:1px;">Consolidação</h4>
                       <p style="font-size:13px; color:var(--tx2); margin:0;">Maximize seu lucro apoiado na sua reputação já estabelecida.</p>
                   </div>
                   <div style="text-align:right;">
                       <div style="font-family:'Syne',sans-serif; font-size:24px; font-weight:800; color:var(--tx);" id="strat-preco-max">R$ 0,00</div>
                   </div>
               </div>
           </div>
           
           <h3 style="font-family:'Syne',sans-serif; font-size:16px; font-weight:700; margin:0 0 16px; color:var(--tx)">Termômetro de Marketplaces</h3>
           <div id="termometro-marketplaces" style="display:flex; flex-direction:column; gap:12px;"></div>
       </div>

    </div>
  </div>
</div>

<div class="modal-backdrop" id="reset-md">
  <div class="modal" style="max-width:400px;text-align:center">
    <div class="modal-head" style="justify-content:center;margin-bottom:8px">
      <h3>Gerar Novo Anúncio</h3>
    </div>
    <div class="modal-note" style="margin-bottom:20px">Deseja gerar um novo anúncio? Os dados atuais serão descartados e o fluxo será reiniciado.</div>
    <div class="arow" style="margin-top:20px;justify-content:center;gap:12px;padding-top:0;border-top:none">
      <button class="btn bs" onclick="document.getElementById('reset-md').classList.remove('show')">Cancelar</button>
      <button class="btn bp" style="background:var(--coral);box-shadow:0 4px 18px rgba(255,77,109,0.3)" onclick="performReset()">Sim, Descartar</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="dims-md">
  <div class="modal" style="max-width:400px">
    <div class="modal-head">
      <h3>Informar Medidas</h3>
      <button class="btn bs bsm" type="button" onclick="document.getElementById('dims-md').classList.remove('show')">Fechar</button>
    </div>
    <div class="modal-note">Insira as medidas para gerar uma imagem técnica com dimensões.</div>
    <div class="g3">
      <div class="fg"><label class="fl">Largura (X)</label><input class="fi" type="number" id="dm-x" placeholder="cm" step="0.1"></div>
      <div class="fg"><label class="fl">Altura (Y)</label><input class="fi" type="number" id="dm-y" placeholder="cm" step="0.1"></div>
      <div class="fg"><label class="fl">Prof. (Z)</label><input class="fi" type="number" id="dm-z" placeholder="cm" step="0.1"></div>
    </div>
    <div class="arow" style="margin-top:6px">
      <button class="btn bp" type="button" style="width:100%;justify-content:center" onclick="genDimsImage(this)">Gerar Imagem de Medidas</button>
    </div>
  </div>
</div>

<div class="wrap">

<header class="topbar">
  <div class="tl" style="cursor:pointer" onclick="resetApp()">
    <img src="https://stlflix.negociosdobruno.com.br/wp-content/uploads/2026/04/stl-ai-logo-1.png" alt="STLAI" onerror="this.style.display='none'">
    <div class="tl-txt"><span>Seller</span></div>
  </div>
  <div class="tr">
    
    <div class="plan-chip" id="plan-chip" onclick="togglePlan()">BÁSICO</div>
    <div style="font-size:12px;color:var(--tx2)" id="cr-disp">1000 créditos</div>
  </div>
</header>

<nav class="snav">
  <div class="si"><button class="sb active" id="nav-1" onclick="tryStep(1)"><span class="sn">1</span>Upload</button></div>
  <span class="sa">›</span>
  <div class="si"><button class="sb locked" id="nav-2" onclick="tryStep(2)"><span class="sn">2</span>Contexto</button></div>
  <span class="sa">›</span>
  <div class="si"><button class="sb locked" id="nav-3" onclick="tryStep(3)"><span class="sn">3</span>Textos</button></div>
  <span class="sa">›</span>
  <div class="si"><button class="sb locked" id="nav-4" onclick="tryStep(4)"><span class="sn">4</span>Imagens</button></div>
  <span class="sa">›</span>
  <div class="si"><button class="sb locked" id="nav-5" onclick="tryStep(5)"><span class="sn">5</span>Vídeo</button></div>
  <span class="sa">›</span>
  <div class="si"><button class="sb locked" id="nav-6" onclick="tryStep(6)"><span class="sn">6</span>Resultado</button></div>
</nav>

<main class="main">

<section class="screen active" id="s1" style="position:relative">
  <div class="s1-bg-glow"></div>
  <div class="sh" style="text-align:center;margin-bottom:40px;position:relative;z-index:2">
    <div class="sh-tag" style="background:transparent;border-color:rgba(108,71,255,0.3)">✦ Passo 1 de 6</div>
    <h1 style="font-size:42px;margin-bottom:12px;letter-spacing:-1px">Envie as fotos<br><span style="color:var(--acc-h)">do produto</span></h1>
    <p style="margin:0 auto;color:var(--tx2)">Até 5 imagens. A IA extrai automaticamente cor e material<br>ao prosseguir.</p>
  </div>
  <div class="card" style="background:transparent;border:none;padding:0;position:relative;z-index:2">
    <div class="dz" id="dz" ondragover="dzEv(event,'dz','add','over')" ondragleave="dzEv(event,'dz','remove','over')" ondrop="onDrop(event)">
      <input type="file" id="fi" accept="image/*" multiple onchange="onFChange(event)">
      <div id="dz-empty-state">
        <div class="dz-ic"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>
        <div class="dz-ti">Arraste ou clique para enviar</div>
        <div class="dz-su">Até 5 fotos do produto</div>
        <div class="dz-ch"><span class="ch">JPG</span><span class="ch">PNG</span><span class="ch">WEBP</span><span class="ch">Máx 10MB</span></div>
      </div>
      <div class="tg" id="tg" style="position:relative;z-index:2"></div>
    </div>
    <div id="up-note" style="font-size:12px;color:var(--tx3);margin-top:10px;display:none"><span id="up-cnt">0</span>/5 imagem(ns)</div>
  </div>
  <div class="vbar" id="u-vbar" style="display:none">
    <div class="vbl">
      <h4>✨ Vision ativada</h4>
      <p id="u-vst">Entendendo seu produto para construir o melhor anúncio.</p>
    </div>
    <div id="u-vspin" class="vbs"><div class="spin"></div>Analisando...</div>
  </div>
  <div class="arow">
    <div></div>
    <button class="btn bp" id="btn1" disabled onclick="goStep2()">Continuar →</button>
  </div>
</section>

<section class="screen" id="s2">
  <div class="sh">
    <div class="sh-tag">✦ Passo 2 de 6</div>
    <h1 style="margin-bottom:12px;letter-spacing:-1px">Configure<br><span style="color:var(--acc-h)">o anúncio</span><div class="sh-info-wrap"><div class="sh-info-ic">?</div><div class="sh-tooltip">A Vision AI preencheu os dados do produto. Verifique e ajuste as informações antes de gerar os textos. As medidas devem ser preenchidas manualmente se desejar uma imagem técnica.</div></div></h1>
    <p>Escolha o plano e revise os dados do produto. A análise da imagem já sugeriu o preenchimento inicial.</p>
  </div>

  <div class="card">
    <div class="sl">Plano</div>
    <div class="pg">
      <div class="pc" id="pc-b" onclick="setPlan('basic')">
        <input type="radio" name="plan" value="basic">
        <div class="pn">Básico</div>
        <div class="pd">Para validação rápida.</div>
        <ul class="pf"><li>4 títulos + 1 descrição</li><li>4 imagens individuais 1:1</li><li>1 imagem combo 2x2</li></ul>
        <div style="margin-top:10px;font-size:11px;color:var(--tx3)">⚡ 15 créditos</div>
      </div>
      <div class="pc" id="pc-p" onclick="setPlan('premium')">
        <input type="radio" name="plan" value="premium">
        <div class="pb">PREMIUM</div>
        <div class="pn">Premium</div>
        <div class="pd">Pacote completo profissional.</div>
        <ul class="pf"><li>4 títulos + descrição avançada</li><li>8 imagens individuais premium</li><li>1 imagem combo 2x2 premium</li><li>Configuração de vídeo</li></ul>
        <div style="margin-top:10px;font-size:11px;color:var(--tx3)">⚡ 80 créditos</div>
      </div>
    </div>
  </div>

  <div class="vbar" id="vbar">
    <div class="vbl">
      <h4>✨ Leitura automática da Vision</h4>
      <p id="v-st">Aguardando imagens para começar.</p>
    </div>
    <div id="v-spin" class="vbs" style="display:none"><div class="spin"></div>Analisando...</div>
    <div id="v-actions" style="display:none; gap:20px; align-items:center;">
      <button class="btn bsm" id="btn-af" style="background:linear-gradient(135deg,rgba(108,71,255,.2),rgba(244,185,66,.1));border:1px solid var(--bd-h);color:var(--acc-h)" onclick="applyAF()">⚡ Reaplicar sugestões</button> 
      <button class="btn-ia-sync" id="btn-ia-sync" onclick="trigVision()"><span style="width:8px;height:8px;border-radius:50%;background:#2dd4bf;display:inline-block;box-shadow:0 0 5px #2dd4bf; animation:pulse 2s infinite ease-in-out;"></span> IA SYNC</button>
    </div>
  </div>

  <div class="card">
    <div class="sl">Dados do produto</div>
    <div class="fg"><label class="fl">Nome <span class="req">*</span></label><input class="fi" type="text" id="f-nm" placeholder="Ex: Mosquetão Alumínio Laranja EDC"></div>
    <div class="fg"><label class="fl">Descrição / Contexto</label><textarea class="ft" id="f-dc" placeholder="Material, funcionalidades, diferenciais, público-alvo..."></textarea></div>
    <div class="g3">
      <div class="fg"><label class="fl">Largura cm (X)</label><input class="fi" type="number" id="f-x" placeholder="8" min="0" step="0.1"></div>
      <div class="fg"><label class="fl">Altura cm (Y)</label><input class="fi" type="number" id="f-y" placeholder="4" min="0" step="0.1"></div>
      <div class="fg"><label class="fl">Profund. cm (Z)</label><input class="fi" type="number" id="f-z" placeholder="1" min="0" step="0.1"></div>
    </div>
    <div class="g2">
      <div class="fg"><label class="fl">Peso (gramas)</label><input class="fi" type="number" id="f-wt" placeholder="45" min="0"></div>
      <div class="fg"><label class="fl">Idioma</label>
        <select class="fs" id="f-lg">
          <option value="pt-BR">🇧🇷 Português (Brasil)</option>
          <option value="en-US">🇺🇸 English</option>
          <option value="es-ES">🇪🇸 Español</option>
        </select>
      </div>
    </div>
    <div class="fg"><label class="fl">Voltagem</label>
      <div class="g4">
        <div class="volt-opt"><input type="radio" name="vlt" id="v1" value="110V"><label for="v1">110V</label></div>
        <div class="volt-opt"><input type="radio" name="vlt" id="v2" value="220V"><label for="v2">220V</label></div>
        <div class="volt-opt"><input type="radio" name="vlt" id="v3" value="Bivolt" checked><label for="v3">Bivolt</label></div>
        <div class="volt-opt"><input type="radio" name="vlt" id="v4" value="N/A"><label for="v4">N/A</label></div>
      </div>
    </div>
    <div class="fg"><label class="fl">Outras características</label><textarea class="ft" id="f-ft" style="min-height:72px" placeholder="Material, certificações, cores disponíveis, acessórios..."></textarea></div>
    <div class="pex" id="pex">
      <div class="pex-lb">★ Premium</div>
      <div class="g2">
        <div class="fg"><label class="fl">Tom</label>
          <select class="fs" id="f-tn"><option value="profissional">Profissional</option><option value="descontraido">Descontraído</option><option value="urgente">Urgência</option><option value="premium">Premium/Luxo</option></select>
        </div>
        <div class="fg"><label class="fl">Público-alvo</label><input class="fi" type="text" id="f-au" placeholder="Ex: Caminhantes, aventureiros..."></div>
      </div>
    </div>
  </div>

  <div class="card mt">
    <div class="sl">Narracao do video</div>
    <div class="voice-grid">
      <label class="voice-card" for="voice-persuasive">
        <input type="radio" name="voice_style" id="voice-persuasive" value="persuasiva">
        <span class="voice-card-body">
          <span class="voice-title">Persuasiva</span>
          <span class="voice-desc">Foco em venda, beneficios e decisao de compra.</span>
        </span>
      </label>
      <label class="voice-card" for="voice-emotional">
        <input type="radio" name="voice_style" id="voice-emotional" value="emocional" checked>
        <span class="voice-card-body">
          <span class="voice-title">Emocional</span>
          <span class="voice-desc">Foco em desejo, conexao e experiencia de uso.</span>
        </span>
      </label>
      <label class="voice-card" for="voice-demo">
        <input type="radio" name="voice_style" id="voice-demo" value="demonstrativa">
        <span class="voice-card-body">
          <span class="voice-title">Demonstrativa</span>
          <span class="voice-desc">Foco em explicar o produto, uso, detalhes e diferenciais.</span>
        </span>
      </label>
      <label class="voice-card" for="voice-premium">
        <input type="radio" name="voice_style" id="voice-premium" value="premium">
        <span class="voice-card-body">
          <span class="voice-title">Premium</span>
          <span class="voice-desc">Foco em sofisticação, acabamento, exclusividade e valor percebido.</span>
        </span>
      </label>
    </div>
  </div>

  <div class="arow">
    <button class="btn bs" onclick="go(1)">← Voltar</button>
    <button class="btn bp" onclick="subCtx()">Gerar Textos →</button>
  </div>
</section>

<section class="screen" id="s3">
  <div class="sh">
    <div class="sh-tag">✦ Passo 3 de 6</div>
    <h1>Revise e aprove os textos</h1>
    <p>Edite livremente. Aprove para liberar a geração de imagens.</p>
  </div>
  <div id="c-ld" style="display:none">
    <div class="skel-grid">
      <div class="skel-card">
         <div class="skel-line short"></div>
         <div class="skel-line long"></div>
         <div class="skel-line long"></div>
      </div>
      <div class="skel-card">
         <div class="skel-line short"></div>
         <div class="skel-line long"></div>
         <div class="skel-line long"></div>
      </div>
      <div class="skel-card">
         <div class="skel-line short"></div>
         <div class="skel-line long"></div>
         <div class="skel-line long"></div>
      </div>
      <div class="skel-card">
         <div class="skel-line short"></div>
         <div class="skel-line long"></div>
         <div class="skel-line long"></div>
      </div>
    </div>
    <div class="skel-card" style="margin-top:16px">
       <div class="skel-line short"></div>
       <div class="skel-line long"></div>
       <div class="skel-line long"></div>
       <div class="skel-line long"></div>
       <div class="skel-line" style="width:40%"></div>
    </div>
    <div style="text-align:center; color:var(--tx2); font-size:13px; margin-top:20px;" id="c-msg">Gerando textos...</div>
  </div>
  <div id="c-out" style="display:none">
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div class="sl" style="margin:0;letter-spacing:1px;font-size:11px">4 TÍTULOS — ESTRATÉGIAS DISTINTAS</div>
        <button class="btn bs bsm" style="background:#0e0e1a;border:1px solid var(--bd)" onclick="regenCopy()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><polyline points="23 20 23 14 17 14"></polyline><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path></svg> Regenerar</button>
      </div>
      <div class="cg" id="ttg"></div>
    </div>
    <div class="card mt">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <div class="sl" style="margin:0">Descrição para marketplace</div>
        <button class="btn bg bsm" onclick="cpEl('d-tx')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
      </div>
      <div class="cctx" id="d-tx" contenteditable="true" style="min-height:120px;color:var(--tx2);font-size:13px;line-height:1.7"></div>
    </div>
    <div class="abann" id="ab">
      <div class="abi">💡</div>
      <div class="abb"><h4>Pronto para avançar?</h4><p>Revise os textos e clique em Aprovar para liberar as imagens.</p></div>
      <button class="btn bsuc bsm" onclick="approveTexts()">✓ Aprovar Textos</button>
    </div>
  </div>
  <div class="arow">
    <button class="btn bs" onclick="go(2)">← Voltar</button>
    <button class="btn bp" id="btn3" disabled onclick="go(4)">Gerar Imagens →</button>
  </div>
</section>

<section class="screen" id="s4">
  <div class="sh">
    <div class="sh-tag">✦ Passo 4 de 6</div>
    <h1>Imagens geradas</h1>
    <p id="img-step-sub">Imagens individuais prontas. Escolha exatamente 4 imagens para compor os clipes do vídeo.</p>
  </div>

  <div id="i-ld" style="display:none">
    <!-- Removido loader antigo aqui -->
  </div>

  <div id="i-out" style="display:none">
    <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <h2 style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin:0 0 4px;color:var(--tx);display:flex;align-items:center;gap:10px">Galeria de Imagens</h2>
        <p style="font-size:12px;color:var(--tx3);margin:0" id="img-block1-desc">Imagens geradas pela IA</p>
      </div>
      <button class="btn bs bsm" onclick="dlAll()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Baixar Imagens</button>
    </div>
    
    <div class="img4-grid" id="grid4" style="grid-template-columns:repeat(4,1fr); margin-bottom:10px;"></div>

    <div id="i-ld-bottom" style="display:none; margin-bottom:24px;">
      <div class="img-prog-wrap" style="max-width:100%">
        <div class="img-prog-bar"><div class="img-prog-fill" id="i-pb"></div></div>
        <div class="img-prog-txt" id="i-msg">Gerando suas imagens...</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;background:#061b17;border:1px solid #144f43;border-radius:12px;padding:14px 18px;margin-top:14px">
      <span style="font-size:18px">🎬</span>
      <span style="font-size:13px;color:#a0d7cc;font-weight:500">Escolha 4 imagens para o vídeo. Elas serão usadas para criar os 4 clipes do anúncio.</span>
      <span style="margin-left:auto;font-family:'Syne',sans-serif;font-size:14px;font-weight:800;color:#fff" id="vsc">0/4 selecionadas</span>
    </div>
  </div>

  <div id="i-act" style="display:none">
    <div class="arow">
      <button class="btn bs" onclick="go(3)">← Voltar</button>
      <div class="arr">
        <button class="btn bs bsm" onclick="dlAll()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Baixar imagens</button>
        <button class="btn bp" onclick="goVideoStep()">Configurar Vídeo →</button>
      </div>
    </div>
  </div>
</section>

<section class="screen" id="s5">
  <div class="sh">
    <div class="sh-tag">✦ Passo 5 de 6</div>
    <h1 style="letter-spacing:-1px">Preparação<br><span style="color:var(--acc-h)">de vídeo</span></h1>
    <p>Revise as imagens, narração e formato antes de gerar o vídeo final.</p>
  </div>

  <div class="video-prep-grid">
    <div class="card video-prep-main">
      <div class="video-panel-head">
        <div>
          <div class="sl">Escolha 4 imagens para o vídeo</div>
          <p>Essas imagens serão usadas para criar os 4 clipes do seu anúncio.</p>
        </div>
        <button class="btn bs bsm" onclick="go(4)">← Voltar para imagens</button>
      </div>
      <div class="srow video-selected-row" id="srow"></div>
    </div>

    <div class="card video-prep-side">
      <div class="sl">Configuração atual</div>
      <div class="video-meta-list">
        <div class="video-meta-item"><span>Narração</span><strong id="video-voice-label">Emocional</strong></div>
        <div class="video-meta-item"><span>Vídeo</span><strong>Geração IA Premium</strong></div>
        <div class="video-meta-item"><span>Narração</span><strong>Voz profissional IA</strong></div>
      </div>
    </div>
  </div>

  <div class="card mt">
    <div class="sl">Pipeline preparado</div>
    <div class="video-pipeline-list">
      <div class="video-pipeline-item"><strong>4</strong><span>clipes comerciais</span></div>
      <div class="video-pipeline-item"><strong>8s</strong><span>por clipe</span></div>
      <div class="video-pipeline-item"><strong>IA Premium</strong><span>geração visual</span></div>
      <div class="video-pipeline-item"><strong>Voz IA</strong><span>narração profissional</span></div>
    </div>
  </div>

  <div class="card mt">
    <div class="video-language-row">
      <label for="video-language-select">
        <span>Idioma da narração</span>
        <select id="video-language-select" onchange="setVideoLanguage(this.value)">
          <option value="pt-BR" selected>Português</option>
          <option value="en-US">English</option>
          <option value="es-ES">Español</option>
          <option value="fr-FR">Français</option>
        </select>
      </label>
      <p id="video-language-hint">O roteiro será preparado em Português.</p>
    </div>
    <div class="video-panel-head">
      <div>
        <div class="sl">Roteiro da narração</div>
        <p>Revise ou edite a copy que será usada na narração do vídeo.</p>
      </div>
      <button class="btn bs bsm" id="btn-video-script-regen" onclick="regenerateVideoScript()">Regenerar roteiro</button>
    </div>
    <textarea class="video-script-text" id="video-script-text" oninput="updateVideoScript(this.value)" rows="7"></textarea>
  </div>

  <div class="card mt">
    <div class="video-panel-head">
      <div>
        <div class="sl">Formato do vídeo</div>
        <p>Escolha o formato ideal para o vídeo do anúncio.</p>
      </div>
    </div>
    <div class="video-format-grid">
      <button type="button" class="video-format-card" data-format="9:16" onclick="setVideoFormat('9:16')">
        <span class="video-aspect-box video-aspect-vertical"></span>
        <strong>9:16 Vertical</strong>
      </button>
      <button type="button" class="video-format-card active" data-format="16:9" onclick="setVideoFormat('16:9')">
        <span class="video-aspect-box video-aspect-wide"></span>
        <strong>16:9 Horizontal</strong>
      </button>
    </div>
  </div>

  <div class="video-status-panel mt" id="video-status-box">
    <div class="video-status-dot"></div>
    <div>
      <div class="video-status-title" id="video-status-title">Pronto para preparar o vídeo.</div>
      <p id="video-status-copy">As imagens selecionadas serão transformadas em clipes e compostas com a narração.</p>
      <div id="video-status-composition-timer" style="display:none"></div>
    </div>
  </div>

  <div class="video-motion-host mt" id="video-motion-box" style="display:none"></div>

  <div class="video-final-box mt" id="video-final-box" style="display:none">
    <div class="video-final-head">
      <div>
        <div class="sl">Vídeo final</div>
        <p>Seu anúncio em vídeo foi gerado com sucesso.</p>
      </div>
      <span>Pronto</span>
    </div>
    <div class="stlai-final-video-shell">
      <video id="video-final-player" controls playsinline webkit-playsinline preload="metadata"></video>
    </div>
    <div class="video-final-actions">
      <button class="btn bs bsm" type="button" onclick="downloadFinalVideo()">Baixar vídeo</button>
      <button class="btn bs bsm" type="button" onclick="openFinalVideo()">Ampliar</button>
      <button class="btn bs bsm" type="button" onclick="copyFinalVideoLink()">Copiar link</button>
    </div>
  </div>

  <div class="video-clips-box mt" id="video-clips-box" style="display:none">
    <div class="sl">Clipes preparados</div>
    <div class="video-clips-grid" id="video-clips-grid"></div>
  </div>

  <div class="video-frames-box mt" id="video-frames-box" style="display:none">
    <div class="video-panel-head">
      <div>
        <div class="sl" id="video-frames-title">Imagens no formato</div>
        <p>Use estas imagens também em anúncios, stories, reels e marketplaces que pedem esse formato.</p>
      </div>
    </div>
    <div class="video-frames-grid" id="video-frames-grid"></div>
  </div>

  <div class="video-test-clip-box mt" id="video-test-clip-box" style="display:none">
    <div class="sl">Clipe IA de teste</div>
    <video id="video-test-clip-player" controls playsinline webkit-playsinline muted preload="metadata" controlsList="nofullscreen nodownload noplaybackrate" disablePictureInPicture></video>
  </div>

  <div class="arow" style="margin-top:24px">
    <button class="btn bs" onclick="go(4)">← Voltar para imagens</button>
    <div class="arr">
      <button class="btn bs" id="btn-video-result" onclick="goVideoResult()">Ir para resultado</button>
      <button class="btn bp" id="btn-generate-video" onclick="mockGenerateVideo()">Gerar vídeo</button>
    </div>
  </div>
  <div class="video-result-hint" id="video-result-hint" style="display:none">Seu vídeo está sendo produzido. Acompanhe o progresso no Resultado Final.</div>
</section>

<section class="screen" id="s6">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px">
    <div class="sh" style="margin-bottom:0">
      <div class="sh-tag">✦ Passo 6 de 6</div>
      <h1>Resultado final</h1>
      <p>Todos os ativos criados para o anúncio ficam reunidos aqui.</p>
    </div>
    <button class="btn bp" style="background:var(--acc-h); color:#000; box-shadow:0 4px 18px rgba(155,127,255,0.3)" onclick="resetApp()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 1 0 2.13-5.88L2 12"></path></svg> GERAR NOVO ADS
    </button>
  </div>

  <div style="display:flex; align-items:center; gap:16px; background:var(--bg-el); border:1px solid var(--bd); border-radius:12px; padding:16px; margin-bottom:24px">
    <div style="width:64px; height:64px; border-radius:8px; overflow:hidden; border:1px solid var(--bd); flex-shrink:0">
      <img id="sum-top-img" src="" alt="Produto" style="width:100%; height:100%; object-fit:cover">
    </div>
    <div>
      <div style="font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:var(--acc-h); margin-bottom:4px; text-transform:uppercase; letter-spacing:1px">Anúncio focado em SEO</div>
      <div id="sum-top-title" style="font-size:16px; font-weight:500; color:var(--tx)"></div>
    </div>
  </div>
  
  <!-- Score Section -->
  <div class="smb">
    <div class="ssh"><div class="sst">📊 Score do Anúncio (Pronto para Marketplace)</div></div>
    <div class="card" id="sum-score" style="background:var(--bg-in); border-color:var(--mint); padding:20px;"></div>
  </div>

  <div class="mg" id="sum-meta"></div>
  <div class="smb">
    <div class="ssh"><div class="sst"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--acc-h)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Textos</div><button class="btn bg bsm" onclick="cpAll()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar tudo</button></div>
    <div class="card" id="sum-tx"></div>
    <div style="margin-top:14px; text-align:center;">
      <button class="btn bg bsm" style="background:#ededf8; color:#0e0e1a; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600; border:none; padding:10px 20px; box-shadow:0 4px 14px rgba(255,255,255,0.15); transition:all 0.2s;" id="btn-tx-long" onclick="regenCopyLong()" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Gerar Títulos Longos e Persuasivos</button>
    </div>
    <div id="tx-long-container" style="display:none; margin-top:20px; padding-top:20px; border-top:1px solid var(--bd)">
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <div class="sl" style="margin:0;letter-spacing:1px;font-size:11px">TÍTULOS LONGOS — ESTRATÉGIAS DISTINTAS</div>
        </div>
        <div class="cg" id="ttg-long"></div>
      </div>
      <div class="card mt">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div class="sl" style="margin:0">Nova descrição longa</div>
          <button class="btn bg bsm" onclick="cpEl('d-tx-long')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar</button>
        </div>
        <div class="cctx" id="d-tx-long" contenteditable="true" style="min-height:120px;color:var(--tx2);font-size:13px;line-height:1.7"></div>
      </div>
    </div>
  </div>
  <div class="smb">
    <div class="ssh">
      <div class="sst"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--mint)"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> Galeria de Imagens</div>
      <div style="display:flex;gap:8px">
        <button class="btn bs bsm" onclick="dlAll()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Baixar Imagens</button>
      </div>
    </div>
    
    <div class="img4-grid" id="sum-g4" style="gap:8px; margin-bottom:16px; grid-template-columns:repeat(4,1fr);"></div>
    
    <div style="max-width:320px; margin:0 auto 16px;">
      <div class="img-combo-wrap" id="sum-combo"><div class="img-combo-ph"><div class="ph-ic">🔲</div></div></div>
      <button class="btn bs bsm" style="margin-top:10px; width:100%; justify-content:center" onclick="dlCombo()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Baixar Combo 2x2</button>
    </div>
    
    <button class="btn bp btn-anim" id="btn-dims-img" style="width:100%; justify-content:center; padding:14px; background:var(--gold); color:#000; box-shadow:0 4px 18px rgba(244,185,66,0.3); margin-bottom:12px" onclick="document.getElementById('dims-md').classList.add('show')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> Faltou imagem com medidas? gerar agora
    </button>
    <button class="btn bp btn-anim" id="btn-more-imgs" style="width:100%; justify-content:center; padding:14px; background:var(--mint); color:#000; box-shadow:0 4px 18px rgba(45,212,191,0.3)" onclick="genMoreImages(this)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg> Gerar mais 4 imagens por vez
    </button>
  </div>
  <div class="smb" id="result-video-section">
    <div class="ssh">
      <div class="sst"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--coral)"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg> Vídeo</div>
    </div>
    <div class="card sum-video-card" id="sum-video-card">
      <div class="sum-video-head">
        <div>
          <div class="sum-video-status" id="sum-video-status">Vídeo ainda não gerado.</div>
          <p id="sum-video-note">Gere o vídeo no passo 5 para acompanhar o resultado final aqui.</p>
        </div>
        <span class="sum-video-badge" id="sum-video-badge">Pendente</span>
      </div>
      <div class="sum-video-grid">
        <div class="sum-video-meta"><span>Formato</span><strong id="sum-video-format">-</strong></div>
        <div class="sum-video-meta"><span>Narração</span><strong id="sum-video-narration">-</strong></div>
      </div>
      <div class="sum-video-final-wrap" id="sum-video-final-wrap" style="display:none">
        <div class="sum-video-final-head">
          <div>
            <span>Vídeo final</span>
            <p>Narração e clipes combinados com sucesso.</p>
          </div>
          <strong>Pronto</strong>
        </div>
        <div class="stlai-final-video-shell">
          <video id="sum-video-final-player" controls playsinline webkit-playsinline preload="metadata"></video>
        </div>
        <div class="video-final-actions">
          <button class="btn bs bsm" type="button" onclick="downloadFinalVideo()">Baixar vídeo</button>
          <button class="btn bs bsm" type="button" onclick="openFinalVideo()">Ampliar</button>
          <button class="btn bs bsm" type="button" onclick="copyFinalVideoLink()">Copiar link</button>
        </div>
      </div>
      <div class="sum-video-motion-wrap" id="sum-video-motion-wrap" style="display:none"></div>
      <div class="sum-video-retry-wrap" id="sum-video-retry-wrap" style="display:none">
        <button class="btn bp" type="button" onclick="mockGenerateVideo()">Tentar novamente</button>
      </div>
      <div class="sum-video-script-wrap">
        <span>Roteiro</span>
        <p id="sum-video-script">Roteiro ainda não preparado.</p>
      </div>
      <div class="sum-video-clips-wrap" id="sum-video-clips-wrap" style="display:none">
        <span>Clipes preparados</span>
        <div class="video-clips-grid" id="sum-video-clips-grid"></div>
      </div>
      <div class="sum-video-frames-wrap" id="sum-video-frames-wrap" style="display:none">
        <span id="sum-video-frames-title">Imagens no formato</span>
        <p>Use estas imagens também em anúncios, stories, reels e marketplaces que pedem esse formato.</p>
        <div class="video-frames-grid" id="sum-video-frames-grid"></div>
      </div>
    </div>
  </div>

  <div class="smb" id="result-ugc-section">
    <div class="ssh">
      <div>
        <div class="sst"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--mint)"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M7 9h.01"></path><path d="M11 9h6"></path><path d="M7 13h10"></path></svg> Vídeos UGC</div>
        <p class="ugc-section-copy">Gere versões com aparência de conteúdo real para redes sociais, reviews, unboxing e demonstrações.</p>
      </div>
      <button class="btn bp bsm" type="button" id="btn-open-ugc-modal" onclick="openUgcModal()">Criar vídeo UGC</button>
    </div>
    <div class="card ugc-card" id="ugc-card">
      <div class="ugc-empty" id="ugc-empty">
        <div>
          <strong>Nenhum vídeo UGC criado ainda.</strong>
          <p>Escolha um preset, uma imagem do anúncio e gere uma variação curta para redes sociais.</p>
        </div>
      </div>
      <div class="ugc-config-warning" id="ugc-config-warning" style="display:none">Configure o provider UGC no painel para gerar vídeos.</div>
      <div class="ugc-jobs-grid" id="ugc-jobs-grid"></div>
    </div>
  </div>
  
  <div class="mk-wrap" id="market-wrap">
      <div class="mk-head">
        <div class="mk-title">✦ Inteligência Competitiva (BETA)</div>
        <button class="mk-btn" onclick="analyzeMarket(this)" id="btn-market">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg> Análise da Concorrência
        </button>
      </div>
      <div id="market-init" style="font-size:13px; color:var(--tx2);">
        <ul style="list-style:none;padding:0;margin:0;line-height:1.8;">
          <li><span style="color:var(--acc-h);margin-right:6px">●</span>Análise de faixa de preço praticada nos Marketplaces</li>
          <li><span style="color:var(--acc-h);margin-right:6px">●</span>Sugestão de posicionamento ideal com base em IA</li>
          <li><span style="color:var(--acc-h);margin-right:6px">●</span>Simulador de Lucro Líquido Real (com taxas e custos ocultos)</li>
        </ul>
      </div>
      <div id="market-results" style="display:none; margin-top:20px;"></div>
  </div>

  <div class="sq-btn-wrap">
    <button class="sq-btn" onclick="toast('Redirecionando...','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
      My Ads
    </button>
    <button class="sq-btn" onclick="toast('Em breve!','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
      Histórico
    </button>
    <button class="sq-btn" onclick="dlAll()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      Imagens
    </button>
    <button class="sq-btn primary" style="background:rgba(45,212,191,0.1); border-color:rgba(45,212,191,0.3); color:var(--mint);" onclick="downloadEverything()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      Baixar Tudo
    </button>
    <button class="sq-btn primary" onclick="toast('Em breve!','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
      Compartilhar
    </button>
  </div>
</section>

</main>
</div>

<div id="lightbox" onclick="closeLightbox(event)">
  <button id="lightbox-close" onclick="closeLightbox(event, true)">&times;</button>
  <img id="lightbox-img" alt="Imagem ampliada" style="display:none">
  <video id="lightbox-video" controls playsinline webkit-playsinline style="display:none"></video>
  <div id="lightbox-fallback" class="lightbox-fallback" style="display:none">Imagem indisponível</div>
</div>

<div class="modal-backdrop ugc-modal-backdrop" id="ugc-modal">
  <div class="modal ugc-modal">
    <div class="modal-head">
      <div>
        <h3>Escolha o formato do vídeo</h3>
        <p class="ugc-modal-sub">Escolha o tipo de vídeo que combina melhor com seu produto e público.</p>
      </div>
      <button class="btn bs bsm" type="button" onclick="closeUgcModal()">Fechar</button>
    </div>
    <div class="ugc-tabs">
      <button type="button" class="ugc-tab active" data-ugc-tab="all" onclick="setUgcTab('all')">Todos</button>
      <button type="button" class="ugc-tab" data-ugc-tab="ugc" onclick="setUgcTab('ugc')">UGC</button>
      <button type="button" class="ugc-tab" data-ugc-tab="commercial" onclick="setUgcTab('commercial')">Comercial</button>
    </div>
    <div class="ugc-preset-grid" id="ugc-preset-grid"></div>
    <div class="ugc-controls">
      <label>Formato
        <select id="ugc-aspect-ratio">
          <option value="9:16">9:16</option>
          <option value="16:9">16:9</option>
          <option value="1:1">1:1</option>
        </select>
      </label>
      <label>Qualidade
        <select id="ugc-resolution">
          <option value="720p">720p</option>
          <option value="1080p">1080p</option>
        </select>
      </label>
      <label>Duração
        <select id="ugc-duration">
          <option value="5">5s</option>
          <option value="8">8s</option>
          <option value="9">9s</option>
          <option value="10">10s</option>
        </select>
      </label>
    </div>
    <div class="ugc-image-picker">
      <div class="sl">Imagem de referência</div>
      <div class="ugc-image-list" id="ugc-image-list"></div>
    </div>
    <div class="arow" style="margin-top:18px">
      <button class="btn bs" type="button" onclick="closeUgcModal()">Cancelar</button>
      <button class="btn bp" type="button" id="btn-start-ugc" onclick="startUgcVideo()">Gerar UGC</button>
    </div>
  </div>
</div>
</div>
