

const S = {
  step: 1,
  plan: "",
  imgs: [],
  vData: null,
  vReady: false,
  vStatus: "idle",
  name: "",
  desc: "",
  x: "",
  y: "",
  z: "",
  wt: "",
  volt: "Bivolt",
  feat: "",
  lang: "pt-BR",
  tone: "profissional",
  audience: "",
  voiceStyle: "emocional",
  titles: [],
  descTxt: "",
  textApproved: false,
  imgs4: [],
  comboUrl: null,
  imageComboJobs: [],
  imageDiagnostics: {},
  selVid: [],
  videoCompositionTimer: {
    jobId: "",
    startedAtMs: 0,
    intervalId: null,
    active: false
  },
  video: {
    status: "idle",
    format: "16:9",
    language: "pt-BR",
    mockReady: false,
    script: "",
    scriptTts: "",
    scriptEdited: false,
    scriptVoiceStyle: "",
    scriptLanguage: "pt-BR",
    jobId: "",
    renderJobId: "",
    progress: 0,
    progressHint: 0,
    jobVersion: 0,
    updatedAt: "",
    message: "",
    audioUrl: "",
    finalVideoUrl: "",
    finalVideoDuration: 0,
    thumbnailUrl: "",
    clips: [],
    clipJobs: [],
    clipStatuses: {},
    clipAttempts: {},
    clipErrors: {},
    missingClips: [],
    videoFrames: [],
    currentClipIndex: 0,
    currentClipAttempt: 0,
    clipRetryCount: 0,
    lastClipError: "",
    failedClipIndex: 0,
    failedClipRole: "",
    errorCode: "",
    compositionStatus: "pending",
    composerStatus: "",
    composerStartedAt: "",
    localCompositionStartedAt: 0,
    compositionTimerStartedAtMs: 0,
    compositionTimerJobId: "",
    composerEndpointConfigured: false,
    composerEndpointHost: "",
    composerEndpointPath: "",
    composerElapsedSeconds: 0,
    composerPollCount: 0,
    softTimeoutSeconds: 300,
    softTimeoutReached: false,
    hardTimeoutSeconds: 1200,
    hardTimeoutReached: false,
    nextPollSeconds: 3,
    externalRenderStatus: "",
    externalRenderCheckedAt: "",
    compositionStartBlocker: "",
    nextClipAction: "",
    nextClipIndex: 0,
    nextClipReason: "",
    autoClipGenerationTriggered: false,
    autoClipGenerationResult: "",
    skippedReason: "",
    maxClipAttempts: 3,
    maxConcurrentClipGenerations: 4,
    clipStartStaggerSeconds: 1,
    startedClipIndexes: [],
    scheduledClipIndexes: [],
    nextClipIndexes: [],
    retryable: false,
    retryReason: "",
    willRetry: false,
    errorFinalReason: "",
    diagnostics: {},
    testClipUrl: "",
    testClipOperationId: "",
    testClipStatus: "idle",
    testClipMessage: "",
    pollTimer: null,
	    progressTimer: null,
	    compositionTimer: null,
	    clipLaunchTimers: [],
	    clipLaunchTimersByIndex: {},
	    clipLaunchStarted: false,
	    clipRequestsInFlight: {},
	    visualStatus: "",
    visualProgress: 0,
    visualPhaseKey: "",
    visualPhaseStartedAt: 0
  },
  ugcJobs: [],
  activeUgcJob: "",
  ugcModalOpen: false,
  selectedUgcPreset: "ugc",
  selectedUgcImage: null,
  ugc: {
    selectedImageUrl: "",
    selectedPreviewSrc: "",
    selectedImageIndex: -1,
    selectedImageLabel: ""
  },
  ugcAspectRatio: window.stlaiConfig?.ugcDefaults?.aspectRatio || "9:16",
  ugcDuration: window.stlaiConfig?.ugcDefaults?.duration || 9,
  ugcResolution: window.stlaiConfig?.ugcDefaults?.resolution || "720p",
  ugcTab: "all",
  ugcPollTimers: {},
  cfg: window.stlaiConfig || {}
};

const VIDEO_FINAL_SCORE_BONUS = 10;

const UGC_PRESETS = [
  {key:"ugc", group:"ugc", title:"UGC", subtitle:"Vídeo realista para redes sociais"},
  {key:"tutorial", group:"ugc", title:"Tutorial", subtitle:"Passo a passo mostrando o produto"},
  {key:"unboxing", group:"ugc", title:"Unboxing", subtitle:"Abertura e primeira impressão"},
  {key:"product_review", group:"ugc", title:"Product Review", subtitle:"Review autêntico do produto"},
  {key:"ugc_virtual_try_on", group:"ugc", title:"UGC Virtual Try On", subtitle:"Demonstração estilo provador virtual"},
  {key:"hyper_motion", group:"commercial", title:"Hyper Motion", subtitle:"Movimento forte com foco no produto"},
  {key:"tv_spot", group:"commercial", title:"TV Spot", subtitle:"Anúncio comercial com narrativa"},
  {key:"wild_card", group:"commercial", title:"Wild Card", subtitle:"Ideia criativa e inesperada"},
  {key:"pro_virtual_try_on", group:"commercial", title:"Pro Virtual Try On", subtitle:"Demonstração premium do produto"}
];

const CMSGS = [
  "Analisando produto...",
  "Estruturando estratégias comerciais...",
  "Gerando Título SEO...",
  "Gerando Título Informativo...",
  "Gerando Título de Benefício...",
  "Gerando Título de Diferencial...",
  "Redigindo descrição otimizada..."
];

// Removidos os arrays estáticos


// setPlan("basic"); // Removido para forçar o usuário a escolher


function tryStep(n){
  const b = document.getElementById(`nav-${n}`);
  if (b && b.classList.contains("locked")) { toast("Complete a etapa atual primeiro.","warn"); return; }
  go(n);
}

async function goStep2() {
  if (S.vReady) {
    go(2);
  } else {
    const ok = await trigVision();
    if(ok) go(2);
  }
}
function go(n){
  document.querySelectorAll(".screen").forEach(e=>e.classList.remove("active"));
  const targetScreen = document.getElementById(`s${n}`);
  if (!targetScreen) return;
  targetScreen.classList.add("active");
  document.querySelectorAll(".sb").forEach((b,i)=>{
    const idx=i+1;
    b.classList.remove("active","done","locked");
    if(idx<n)b.classList.add("done");
    else if(idx===n)b.classList.add("active");
    else if(!S[`ul${idx}`])b.classList.add("locked");
  });
  S.step=Math.max(S.step,n);
  S[`ul${n}`]=true;
  window.scrollTo({top:0,behavior:"smooth"});
  if(n===2 && !S.hasShownCtxPopup && S.vData) {
    S.hasShownCtxPopup = true;
    const ctxPopup = document.getElementById("ctx-popup");
    if (ctxPopup) {
      ctxPopup.style.transform="translateX(0)";
      setTimeout(() => {
        ctxPopup.style.transform="translateX(150%)";
      }, 8000);
    }
  }
  if(n===4 && S.imgs4.length===0)startImgGen();
  if(n===5)popVid();
  if(n===6)popSum();
}

function unlock(n){const b=document.getElementById(`nav-${n}`);if(b)b.classList.remove("locked");S[`ul${n}`]=true;}
function dzEv(e,id,fn,cls){e.preventDefault();document.getElementById(id).classList[fn](cls);}
function delay(ms){return new Promise(r=>setTimeout(r,ms))}

function normalizeMediaUrl(url){
  let value=String(url || "").trim();
  value=value.replace(/^["']|["']$/g,"").replace(/\\\//g,"/");
  if(!value) return "";
  if(/^data:image\//i.test(value) || /^blob:/i.test(value)) return value;
  if(/^https?:\/\//i.test(value)) return value;
  return "";
}

function resolveImageUrl(imageOrUrl){
  if(typeof imageOrUrl==="string") return normalizeMediaUrl(imageOrUrl);
  if(!imageOrUrl || typeof imageOrUrl!=="object") return "";
  const candidates=[
    imageOrUrl.full_url,
    imageOrUrl.fullUrl,
    imageOrUrl.url,
    imageOrUrl.image_url,
    imageOrUrl.imageUrl,
    imageOrUrl.download_url,
    imageOrUrl.downloadUrl,
    imageOrUrl.src
  ];
  for(const candidate of candidates){
    const clean=normalizeMediaUrl(candidate);
    if(clean) return clean;
  }
  return "";
}

function decodeHtmlUrl(value){
  return String(value || "").trim().replace(/&amp;/g,"&");
}

function isPublicHttpUrl(value){
  return typeof value==="string" && /^https?:\/\//i.test(value.trim());
}

function isDataImage(value){
  return typeof value==="string" && /^data:image\//i.test(value.trim());
}

function isDomElement(value){
  return value && typeof value==="object" && value.nodeType===1;
}

function publicUrlFromCandidate(candidate){
  const clean=normalizeMediaUrl(decodeHtmlUrl(candidate));
  return isPublicHttpUrl(clean) ? clean : "";
}

function previewSrcFromCandidate(candidate){
  const clean=normalizeMediaUrl(decodeHtmlUrl(candidate));
  return clean && (isPublicHttpUrl(clean) || isDataImage(clean)) ? clean : "";
}

function getImagePublicUrl(imageOrElement, element=null){
  const primaryElement=isDomElement(imageOrElement) ? imageOrElement : element;
  const image=isDomElement(imageOrElement) ? null : imageOrElement;
  const dataset=primaryElement?.dataset || {};
  const imgEl=primaryElement?.tagName?.toLowerCase()==="img" ? primaryElement : primaryElement?.querySelector?.("img");
  const candidates=[
    image?.full_url,
    image?.fullUrl,
    image?.url,
    image?.image_url,
    image?.imageUrl,
    image?.download_url,
    image?.downloadUrl,
    image?.original_url,
    image?.originalUrl,
    image?.generated_url,
    image?.generatedUrl,
    image?.public_url,
    image?.publicUrl,
    image?.wp_url,
    image?.wpUrl,
    image?.upload_url,
    image?.uploadUrl,
    image?.file_url,
    image?.fileUrl,
    image?.prepared_frame_url,
    image?.preparedFrameUrl,
    image?.frame_url,
    image?.frameUrl,
    dataset.imageUrl,
    dataset.fullUrl,
    dataset.url,
    dataset.downloadUrl,
    primaryElement?.getAttribute?.("data-image-url"),
    primaryElement?.getAttribute?.("data-full-url"),
    primaryElement?.getAttribute?.("data-url"),
    primaryElement?.getAttribute?.("data-download-url")
  ];
  console.log("[STLAI UGC] image url candidates", candidates);
  for(const candidate of candidates){
    const clean=publicUrlFromCandidate(candidate);
    if(clean) return clean;
  }
  return "";
}

function getImagePreviewSrc(imageOrElement, element=null){
  const primaryElement=isDomElement(imageOrElement) ? imageOrElement : element;
  const image=isDomElement(imageOrElement) ? null : imageOrElement;
  const dataset=primaryElement?.dataset || {};
  const imgEl=primaryElement?.tagName?.toLowerCase()==="img" ? primaryElement : primaryElement?.querySelector?.("img");
  const candidates=[
    image?.preview_src,
    image?.previewSrc,
    image?.preview_url,
    image?.previewUrl,
    image?.thumbnail,
    image?.thumb,
    image?.src,
    image?.data_url,
    image?.dataUrl,
    image?.base64,
    image?.url,
    dataset.previewSrc,
    dataset.src,
    primaryElement?.getAttribute?.("data-preview-src"),
    primaryElement?.getAttribute?.("data-src"),
    imgEl?.src,
    primaryElement?.tagName?.toLowerCase()==="img" ? primaryElement.src : ""
  ];
  for(const candidate of candidates){
    const clean=previewSrcFromCandidate(candidate);
    if(clean) return clean;
  }
  return "";
}

function normalizeVideoClipUrlData(clip){
  if(!clip || typeof clip!=="object") return clip;
  return {
    ...clip,
    url: normalizeMediaUrl(clip.url),
    prepared_frame_url: normalizeMediaUrl(clip.prepared_frame_url)
  };
}

function normalizeVideoFrameUrlData(frame){
  if(!frame || typeof frame!=="object") return frame;
  return {
    ...frame,
    url: normalizeMediaUrl(frame.url)
  };
}

function onDrop(e){e.preventDefault();document.getElementById("dz").classList.remove("over");handleFiles(Array.from(e.dataTransfer.files));}
function onFChange(e){
  if(e && e.target && e.target.files) {
    handleFiles(Array.from(e.target.files));
  } else {
    toast("Nenhum arquivo encontrado no input.", "error");
  }
}

function handleFiles(files){
  if(!files || !files.length) return;
  const valid = files.filter(f => true); // Aceita qualquer arquivo visual
  const rem=5-S.imgs.length;
  const add=valid.slice(0,rem);
  if(files.length>rem)toast("Máximo 5 imagens.","warn");

  let loaded = 0;
  if(add.length === 0) {
    // If no valid images were added, but there are already images, render
    if(S.imgs.length > 0) renderThumbs();
    return;
  }
  
  toast("Carregando imagem...", "info");
  
  add.forEach(f=>{
    const rd = new FileReader();
    rd.onload = ev => {
      const src = ev.target.result;
      const b64 = src.split(",")[1];
      S.imgs.push({ id:`i${Date.now()}${Math.random().toString(36).slice(2,5)}`, src, b64, mime:f.type || 'image/jpeg', file:f });
      loaded++;
      // Render as soon as one image finishes loading so it feels instant
      renderThumbs();
    };
    rd.onerror = () => {
      toast("Erro ao ler arquivo.", "error");
      loaded++;
    };
    rd.readAsDataURL(f);
  });
  const fi = document.getElementById("fi");
  if(fi) fi.value="";
}

function remImg(id){
  S.imgs = S.imgs.filter(i=>i.id!==id);
  renderThumbs();
  if(!S.imgs.length){
    S.vReady=false;S.vData=null;S.vStatus="idle";
    document.getElementById("u-vbar").style.display="none";
    document.getElementById("v-spin").style.display="none";
    document.getElementById("v-actions").style.display="none";
    document.getElementById("v-st").textContent="Aguardando imagens para começar.";
  }
}

function renderThumbs(){
  const g=document.getElementById("tg");
  if(!g) return;
  g.innerHTML="";
  S.imgs.forEach(img=>{
    const d=document.createElement("div");
    d.className="th";
    d.innerHTML=`<img src="${img.src}" alt="Imagem enviada"><button class="th-del" onclick="remImg('${img.id}')">✕</button>`;
    g.appendChild(d);
  });
  const emptyState = document.getElementById("dz-empty-state");
  if(emptyState) emptyState.style.display = S.imgs.length ? "none" : "block";

  const n=document.getElementById("up-note");
  if(n) n.style.display=S.imgs.length?"block":"none";
  const upCnt = document.getElementById("up-cnt");
  if(upCnt) upCnt.textContent=S.imgs.length;
  const btn1 = document.getElementById("btn1");
  if(S.imgs.length > 0) {
    if(btn1) {
      btn1.removeAttribute("disabled");
      btn1.disabled = false;
      btn1.classList.add("ready-glow");
    }
  } else {
    if(btn1) {
      btn1.setAttribute("disabled", "true");
      btn1.disabled = true;
      btn1.classList.remove("ready-glow");
    }
  }
}

const VISION_MSGS = [
  "Identificando formato e dimensões...",
  "Analisando textura e materiais 3D...",
  "Detectando cores predominantes...",
  "Extraindo diferenciais do produto...",
  "Preparando dados para o anúncio..."
];

async function trigVision(){
  if(!ensureCfg(false)) return false;
  S.vStatus="loading";
  
  // Mostrar overlay de visão
  document.getElementById("vision-overlay").classList.add("show");
  const btn1 = document.getElementById("btn1");
  btn1.disabled = true;
  btn1.classList.remove("ready-glow");

  const vMsgEl = document.getElementById("vo-text");
  vMsgEl.textContent = "A inteligência artificial está decodificando todos os atributos e materiais do seu produto em 3D...";
  let vmIdx = 0;
  const visionInterval = setInterval(() => {
    if(vmIdx < VISION_MSGS.length) {
      vMsgEl.textContent = VISION_MSGS[vmIdx++];
    } else {
      vmIdx = 0;
      vMsgEl.textContent = VISION_MSGS[vmIdx++];
    }
  }, 2500);

  document.getElementById("u-vbar").style.display="flex";
  document.getElementById("u-vspin").style.display="flex";
  document.getElementById("dz").classList.add("analyzing");
  document.getElementById("u-vst").textContent="Entendendo seu produto para construir o melhor anúncio.";
  document.getElementById("v-spin").style.display="flex";
  document.getElementById("v-actions").style.display="none";
  document.getElementById("v-st").textContent="Analisando imagens em segundo plano...";
  try{
    const prompt = S.cfg.promptVision || window.stlaiConfig.promptVision;
    const txt = await apiGenerateText(prompt, S.imgs);
    const d = parseJSON(txt);
    if(!d) throw new Error("JSON inválido na leitura do produto");
    S.vData = d;
    S.vReady = true;
    S.vStatus = "done";
    applyAF(true);
    document.getElementById("u-vspin").style.display="none";
    document.getElementById("u-vst").textContent="Leitura concluída. O contexto foi sugerido automaticamente.";
    document.getElementById("v-spin").style.display="none";
    document.getElementById("v-actions").style.display="flex";
    document.getElementById("v-st").textContent="Sugestões aplicadas. Revise os campos abaixo e ajuste se quiser.";
    
    // Habilitar botão e adicionar brilho
    btn1.disabled = false;
    btn1.classList.add("ready-glow");
    return true;
  }catch(e){
    console.warn("vision",e);
    S.vStatus = "error";
    document.getElementById("u-vspin").style.display="none";
    document.getElementById("u-vst").textContent="Não foi possível concluir a leitura automática agora.";
    document.getElementById("v-spin").style.display="none";
    document.getElementById("v-st").textContent = e.message || "Análise automática indisponível. Preencha manualmente.";
    toast(`Falha na leitura automática: ${e.message}`,"error");
    btn1.disabled = false;
    return false;
  }finally{
    clearInterval(visionInterval);
    document.getElementById("dz").classList.remove("analyzing");
    document.getElementById("vision-overlay").classList.remove("show");
  }
}

function applyAF(silent=false){
  if(!S.vData) return;
  const d = S.vData;
  S.name = d.name || S.name;
  S.desc = d.description || S.desc;
  S.feat = d.features || S.feat;
  S.volt = d.voltage || S.volt;
  const sv=(id,v)=>{const e=document.getElementById(id); if(e && v){ e.value=v; e.style.borderColor="var(--mint)"; setTimeout(()=>e.style.borderColor="",1600); }};
  sv("f-nm", d.name || "");
  let dc = d.description || "";
  if(d.material) dc += `${dc ? "\n" : ""}Material: ${d.material}.`;
  if(d.color) dc += `${dc ? "\n" : ""}Cor: ${d.color}.`;
  sv("f-dc", dc);
  sv("f-ft", d.features || "");
  const vm = {"110v":"v1","220v":"v2","bivolt":"v3","n/a":"v4"};
  const vk = vm[String(d.voltage || "N/A").toLowerCase()];
  if(vk) document.getElementById(vk).checked = true;
  if(!silent) toast("Campos preenchidos pela IA. Revise antes de continuar.","success");
}

function currentImageTypes(){
  const hasDims = S.x || S.y || S.z;
  const infoScene = buildInformativeImageScene();
  const dimsScene = hasDims ? buildTechnicalDimensionsScene(dimsText()) : "";
  const cover=makeImageSlot("capa", "Capa — Fundo Branco", "cover_white_background", S.cfg.sceneCapa || window.stlaiConfig.sceneCapa);
  const dims=makeImageSlot("dims", "Medidas", "technical_dimensions", dimsScene, true);
  const info=makeImageSlot("info", "Características", "informative_features", infoScene, true);
  const amb1=makeImageSlot("amb1", "Ambientada — Uso 1", "ambient_use_1", S.cfg.sceneAmb1 || window.stlaiConfig.sceneAmb1);
  const amb2=makeImageSlot("amb2", "Ambientada — Uso 2", "ambient_use_2", S.cfg.sceneAmb2 || window.stlaiConfig.sceneAmb2);
  const amb3=makeImageSlot("amb3", "Ambientada — Uso 3", "ambient_use_3", S.cfg.sceneAmb3 || window.stlaiConfig.sceneAmb3);
  const detail=makeImageSlot("detail", "Detalhe — Acabamento", "detail_finish", S.cfg.sceneDetail || window.stlaiConfig.sceneDetail);
  const benefit=makeImageSlot("feature", "Destaque — Benefício", "benefit_visual", S.cfg.sceneFeature || window.stlaiConfig.sceneFeature);
  const hero=makeImageSlot("hero", "Hero — Cena Final", "hero_final", S.cfg.sceneHero || window.stlaiConfig.sceneHero);

  const basic = hasDims
    ? [cover, dims, info, amb1]
    : [cover, info, amb1, amb2];

  const premium = hasDims
    ? [cover, dims, info, amb1, amb2, amb3, detail, hero]
    : [cover, info, amb1, amb2, amb3, detail, benefit, hero];

  return S.plan === "premium" ? premium : basic;
}

function syncImageStepCopy(){
  const count = currentImageTypes().length;
  const hasDims = S.x || S.y || S.z;
  const stepSub = document.getElementById("img-step-sub");
  const blockLabel = document.getElementById("img-block1-label");
  const blockDesc = document.getElementById("img-block1-desc");
  if(stepSub) stepSub.textContent = `${count} imagens individuais 1:1 + 1 imagem combo 2x2. Escolha exatamente 4 imagens para o vídeo.`;
  if(blockLabel) blockLabel.innerHTML = `Bloco 1 — ${count} imagens individuais <span>1:1 QUADRADO</span>`;
  if(blockDesc) blockDesc.textContent = hasDims
    ? "Fundo branco + imagem de medidas + arte informativa + cenas comerciais"
    : "Fundo branco + arte informativa + cenas ambientadas em contexto de uso";
}

function productPreservationInstruction(){
  return "Preserve completamente o produto da imagem de referência, sem alterar formato, cor, estrutura, material, acabamento, textura, proporção, detalhes ou identidade. Não adicione, remova ou invente partes, acessórios, funções, textos técnicos falsos ou especificações não fornecidas.";
}

function noTextOverlayInstruction(){
  return "Não adicione nenhum texto, título, legenda, label, callout, ícone, bullet point, tipografia, número, medida, interface, banner, selo, sticker, faixa ou elemento escrito. Do not add any text, titles, captions, labels, callouts, icons, bullet points, typography, numbers, measurements, interface elements, banners or stickers. A imagem deve ser puramente fotográfica/comercial visual, sem conteúdo escrito.";
}

function buildVisualSlotScene(role, baseScene){
  const base=String(baseScene || "").trim();
  const rolePrompts={
    cover_white_background:"Crie uma imagem principal de produto em fundo branco limpo, comercial e realista.",
    ambient_use_1:"Crie uma imagem comercial ambientada realista do produto em contexto de uso, premium e limpa.",
    ambient_use_2:"Crie uma segunda imagem comercial ambientada realista do produto em contexto de uso, com composição diferente da anterior.",
    ambient_use_3:"Crie uma terceira imagem comercial ambientada realista do produto em contexto de uso, com iluminação natural e cena elegante.",
    detail_finish:"Crie uma imagem de detalhe/acabamento do produto, com foco em textura, material e qualidade visual.",
    benefit_visual:"Crie uma imagem visual que comunique o principal benefício do produto pela cena e composição, sem usar texto.",
    hero_final:"Crie uma imagem hero premium do produto em uma cena final comercial, visualmente forte e limpa."
  };
  return [
    rolePrompts[role] || base || "Crie uma imagem comercial visual do produto.",
    base,
    productPreservationInstruction(),
    noTextOverlayInstruction()
  ].filter(Boolean).join(" ");
}

function makeImageSlot(key, label, role, scene, allowsText=false){
  const canUseText=role==="technical_dimensions" || role==="informative_features";
  return {
    key,
    label,
    role,
    allows_text: Boolean(allowsText && canUseText),
    scene: canUseText ? scene : buildVisualSlotScene(role, scene)
  };
}

function buildTechnicalDimensionsScene(dimsTxt){
  return [
    "Crie uma única imagem técnica com medidas reais do produto.",
    productPreservationInstruction(),
    "Mostre o produto em destaque com fundo claro, limpo e neutro.",
    `Inclua apenas estas dimensões reais fornecidas no contexto: ${dimsTxt || "não informado"}.`,
    "Use linhas, setas e marcadores discretos, legíveis e profissionais para largura, altura e/ou profundidade quando esses dados existirem.",
    "Não invente medidas. Se uma dimensão estiver ausente, não crie valor para ela.",
    "O layout deve parecer uma arte informativa profissional de e-commerce ou marketplace, limpa, organizada e elegante.",
    "Texto curto, objetivo e sem poluição visual. Proporção 1:1."
  ].join(" ");
}

function buildInformativeImageScene(){
  const hasManualWeight = Boolean(String(S.wt || "").trim());
  const facts=[
    S.name ? `Produto: ${S.name}.` : "",
    S.descTxt || S.desc ? `Contexto/descrição: ${S.descTxt || S.desc}.` : "",
    S.feat ? `Características reais: ${S.feat}.` : "",
    dimsText() ? `Dimensões disponíveis: ${dimsText()}.` : "",
    hasManualWeight ? `Peso informado manualmente: ${S.wt} g.` : "",
    S.volt && S.volt !== "N/A" ? `Voltagem informada: ${S.volt}.` : ""
  ].filter(Boolean).join(" ");

  return [
    "Crie uma única arte informativa comercial do produto.",
    productPreservationInstruction(),
    "Mostre o produto com destaque e organize no máximo 3 ou 4 características curtas, benefícios, usos ou especificações reais disponíveis no contexto.",
    facts ? `Use somente estas informações como fonte: ${facts}` : "Se faltarem especificações técnicas, use benefícios e contexto de uso de forma genérica, sem inventar dados.",
    hasManualWeight ? `Se exibir peso, use exatamente este valor: ${S.wt} g.` : "NUNCA exiba peso, gramagem, gramas, kg, balança ou qualquer número de peso, pois esse dado não foi informado manualmente.",
    "Todo texto, ícone, selo e elemento informativo deve ficar 100% dentro da área segura da imagem, com margens largas. Nada pode tocar, passar ou ser cortado pelas bordas.",
    "Use fonte menor quando necessário para caber tudo. Se não couber, reduza a quantidade de texto em vez de cortar palavras ou informações.",
    "Pode combinar o produto com uma cena de uso ou utilidade quando fizer sentido, sem modificar o item.",
    "Layout limpo, elegante, comercial e fácil de entender, com texto curto e objetivo.",
    "Não invente potência, voltagem, material, dimensões, peso, gramagem, capacidade, quantidades ou qualquer especificação técnica.",
    "Não use textos longos. Não repita essa lógica nas demais imagens.",
    "Proporção 1:1."
  ].join(" ");
}

function togglePlan(){setPlan(S.plan==="basic"?"premium":"basic");}
function setPlan(p){
  S.plan=p;
  document.getElementById("pc-b").className=`pc${p==="basic"?" sel":""}`;
  document.getElementById("pc-p").className=`pc${p==="premium"?" selp":""}`;
  document.getElementById("pex").classList.toggle("show",p==="premium");
  document.getElementById("plan-chip").textContent=p==="premium"?"PREMIUM":"BÁSICO";
  document.getElementById("plan-chip").className=`plan-chip${p==="premium"?" prem":""}`;
  syncImageStepCopy();
}

function colForm(){
  S.name=document.getElementById("f-nm").value.trim();
  S.desc=document.getElementById("f-dc").value.trim();
  S.x=document.getElementById("f-x").value;
  S.y=document.getElementById("f-y").value;
  S.z=document.getElementById("f-z").value;
  S.wt=document.getElementById("f-wt").value;
  S.lang=document.getElementById("f-lg").value;
  S.feat=document.getElementById("f-ft").value.trim();
  const ve=document.querySelector('input[name="vlt"]:checked');
  S.volt=ve?ve.value:"Bivolt";
  const voiceEl=document.querySelector('input[name="voice_style"]:checked');
  S.voiceStyle=normalizeVoiceStyle(voiceEl?voiceEl.value:"emocional");
  if(S.plan==="premium"){
    S.tone=document.getElementById("f-tn").value;
    S.audience=document.getElementById("f-au").value.trim();
  }else{
    S.tone="profissional";
    S.audience="";
  }
}

async function subCtx(){
  colForm();
  if(!S.plan){toast("Selecione um plano (Básico ou Premium) antes de continuar!","warn");return;}
  if(!S.name){toast("Informe o nome do produto!","error");return;}
  if(!ensureCfg()) return;
  unlock(3);go(3);startCopy();
}

async function startCopy(forceLarge=false){
  document.getElementById("c-ld").style.display="block";
  document.getElementById("c-out").style.display="none";
  document.getElementById("btn3").disabled=true;
  S.textApproved=false;
  const msg=document.getElementById("c-msg");
  let p=0, mi=0;
  const iv=setInterval(()=>{
    p=Math.min(p+12,90);
    
    if(mi<CMSGS.length) msg.textContent=CMSGS[mi++];
  },520);

  try{
    const prompt = buildTextPrompt(forceLarge);
    const raw = await apiGenerateText(prompt, S.imgs);
    clearInterval(iv);
    
    const parsed = parseJSON(raw);
    const titlesArray = parsed?.titles || parsed?.titulos || parsed?.títulos || [];
    if(titlesArray && Array.isArray(titlesArray) && titlesArray.length >= 4){
      S.titles = titlesArray.slice(0,4);
      S.descTxt = parsed?.description || parsed?.descricao || parsed?.descrição || "";
    } else {
      throw new Error("A IA não retornou 4 títulos válidos.");
    }
    if(!S.descTxt) throw new Error("A descrição não foi retornada.");
    setTimeout(()=>{
      document.getElementById("c-ld").style.display="none";
      renderCopy();
      document.getElementById("c-out").style.display="block";
    },220);
  }catch(e){
    clearInterval(iv);
    document.getElementById("c-ld").style.display="none";
    toast(`Erro ao gerar textos: ${e.message}`,"error");
  }
}

function buildTextPrompt(forceLarge=false){
  const promptTemplate = forceLarge 
    ? S.cfg.textPrompt.replace(/curta, escaneável e persuasiva/g, "longa e detalhada").replace(/exatamente 4 títulos/g, "exatamente 4 títulos longos e elaborados")
    : S.cfg.textPrompt;

  return fillTpl(promptTemplate, {
    languageLabel: languageLabel(),
    name: S.name || "não informado",
    desc: S.desc || "não informado",
    dims: dimsText() || "não informado",
    weight: S.wt ? `${S.wt} g` : "não informado",
    volt: S.volt || "N/A",
    feat: S.feat || "não informado",
    tone: S.tone || "profissional",
    audience: S.audience || "compradores online"
  });
}

function fillTpl(str,map){
  return String(str || "").replace(/\{\{(\w+)\}\}/g, (_, key) => {
    const v = map[key];
    return v === undefined || v === null || v === "" ? "não informado" : String(v);
  });
}

function languageLabel(){
  return {"pt-BR":"português do Brasil","en-US":"English","es-ES":"español","fr-FR":"français"}[S.lang] || "português do Brasil";
}

function dimsText(){
  const vals=[S.x,S.y,S.z].filter(Boolean);
  return vals.length ? `${S.x || "-"} x ${S.y || "-"} x ${S.z || "-"} cm` : "";
}

function renderCopy(){
  const g=document.getElementById("ttg");
  const tags=["SEO","Informativa","Benefício","Diferencial"];
  g.innerHTML="";
  const badgeClasses = ["cct-seo","cct-info","cct-ben","cct-dif"];
  S.titles.forEach((t,i)=>{
    let safeT = String(t).replace(/\.\.\.$/, "").trim();
    if (safeT.length > 60) {
      let words = safeT.split(" ");
      let newT = "";
      for (let w of words) {
        if ((newT + (newT ? " " : "") + w).length <= 60) {
          newT += (newT ? " " : "") + w;
        } else {
          break;
        }
      }
      safeT = newT;
    }
    
    let prevT;
    do {
      prevT = safeT;
      safeT = safeT.replace(/\s+(e|ou|de|da|do|das|dos|para|com|sem|em|na|no|nas|nos|a|o|as|os|um|uma|uns|umas|por|pelo|pela|pelos|pelas|[-:,;]+)$/i, "").trim();
    } while(prevT !== safeT && safeT.length > 0);
    
    if(!safeT) safeT = String(t).substring(0, 60);
    S.titles[i] = safeT;
    
    g.innerHTML += `<div class="cc" id="tc${i}"><div><div class="cct ${badgeClasses[i]||''}"> ${tags[i] || "Estratégia"}</div></div><div class="cctx" contenteditable="true" id="tt${i}">${esc(safeT)}</div><div class="cca"><button class="btn bg" style="padding:6px;border-radius:6px;background:var(--bg-card);border:1px solid var(--bd)" onclick="cpEl('tt${i}')" title="Copiar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button></div></div>`;
  });
  
  const descText = String(S.descTxt || "");
  const descParagraphs = descText.split(/\r?\n/).filter(p => p.trim().length > 0);
  const formattedDesc = descParagraphs.map(p => {
    p = p.trim();
    if(p.startsWith('-') || p.startsWith('•') || p.startsWith('*')) {
      return p + '<br>';
    }
    return '<br>' + p + '<br>';
  }).join('').replace(/^(<br>)+/, '');
  document.getElementById("d-tx").innerHTML = formattedDesc;
  document.getElementById("ab").style.display = "flex";
}

function regenCopy(forceLarge=false){
  S.textApproved=false;
  document.getElementById("btn3").disabled=true;
  document.querySelectorAll(".cc").forEach(c=>c.classList.remove("appr"));
  document.getElementById("ab").style.display="flex";
  startCopy(forceLarge);
}

async function regenCopyLong(){
  const btn = document.getElementById("btn-tx-long");
  if(btn) { btn.disabled = true; btn.textContent = "Gerando..."; }
  
  try{
    const prompt = buildTextPrompt(true);
    const raw = await apiGenerateText(prompt, S.imgs);
    const parsed = parseJSON(raw);
    const titlesArray = parsed?.titles || parsed?.titulos || parsed?.títulos || [];
    if(titlesArray && Array.isArray(titlesArray) && titlesArray.length >= 4){
      const ttg = document.getElementById("ttg-long");
      const tags=["SEO","Informativa","Benefício","Diferencial"];
      ttg.innerHTML="";
      const badgeClasses = ["cct-seo","cct-info","cct-ben","cct-dif"];
      titlesArray.slice(0,4).forEach((t,i)=>{
        ttg.innerHTML += `<div class="cc"><div><div class="cct ${badgeClasses[i]||''}"> ${tags[i] || "Estratégia"}</div></div><div class="cctx" contenteditable="true" id="tt-long${i}">${esc(t)}</div><div class="cca"><button class="btn bg" style="padding:6px;border-radius:6px;background:var(--bg-card);border:1px solid var(--bd)" onclick="cpEl('tt-long${i}')" title="Copiar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button></div></div>`;
      });
      
      const descLong = String(parsed?.description || parsed?.descricao || parsed?.descrição || "");
      const descParagraphs = descLong.split(/\r?\n/).filter(p => p.trim().length > 0);
      const formattedDesc = descParagraphs.map(p => {
        p = p.trim();
        if(p.startsWith('-') || p.startsWith('•') || p.startsWith('*')) {
          return p + '<br>';
        }
        return '<br>' + p + '<br>';
      }).join('').replace(/^(<br>)+/, '');
      document.getElementById("d-tx-long").innerHTML = formattedDesc;
      document.getElementById("tx-long-container").style.display = "block";
      toast("Textos longos gerados com sucesso!", "success");
    } else {
      throw new Error("Formato inválido.");
    }
  }catch(e){
    toast(`Erro ao gerar textos longos: ${e.message}`,"error");
  } finally {
    if(btn) { btn.disabled = false; btn.textContent = "Gerar Títulos Longos e Persuasivos"; }
  }
}

function approveTexts(){
  S.titles=S.titles.map((_,i)=>{const e=document.getElementById(`tt${i}`);return e?(e.innerText||e.textContent).trim():S.titles[i];});
  const d=document.getElementById("d-tx");
  S.descTxt = d ? (d.innerText || d.textContent).trim() : S.descTxt;
  S.textApproved=true;
  document.getElementById("btn3").disabled=false;
  document.getElementById("ab").style.display="none";
  document.querySelectorAll(".cc").forEach(c=>c.classList.add("appr"));
  unlock(4);
  toast("Textos aprovados! Gere as imagens.","success");
}

async function startImgGen(){
  if(!ensureCfg()) return;
  const ref=S.imgs[0];
  if(!ref){ toast("Envie ao menos uma imagem de referência.","error"); go(1); return; }
  const imageTypes = currentImageTypes();

  const bar=document.getElementById("i-pb"), msg=document.getElementById("i-msg");
  document.getElementById("i-ld-bottom").style.display="block";
  document.getElementById("i-out").style.display="block";
  document.getElementById("i-act").style.display="none";
  S.imgs4=[]; S.comboUrl=null; S.selVid=[]; S.imageComboJobs=[]; S.imageDiagnostics={};
  document.getElementById("vsc").textContent="0/4 selecionadas";

  const g=document.getElementById("grid4");g.innerHTML="";
  // Adiciona a classe que renderiza a animação nos cards individuais
  imageTypes.forEach(t=>{
    const tile=document.createElement("div");
    tile.className="img4-tile gen";
    tile.id=`t4-${t.key}`;
    tile.innerHTML=`<div class="img-ph-pulse"></div><div class="img-ph"><div class="ph-ic">🎨</div><div class="ph-tx">${t.label}</div></div>`;
    g.appendChild(tile);
  });

  // Ocultar a section superior que continha o texto do Bloco 2 se houver
  // document.getElementById("combo-loading").style.display="flex"; 
  // document.getElementById("combo-info").style.display="none";
  const cMsg = document.getElementById("combo-msg");
  if(cMsg) cMsg.textContent="Preparando a composição final...";
  const cPb = document.getElementById("combo-pb");
  if(cPb) cPb.style.width="0%";

  const pb = document.getElementById("i-pb");
  const pMsg = document.getElementById("i-msg");
  if(pb) {
    pb.style.width = "0%";
    pb.classList.remove("done");
  }
  if(pMsg) pMsg.textContent = "Gerando suas imagens...";

  let prog = 8;
  const pbInterval = setInterval(() => {
    const ready = S.imageDiagnostics?.images_ready_count || S.imgs4.length || 0;
    const total = S.imageDiagnostics?.images_total_count || imageTypes.length || 8;
    const target = Math.min(94, 12 + Math.round((ready / Math.max(1,total)) * 76));
    if(prog < target) prog += 2;
    else if(prog < 90) prog += 1;
    if(pb) pb.style.width = prog + "%";
    if(pMsg) pMsg.innerHTML = `Gerando suas imagens... ${ready} de ${total} prontas <span>${prog}%</span>`;
  }, 1000);

  try{
    const comboBatches = buildImageComboBatches(imageTypes);
    S.imageComboJobs = comboBatches.map(combo => createImageComboJob(combo));
    updateImageComboDiagnostics({images_total_count:imageTypes.length});
    const comboResults = await runImageComboQueue(comboBatches, ref, imageTypes.length);
    const hasError = comboResults.some(result => !result?.ok);

    if(S.imgs4.length >= 4) {
      msg.textContent = "Montando imagem combo 2×2...";
      
      
      
      S.comboUrl = await buildCombo2x2(comboSourceImages());
      renderCombo(S.comboUrl);
      
    } else {
      
    }

        clearInterval(pbInterval);
    if(pb) pb.style.width = "100%";
    if(pb) pb.classList.add("done");
    if(pMsg) pMsg.innerHTML = hasError ? `Imagens geradas com avisos. ${S.imgs4.length} de ${imageTypes.length} prontas.` : "Imagens prontas! <span>100%</span>";
    setTimeout(() => { document.getElementById("i-ld-bottom").style.display="none"; }, 1500);
    document.getElementById("i-ld").style.display="none";
    document.getElementById("i-act").style.display="block";
    unlock(5);
    if(!hasError) toast("Imagens prontas!","success");
    else toast("Geração concluída, mas algumas imagens falharam.", "warn");
  }catch(e){
    console.error(e);
    document.getElementById("i-ld").style.display="none";
    toast(`Erro crítico ao gerar imagens: ${e.message}`,"error");
  }
}

const MAX_CONCURRENT_IMAGE_COMBO_GENERATIONS = 2;
const IMAGE_COMBO_MAX_ATTEMPTS = 3;
const IMAGE_COMBO_STALE_SECONDS = 120;

function buildImageComboBatches(imageTypes){
  const batches=[];
  for(let i=0; i<imageTypes.length; i+=4){
    const items=imageTypes.slice(i, i+4);
    if(items.length){
      batches.push({combo_index:batches.length + 1, start_index:i + 1, items});
    }
  }
  return batches;
}

function createImageComboJob(combo){
  return {
    combo_index:combo.combo_index,
    prompt:"",
    status:"pending",
    attempt:0,
    max_attempts:IMAGE_COMBO_MAX_ATTEMPTS,
    source_combo_url:"",
    cropped_image_indexes:combo.items.map((_,i)=>combo.start_index + i),
    cropped_image_urls:[],
    error:"",
    started_at:"",
    finished_at:""
  };
}

function updateImageComboJob(comboIndex, changes={}){
  const now=new Date().toISOString();
  const existing=Array.isArray(S.imageComboJobs) ? S.imageComboJobs : [];
  const idx=existing.findIndex(job=>Number(job.combo_index)===Number(comboIndex));
  const current=idx>=0 ? existing[idx] : createImageComboJob({combo_index:comboIndex,start_index:(comboIndex-1)*4+1,items:[1,2,3,4]});
  const next={...current,...changes,combo_index:Number(comboIndex)};
  if(changes.status==="generating" || changes.status==="retrying") next.started_at=next.started_at || now;
  if(["ready","cropped","error_final"].includes(changes.status)) next.finished_at=changes.finished_at || now;
  if(idx>=0) S.imageComboJobs[idx]=next;
  else S.imageComboJobs.push(next);
  updateImageComboDiagnostics();
  return next;
}

function imageComboAgeSeconds(job){
  const ts=Date.parse(job?.started_at || "");
  return ts ? Math.max(0, Math.floor((Date.now()-ts)/1000)) : 0;
}

function isImageComboStale(job){
  const status=String(job?.status || "pending");
  return ["generating","retrying"].includes(status) && !job?.source_combo_url && imageComboAgeSeconds(job)>=IMAGE_COMBO_STALE_SECONDS;
}

function updateImageComboDiagnostics(extra={}){
  const jobs=Array.isArray(S.imageComboJobs) ? S.imageComboJobs : [];
  const active=jobs.filter(job=>["generating","retrying","cropping"].includes(String(job.status)) && !isImageComboStale(job)).length;
  const ready=jobs.filter(job=>["ready","cropped"].includes(String(job.status))).length;
  const cropped=jobs.filter(job=>String(job.status)==="cropped").length;
  S.imageDiagnostics={
    image_combos_total_count:jobs.length,
    image_combos_ready_count:ready,
    image_combos_cropped_count:cropped,
    active_image_combo_generations_count:active,
    max_concurrent_image_combo_generations:MAX_CONCURRENT_IMAGE_COMBO_GENERATIONS,
    started_combo_indexes:jobs.filter(job=>["generating","cropping"].includes(String(job.status))).map(job=>Number(job.combo_index)),
    retry_combo_indexes:jobs.filter(job=>String(job.status)==="retrying").map(job=>Number(job.combo_index)),
    combo_jobs_summary:jobs.map(job=>({
      combo_index:Number(job.combo_index),
      status:String(job.status || "pending"),
      attempt:Number(job.attempt || 0),
      max_attempts:Number(job.max_attempts || IMAGE_COMBO_MAX_ATTEMPTS),
      has_source_combo_url:Boolean(job.source_combo_url),
      cropped_image_indexes:Array.isArray(job.cropped_image_indexes) ? job.cropped_image_indexes : [],
      cropped_image_urls_count:Array.isArray(job.cropped_image_urls) ? job.cropped_image_urls.filter(Boolean).length : 0,
      error:String(job.error || ""),
      age_seconds:imageComboAgeSeconds(job),
      is_stale:isImageComboStale(job)
    })),
    images_ready_count:S.imgs4.length,
    images_total_count:extra.images_total_count || S.imageDiagnostics?.images_total_count || currentImageTypes().length,
    ...(extra || {})
  };
}

function setComboTilesLoading(combo, text="Gerando imagem"){
  combo.items.forEach((t,i)=>{
    const tile=document.getElementById(`t4-${t.key}`);
    if(tile && !tile.querySelector("img")){
      tile.classList.add("gen");
      tile.innerHTML=`<div class="img-ph-pulse"></div><div class="img-ph"><div style="width:30px;height:30px;border:3px solid transparent;border-top-color:var(--acc);border-bottom-color:var(--acc);border-radius:50%;animation:sp 1s linear infinite;margin-bottom:8px"></div><div class="ph-tx">${esc(text)} ${combo.start_index + i}</div></div>`;
    }
  });
}

function upsertGeneratedImage(t, url, absoluteIndex){
  const existingIndex=S.imgs4.findIndex(img=>img.key===t.key);
  const item={key:t.key,label:t.label,url,index:absoluteIndex};
  if(existingIndex>=0) S.imgs4[existingIndex]={...S.imgs4[existingIndex],...item};
  else S.imgs4.push(item);
  S.imgs4.sort((a,b)=>(Number(a.index || 99)-Number(b.index || 99)));
  updateImageComboDiagnostics();
}

async function runImageComboQueue(comboBatches, ref, totalImages){
  const results=[];
  let cursor=0;
  async function worker(){
    while(cursor<comboBatches.length){
      const combo=comboBatches[cursor++];
      results[combo.combo_index-1]=await runImageComboWithRetries(combo, ref, totalImages);
    }
  }
  const workers=Array.from({length:Math.min(MAX_CONCURRENT_IMAGE_COMBO_GENERATIONS, comboBatches.length)}, worker);
  await Promise.all(workers);
  return results;
}

async function runImageComboWithRetries(combo, ref, totalImages){
  for(let attempt=1; attempt<=IMAGE_COMBO_MAX_ATTEMPTS; attempt++){
    const status=attempt>1 ? "retrying" : "generating";
    updateImageComboJob(combo.combo_index,{status,attempt,error:""});
    updateImageComboDiagnostics({
      started_combo_indexes:S.imageComboJobs.filter(job=>["generating","retrying"].includes(job.status)).map(job=>job.combo_index),
      retry_combo_indexes:S.imageComboJobs.filter(job=>job.status==="retrying").map(job=>job.combo_index),
      images_total_count:totalImages
    });
    setComboTilesLoading(combo, attempt>1 ? "Tentando novamente" : "Gerando imagem");

    try{
      const scene=getBatchScene(combo);
      const prompt=fillTpl(S.cfg.imagePrompt, {
        scene,
        name:S.name || "não informado",
        desc:S.desc || "não informado",
        feat:S.feat || "não informado"
      });
      updateImageComboJob(combo.combo_index,{prompt});
      const sourceUrl=await apiGenerateImage(prompt, ref);
      updateImageComboJob(combo.combo_index,{status:"ready",source_combo_url:sourceUrl});
      const urls=await cropImageCombo(combo, sourceUrl, totalImages);
      updateImageComboJob(combo.combo_index,{status:"cropped",cropped_image_urls:urls,error:""});
      return {ok:true,combo_index:combo.combo_index,urls};
    }catch(err){
      console.error(`Erro no combo ${combo.combo_index}`, err);
      updateImageComboJob(combo.combo_index,{status:attempt>=IMAGE_COMBO_MAX_ATTEMPTS ? "error_final" : "pending",attempt,error:err.message || "Erro ao gerar combo"});
      if(attempt>=IMAGE_COMBO_MAX_ATTEMPTS){
        combo.items.forEach(t=>renderTileError(t, err.message || "Erro ao gerar imagem"));
        return {ok:false,combo_index:combo.combo_index,error:err};
      }
      await delay(attempt===1 ? 2000 : 4000);
    }
  }
  return {ok:false,combo_index:combo.combo_index};
}

async function cropImageCombo(combo, sourceUrl, totalImages){
  updateImageComboJob(combo.combo_index,{status:"cropping"});
  updateImageComboDiagnostics({images_total_count:totalImages});
  const croppedUrls=await splitImageInto4(sourceUrl);
  const urls=[];
  for(let i=0; i<combo.items.length; i++){
    const t=combo.items[i];
    const absoluteIndex=combo.start_index + i;
    const url=await resizeSquare(croppedUrls[i], getSelectedSquarePx());
    urls.push(url);
    upsertGeneratedImage(t, url, absoluteIndex);
    renderTile4(t, url);
    const pMsg=document.getElementById("i-msg");
    if(pMsg) pMsg.innerHTML=`Gerando suas imagens... ${S.imgs4.length} de ${totalImages} prontas`;
    await delay(180);
  }
  return urls;
}

async function genMoreImages(btn) {
  if(!ensureCfg()) return;
  const ref = S.imgs[0];
  if(!ref) return;

  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<div style="width:14px;height:14px;border:2px solid transparent;border-top-color:#000;border-bottom-color:#000;border-radius:50%;animation:sp 1s linear infinite;margin-right:6px"></div> Gerando mais 4 imagens...`;

  const prodName = S.name || "produto";
  const scene = [
    fillTpl(S.cfg.promptBatchMore || window.stlaiConfig.promptBatchMore, { name: prodName }),
    productPreservationInstruction(),
    noTextOverlayInstruction()
  ].join(" ");

  try {
    const prompt = fillTpl(S.cfg.imagePrompt, {
      scene,
      name: prodName,
      desc: S.desc || "não informado",
      feat: S.feat || "não informado"
    });
    
    const masterUrl = await apiGenerateImage(prompt, ref);
    const croppedUrls = await splitImageInto4(masterUrl);
    
    const grid4 = document.getElementById("grid4");

    for(let i=0; i<4; i++){
       const url = await resizeSquare(croppedUrls[i], getSelectedSquarePx());
       const tKey = "extra_" + Date.now() + "_" + i;
       const tLabel = "Ambientada Extra " + (S.imgs4.length + 1);
       const img={ key: tKey, label: tLabel, url };
       S.imgs4.push(img);
       if (grid4) {
         const tile=document.createElement("div");
         tile.className="img4-tile";
         tile.id=`t4-${tKey}`;
         grid4.appendChild(tile);
         renderTile4(img, url);
       }
    }
    
    // Update score since we added 4 images
    popSum();
    toast("4 novas imagens adicionadas à galeria!", "success");
  } catch(err) {
    console.error("Erro ao gerar mais imagens", err);
    toast(`Erro: ${err.message}`, "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
  }
}

async function genDimsImage(btn) {
  if(!ensureCfg()) return;
  const ref = S.imgs[0];
  if(!ref) return;

  const dx = document.getElementById("dm-x").value;
  const dy = document.getElementById("dm-y").value;
  const dz = document.getElementById("dm-z").value;
  if(!dx && !dy && !dz) {
    toast("Informe pelo menos uma medida.", "warn");
    return;
  }

  S.x = dx; S.y = dy; S.z = dz;
  
  document.getElementById('dims-md').classList.remove('show');
  
  const tKey = "dims_" + Date.now();
  const tLabel = "Medidas — Fundo Branco";
  
  const tileHtml = `<div class="img4-tile gen" id="t4-${tKey}"><div class="img-ph"><div style="width:30px;height:30px;border:3px solid transparent;border-top-color:var(--acc);border-bottom-color:var(--acc);border-radius:50%;animation:sp 1s linear infinite;margin-bottom:8px"></div><div class="ph-tx">Gerando medidas...</div></div></div>`;
  
  const sumG4 = document.getElementById("sum-g4");
  const grid4 = document.getElementById("grid4");
  if(sumG4) sumG4.insertAdjacentHTML('afterbegin', tileHtml);
  if(grid4) grid4.insertAdjacentHTML('afterbegin', tileHtml);

  btn.disabled = true;

  const prodName = S.name || "produto";
  const dimsTxt = [S.x, S.y, S.z].filter(Boolean).join("x") + " cm";
  const scene = fillTpl(S.cfg.promptDims || window.stlaiConfig.promptDims, { name: prodName, dimsTxt });

  try {
    const prompt = fillTpl(S.cfg.imagePrompt, {
      scene,
      name: prodName,
      desc: S.desc || "não informado",
      feat: S.feat || "não informado"
    });
    
    const url = await apiGenerateImage(prompt, ref);
    
    S.imgs4.unshift({ key: tKey, label: tLabel, url });
    popSum();
    if(document.getElementById('grid4')) {
      renderTile4({key: tKey, label: tLabel}, url);
    }
    toast("Imagem de dimensões gerada com sucesso! Seu score aumentou.", "success");
  } catch(err) {
    console.error("Erro ao gerar dimensões", err);
    toast(`Erro: ${err.message}`, "error");
    const el = document.getElementById(`t4-${tKey}`);
    if(el) el.remove();
  } finally {
    btn.disabled = false;
  }
}

function getBatchScene(batchOrCombo) {
  if(batchOrCombo && typeof batchOrCombo==="object" && Array.isArray(batchOrCombo.items)){
    return buildImageComboScene(batchOrCombo);
  }

  const batchNum=Number(batchOrCombo || 1);
  const prodName = S.name || "produto";
  const hasDims = S.x || S.y || S.z;
  const dimsTxt = [S.x, S.y, S.z].filter(Boolean).join("x") + " cm";

  if (batchNum === 1) {
    let q2 = hasDims 
      ? `- Top-Right Quadrant: Technical photography of the exact same ${prodName} on a pure white background, keeping the exact same shape and appearance but adding subtle professional dimension lines indicating the size: ${dimsTxt}.`
      : `- Top-Right Quadrant: The exact same ${prodName} placed in a realistic lifestyle setting perfectly suited for its actual use case and context.`;

    return fillTpl(S.cfg.promptBatch1 || window.stlaiConfig.promptBatch1, { name: prodName, q2 });
  } else {
    return fillTpl(S.cfg.promptBatch2 || window.stlaiConfig.promptBatch2, { name: prodName });
  }
}

function buildImageComboScene(combo){
  const positions=[
    "Top-Left Quadrant",
    "Top-Right Quadrant",
    "Bottom-Left Quadrant",
    "Bottom-Right Quadrant"
  ];
  const facts=[
    S.name ? `Product name: ${S.name}.` : "",
    S.descTxt || S.desc ? `Description/context: ${S.descTxt || S.desc}.` : "",
    S.feat ? `Real features: ${S.feat}.` : "",
    dimsText() ? `Real dimensions: ${dimsText()}.` : "",
    S.wt ? `Real weight: ${S.wt} g.` : "",
    S.volt && S.volt !== "N/A" ? `Real voltage: ${S.volt}.` : ""
  ].filter(Boolean).join(" ");
  const quadrants=combo.items.map((item,index)=>{
    const position=positions[index] || `Quadrant ${index + 1}`;
    const label=item.label || `Imagem ${combo.start_index + index}`;
    const allowsText=Boolean(item.allows_text && (item.role==="technical_dimensions" || item.role==="informative_features"));
    const textRule=allowsText
      ? "TEXT IS ALLOWED ONLY IN THIS QUADRANT, and only as short factual marketplace copy based on the provided context."
      : "NO TEXT ALLOWED IN THIS QUADRANT: no titles, captions, labels, icons, bullets, numbers, measurements, callouts, banners, stickers or typography.";
    return `- ${position} (${label}, role: ${item.role || "visual"}, allows_text: ${allowsText ? "true" : "false"}): ${textRule} ${item.scene || label}`;
  }).join("\n");

  return [
    "Generate exactly ONE large square image divided evenly into a clean 2x2 grid. Each quadrant must be a complete independent 1:1 commercial image, because it will be cropped into separate product images.",
    "Do not merge quadrants. Do not create diagonal layouts. Keep the product fully visible in each quadrant.",
    "The product from the reference image is mandatory and must remain exactly the same physical item in every quadrant.",
    "Do not modify the product shape, color, material, structure, texture, proportions, finish, printed details, accessories, identity or function.",
    "Do not add, remove, redesign, stylize, replace or invent any part of the product.",
    "Use only factual information provided here. Never invent dimensions, material, power, voltage, capacity, quantity, weight, grams, kg or technical specifications.",
    facts ? `Available factual context: ${facts}` : "Available factual context is limited; use generic commercial benefits and usage context without inventing technical data.",
    "There can be at most one informative/features quadrant. There can be at most one technical dimensions quadrant, and only if real dimensions were provided.",
    "All visual quadrants must be purely photographic/commercial with absolutely no written content.",
    "Quadrant plan:",
    quadrants,
    "Do not copy the informative layout into visual quadrants. Do not add text to ambient, detail, benefit or hero images. The benefit image must communicate by scene and composition, not words.",
    "For the technical dimensions quadrant, use only real dimensions from the context with subtle lines/arrows. If dimensions are missing, do not invent them.",
    "For the informative/features quadrant, use at most 3 or 4 short real points. Do not use long text.",
    "For every informative text area, keep all text, icons and labels fully inside safe margins. Nothing may touch or be cropped by image borders. If content does not fit, shorten it instead of cropping.",
    S.wt ? `Only show weight if needed using exactly this manually provided value: ${S.wt} g.` : "Do not show weight, grams, kg, scale icons or any weight value anywhere because no manual weight was provided.",
    "Overall style: premium marketplace product imagery, realistic, clean, elegant, commercially useful, no watermark, no UI, no fake logos, no extra props attached to the product."
  ].join("\n");
}

async function genBatchImage(batchNum, ref) {
  const scene = getBatchScene(batchNum);
  const prompt = fillTpl(S.cfg.imagePrompt, {
    scene,
    name: S.name || "não informado",
    desc: S.desc || "não informado",
    feat: S.feat || "não informado"
  });
  const rawUrl = await apiGenerateImage(prompt, ref);
  return rawUrl;
}

async function splitImageInto4(imageUrl) {
  const img = await loadImage(imageUrl);
  const w = Math.floor(img.naturalWidth / 2);
  const h = Math.floor(img.naturalHeight / 2);
  const canvas = document.getElementById("offscreen-canvas");
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext("2d");
  const urls = [];
  const positions = [
    [0, 0], [w, 0],
    [0, h], [w, h]
  ];
  const crop = 3; // Corta 3px de cada borda interna para remover linhas brancas da IA
  for (let i = 0; i < 4; i++) {
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, w, h);
    // As posições originais eram: 0,0 | w,0 | 0,h | w,h
    // Ajustamos source X, Y, Width e Height para comer a borda.
    const crop = 16; // Corta 16px de todas as bordas para remover qualquer linha preta ou branca da IA
    let sx = positions[i][0] + crop;
    let sy = positions[i][1] + crop;
    let sw = w - crop * 2;
    let sh = h - crop * 2;

    ctx.drawImage(img, sx, sy, sw, sh, 0, 0, w, h);
    urls.push(canvas.toDataURL("image/jpeg", 0.95));
  }
  return urls;
}

async function genSceneImage(scene, ref){
  const prompt = fillTpl(S.cfg.imagePrompt, {
    scene,
    name: S.name || "não informado",
    desc: S.desc || "não informado",
    feat: S.feat || "não informado"
  });
  const rawUrl = await apiGenerateImage(prompt, ref);
  const target = getSelectedSquarePx();
  return resizeSquare(rawUrl, target);
}

function getSelectedSquarePx(){
  const chosen = document.querySelector('input[name="res"]:checked')?.value || "1000";
  return Number(chosen);
}

function renderTile4(t,url){
  const tile=document.getElementById(`t4-${t.key}`);
  if(!tile)return;
  const imageUrl=resolveImageUrl(url);
  tile.classList.remove("gen", "error");
  tile.innerHTML=`<img src="${imageUrl}" alt="${esc(t.label)}" style="opacity:0; transition:opacity 0.6s ease" onload="this.style.opacity=1"><button class="img4-view-btn" type="button" title="Ver imagem maior" aria-label="Ver imagem maior"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg></button><div class="img4-tile-lbl">${esc(t.label)}</div>`;
  const viewBtn=tile.querySelector(".img4-view-btn");
  if(viewBtn) viewBtn.addEventListener("click", (e)=>{ e.preventDefault(); e.stopPropagation(); openLightbox(imageUrl, "image", t.label); });
  tile.onclick=(e)=>{ if(e.target.closest('button')) return; togVid(t.key,tile); };
}

function renderTileError(t, msg){
  const tile=document.getElementById(`t4-${t.key}`);
  if(!tile)return;
  tile.classList.remove("gen");
  tile.classList.add("error");
  tile.innerHTML=`<div class="img-ph" style="padding:10px;text-align:center"><div class="ph-ic" style="color:var(--coral);font-size:18px;margin-bottom:4px">⚠</div><div class="ph-tx" style="color:var(--coral);font-weight:bold;margin-bottom:2px">Falha</div><div class="ph-tx" style="font-size:9px;opacity:0.8;word-break:break-word">${esc(msg)}</div></div><div class="img4-tile-ov"><button class="btn bs bsm" onclick="regenSingleImage('${t.key}')">🔄 Tentar</button></div>`;
  tile.onclick=null;
}

async function regenSingleImage(key) {
  const types = currentImageTypes();
  const t = types.find(x=>x.key===key);
  if(!t)return;
  let tile = document.getElementById(`t4-${key}`);
  let isSumTile = false;
  if(!tile) {
      tile = document.getElementById(`sum-t4-${key}`);
      isSumTile = true;
  }
  if(tile) {
    tile.classList.remove("error");
    tile.classList.add("gen");
    tile.innerHTML=`<div class="img-ph-pulse"></div><div class="img-ph"><div style="width:30px;height:30px;border:3px solid transparent;border-top-color:var(--acc);border-bottom-color:var(--acc);border-radius:50%;animation:sp 1s linear infinite;margin-bottom:8px"></div></div>`;
  }
  const ref=S.imgs[0];
  try {
    const url = await genSceneImage(t.scene, ref);
    const idx = S.imgs4.findIndex(i=>i.key===key);
    if(idx>-1) S.imgs4[idx].url = url;
    else S.imgs4.push({ key:t.key, label:t.label, url });
    
    if(isSumTile) {
      popSum();
    } else if(tile) {
      renderTile4(t, url);
    }
    toast(`${t.label} regerada!`, "success");
    if (S.imgs4.length >= 4) {
      S.comboUrl = await buildCombo2x2(comboSourceImages());
      renderCombo(S.comboUrl);
    }
  } catch (err) {
    console.error("Erro na cena", t.key, err);
    if(tile && !isSumTile) {
        renderTileError(t, err.message);
    } else if (isSumTile) {
        tile.classList.remove("gen");
        tile.classList.add("error");
        tile.innerHTML=`<div class="img-ph" style="padding:10px;text-align:center"><div class="ph-ic" style="color:var(--coral);font-size:18px;margin-bottom:4px">⚠</div><div class="ph-tx" style="color:var(--coral);font-weight:bold;margin-bottom:2px">Falha</div></div><div class="img4-tile-ov"><button class="btn bs bsm" onclick="regenSingleImage('${t.key}')">🔄 Tentar</button></div>`;
    }
    toast(`Erro ao regerar: ${err.message}`, "error");
  }
}

function renderCombo(url){
  const w=document.getElementById("combo-wrap");
  if(w) w.innerHTML=`<img src="${url}" alt="Combo 2x2"><div class="combo-ov"><button class="btn bs bsm" onclick="window.dlCombo()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Download</button><button class="btn bs bsm" onclick="window.openLightbox('${url}')" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button></div>`;
  const sw=document.getElementById("sum-combo");
  if(sw) sw.innerHTML=`<img src="${url}" alt="Combo 2x2" style="width:100%;height:100%;object-fit:cover"><div class="combo-ov"><button class="btn bs bsm" onclick="window.dlCombo()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button><button class="btn bs bsm" onclick="window.openLightbox('${url}')" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button></div>`;
}

function comboSourceImages(){
  const preferred = S.imgs4.filter(img => img.key !== "capa").slice(0, 4);
  if (preferred.length === 4) return preferred.map(img => resolveImageUrl(img));
  return S.imgs4.slice(0, 4).map(img => resolveImageUrl(img));
}

async function buildCombo2x2(urls){
  const size = Math.max(getSelectedSquarePx(), 1000);
  const half = size/2;
  const canvas=document.getElementById("offscreen-canvas");
  canvas.width=size;canvas.height=size;
  const ctx=canvas.getContext("2d");
  ctx.fillStyle="#ffffff";
  ctx.fillRect(0,0,size,size);
  const positions=[[0,0],[half,0],[0,half],[half,half]];
  for(let i=0;i<4;i++){
    const url=urls[i]||"";
    if(!url)continue;
    await new Promise(res=>{
      const img=new Image();
      img.onload=()=>{
        const sw=img.naturalWidth, sh=img.naturalHeight;
        const side=Math.min(sw,sh);
        const sx=(sw-side)/2, sy=(sh-side)/2;
        const [px,py]=positions[i];
        ctx.drawImage(img,sx,sy,side,side,px,py,half,half);
        res();
      };
      img.onerror=()=>res();
      img.src=url;
    });
  }
  ctx.strokeStyle="rgba(255,255,255,0.9)";
  ctx.lineWidth=3;
  ctx.beginPath();ctx.moveTo(half,0);ctx.lineTo(half,size);ctx.stroke();
  ctx.beginPath();ctx.moveTo(0,half);ctx.lineTo(size,half);ctx.stroke();
  return canvas.toDataURL("image/jpeg",0.95);
}

function togVid(key,tile){
  const idx=S.selVid.indexOf(key);
  if(idx>-1){S.selVid.splice(idx,1);tile.classList.remove("fv");}
  else{
    if(S.selVid.length>=4){toast("Escolha exatamente 4 imagens para o vídeo.","warn");return;}
    S.selVid.push(key);tile.classList.add("fv");
  }
  document.getElementById("vsc").textContent=`${S.selVid.length}/4 selecionadas`;
}

function goVideoStep(){
  if(S.selVid.length!==4){
    toast("Selecione exatamente 4 imagens para gerar o vídeo.","warn");
    return;
  }
  go(5);
}

function goVideoResult(){
  unlock(6);
  go(6);
  setTimeout(()=>{
    const target=document.getElementById("s6");
    if(target) target.scrollIntoView({behavior:"smooth", block:"start"});
  }, 120);
}

function dlImg(url,label){
  const clean=resolveImageUrl(url);
  if(!clean){toast("Imagem ainda não disponível.","warn");return;}
  const a=document.createElement("a");
  a.href=clean;
  a.download=`stlai-${String(label).replace(/\s+/g,"-").toLowerCase()}.jpg`;
  a.click();
  toast(`Download: ${label}`,"info");
}

function downloadVideoAsset(url,label="video"){
  const clean=normalizeMediaUrl(url);
  if(!clean){toast("Vídeo ainda não disponível.","warn");return;}
  const a=document.createElement("a");
  a.href=clean;
  a.download=`stlai-${String(label).replace(/\s+/g,"-").toLowerCase()}.mp4`;
  a.target="_blank";
  a.rel="noopener";
  document.body.appendChild(a);
  a.click();
  a.remove();
}

function openVideoAsset(url){
  const clean=normalizeMediaUrl(url);
  if(!clean){toast("Vídeo ainda não disponível.","warn");return;}
  openLightbox(clean,"video");
}

function copyMediaLink(url){
  const clean=normalizeMediaUrl(url);
  if(!clean){toast("Link ainda não disponível.","warn");return;}
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(clean).then(()=>toast("Link copiado.","success")).catch(()=>window.open(clean,"_blank","noopener"));
    return;
  }
  window.open(clean,"_blank","noopener");
}

function downloadFinalVideo(){
  downloadVideoAsset(S.video.finalVideoUrl,"video-final");
}

function openFinalVideo(){
  openVideoAsset(S.video.finalVideoUrl);
}

function copyFinalVideoLink(){
  copyMediaLink(S.video.finalVideoUrl);
}

function dlCombo(){
  if(!S.comboUrl){toast("Combo ainda não gerado.","warn");return;}
  const a=document.createElement("a");
  a.href=S.comboUrl;
  a.download="stlai-combo-2x2.jpg";
  a.click();
  toast("Download: Combo 2×2","info");
}

function dlAll(){
  if(!S.imgs4.length){toast("Ainda não há imagens prontas.","warn");return;}
  S.imgs4.forEach((img,idx)=>setTimeout(()=>dlImg(img.url,img.label), idx*180));
}

function popVid(){
  const row=document.getElementById("srow");
  const voiceLabel=document.getElementById("video-voice-label");
  if(voiceLabel) voiceLabel.textContent=voiceStyleLabel();
  S.video.language=normalizeVideoLanguage(S.video.language || "pt-BR");
  if(!S.video.script || (!S.video.scriptEdited && (S.video.scriptVoiceStyle!==S.voiceStyle || S.video.scriptLanguage!==S.video.language))){
    S.video.script=buildVideoScript();
    S.video.scriptTts=buildTtsScript(S.video.script);
    S.video.scriptEdited=false;
    S.video.scriptVoiceStyle=S.voiceStyle;
    S.video.scriptLanguage=S.video.language;
  }
  renderVideoLanguage();
  renderVideoScript();
  renderVideoStatus();
  renderVideoFormat();
  if(!row){
    unlock(6);
    return;
  }
  row.innerHTML="";
  const sel=S.imgs4.filter(i=>S.selVid.includes(i.key));
  if(!sel.length){row.innerHTML='<div style="font-size:12px;color:var(--tx3)">Selecione imagens na etapa anterior.</div>';return;}
  sel.forEach(img=>{
    row.innerHTML+=`<div class="si-sm video-shot"><img src="${img.url}" alt="${esc(img.label)}"><span>${esc(img.label)}</span></div>`;
  });
  unlock(6);
}

function voiceStyleLabel(){
  const labels={
    persuasiva:"Persuasiva",
    emocional:"Emocional",
    demonstrativa:"Demonstrativa",
    premium:"Premium"
  };
  return labels[normalizeVoiceStyle(S.voiceStyle)] || "Emocional";
}

function normalizeVoiceStyle(value){
  const key=String(value || "").toLowerCase().trim();
  const map={
    persuasive:"persuasiva",
    persuasiva:"persuasiva",
    emotional:"emocional",
    emocional:"emocional",
    demo:"demonstrativa",
    demonstrative:"demonstrativa",
    demonstrativa:"demonstrativa",
    premium:"premium"
  };
  return map[key] || "emocional";
}

function normalizeVideoLanguage(value){
  const lang=String(value || "pt-BR");
  return ["pt-BR","en-US","es-ES","fr-FR"].includes(lang) ? lang : "pt-BR";
}

function videoLanguageLabel(lang=S.video.language){
  return {"pt-BR":"Português","en-US":"English","es-ES":"Español","fr-FR":"Français"}[normalizeVideoLanguage(lang)] || "Português";
}

function cleanScriptPiece(value, fallback){
  const txt=String(value || "").replace(/\s+/g," ").trim();
  return txt || fallback;
}

function stripNarrationDirections(text){
  return String(text || "")
    .replace(/\[[^\]\r\n]{1,80}\]\s*/g, "")
    .replace(/\b(thoughtful|warmly|short pause|gentle pause|delighted|excited|softly|amazed|chuckles|sighs|confident|impressed)\b\s*/gi, "")
    .replace(/\s+([,.!?;:])/g, "$1")
    .replace(/\s+/g, " ")
    .trim();
}

function normalizeScriptTerms(text, lang=S.video.language){
  let out=stripNarrationDirections(text);
  const selected=normalizeVideoLanguage(lang);
  if(selected==="pt-BR"){
    out=out
      .replace(/\bwedding topper\b/gi, "topo de bolo de casamento")
      .replace(/\bcake topper\b/gi, "topo de bolo")
      .replace(/\bpersonalized topper\b/gi, "topo de bolo personalizado")
      .replace(/\bcustom topper\b/gi, "topo personalizado")
      .replace(/\btopper personalizado\b/gi, "topo de bolo personalizado")
      .replace(/\btopper\b/gi, "topo de bolo");
  }else if(selected==="es-ES"){
    out=out
      .replace(/\bwedding topper\b/gi, "decoración para pastel de boda")
      .replace(/\bcake topper\b/gi, "decoración para pastel")
      .replace(/\bpersonalized topper\b/gi, "decoración personalizada para pastel")
      .replace(/\bcustom topper\b/gi, "decoración personalizada para pastel")
      .replace(/\btopper\b/gi, "decoración para pastel");
  }else if(selected==="fr-FR"){
    out=out
      .replace(/\bwedding topper\b/gi, "décoration de gâteau de mariage")
      .replace(/\bcake topper\b/gi, "décoration de gâteau")
      .replace(/\bpersonalized topper\b/gi, "décoration de gâteau personnalisée")
      .replace(/\bcustom topper\b/gi, "décoration de gâteau personnalisée")
      .replace(/\btopper\b/gi, "décoration de gâteau");
  }
  out=out
    .replace(/\btopo de bolo de bolo personalizado\b/gi, "topo de bolo personalizado")
    .replace(/\btopo de bolo de bolo\b/gi, "topo de bolo")
    .replace(/\btopo de bolo de topo de bolo\b/gi, "topo de bolo")
    .replace(/\bproduto de produto\b/gi, "produto");
  out=normalizeNarrationNumbers(out, selected);
  return out.replace(/\s+/g, " ").trim();
}

function numberToWordsPt(value){
  const n=Number(String(value).replace(",","."));
  const words={
    0:"zero",1:"um",2:"dois",3:"três",4:"quatro",5:"cinco",6:"seis",7:"sete",8:"oito",9:"nove",10:"dez",
    11:"onze",12:"doze",13:"treze",14:"quatorze",15:"quinze",16:"dezesseis",17:"dezessete",18:"dezoito",19:"dezenove",
    20:"vinte",30:"trinta",40:"quarenta",50:"cinquenta",60:"sessenta",70:"setenta",80:"oitenta",90:"noventa",
    100:"cem",200:"duzentos",300:"trezentos",400:"quatrocentos",500:"quinhentos",600:"seiscentos",700:"setecentos",800:"oitocentos",900:"novecentos"
  };
  if(!Number.isFinite(n) || n%1!==0) return String(value).replace(".", ",");
  if(words[n]) return words[n];
  if(n<100) return `${words[Math.floor(n/10)*10]} e ${words[n%10]}`;
  if(n<1000){
    const hundred=Math.floor(n/100)*100;
    const rest=n%100;
    return `${hundred===100 ? "cento" : words[hundred]} e ${numberToWordsPt(rest)}`;
  }
  return String(value);
}

function normalizeNarrationNumbers(text, lang=S.video.language){
  if(normalizeVideoLanguage(lang)!=="pt-BR") return String(text || "");
  return String(text || "")
    .replace(/\b(\d+)\s*x\s*(\d+)\s*x\s*(\d+)\s*cm\b/gi, (_,a,b,c)=>`${numberToWordsPt(a)} por ${numberToWordsPt(b)} por ${numberToWordsPt(c)} centímetros`)
    .replace(/\b(\d+)\s*x\s*(\d+)\s*cm\b/gi, (_,a,b)=>`${numberToWordsPt(a)} por ${numberToWordsPt(b)} centímetros`)
    .replace(/\b(\d+)\s*cm\b/gi, (_,n)=>`${numberToWordsPt(n)} centímetros`)
    .replace(/\b(\d+)\s*W\b/g, (_,n)=>`${numberToWordsPt(n)} watts`)
    .replace(/\b(\d+)\s*(?:V|volts?)\b/gi, (_,n)=>`${numberToWordsPt(n)} volts`)
    .replace(/\b(\d+)\s+velocidades\b/gi, (_,n)=>`${numberToWordsPt(n)} velocidades`)
    .replace(/\b(\d+)\s+pás\b/gi, (_,n)=>`${numberToWordsPt(n)} pás`)
    .replace(/\b3D\b/g, "três D");
}

function buildVideoScript(){
  const lang=normalizeVideoLanguage(S.video.language);
  S.voiceStyle=normalizeVoiceStyle(S.voiceStyle);
  const name=normalizeProductNameForNarration(cleanScriptPiece(S.name, lang==="pt-BR" ? "este produto" : "this product"), lang);
  const shortName=shortProductReference(name, lang);
  const desc=productDescriptionForNarration(S.descTxt || S.desc, lang);
  const feat=productFeaturesForNarration(S.feat, lang);
  let script="";
  if(lang==="en-US"){
    script=buildLocalizedVideoScriptEn(name, shortName, desc, feat);
  }else if(lang==="es-ES"){
    script=buildLocalizedVideoScriptEs(name, shortName, desc, feat);
  }else if(lang==="fr-FR"){
    script=buildLocalizedVideoScriptFr(name, shortName, desc, feat);
  }else{
    script=buildLocalizedVideoScriptPt(name, shortName, desc, feat);
  }
  return normalizeScriptTerms(script, lang);
}

function productDescriptionForNarration(value, lang){
  const fallback={
    "pt-BR":"uma peça pensada para valorizar o momento com presença, cuidado e bom acabamento",
    "en-US":"a product designed to add presence, care, and a polished finish to the moment",
    "es-ES":"una pieza pensada para dar presencia, cuidado y buen acabado al momento",
    "fr-FR":"une pièce pensée pour apporter de la présence, du soin et une belle finition au moment"
  }[lang] || "uma peça pensada para valorizar o momento";
  return normalizeScriptTerms(cleanScriptPiece(value, fallback), lang)
    .replace(/\bSEO\b.*$/gi, "")
    .replace(/\bpalavras[- ]chave\b.*$/gi, "")
    .replace(/(?:^|\s)[•*-]\s*/g, " ")
    .replace(/\b\d+(?:[,.]\d+)?\s*x\s*\d+(?:[,.]\d+)?(?:\s*x\s*\d+(?:[,.]\d+)?)?\s*cm\b/gi, "")
    .replace(/\s+/g, " ")
    .trim() || fallback;
}

function productFeaturesForNarration(value, lang){
  const fallback={
    "pt-BR":"acabamento cuidadoso, visual bem definido e presença na composição",
    "en-US":"careful finishing, a clear look, and strong presentation",
    "es-ES":"acabado cuidado, visual definido y buena presencia",
    "fr-FR":"une finition soignée, un visuel net et une belle présence"
  }[lang] || "acabamento cuidadoso e visual marcante";
  const text=normalizeScriptTerms(cleanScriptPiece(value, fallback), lang).replace(/(?:^|\s)[•*-]\s*/g, " ");
  const parts=text.split(/[,.;]\s*/).filter(Boolean).slice(0,3);
  return parts.length ? parts.join(", ") : fallback;
}

function normalizeProductNameForNarration(productName, lang=S.video.language){
  const selected=normalizeVideoLanguage(lang);
  let name=normalizeScriptTerms(productName, selected)
    .replace(/\btopo de bolo de bolo personalizado\b/gi, "topo de bolo personalizado")
    .replace(/\btopo de bolo de bolo\b/gi, "topo de bolo")
    .replace(/\bpersonalizado personalizado\b/gi, "personalizado")
    .replace(/\s+/g, " ")
    .trim();
  if(selected==="pt-BR"){
    name=name.replace(/^o\s+/i,"").replace(/^a\s+/i,"");
    if(!/[A-Z]{2,}|[0-9]/.test(name.slice(1))) name=name.charAt(0).toLowerCase()+name.slice(1);
    if(!/^(esse|essa|este|esta)\b/i.test(name)) name=`esse ${name}`;
  }
  return name || (selected==="pt-BR" ? "este produto" : "this product");
}

function shortProductReference(name, lang=S.video.language){
  const selected=normalizeVideoLanguage(lang);
  if(selected==="pt-BR"){
    const clean=String(name || "").replace(/^(esse|essa|este|esta)\s+/i,"").trim();
    if(/topo de bolo/i.test(clean)) return "o topo";
    if(/peça|decor/i.test(clean)) return "a peça";
    return "esse detalhe";
  }
  if(selected==="es-ES") return "la pieza";
  if(selected==="fr-FR") return "la pièce";
  return "the piece";
}

function buildLocalizedVideoScriptPt(name, shortName, desc, feat){
  const style=normalizeVoiceStyle(S.voiceStyle);
  if(style==="persuasiva") return `Seu produto merece uma apresentação que chame atenção sem parecer exagerada. ${name} valoriza a cena, destaca o cuidado nos detalhes e ajuda a transformar interesse em desejo de compra. ${desc}. Com ${feat}, ${shortName} entrega presença e utilidade de um jeito simples, bonito e fácil de entender. É uma escolha que deixa o anúncio mais forte e a decisão mais natural.`;
  if(style==="demonstrativa") return `${name} foi pensado para quem quer entender o produto de forma clara antes de escolher. Ele reúne ${feat} em uma apresentação objetiva, sem perder o cuidado visual. ${desc}. Na prática, ${shortName} ajuda a mostrar uso, acabamento e diferencial sem depender de uma ficha técnica longa.`;
  if(style==="premium") return `Alguns detalhes elevam a percepção de valor logo no primeiro olhar. ${name} traz uma presença elegante, com ${feat}, criando uma apresentação mais refinada e memorável. ${desc}. É uma peça que comunica cuidado, acabamento e intenção, com linguagem sofisticada e sem exagero.`;
  return `Às vezes, o que torna um momento mais especial está nos pequenos detalhes. ${name} foi criado para trazer presença, cuidado e significado sem perder naturalidade. ${desc}. O acabamento, o visual e ${feat} fazem ${shortName} parecer uma escolha pessoal, daquelas que aparecem bem, emocionam e continuam fazendo sentido depois.`;
}

function buildLocalizedVideoScriptEn(name, shortName, desc, feat){
  if(S.voiceStyle==="demonstrativa") return `${name} is made for people who want to understand the product clearly before choosing it. It brings ${feat} in a simple and useful presentation. ${desc}. In practice, ${shortName} helps show use, finish, and value without sounding like a technical sheet.`;
  if(S.voiceStyle==="premium") return `Some details raise perceived value from the very first look. ${name} brings an elegant presence, with ${feat}, creating a refined and memorable presentation. ${desc}. It feels intentional, polished, and made to stand out with subtlety.`;
  if(S.voiceStyle==="persuasiva") return `Your product deserves a presentation that catches attention without feeling forced. ${name} highlights the details, makes the offer easier to understand, and helps turn interest into desire. ${desc}. With ${feat}, ${shortName} becomes a simple, attractive, and confident choice.`;
  return `Sometimes, the smallest details make a moment feel more personal. ${name} brings presence, care, and meaning in a natural way. ${desc}. The finish, the look, and ${feat} make ${shortName} feel thoughtful, memorable, and easy to connect with.`;
}

function buildLocalizedVideoScriptEs(name, shortName, desc, feat){
  if(S.voiceStyle==="demonstrativa") return `${name} fue pensado para explicar el producto de forma clara antes de elegirlo. Reúne ${feat} en una presentación simple y útil. ${desc}. En la práctica, ${shortName} ayuda a mostrar uso, acabado y diferencial sin parecer una ficha técnica.`;
  if(S.voiceStyle==="premium") return `Hay detalles que elevan el valor percibido desde el primer vistazo. ${name} aporta una presencia elegante, con ${feat}, creando una presentación refinada y memorable. ${desc}. Es una pieza que comunica cuidado, acabado e intención.`;
  if(S.voiceStyle==="persuasiva") return `Tu producto merece una presentación que llame la atención sin parecer forzada. ${name} destaca los detalles, hace la oferta más fácil de entender y ayuda a convertir interés en deseo. ${desc}. Con ${feat}, ${shortName} se vuelve una elección atractiva y segura.`;
  return `A veces, lo que hace especial un momento está en los pequeños detalles. ${name} aporta presencia, cuidado y significado de forma natural. ${desc}. El acabado, el visual y ${feat} hacen que ${shortName} se sienta personal, memorable y fácil de querer.`;
}

function buildLocalizedVideoScriptFr(name, shortName, desc, feat){
  if(S.voiceStyle==="demonstrativa") return `${name} a été pensé pour expliquer le produit clairement avant de le choisir. Il réunit ${feat} dans une présentation simple et utile. ${desc}. En pratique, ${shortName} montre l'usage, la finition et la valeur sans ressembler à une fiche technique.`;
  if(S.voiceStyle==="premium") return `Certains détails élèvent la perception de valeur dès le premier regard. ${name} apporte une présence élégante, avec ${feat}, pour une présentation raffinée et mémorable. ${desc}. C'est une pièce qui communique le soin, la finition et l'intention.`;
  if(S.voiceStyle==="persuasiva") return `Votre produit mérite une présentation qui attire l'attention sans paraître forcée. ${name} met les détails en valeur, rend l'offre plus claire et transforme l'intérêt en envie. ${desc}. Avec ${feat}, ${shortName} devient un choix attractif et évident.`;
  return `Parfois, ce sont les petits détails qui rendent un moment plus personnel. ${name} apporte de la présence, du soin et du sens naturellement. ${desc}. La finition, le visuel et ${feat} donnent à ${shortName} une impression attentionnée, mémorable et facile à aimer.`;
}

function buildTtsScript(publicScript){
  const clean=normalizeScriptTerms(publicScript, S.video.language);
  if(!clean) return "";
  const sentences=clean.split(/(?<=[.!?])\s+/).filter(Boolean);
  if(!sentences.length) return clean;
  const style=normalizeVoiceStyle(S.voiceStyle);
  const directions={
    persuasiva:["[confident]","[excited]","[warmly]"],
    emocional:["[thoughtful]","[warmly]","[softly]"],
    demonstrativa:["[confident]","[warmly]","[warmly]"],
    premium:["[softly]","[warmly]","[softly]"]
  }[style] || ["[thoughtful]","[warmly]","[softly]"];
  const parts=sentences.map((sentence, index)=>{
    if(index===0) return `${directions[0]} ${sentence}`;
    if(index===1) return `[short pause] ${directions[1]} ${sentence}`;
    if(index===sentences.length-1) return `${directions[2]} ${sentence}`;
    return sentence;
  });
  return parts.join("\n").replace(/\n{3,}/g, "\n\n").trim();
}

function renderVideoScript(){
  const scriptEl=document.getElementById("video-script-text");
  if(scriptEl && scriptEl.value!==S.video.script) scriptEl.value=S.video.script;
}

function updateVideoScript(value){
  S.video.script=normalizeScriptTerms(value, S.video.language);
  S.video.scriptTts=buildTtsScript(S.video.script);
  S.video.scriptEdited=true;
  renderVideoStatus();
}

function regenerateVideoScript(){
  S.video.script=buildVideoScript();
  S.video.scriptTts=buildTtsScript(S.video.script);
  S.video.scriptEdited=false;
  S.video.scriptVoiceStyle=S.voiceStyle;
  S.video.scriptLanguage=S.video.language;
  renderVideoScript();
  renderVideoLanguage();
  renderVideoStatus();
  toast("Roteiro regenerado localmente.","success");
}

function setVideoLanguage(value){
  const previous=S.video.language;
  S.video.language=normalizeVideoLanguage(value);
  if(!S.video.scriptEdited || !S.video.script){
    S.video.script=buildVideoScript();
    S.video.scriptTts=buildTtsScript(S.video.script);
    S.video.scriptEdited=false;
    S.video.scriptVoiceStyle=S.voiceStyle;
    S.video.scriptLanguage=S.video.language;
    renderVideoScript();
  }else if(previous!==S.video.language){
    S.video.scriptTts=buildTtsScript(S.video.script);
    toast("Idioma atualizado. Use Regenerar roteiro para recriar a copy nesse idioma.","info");
  }
  renderVideoLanguage();
  renderVideoStatus();
}

function renderVideoLanguage(){
  S.video.language=normalizeVideoLanguage(S.video.language);
  const select=document.getElementById("video-language-select");
  const hint=document.getElementById("video-language-hint");
  if(select && select.value!==S.video.language) select.value=S.video.language;
  if(hint) hint.textContent=`O roteiro será preparado em ${videoLanguageLabel(S.video.language)}.`;
}

function setVideoFormat(format){
  S.video.format=normalizeVideoFormat(format);
  renderVideoFormat();
  renderVideoStatus();
}

function renderVideoFormat(){
  S.video.format=normalizeVideoFormat(S.video.format);
  document.querySelectorAll(".video-format-card").forEach(card=>{
    card.classList.toggle("active", card.dataset.format===S.video.format);
  });
}

function normalizeVideoFormat(format){
	  return format==="16:9" ? "16:9" : "9:16";
	}

function configuredImageQuality(){
  const quality=String(S.cfg.imageQuality || S.cfg.imgQuality || "auto").toLowerCase();
  return ["auto","high","medium","low"].includes(quality) ? quality : "auto";
}

function isVideoFormatVertical(){
  return normalizeVideoFormat(S.video.format)==="9:16";
}

function videoFormatClass(){
  return isVideoFormatVertical() ? "is-format-vertical" : "is-format-horizontal";
}

function videoFramesTitle(){
  const format=normalizeVideoFormat(S.video.format);
  return format ? `Imagens no formato ${format}` : "Imagens no formato";
}

function recoverableVideoErrorStatus(status){
  return ["clips_partial_error","clip_generation_error","composition_error","composition_pending","ready_for_composition"].includes(String(status || ""));
}

function hasVideoActivity(){
  return Boolean(S.video.jobId || S.video.renderJobId || S.video.finalVideoUrl || S.video.audioUrl || (Array.isArray(S.video.clips) && S.video.clips.length));
}

function isVideoBusyStatus(status){
  return status==="submitting"
    || status==="queued"
    || status==="generating_audio"
    || status==="generating_narration"
    || status==="generating_clips"
    || /^generating_clip_[1-4]$/.test(status)
    || /^retrying_clip_[1-4]$/.test(status)
    || status==="composing"
    || status==="composing_final"
    || status==="composing_final_video"
    || status==="composition_queued"
    || status==="composition_processing"
    || status==="composition_waiting";
}

function isVideoCompositionState(status=videoStatusForDisplay(), compositionStatus=S.video.compositionStatus){
  const value=String(status || "");
  const comp=String(compositionStatus || "");
  return value==="composition_queued"
    || value==="composition_processing"
    || value==="composition_waiting"
    || value==="composition_pending"
    || value==="composing_final"
    || value==="composing_final_video"
    || value==="composing"
    || comp==="queued"
    || comp==="processing"
    || comp==="waiting";
}

function formatElapsedTime(seconds){
  const total=Math.max(0, Math.floor(Number(seconds || 0)));
  const minutes=Math.floor(total / 60);
  const secs=total % 60;
  return `${String(minutes).padStart(2,"0")}:${String(secs).padStart(2,"0")}`;
}

function parseCompositionDateMs(value){
  if(!value) return 0;
  const raw=String(value).trim();
  if(!raw) return 0;
  const candidates=[raw, raw.replace(" ", "T")];
  for(const candidate of candidates){
    const parsed=Date.parse(candidate);
    if(Number.isFinite(parsed)) return parsed;
  }
  return 0;
}

function isCompositionTimerTerminalState(){
  const status=videoStatusForDisplay();
  const compositionStatus=String(S.video.compositionStatus || "");
  return Boolean(
    S.video.finalVideoUrl
    || status==="ready"
    || compositionStatus==="complete"
    || compositionStatus==="ready"
    || S.video.status==="composition_error"
    || compositionStatus==="error"
    || compositionStatus==="hard_timeout"
  );
}

function ensureCompositionTimerStartedAt(){
  if(!isVideoCompositionState() || isCompositionTimerTerminalState()) return 0;
  const backendElapsed=Math.max(0, Number(S.video.composerElapsedSeconds || 0));
  const backendStarted=parseCompositionDateMs(S.video.composerStartedAt);
  const currentJobId=String(S.video.jobId || S.video.renderJobId || "");

  if(S.video.compositionTimerJobId && currentJobId && S.video.compositionTimerJobId!==currentJobId){
    stopCompositionTimerLoop();
  }

  if(!S.video.compositionTimerStartedAtMs){
    S.video.compositionTimerStartedAtMs=backendStarted || (Date.now() - (backendElapsed * 1000));
    S.video.localCompositionStartedAt=S.video.compositionTimerStartedAtMs;
    S.video.compositionTimerJobId=currentJobId;
  }

  return S.video.compositionTimerStartedAtMs;
}

function compositionElapsedUiSeconds(){
  if(isCompositionTimerTerminalState()) return 0;
  const backendElapsed=Number(S.video.composerElapsedSeconds || 0);
  const startedAt=ensureCompositionTimerStartedAt();
  if(startedAt) return Math.max(backendElapsed, Math.floor((Date.now() - startedAt) / 1000));
  return backendElapsed;
}

function compositionTimerSubtitle(seconds=compositionElapsedUiSeconds()){
  if(seconds >= 300) return "Ainda estamos processando. Você pode revisar as imagens e textos enquanto isso.";
  if(seconds >= 180) return "Finalizando o processamento. Isso pode levar alguns minutos.";
  return "Montando clipes, narração e transições.";
}

function renderCompositionTimer(){
  const elapsed=compositionElapsedUiSeconds();
  return `<div class="stlai-composition-timer" data-stlai-composition-timer-wrap>
    <span class="stlai-composition-timer__label">Tempo decorrido</span>
    <strong class="stlai-composition-timer__value" data-stlai-composition-timer>${esc(formatElapsedTime(elapsed))}</strong>
    <span class="stlai-composition-timer__hint" data-stlai-composition-timer-hint>${esc(compositionTimerSubtitle(elapsed))}</span>
  </div>`;
}

function renderVideoStatusCompositionTimer(show){
  const wrap=document.getElementById("video-status-composition-timer");
  if(!wrap) return;
  if(!show){
    wrap.style.display="none";
    wrap.innerHTML="";
    return;
  }
  wrap.style.display="block";
  wrap.innerHTML=renderCompositionTimer();
  ensureCompositionTimerStartedAt();
  updateCompositionTimerDom();
  startCompositionTimerLoop();
}

function startCompositionTimerLoop(){
  if(S.video.compositionTimer || !isVideoCompositionState() || isCompositionTimerTerminalState()) return;
  ensureCompositionTimerStartedAt();
  updateCompositionTimerDom();
  S.video.compositionTimer=setInterval(()=>{
    if(!isVideoCompositionState() || isCompositionTimerTerminalState()){
      stopCompositionTimerLoop(false);
      return;
    }
    updateCompositionTimerDom();
  }, 1000);
  S.videoCompositionTimer.intervalId=S.video.compositionTimer;
  S.videoCompositionTimer.jobId=String(S.video.jobId || S.video.renderJobId || "");
  S.videoCompositionTimer.startedAtMs=Number(S.video.compositionTimerStartedAtMs || 0);
  S.videoCompositionTimer.active=true;
}

function updateCompositionTimerDom(){
  if(!isVideoCompositionState() || isCompositionTimerTerminalState()) return;
  const elapsed=compositionElapsedUiSeconds();
  const formatted=formatElapsedTime(elapsed);
  const note=compositionTimerSubtitle(elapsed);
  document.querySelectorAll("[data-stlai-composition-timer]").forEach(el=>{
    el.textContent=formatted;
  });
  document.querySelectorAll("[data-stlai-composition-timer-hint], [data-stlai-composition-timer-note]").forEach(el=>{
    el.textContent=note;
  });
  S.videoCompositionTimer.jobId=String(S.video.jobId || S.video.renderJobId || "");
  S.videoCompositionTimer.startedAtMs=Number(S.video.compositionTimerStartedAtMs || 0);
  S.videoCompositionTimer.intervalId=S.video.compositionTimer;
  S.videoCompositionTimer.active=Boolean(S.video.compositionTimer);
  S.video.diagnostics={
    ...(S.video.diagnostics || {}),
    composition_timer_started_at_ms:Number(S.video.compositionTimerStartedAtMs || 0),
    composition_timer_elapsed_seconds:elapsed,
    composition_timer_interval_active:Boolean(S.video.compositionTimer)
  };
}

function stopCompositionTimerLoop(clearLocal=true){
  if(S.video.compositionTimer){
    clearInterval(S.video.compositionTimer);
    S.video.compositionTimer=null;
  }
  S.videoCompositionTimer.intervalId=null;
  S.videoCompositionTimer.active=false;
  if(clearLocal){
    S.video.localCompositionStartedAt=0;
    S.video.compositionTimerStartedAtMs=0;
    S.video.compositionTimerJobId="";
    S.videoCompositionTimer.jobId="";
    S.videoCompositionTimer.startedAtMs=0;
  }
}

function normalizeClipJobs(jobs, clips=S.video.clips){
  const byIndex={};
  if(Array.isArray(jobs)){
    jobs.forEach(job=>{
      const index=Number(job?.index || 0);
      if(index>=1 && index<=4){
        byIndex[index]={
          index,
	          status:String(job.status || "pending"),
	          attempt:Number(job.attempt || 0),
          max_attempts:Number(job.max_attempts || job.maxAttempts || 3),
	          url:normalizeMediaUrl(job.url),
          error:String(job.error || ""),
          retryable:Boolean(job.retryable),
          will_retry:Boolean(job.will_retry || job.willRetry),
          retry_reason:String(job.retry_reason || job.retryReason || ""),
          scheduled_start_at:String(job.scheduled_start_at || job.scheduledStartAt || ""),
          started_at:String(job.started_at || ""),
          finished_at:String(job.finished_at || ""),
          operation_id:String(job.operation_id || ""),
          operation_id_exists:Boolean(job.operation_id_exists || job.operation_id),
          operation_poll_count:Number(job.operation_poll_count || 0),
          operation_elapsed_seconds:Number(job.operation_elapsed_seconds || 0),
          clip_operation_soft_timeout_seconds:Number(job.clip_operation_soft_timeout_seconds || 0),
          clip_operation_hard_timeout_seconds:Number(job.clip_operation_hard_timeout_seconds || 0),
          operation_still_processing:Boolean(job.operation_still_processing),
          attempt_counts_operations_not_polls:job.attempt_counts_operations_not_polls !== false,
          requested_resolution:String(job.requested_resolution || ""),
          effective_resolution:String(job.effective_resolution || ""),
          video_model:String(job.video_model || "")
        };
      }
    });
  }
  (Array.isArray(clips) ? clips : []).forEach((clip,idx)=>{
    const index=Number(clip?.index || idx + 1);
    if(index>=1 && index<=4 && clip?.url){
	      byIndex[index]={...(byIndex[index] || {index}), index, status:"ready", attempt:Math.max(1, Number(byIndex[index]?.attempt || 1)), url:normalizeMediaUrl(clip.url), error:""};
    }
  });
  for(let index=1; index<=4; index++){
    if(!byIndex[index]) byIndex[index]={index,status:"pending",attempt:0,url:"",error:""};
  }
  return Object.keys(byIndex).sort((a,b)=>Number(a)-Number(b)).map(key=>byIndex[key]);
}

function hasActiveVideoClipOperations(jobs, clips=S.video.clips){
  return normalizeClipJobs(jobs || [], clips).some(job=>{
    if(job.url || job.status==="ready") return false;
    return Boolean(job.operation_id_exists || job.operation_id || job.operation_still_processing)
      && (Boolean(job.operation_still_processing) || ["pending","queued","scheduled","generating","retrying"].includes(String(job.status || "")));
  });
}

function clipStatusRank(status){
  const rank={pending:1,queued:2,scheduled:3,generating:4,retrying:5,error:6,error_final:7,ready:8};
  return rank[String(status || "pending")] || 1;
}

function mergeVideoClips(existing=[], incoming=[]){
  const byIndex={};
  const push=clip=>{
    if(!clip || typeof clip!=="object") return;
    const normalized=normalizeVideoClipUrlData(clip);
    const index=Number(normalized.index || 0);
    if(index<1 || index>4 || !normalized.url) return;
    if(byIndex[index] && byIndex[index].url){
      byIndex[index]={...normalized, ...byIndex[index], index, url:byIndex[index].url};
      return;
    }
    byIndex[index]={...(byIndex[index] || {}), ...normalized, index, url:normalized.url};
  };
  (Array.isArray(existing) ? existing : []).forEach(push);
  (Array.isArray(incoming) ? incoming : []).forEach(push);
  return Object.keys(byIndex).sort((a,b)=>Number(a)-Number(b)).map(key=>byIndex[key]);
}

function mergeVideoFrames(existing=[], incoming=[]){
  const byIndex={};
  const push=frame=>{
    if(!frame || typeof frame!=="object") return;
    const normalized=normalizeVideoFrameUrlData(frame);
    const index=Number(normalized.index || 0);
    if(index<1 || index>4 || !normalized.url) return;
    byIndex[index]={...(byIndex[index] || {}), ...normalized, index, url:normalized.url};
  };
  (Array.isArray(existing) ? existing : []).forEach(push);
  (Array.isArray(incoming) ? incoming : []).forEach(push);
  return Object.keys(byIndex).sort((a,b)=>Number(a)-Number(b)).map(key=>byIndex[key]);
}

function strongerClipJob(existing={}, incoming={}){
  const left={...existing};
  const right={...incoming};
  if(left.url) left.status="ready";
  if(right.url) right.status="ready";
  const leftActiveOperation=Boolean(left.operation_id_exists || left.operation_id || left.operation_still_processing)
    && !left.url
    && (Boolean(left.operation_still_processing) || ["pending","queued","scheduled","generating","retrying"].includes(String(left.status || "")));
  const rightActiveOperation=Boolean(right.operation_id_exists || right.operation_id || right.operation_still_processing)
    && !right.url
    && (Boolean(right.operation_still_processing) || ["pending","queued","scheduled","generating","retrying"].includes(String(right.status || "")));
  if(left.status==="error_final" && rightActiveOperation){
    left.status="generating";
    left.error="";
    right.status="generating";
    right.error="";
  }
  if(right.status==="error_final" && leftActiveOperation){
    left.status="generating";
    left.error="";
    right.status="generating";
    right.error="";
  }
  const winner=clipStatusRank(right.status)>=clipStatusRank(left.status) ? right : left;
  const merged={...left, ...winner};
  if(left.status==="ready" && left.url){
    merged.status="ready";
    merged.url=left.url;
    merged.error="";
  }
  if(right.status==="ready" && right.url){
    merged.status="ready";
    merged.url=right.url;
    merged.error="";
  }
  merged.index=Number(merged.index || left.index || right.index || 0);
  merged.url=normalizeMediaUrl(merged.url);
  return merged;
}

function mergeVideoClipJobs(existingJobs=[], incomingJobs=[], clips=[]){
  const byIndex={};
  normalizeClipJobs(existingJobs, S.video.clips).forEach(job=>{
    byIndex[job.index]=job;
  });
  normalizeClipJobs(incomingJobs, clips).forEach(job=>{
    byIndex[job.index]=strongerClipJob(byIndex[job.index] || {index:job.index,status:"pending",attempt:0,url:"",error:""}, job);
  });
  normalizeClipJobs(Object.values(byIndex), clips).forEach(job=>{
    byIndex[job.index]=strongerClipJob(byIndex[job.index] || {}, job);
  });
  return Object.keys(byIndex).sort((a,b)=>Number(a)-Number(b)).map(key=>byIndex[key]);
}

function clientReadyVideoClips(){
  const framesByIndex={};
  (Array.isArray(S.video.videoFrames) ? S.video.videoFrames : []).forEach(frame=>{
    const index=Number(frame?.index || 0);
    if(index>=1 && index<=4 && frame?.url) framesByIndex[index]=normalizeVideoFrameUrlData(frame);
  });

  const clipsByIndex={};
  mergeVideoClips(S.video.clips, []).forEach(clip=>{
    const index=Number(clip.index || 0);
    if(index>=1 && index<=4 && clip.url) clipsByIndex[index]=clip;
  });

  normalizeClipJobs(S.video.clipJobs, S.video.clips).forEach(job=>{
    const index=Number(job.index || 0);
    if(index>=1 && index<=4 && job.url && !clipsByIndex[index]){
      clipsByIndex[index]={index, url:job.url, label:`Clipe ${index}`, duration:8, muted:true};
    }
  });

  return Object.keys(clipsByIndex)
    .sort((a,b)=>Number(a)-Number(b))
    .map(key=>{
      const clip=normalizeVideoClipUrlData(clipsByIndex[key]);
      const index=Number(clip.index || key);
      const frame=framesByIndex[index] || {};
      return {
        index,
        url:clip.url,
        role:clip.role || "",
        label:clip.label || `Clipe ${index}`,
        duration:Number(clip.duration || 8),
        muted:true,
        prepared_frame_url:clip.prepared_frame_url || frame.url || "",
        prepared_frame_width:Number(clip.prepared_frame_width || frame.width || 0),
        prepared_frame_height:Number(clip.prepared_frame_height || frame.height || 0),
        aspect_ratio:clip.aspect_ratio || frame.aspect_ratio || S.video.format || ""
      };
    })
    .filter(clip=>clip.index>=1 && clip.index<=4 && clip.url);
}

function applyVideoClipJobData(data={}){
  const incomingClips=mergeVideoClips(Array.isArray(data.clips) ? data.clips : [], Array.isArray(data.partial_clips) ? data.partial_clips : []);
  const mergedClips=mergeVideoClips(S.video.clips, incomingClips);
  S.video.clipJobs=mergeVideoClipJobs(S.video.clipJobs, Array.isArray(data.clip_jobs) ? data.clip_jobs : [], mergedClips);
	  S.video.clipStatuses=data.clip_statuses && typeof data.clip_statuses==="object" ? data.clip_statuses : S.video.clipStatuses;
  S.video.clipAttempts=data.clip_attempts && typeof data.clip_attempts==="object" ? data.clip_attempts : S.video.clipAttempts;
  S.video.clipErrors=data.clip_errors && typeof data.clip_errors==="object" ? data.clip_errors : S.video.clipErrors;
  const readyIndexes=S.video.clipJobs.filter(job=>job.status==="ready" || job.url).map(job=>Number(job.index));
  const incomingMissing=Array.isArray(data.missing_clips)
    ? data.missing_clips.map(Number).filter(index=>index>=1 && index<=4 && !readyIndexes.includes(index))
    : [];
  S.video.missingClips=incomingMissing.length ? incomingMissing : S.video.clipJobs.filter(job=>job.status!=="ready" && !job.url).map(job=>job.index);
	}

function normalizeVideoJobPayload(data={}){
  const next={...data};
  next.audio_url=normalizeMediaUrl(next.audio_url);
  next.final_video_url=normalizeMediaUrl(next.final_video_url);
  next.thumbnail_url=normalizeMediaUrl(next.thumbnail_url);
  next.test_clip_url=normalizeMediaUrl(next.test_clip_url);
  if(Array.isArray(next.clips)) next.clips=next.clips.map(normalizeVideoClipUrlData);
  if(Array.isArray(next.partial_clips)) next.partial_clips=next.partial_clips.map(normalizeVideoClipUrlData);
  if(Array.isArray(next.video_frames)) next.video_frames=next.video_frames.map(normalizeVideoFrameUrlData);
  if(Array.isArray(next.clip_jobs)){
    next.clip_jobs=next.clip_jobs.map(job=>({...job,url:normalizeMediaUrl(job.url)}));
  }
  if(Array.isArray(next.ugc_jobs)){
    next.ugc_jobs=next.ugc_jobs.map(normalizeUgcJob);
  }
  if(next.ugc_job && typeof next.ugc_job==="object"){
    next.ugc_job=normalizeUgcJob(next.ugc_job);
  }
  return next;
}

function videoStateRank(snapshot={}){
  const clips=Array.isArray(snapshot.clips) ? snapshot.clips.filter(clip=>clip && clip.url).length : 0;
  const status=String(snapshot.status || "");
  const compositionStatus=String(snapshot.compositionStatus || snapshot.composition_status || "");
  const composerStatus=String(snapshot.composerStatus || snapshot.composer_status || "");
  if(snapshot.finalVideoUrl || snapshot.final_video_url || status==="ready" || compositionStatus==="complete" || compositionStatus==="ready" || composerStatus==="ready") return 100;
  if(status==="composition_waiting" || compositionStatus==="waiting" || composerStatus==="waiting") return 92;
  if(status==="composition_processing" || compositionStatus==="processing" || composerStatus==="processing") return 90;
  if(status==="composition_queued" || status==="composing_final_video" || status==="composition_pending" || compositionStatus==="queued" || composerStatus==="queued") return 80;
  if(clips>=4 || status==="clips_ready" || status==="ready_for_composition") return 70;
  if(clips>0) return 30 + clips;
  if(snapshot.audioUrl || snapshot.audio_url) return 20;
  if(isVideoBusyStatus(status)) return 10;
  return 0;
}

function deriveMergedVideoStatus(previous, incoming, merged){
  const incomingStatus=String(incoming.status || "");
  const previousStatus=String(previous.status || "");
  const clipsReady=Array.isArray(merged.clips) ? merged.clips.filter(clip=>clip && clip.url).length : 0;
  const compositionStatus=String(merged.compositionStatus || "");
  const composerStatus=String(merged.composerStatus || "");
  const activeClipOperations=hasActiveVideoClipOperations(merged.clipJobs, merged.clips)
    || Number(incoming.active_generating_count || incoming.diagnostics?.active_generating_count || 0) > 0
    || (Array.isArray(incoming.polling_operation_indexes) && incoming.polling_operation_indexes.length > 0)
    || (Array.isArray(incoming.diagnostics?.polling_operation_indexes) && incoming.diagnostics.polling_operation_indexes.length > 0);

  if(merged.finalVideoUrl || incomingStatus==="ready" || previousStatus==="ready" || compositionStatus==="complete" || compositionStatus==="ready" || composerStatus==="ready") return "ready";
  if(activeClipOperations && ["clip_generation_error","clips_partial_error","error"].includes(incomingStatus)) return "generating_clips";
  if(activeClipOperations && ["clip_generation_error","clips_partial_error","error"].includes(previousStatus)) return "generating_clips";
  if((compositionStatus==="timeout" || composerStatus==="timeout") && (incoming.render_job_id || previous.renderJobId || previous.render_job_id) && !incoming.hard_timeout_reached) return "composition_waiting";
  if(compositionStatus==="waiting" || composerStatus==="waiting" || incomingStatus==="composition_waiting" || previousStatus==="composition_waiting") return "composition_waiting";
  if(compositionStatus==="error" || compositionStatus==="timeout" || composerStatus==="error" || composerStatus==="timeout") return "composition_error";
  if(incomingStatus==="composition_error" || previousStatus==="composition_error") return "composition_error";
  if(incomingStatus==="clip_generation_error" || incomingStatus==="clips_partial_error") return incomingStatus;
  if(previousStatus==="clip_generation_error" || previousStatus==="clips_partial_error"){
    const active=normalizeClipJobs(merged.clipJobs, merged.clips).some(job=>["pending","queued","generating","retrying"].includes(job.status));
    if(!active) return previousStatus;
  }
  if(compositionStatus==="processing" || composerStatus==="processing" || incomingStatus==="composition_processing" || previousStatus==="composition_processing" || previousStatus==="composing_final_video") return "composition_processing";
  if(compositionStatus==="queued" || composerStatus==="queued" || incomingStatus==="composition_queued" || incomingStatus==="composition_pending" || previousStatus==="composition_queued" || previousStatus==="composition_pending") return "composition_queued";
  if(clipsReady>=4) return "clips_ready";
  if(clipsReady>0) return "generating_clips";
  if(merged.audioUrl && (incomingStatus==="generating_narration" || previousStatus==="generating_narration" || isVideoBusyStatus(incomingStatus) || isVideoBusyStatus(previousStatus))) return "generating_clips";
  return incomingStatus || previousStatus || "idle";
}

function applyVideoState(payload={}, options={}){
  const incoming=normalizeVideoJobPayload(payload || {});
  const previous={
    status:S.video.status,
    progress:S.video.progress,
    progressHint:S.video.progressHint,
    jobVersion:S.video.jobVersion,
    updatedAt:S.video.updatedAt,
    renderJobId:S.video.renderJobId,
    audioUrl:S.video.audioUrl,
    finalVideoUrl:S.video.finalVideoUrl,
    clips:Array.isArray(S.video.clips) ? S.video.clips : [],
    clipJobs:Array.isArray(S.video.clipJobs) ? S.video.clipJobs : [],
    videoFrames:Array.isArray(S.video.videoFrames) ? S.video.videoFrames : [],
    compositionStatus:S.video.compositionStatus,
    composerStatus:S.video.composerStatus
  };
  const incomingVersion=Number(incoming.job_version || incoming.revision || 0);
  const previousVersion=Number(previous.jobVersion || 0);
  const stale=incomingVersion > 0 && previousVersion > 0 && incomingVersion < previousVersion;
  const incomingClips=mergeVideoClips(Array.isArray(incoming.clips) ? incoming.clips : [], Array.isArray(incoming.partial_clips) ? incoming.partial_clips : []);
  const mergedClips=mergeVideoClips(previous.clips, incomingClips);
  const mergedFrames=mergeVideoFrames(previous.videoFrames, Array.isArray(incoming.video_frames) ? incoming.video_frames : []);
  const mergedClipJobs=mergeVideoClipJobs(previous.clipJobs, Array.isArray(incoming.clip_jobs) ? incoming.clip_jobs : [], mergedClips);
  const merged={
    clips:mergedClips,
    clipJobs:mergedClipJobs,
    audioUrl:incoming.audio_url || previous.audioUrl || "",
    finalVideoUrl:incoming.final_video_url || previous.finalVideoUrl || "",
    compositionStatus:incoming.composition_status || previous.compositionStatus || "pending",
    composerStatus:incoming.composer_status || previous.composerStatus || ""
  };
  const previousRank=videoStateRank({...previous, clips:previous.clips});
  const incomingRank=videoStateRank({...incoming, clips:incomingClips});
  const mergedStatus=deriveMergedVideoStatus(previous, incoming, merged);
  const canUseIncomingStatus=!stale || incomingRank>=previousRank || videoPhaseRank(incoming.status)>=videoPhaseRank(previous.status);
  const activeClipOperations=hasActiveVideoClipOperations(mergedClipJobs, mergedClips)
    || Number(incoming.active_generating_count || incoming.diagnostics?.active_generating_count || 0) > 0
    || (Array.isArray(incoming.polling_operation_indexes) && incoming.polling_operation_indexes.length > 0)
    || (Array.isArray(incoming.diagnostics?.polling_operation_indexes) && incoming.diagnostics.polling_operation_indexes.length > 0)
    || Boolean(incoming.diagnostics?.active_operations_suppress_error);

  const previousJobId=String(S.video.jobId || "");
  const incomingJobId=String(incoming.job_id || "");
  if(previousJobId && incomingJobId && previousJobId!==incomingJobId){
    stopCompositionTimerLoop();
  }
  S.video.jobId=incoming.job_id || S.video.jobId || "";
  S.video.language=normalizeVideoLanguage((!stale && (incoming.video_language || incoming.narration_language)) || S.video.language || "pt-BR");
  S.video.videoProvider=(!stale && incoming.video_provider) || incoming.diagnostics?.video_provider || S.video.videoProvider || "gemini_veo";
  S.video.videoModel=(!stale && incoming.video_model) || incoming.diagnostics?.video_model || S.video.videoModel || "veo-3.1-lite-generate-preview";
  S.video.outputResolution=(!stale && incoming.output_resolution) || incoming.diagnostics?.output_resolution || S.video.outputResolution || "720p";
  S.video.requestedResolution=(!stale && incoming.requested_resolution) || incoming.diagnostics?.requested_resolution || S.video.requestedResolution || S.video.outputResolution;
  S.video.effectiveResolution=(!stale && incoming.effective_resolution) || incoming.diagnostics?.effective_resolution || S.video.effectiveResolution || S.video.outputResolution;
  S.video.resolutionFallbackReason=(!stale && incoming.resolution_fallback_reason) || incoming.diagnostics?.resolution_fallback_reason || S.video.resolutionFallbackReason || "";
  if(!stale && incoming.script_public){
    S.video.script=normalizeScriptTerms(incoming.script_public, S.video.language);
    S.video.scriptTts=S.video.scriptTts || buildTtsScript(S.video.script);
  }
  S.video.status=canUseIncomingStatus ? mergedStatus : deriveMergedVideoStatus(previous, {}, merged);
  S.video.progress=Math.max(Number(S.video.progress || 0), Number(incoming.progress || 0));
  S.video.progressHint=Math.max(Number(S.video.progressHint || 0), Number(incoming.progress_hint || incoming.progress || 0), S.video.progress);
  S.video.message=(!stale && incoming.message) ? incoming.message : (S.video.message || incoming.message || "");
  S.video.audioUrl=merged.audioUrl;
  S.video.finalVideoUrl=merged.finalVideoUrl;
  S.video.finalVideoDuration=Number(incoming.final_video_duration || S.video.finalVideoDuration || 0);
  S.video.thumbnailUrl=incoming.thumbnail_url || S.video.thumbnailUrl || "";
  S.video.clips=mergedClips;
  S.video.clipJobs=mergedClipJobs;
  S.video.videoFrames=mergedFrames.length ? mergedFrames : videoFramesFromClips(mergedClips);
  if(Array.isArray(incoming.ugc_jobs) && incoming.ugc_jobs.length){
    S.ugcJobs=mergeUgcJobs(S.ugcJobs, incoming.ugc_jobs);
  }
  S.video.currentClipIndex=Number((!stale && incoming.current_clip_index) || S.video.currentClipIndex || 0);
  S.video.currentClipAttempt=Number((!stale && incoming.current_clip_attempt) || S.video.currentClipAttempt || 0);
  S.video.clipRetryCount=Number((!stale && incoming.clip_retry_count) || S.video.clipRetryCount || 0);
  S.video.lastClipError=(!stale && incoming.last_clip_error) || S.video.lastClipError || "";
  S.video.failedClipIndex=activeClipOperations ? 0 : Number((!stale && (incoming.failed_clip_index || incoming.failed_clip)) || S.video.failedClipIndex || 0);
  S.video.failedClipRole=activeClipOperations ? "" : ((!stale && incoming.failed_clip_role) || S.video.failedClipRole || "");
  S.video.errorCode=activeClipOperations ? "" : ((!stale && (incoming.error_code || incoming.code)) || S.video.errorCode || "");
  S.video.compositionStatus=merged.compositionStatus;
  S.video.composerStatus=merged.composerStatus;
  S.video.renderJobId=incoming.render_job_id || S.video.renderJobId || "";
  S.video.composerStartedAt=(!stale && incoming.composer_started_at) || incoming.diagnostics?.composer_started_at || S.video.composerStartedAt || "";
  S.video.composerEndpointConfigured=Boolean(incoming.composer_endpoint_configured ?? S.video.composerEndpointConfigured);
  S.video.composerEndpointHost=(!stale && incoming.composer_endpoint_host) || S.video.composerEndpointHost || "";
  S.video.composerEndpointPath=(!stale && incoming.composer_endpoint_path) || S.video.composerEndpointPath || "";
  S.video.composerElapsedSeconds=Number((!stale && incoming.composer_elapsed_seconds) || S.video.composerElapsedSeconds || 0);
  S.video.composerPollCount=Number((!stale && incoming.composer_poll_count) || S.video.composerPollCount || 0);
  S.video.softTimeoutSeconds=Number((!stale && incoming.soft_timeout_seconds) || incoming.diagnostics?.soft_timeout_seconds || S.video.softTimeoutSeconds || 300);
  S.video.softTimeoutReached=Boolean((!stale && incoming.soft_timeout_reached) || incoming.diagnostics?.soft_timeout_reached || false);
  S.video.hardTimeoutSeconds=Number((!stale && incoming.hard_timeout_seconds) || incoming.diagnostics?.hard_timeout_seconds || S.video.hardTimeoutSeconds || 1200);
  S.video.hardTimeoutReached=Boolean((!stale && incoming.hard_timeout_reached) || incoming.diagnostics?.hard_timeout_reached || false);
  S.video.nextPollSeconds=Number((!stale && incoming.next_poll_seconds) || incoming.diagnostics?.next_poll_seconds || S.video.nextPollSeconds || 3);
  S.video.externalRenderStatus=(!stale && incoming.external_render_status) || incoming.diagnostics?.external_render_status || S.video.externalRenderStatus || "";
  S.video.externalRenderCheckedAt=(!stale && incoming.external_render_checked_at) || incoming.diagnostics?.external_render_checked_at || S.video.externalRenderCheckedAt || "";
  S.video.compositionStartBlocker=(!stale && incoming.composition_start_blocker) || S.video.compositionStartBlocker || "";
  S.video.nextClipAction=(!stale && incoming.next_clip_action) || incoming.diagnostics?.next_clip_action || S.video.nextClipAction || "";
  S.video.nextClipIndex=Number((!stale && incoming.next_clip_index) || incoming.diagnostics?.next_clip_index || S.video.nextClipIndex || 0);
  S.video.nextClipIndexes=Array.isArray(incoming.next_clip_indexes) ? incoming.next_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (Array.isArray(incoming.diagnostics?.next_clip_indexes) ? incoming.diagnostics.next_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (S.video.nextClipIndexes || []));
  S.video.nextClipReason=(!stale && incoming.next_clip_reason) || incoming.diagnostics?.next_clip_reason || S.video.nextClipReason || "";
  S.video.startedClipIndexes=Array.isArray(incoming.started_clip_indexes) ? incoming.started_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (Array.isArray(incoming.started_clip_indexes_this_tick) ? incoming.started_clip_indexes_this_tick.map(Number).filter(index=>index>=1 && index<=4) : (Array.isArray(incoming.diagnostics?.started_clip_indexes_this_tick) ? incoming.diagnostics.started_clip_indexes_this_tick.map(Number).filter(index=>index>=1 && index<=4) : (Array.isArray(incoming.diagnostics?.started_clip_indexes) ? incoming.diagnostics.started_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (S.video.startedClipIndexes || []))));
  S.video.scheduledClipIndexes=Array.isArray(incoming.scheduled_clip_indexes) ? incoming.scheduled_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (Array.isArray(incoming.diagnostics?.scheduled_clip_indexes) ? incoming.diagnostics.scheduled_clip_indexes.map(Number).filter(index=>index>=1 && index<=4) : (S.video.scheduledClipIndexes || []));
  S.video.autoClipGenerationTriggered=Boolean((!stale && incoming.auto_clip_generation_triggered) || incoming.diagnostics?.auto_clip_generation_triggered || false);
  S.video.autoClipGenerationResult=(!stale && incoming.auto_clip_generation_result) || incoming.diagnostics?.auto_clip_generation_result || S.video.autoClipGenerationResult || "";
  S.video.skippedReason=(!stale && incoming.skipped_reason) || incoming.diagnostics?.skipped_reason || S.video.skippedReason || "";
  S.video.maxClipAttempts=Number((!stale && incoming.max_clip_attempts) || incoming.diagnostics?.max_clip_attempts || S.video.maxClipAttempts || 3);
  S.video.maxConcurrentClipGenerations=Math.max(4, Number((!stale && incoming.max_concurrent_clip_generations) || incoming.diagnostics?.max_concurrent_clip_generations || S.video.maxConcurrentClipGenerations || 4));
  S.video.clipStartStaggerSeconds=Number((!stale && incoming.clip_start_stagger_seconds) || incoming.diagnostics?.clip_start_stagger_seconds || S.video.clipStartStaggerSeconds || 1);
  S.video.retryable=Boolean((!stale && incoming.retryable) || incoming.diagnostics?.retryable || false);
  S.video.retryReason=activeClipOperations ? (incoming.diagnostics?.retry_reason || incoming.retry_reason || "operation_still_processing") : ((!stale && incoming.retry_reason) || incoming.diagnostics?.retry_reason || S.video.retryReason || "");
  S.video.willRetry=Boolean((!stale && incoming.will_retry) || incoming.diagnostics?.will_retry || false);
  S.video.errorFinalReason=activeClipOperations ? "" : ((!stale && incoming.error_final_reason) || incoming.diagnostics?.error_final_reason || S.video.errorFinalReason || "");
  S.video.diagnostics=(!stale && incoming.diagnostics && typeof incoming.diagnostics==="object") ? incoming.diagnostics : (S.video.diagnostics || {});
  if(isVideoCompositionState(merged.status, S.video.compositionStatus)){
    ensureCompositionTimerStartedAt();
  }
  if(!S.video.errorCode && incoming.last_composer_error_code) S.video.errorCode=incoming.last_composer_error_code;
  S.video.jobVersion=Math.max(previousVersion, incomingVersion);
  S.video.updatedAt=(!stale && incoming.updated_at) ? incoming.updated_at : (S.video.updatedAt || incoming.updated_at || "");
  applyVideoClipJobData({...incoming, clips:mergedClips, partial_clips:mergedClips, clip_jobs:mergedClipJobs});
  const readySignal=Boolean(
    S.video.finalVideoUrl
    || incoming.final_video_url_exists
    || incoming.status==="ready"
    || incoming.composition_status==="complete"
    || incoming.composition_status==="ready"
    || incoming.composer_status==="ready"
  );
  if(readySignal){
    S.video.status="ready";
    S.video.progress=100;
    S.video.progressHint=100;
    S.video.visualProgress=100;
    S.video.compositionStatus="complete";
    S.video.composerStatus="ready";
    S.video.message="Vídeo final pronto.";
    clearVideoPolling();
    clearVideoClipLaunchers();
    stopVideoProgressLoop();
    stopCompositionTimerLoop();
    S.video.finalVideoScoreBonusApplied=true;
    if(document.getElementById("sum-score")) renderSummaryScore();
    if(!S.video.finalVideoUrl){
      console.warn("STLAI final video ready signal without renderable URL", {
        status:S.video.status,
        composition_status:S.video.compositionStatus,
        composer_status:S.video.composerStatus,
        final_video_url_exists:Boolean(incoming.final_video_url_exists)
      });
    }
  }else if(isVideoCompositionState()){
    startCompositionTimerLoop();
  }else if(S.video.status==="composition_error" || S.video.compositionStatus==="error" || S.video.compositionStatus==="hard_timeout" || S.video.status==="clip_generation_error" || S.video.status==="error"){
    stopCompositionTimerLoop(false);
  }
  if(options.debug && S.cfg && S.cfg.debugVideo){
    console.debug("stlai video merge", {
      incoming_clips:incomingClips.length,
      previous_clips:previous.clips.length,
      merged_clips:mergedClips.length,
      ignored_stale_update:stale && !canUseIncomingStatus,
      job_version:S.video.jobVersion
    });
  }
  if(S.step>=6) renderUgcSection();
}

function warnVideoCompositionDiagnostic(context="poll"){
  const status=String(S.video.status || "");
  const compositionStatus=String(S.video.compositionStatus || "");
  const shouldWarn=status==="composition_error"
    || compositionStatus==="error"
    || compositionStatus==="timeout"
    || (status==="composition_queued" && Number(S.video.composerElapsedSeconds || 0) >= 30 && !S.video.renderJobId);
  if(!shouldWarn) return;
  console.warn("STLAI video composition diagnostic", {
    context,
    composition_status:compositionStatus,
    render_job_id_exists:Boolean(S.video.renderJobId),
    clips_ready_count:readyVideoClipCount(),
    has_audio:Boolean(S.video.audioUrl),
    last_composer_error_code:S.video.diagnostics?.last_composer_error_code || S.video.errorCode || "",
    last_composer_error_message:S.video.diagnostics?.last_composer_error_message || S.video.message || "",
    composer_elapsed_seconds:Number(S.video.composerElapsedSeconds || 0),
    soft_timeout_reached:Boolean(S.video.softTimeoutReached),
    hard_timeout_reached:Boolean(S.video.hardTimeoutReached),
    next_poll_seconds:Number(S.video.nextPollSeconds || 0),
    external_render_status:S.video.externalRenderStatus || "",
    composition_start_blocker:S.video.compositionStartBlocker || S.video.diagnostics?.composition_start_blocker || ""
  });
}

function readyVideoClipCount(){
  const jobs=normalizeClipJobs(S.video.clipJobs, S.video.clips);
  const readyJobs=jobs.filter(job=>job.status==="ready" || job.url).length;
  if(readyJobs) return readyJobs;
  const clips=Array.isArray(S.video.clips) ? S.video.clips : [];
  return clips.filter(clip=>clip && clip.url).length;
}

function currentClipJob(){
  const jobs=normalizeClipJobs(S.video.clipJobs, S.video.clips);
  if(S.video.status==="clip_generation_error" || S.video.status==="clips_partial_error"){
    const failed=jobs.find(job=>job.status==="error");
    if(failed) return failed;
  }
	  return jobs.find(job=>job.status==="generating" || job.status==="retrying")
	    || jobs.find(job=>job.status==="queued" || job.status==="pending")
    || jobs.find(job=>job.status==="error_final" || job.status==="error")
    || null;
}

function clipReadyProgress(){
  const ready=readyVideoClipCount();
  const map={0:25,1:35,2:50,3:65,4:78};
  return map[Math.max(0,Math.min(4,ready))] || 25;
}

function videoPhaseRank(status){
  const value=String(status || "");
  const genMatch=value.match(/^generating_clip_([1-4])$/);
  const retryMatch=value.match(/^retrying_clip_([1-4])$/);
  if(value==="ready") return 100;
  if(value==="composition_waiting") return 92;
  if(value==="composition_processing") return 90;
  if(value==="composition_queued" || value==="composing" || value==="composing_final_video" || value==="composition_pending") return 80;
  if(value==="clips_ready" || value==="ready_for_composition") return 70;
  if(genMatch || retryMatch) return 20 + Number((genMatch || retryMatch)[1]);
  if(value==="generating_clips") return 21;
  if(value==="submitting" || value==="queued" || value==="generating_audio" || value==="generating_narration") return 10;
  return 0;
}

function deriveVideoStatusFromJob(status){
  const base=String(status || "idle");
  const clipsReady=readyVideoClipCount();
  const activeJob=currentClipJob();
  const currentClip=Number(S.video.currentClipIndex || 0);
  const currentAttempt=Number(S.video.currentClipAttempt || 0);
  const compositionStatus=String(S.video.compositionStatus || "");
  const composerStatus=String(S.video.composerStatus || "");

  if(S.video.finalVideoUrl || base==="ready" || compositionStatus==="complete" || compositionStatus==="ready" || composerStatus==="ready") return "ready";
  if(base==="composition_error" || base==="clip_generation_error" || base==="clips_partial_error" || base==="error") return base;
  if(/^retrying_clip_[1-4]$/.test(base) || /^generating_clip_[1-4]$/.test(base)) return base;
  if(base==="composition_waiting" || composerStatus==="waiting" || compositionStatus==="waiting") return "composition_waiting";
  if(base==="composition_processing" || composerStatus==="processing" || compositionStatus==="processing") return "composition_processing";
  if(base==="composition_queued" || base==="composing" || base==="composing_final_video" || composerStatus==="queued" || compositionStatus==="queued") return "composition_queued";
  if(base==="composition_pending") return "composition_queued";

  if(clipsReady>=4){
    return "composition_queued";
  }

  if(activeJob && activeJob.status==="retrying"){
    return `retrying_clip_${activeJob.index}`;
  }

  if(activeJob && activeJob.status==="generating"){
    return `generating_clip_${activeJob.index}`;
  }

  if(currentClip>=1 && currentClip<=4){
    return currentAttempt>1 || base.startsWith("retrying_clip_")
      ? `retrying_clip_${currentClip}`
      : `generating_clip_${currentClip}`;
  }

  if(activeJob && activeJob.status==="pending" && S.video.audioUrl && isVideoBusyStatus(base)){
    return `generating_clip_${activeJob.index}`;
  }

  if(clipsReady>0 && clipsReady<4 && isVideoBusyStatus(base)){
    return `generating_clip_${Math.min(4, clipsReady + 1)}`;
  }

  if(base==="generating_clips"){
    return `generating_clip_${Math.min(4, Math.max(1, clipsReady + 1))}`;
  }

  if((base==="submitting" || base==="queued" || base==="generating_audio" || base==="generating_narration") && S.video.audioUrl && clipsReady<4){
    return `generating_clip_${Math.min(4, Math.max(1, clipsReady + 1))}`;
  }

  return base;
}

function videoStatusForDisplay(){
  const derived=deriveVideoStatusFromJob(S.video.status || "idle");
  const visual=S.video.visualStatus || "";
  if(S.video.status==="submitting" && visual && isVideoBusyStatus(visual) && videoPhaseRank(visual)>videoPhaseRank(derived)){
    return visual;
  }
  return derived || visual || S.video.status || "idle";
}

function videoPhaseRange(status){
  const retryMatch=String(status || "").match(/^retrying_clip_([1-4])$/);
  const genMatch=String(status || "").match(/^generating_clip_([1-4])$/);
  const clipNumber=retryMatch ? Number(retryMatch[1]) : (genMatch ? Number(genMatch[1]) : 0);
  const clipRanges={1:[25,37],2:[38,51],3:[52,65],4:[66,78]};
  if(status==="submitting" || status==="queued" || status==="generating_audio" || status==="generating_narration") return [8,24];
  if(clipNumber) return clipRanges[clipNumber] || [25,78];
  if(status==="generating_clips") return [25,78];
  if(status==="clips_ready" || status==="ready_for_composition") return [79,79];
  if(status==="composition_pending" || status==="composition_queued" || status==="composing" || status==="composing_final_video") return [80,84];
  if(status==="composition_processing") return [85,96];
  if(status==="composition_waiting") return [92,98];
  if(status==="ready") return [100,100];
  return [0,0];
}

function estimatedSubmittingStatus(elapsedMs){
  if(elapsedMs < 14000) return "generating_narration";
  if(elapsedMs < 52000) return "generating_clip_1";
  if(elapsedMs < 90000) return "generating_clip_2";
  if(elapsedMs < 128000) return "generating_clip_3";
  return "generating_clip_4";
}

function updateVideoVisualProgress(){
  const realStatus=S.video.status || "idle";
  let displayStatus=deriveVideoStatusFromJob(realStatus);

  if(!isVideoBusyStatus(realStatus) && !isVideoBusyStatus(displayStatus)){
    if(realStatus==="ready") S.video.visualProgress=100;
    S.video.visualStatus="";
    return;
  }

  const now=Date.now();
  if(realStatus==="submitting" && displayStatus==="submitting"){
    const started=S.video.visualPhaseStartedAt || now;
    displayStatus=estimatedSubmittingStatus(now - started);
  }

  const phaseKey=displayStatus;
  if(S.video.visualPhaseKey!==phaseKey){
    S.video.visualPhaseKey=phaseKey;
    S.video.visualPhaseStartedAt=now;
  }

  const range=videoPhaseRange(displayStatus);
  const elapsed=Math.max(0, now - (S.video.visualPhaseStartedAt || now));
  const span=Math.max(0, range[1] - range[0]);
  const growth=Math.min(span, elapsed / 2600);
  const realProgress=Math.max(Number(S.video.progress || 0), Number(S.video.progressHint || 0));
  const clipProgress=/^(?:generating|retrying)_clip_[1-4]$/.test(displayStatus) || displayStatus==="generating_clips"
    ? clipReadyProgress()
    : 0;
  const next=Math.min(range[1], Math.max(range[0], realProgress, clipProgress, S.video.visualProgress || 0, range[0] + growth));
  S.video.visualStatus=displayStatus;
  S.video.visualProgress=next;
}

function startVideoProgressLoop(){
  stopVideoProgressLoop(false);
  S.video.visualStatus="";
  S.video.visualProgress=Math.max(0, Number(S.video.progress || 0), Number(S.video.progressHint || 0));
  S.video.visualPhaseKey="";
  S.video.visualPhaseStartedAt=Date.now();
  updateVideoVisualProgress();
  S.video.progressTimer=setInterval(()=>{
    updateVideoVisualProgress();
    renderVideoStatus();
    if(S.step>=6) renderSummaryVideo();
  }, 650);
}

function stopVideoProgressLoop(clearVisual=true){
  if(S.video.progressTimer){
    clearInterval(S.video.progressTimer);
    S.video.progressTimer=null;
  }
  if(clearVisual){
    S.video.visualStatus="";
    S.video.visualPhaseKey="";
    S.video.visualPhaseStartedAt=0;
  }
}

function renderVideoResultCta(){
  const btn=document.getElementById("btn-video-result");
  const hint=document.getElementById("video-result-hint");
  if(!btn) return;
  const busy=isVideoBusyStatus(S.video.status);
  const visualBusy=isVideoBusyStatus(videoStatusForDisplay());
  const hasFinal=Boolean(S.video.finalVideoUrl);
  const recoverable=recoverableVideoErrorStatus(S.video.status) || (S.video.status==="error" && Boolean(S.video.jobId));
  const recoverableError=S.video.status==="composition_error" || S.video.status==="clip_generation_error" || S.video.status==="clips_partial_error" || (S.video.status==="error" && Boolean(S.video.jobId));
  const active=busy || visualBusy || hasFinal || recoverable || hasVideoActivity();
  btn.classList.toggle("is-video-live", active);
  if(hasFinal){
    btn.textContent="Ver resultado final";
  }else if(recoverableError){
    btn.textContent="Ir para resultado";
  }else if(active || busy || visualBusy){
    btn.textContent="Acompanhar resultado";
  }else{
    btn.textContent="Ir para resultado";
  }
  if(hint){
    hint.style.display=(busy || visualBusy) ? "block" : "none";
    hint.textContent="Seu vídeo está sendo produzido. Você pode acompanhar o processamento no Resultado Final enquanto continua revisando suas criações.";
  }
}

async function mockGenerateVideo(){
  const ajaxurl=S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  if(!ajaxurl){
    toast("Não foi possível localizar o endpoint AJAX do WordPress.","error");
    return;
  }

  const selected=selectedVideoImagesForPayload();
  if(selected.length!==4){
    toast("Selecione exatamente 4 imagens para gerar o vídeo.","warn");
    return;
  }

  if(!String(S.video.script || "").trim()){
    toast("Revise o roteiro da narração antes de gerar o vídeo.","warn");
    return;
  }
  S.video.language=normalizeVideoLanguage(S.video.language);
  S.video.script=normalizeScriptTerms(S.video.script, S.video.language);
  S.video.scriptTts=buildTtsScript(S.video.script);
  renderVideoScript();

	  clearVideoPolling();
	  clearVideoClipLaunchers();
  const retryingPartial=(S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error") && Boolean(S.video.jobId);
  const retryingComposition=(S.video.status==="ready_for_composition" || S.video.status==="composition_pending" || S.video.status==="composition_queued" || S.video.status==="composition_processing" || S.video.status==="composition_error") && Boolean(S.video.jobId) && S.video.clips.length>=4 && Boolean(S.video.audioUrl) && !S.video.finalVideoUrl;
  const retryingRecoverable=S.video.status==="error" && Boolean(S.video.jobId) && (S.video.clips.length || S.video.audioUrl);
  const retryingReusable=retryingPartial || retryingComposition || retryingRecoverable;
  const existingJobId=S.video.jobId || "";
  const existingAudioUrl=S.video.audioUrl || "";
  const existingClips=Array.isArray(S.video.clips) ? S.video.clips : [];
  S.video.status="submitting";
  S.video.mockReady=false;
  S.video.progress=0;
  S.video.progressHint=0;
  S.video.jobVersion=retryingReusable ? S.video.jobVersion : 0;
  S.video.updatedAt=retryingReusable ? S.video.updatedAt : "";
  S.video.jobId=retryingReusable ? existingJobId : "";
  S.video.audioUrl=retryingReusable ? existingAudioUrl : "";
  S.video.finalVideoUrl="";
  S.video.finalVideoDuration=0;
  S.video.thumbnailUrl="";
  S.video.clips=retryingReusable ? existingClips : [];
  S.video.clipJobs=retryingReusable ? normalizeClipJobs(S.video.clipJobs, existingClips) : [];
  S.video.clipStatuses={};
  S.video.clipAttempts={};
  S.video.clipErrors={};
  S.video.missingClips=[];
  S.video.videoFrames=retryingReusable ? S.video.videoFrames : [];
	  S.video.currentClipIndex=0;
  S.video.currentClipAttempt=0;
  S.video.clipRetryCount=0;
  S.video.lastClipError="";
  S.video.failedClipIndex=0;
  S.video.failedClipRole="";
  S.video.errorCode="";
  S.video.compositionStatus="pending";
  S.video.composerStatus="";
  S.video.composerStartedAt="";
  S.video.localCompositionStartedAt=0;
  S.video.compositionTimerStartedAtMs=0;
  S.video.compositionTimerJobId="";
  S.video.renderJobId="";
  S.video.clipLaunchStarted=false;
  S.video.clipLaunchTimersByIndex={};
  S.video.clipRequestsInFlight={};
  stopCompositionTimerLoop();
  S.video.message=retryingComposition
    ? "Tentando compor o vídeo final novamente..."
    : (retryingPartial ? "Tentando novamente a partir do clipe pendente..." : "Gerando narração e preparando pipeline...");
  startVideoProgressLoop();
  renderVideoStatus();

  try{
    const data=await videoAjaxRequest("stlai_create_video_job", {
      job_id: retryingReusable ? existingJobId : "",
      selected_images: JSON.stringify(selected),
      narration_type: S.voiceStyle,
      narration_style: S.voiceStyle,
      video_language: S.video.language,
      narration_language: S.video.language,
      format: S.video.format,
      script: S.video.script,
      script_public: S.video.script,
      script_tts: S.video.scriptTts,
      product_name: S.name,
      product_description: S.descTxt || S.desc
    });

    applyVideoState({...data, status:data.status || "queued", progress:data.progress || 10, progress_hint:data.progress_hint || data.progress || 10, message:data.message || "Job de vídeo criado."}, {debug:true});
    if(data.video_language || data.narration_language) S.video.language=normalizeVideoLanguage(data.video_language || data.narration_language);
    if(data.script_public) S.video.script=normalizeScriptTerms(String(data.script_public || S.video.script), S.video.language);
    if(data.script_tts_exists || data.script_tts) S.video.scriptTts=S.video.scriptTts || buildTtsScript(S.video.script);
    renderVideoLanguage();
    renderVideoScript();
    renderVideoStatus();
    unlock(6);
	    renderSummaryVideo();
	    if(S.video.audioUrl && !S.video.finalVideoUrl && readyVideoClipCount()<4 && !recoverableVideoErrorStatus(S.video.status)){
	      scheduleVideoClipStarts();
	    }
	    toast(S.video.finalVideoUrl ? "Vídeo final preparado com sucesso." : (S.video.renderJobId ? "Composição final iniciada." : (S.video.clips.length===4 ? "4 clipes gerados com sucesso." : (S.video.audioUrl ? "Narração gerada com sucesso." : "Job de vídeo criado com sucesso."))),"success");
	    const activeClipJobs=hasActiveClipJobs();
	    const terminalClipError=S.video.status==="clip_generation_error" && !activeClipJobs;
	    if(S.video.status==="ready" || S.video.status==="composition_pending" || S.video.status==="composition_error" || terminalClipError){
	      S.video.mockReady=true;
	      if(S.video.status==="ready" || S.video.status==="composition_pending" || S.video.status==="composition_error" || terminalClipError) stopVideoProgressLoop();
      renderSummaryVideo();
      return;
    }
	    pollVideoStatus();
  }catch(err){
    const data=err.data || {};
    console.warn("Video generation error", data || err);
    applyVideoState({...data, status:data.status || (data.failed_clip_index || data.failed_clip ? "clip_generation_error" : "error"), message:data.message || err.message}, {debug:true});
    S.video.message=isComposerPendingCode(S.video.errorCode) || S.video.status==="composition_pending"
      ? "Narração e clipes preparados. A composição final está pendente."
      : (S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error"
      ? partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length)
      : (S.video.status==="composition_error"
      ? "Não foi possível concluir o vídeo final. Os clipes foram preservados. Você pode tentar novamente."
      : (err.message || "Falha ao criar job de vídeo.")));
    stopVideoProgressLoop();
    renderVideoStatus();
    if(S.video.jobId && (S.video.clips.length || S.video.status==="composition_error" || S.video.status==="composition_pending")){
      unlock(6);
      renderSummaryVideo();
    }
    toast(S.video.message,"error");
  }
}

function renderVideoStatus(){
  const box=document.getElementById("video-status-box");
  const title=document.getElementById("video-status-title");
  const copy=document.getElementById("video-status-copy");
  if(!box || !title || !copy) return;
  const voice=voiceStyleLabel();
  const displayStatus=videoStatusForDisplay();
  const displayBusy=isVideoBusyStatus(displayStatus);
  box.classList.toggle("ready", S.video.status==="ready" || S.video.status==="prepared" || S.video.status==="ready_for_composition" || S.video.status==="clips_ready" || S.video.status==="composition_pending" || S.video.status==="composition_queued" || S.video.status==="composition_processing" || S.video.status==="composition_waiting" || S.video.status==="composition_error");
  renderVideoActionButton();
  renderVideoResultCta();
  renderVideoTestClip();
  renderVideoClips();
  renderVideoFrames();
  renderFinalVideo();
  renderVideoMotion();
  renderVideoStatusCompositionTimer(false);
  if(S.video.testClipStatus==="generating"){
    title.textContent="Gerando clipe de teste...";
    copy.textContent="A primeira imagem selecionada está sendo enviada para uma geração isolada de prévia.";
    renderVideoAudio();
    return;
  }
  if(S.video.testClipStatus==="ready" && S.video.testClipUrl && S.video.status==="idle"){
    title.textContent="Clipe IA de teste gerado.";
    copy.textContent="A prévia isolada foi salva com segurança. O pipeline principal ainda não foi alterado.";
    renderVideoAudio();
    return;
  }
  if(S.video.testClipStatus==="error" && S.video.status==="idle"){
    title.textContent="Não foi possível gerar o clipe de teste.";
    copy.textContent=S.video.testClipMessage || "Confira os detalhes seguros no console e na aba Network.";
    renderVideoAudio();
    return;
  }
  if(displayStatus==="submitting"){
    title.textContent="Gerando narração";
    copy.textContent="Preparando roteiro e voz para o pipeline.";
    renderVideoAudio();
    return;
  }
  if(displayStatus==="queued"){
    title.textContent=`Pipeline iniciado (${S.video.progress || 10}%).`;
    copy.textContent=S.video.message || "Aguardando processamento do vídeo.";
    renderVideoAudio();
    return;
  }
  if(displayStatus==="generating_audio" || displayStatus==="generating_narration"){
    title.textContent=S.video.audioUrl ? "Narração gerada com sucesso." : "Gerando narração";
    copy.textContent=S.video.audioUrl ? (S.video.message || "Preparando os clipes IA.") : "Preparando roteiro e voz para o pipeline.";
    renderVideoAudio();
    return;
  }
  if(displayStatus==="generating_clips"){
    const ready=readyVideoClipCount();
    title.textContent="Gerando clipes IA";
    copy.textContent=ready>0
      ? `${ready} de 4 clipes prontos. Continuando a geração dos demais.`
      : "Criando os clipes comerciais a partir das imagens no formato escolhido.";
    renderVideoAudio();
    return;
  }
  if(/^generating_clip_[1-4]$/.test(displayStatus)){
    const clipNumber=Number(displayStatus.replace("generating_clip_","")) || 1;
    title.textContent="Gerando clipes IA";
    copy.textContent=`Criando clipe ${clipNumber} de 4 a partir das imagens selecionadas.`;
    renderVideoAudio();
    return;
  }
  if(/^retrying_clip_[1-4]$/.test(displayStatus)){
    const clipNumber=Number(displayStatus.replace("retrying_clip_","")) || S.video.currentClipIndex || 1;
    const attempt=Math.max(2, Number(S.video.currentClipAttempt || 2));
    title.textContent="Ajustando clipe IA";
    copy.textContent=`Refazendo o clipe ${clipNumber} automaticamente. Tentativa ${attempt} de 3.`;
    renderVideoAudio();
    return;
  }
  if(displayStatus==="composition_queued"){
    title.textContent="Vídeo na fila de composição";
    copy.textContent="Narração e clipes prontos. Seu vídeo entrará em processamento em instantes.";
    renderVideoStatusCompositionTimer(true);
    renderVideoAudio();
    return;
  }
  if(displayStatus==="composing" || displayStatus==="composing_final" || displayStatus==="composing_final_video" || displayStatus==="composition_processing"){
    title.textContent="Compondo vídeo final";
    copy.textContent="Montando o vídeo completo com os clipes e a narração.";
    renderVideoStatusCompositionTimer(true);
    renderVideoAudio();
    return;
  }
  if(displayStatus==="composition_waiting"){
    title.textContent="Composição ainda em andamento";
    copy.textContent="Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos.";
    renderVideoStatusCompositionTimer(true);
    renderVideoAudio();
    return;
  }
  if(S.video.status==="ready_for_composition" || S.video.status==="clips_ready"){
    title.textContent=S.video.clips.length>=4 ? "Clipes IA concluídos" : "Aguardando todos os clipes.";
    copy.textContent=S.video.clips.length>=4
      ? "Narração e 4 clipes foram preparados. Iniciando composição final."
      : "Aguardando todos os clipes para compor o vídeo final.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="composition_pending" || S.video.status==="composition_error"){
    title.textContent=S.video.status==="composition_error" ? "Não foi possível concluir o vídeo final" : "Vídeo na fila de composição";
    copy.textContent=S.video.status==="composition_error"
      ? "Os clipes foram preservados. Você pode tentar novamente."
      : "Narração e clipes prontos. Seu vídeo entrará em processamento em instantes.";
    renderVideoStatusCompositionTimer(S.video.status==="composition_pending");
    renderVideoAudio();
    return;
  }
  if(S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error"){
    title.textContent="Geração parcial salva.";
    copy.textContent=S.video.message || partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length);
    renderVideoAudio();
    return;
  }
  if(S.video.status==="ready" || S.video.status==="prepared"){
    title.textContent=S.video.finalVideoUrl ? "Vídeo final pronto" : `Pipeline preparado com roteiro revisado, formato ${S.video.format} e narração ${voice}.`;
    copy.textContent=S.video.finalVideoUrl
      ? "Seu anúncio em vídeo foi gerado com sucesso."
      : (S.video.audioUrl ? "Narração gerada com sucesso." : (S.video.message || "Pipeline preparado."));
    renderVideoAudio();
    return;
  }
  if(S.video.status==="error" && !displayBusy){
    title.textContent="Não foi possível preparar o vídeo.";
    copy.textContent=S.video.message || "Tente novamente em alguns instantes.";
    renderVideoAudio();
    return;
  }
  title.textContent="Pronto para preparar o vídeo.";
  copy.textContent=`Formato selecionado: ${S.video.format}. Narração ${voice}. Roteiro preparado para revisão.`;
  renderVideoAudio();
}

function videoMotionState(){
  updateVideoVisualProgress();
  const status=String(videoStatusForDisplay() || "");
  const clipMatch=status.match(/^generating_clip_([1-4])$/);
  const retryMatch=status.match(/^retrying_clip_([1-4])$/);
  const rawProgress=Math.max(Number(S.video.progress || 0), Number(S.video.visualProgress || 0));
  const clamp=(value,min,max)=>Math.max(min,Math.min(max,Number(value || 0)));
  let stage=0;
  let progress=0;
  let title="";
  let subtitle="";
  let mode="loading";
  let visible=true;
  let timerActive=false;
  let elapsedSeconds=0;
  let timerNote="";

  if(status==="ready" && S.video.finalVideoUrl){
    visible=false;
  }else if(status==="submitting" || status==="queued" || status==="generating_audio" || status==="generating_narration"){
    stage=0;
    progress=clamp(rawProgress || 12,8,24);
    title="Gerando narração";
    subtitle="Preparando roteiro e voz para o pipeline.";
  }else if(clipMatch || retryMatch){
    const clipNumber=Number((clipMatch || retryMatch)[1]);
    const ranges={1:[25,37],2:[38,51],3:[52,65],4:[66,78]};
    const range=ranges[clipNumber] || [30,65];
    stage=1;
    progress=clamp(rawProgress || range[0] + 4, range[0], range[1]);
    title=retryMatch ? "Ajustando clipe IA" : "Gerando clipes IA";
    subtitle=retryMatch
      ? `Refazendo o clipe ${clipNumber} automaticamente. Tentativa ${Math.max(2, Number(S.video.currentClipAttempt || 2))} de 3.`
      : `Criando clipe ${clipNumber} de 4 a partir das imagens selecionadas.`;
  }else if(status==="generating_clips"){
    const ready=readyVideoClipCount();
    stage=1;
    progress=clamp(Math.max(rawProgress || 0, clipReadyProgress()),25,78);
    title="Gerando clipes IA";
    subtitle=ready>0
      ? `${ready} de 4 clipes prontos. Continuando a geração dos demais.`
      : "Criando os clipes comerciais a partir das imagens no formato escolhido.";
  }else if(status==="clips_ready" || status==="ready_for_composition"){
    stage=1;
    progress=79;
    title="Clipes IA concluídos";
    subtitle="Narração e 4 clipes foram preparados. Iniciando composição final.";
  }else if(status==="composition_pending" || status==="composition_queued" || status==="composing" || status==="composing_final" || status==="composing_final_video"){
    stage=2;
    progress=status==="composition_pending" ? 80 : clamp(rawProgress || 80,80,84);
    title="Vídeo na fila de composição";
    subtitle="Narração e clipes prontos. Seu vídeo entrará em processamento em instantes.";
    timerActive=true;
  }else if(status==="composition_processing"){
    stage=rawProgress >= 92 ? 3 : 2;
    progress=clamp(rawProgress || 88,85,96);
    title="Compondo vídeo final";
    subtitle="Montando o vídeo completo com os clipes e a narração.";
    timerActive=true;
  }else if(status==="composition_waiting"){
    stage=3;
    progress=clamp(rawProgress || 94,92,98);
    title="Composição ainda em andamento";
    subtitle="Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos.";
    timerActive=true;
  }else if(status==="composition_error"){
    stage=2;
    progress=clamp(rawProgress || S.video.progress || 88,80,96);
    title="Não foi possível concluir o vídeo final";
    subtitle="Os clipes foram preservados. Você pode tentar novamente.";
    mode="error";
  }else if(status==="clips_partial_error" || status==="clip_generation_error"){
    stage=1;
    const failed=Number(S.video.failedClipIndex || S.video.currentClipIndex || 3);
    const ranges={1:[25,37],2:[38,51],3:[52,65],4:[66,78]};
    const range=ranges[failed] || [52,78];
    progress=clamp(rawProgress || range[0], range[0], range[1]);
    title=S.video.willRetry ? "Ajustando clipe IA" : "Clipes parcialmente preparados";
    subtitle=S.video.willRetry
      ? `Refazendo o clipe ${failed} automaticamente. Tentativa ${Math.max(2, Number(S.video.currentClipAttempt || 1) + 1)} de ${S.video.maxClipAttempts || 3}.`
      : (S.video.message || partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length));
    mode=S.video.willRetry ? "loading" : "error";
  }else{
    visible=false;
  }

  const fourClipsReady=(Array.isArray(S.video.clips) ? S.video.clips : []).filter(clip=>clip && clip.url).length>=4;
  let stepStates=["pending","pending","pending","pending"];
  if(status==="submitting" || status==="queued" || status==="generating_audio" || status==="generating_narration"){
    stepStates=["active","pending","pending","pending"];
  }else if(clipMatch || retryMatch || status==="generating_clips"){
    stepStates=["done","active","pending","pending"];
  }else if(status==="clips_ready" || status==="ready_for_composition"){
    stepStates=["done","done","pending","pending"];
  }else if(status==="composition_pending" || status==="composition_queued" || status==="composing" || status==="composing_final" || status==="composing_final_video" || status==="composition_processing" || status==="composition_waiting"){
    stepStates=["done","done","active","pending"];
  }else if(status==="composition_error"){
    stepStates=[S.video.audioUrl ? "done" : "pending", fourClipsReady ? "done" : "pending", "active", "pending"];
  }else if(status==="clips_partial_error" || status==="clip_generation_error"){
    stepStates=[S.video.audioUrl ? "done" : "pending", "active", "pending", "pending"];
  }else if(status==="ready"){
    stepStates=["done","done","done","done"];
  }

  if(timerActive){
    elapsedSeconds=compositionElapsedUiSeconds();
    timerNote=compositionTimerSubtitle(elapsedSeconds);
  }

  return {visible,stage,progress,title,subtitle,mode,stepStates,timerActive,elapsedSeconds,timerNote};
}

function renderMotionMarkup(state){
  const steps=["Narração","Clipes IA","Composição","Finalização"];
  const stepHtml=steps.map((label,index)=>{
    const cls=Array.isArray(state.stepStates)
      ? state.stepStates[index]
      : (index<state.stage ? "done" : (index===state.stage ? "active" : "pending"));
    return `<div class="video-motion-step ${cls}"><b>${esc(label)}</b></div>`;
  }).join("");
  const progress=Math.max(0,Math.min(100,Number(state.progress || 0)));
  const timerHtml=state.timerActive ? renderCompositionTimer() : "";
  return `<div class="video-motion-box ${state.mode==="error" ? "error" : ""}">
    <div class="video-motion-inner">
      <div class="video-motion-preview">
        <div class="video-motion-scan"></div>
        <div class="video-motion-play"></div>
      </div>
      <div class="video-motion-copy">
        <div class="video-motion-kicker"><span class="video-motion-orb"></span><span>${state.mode==="error" ? "Ação necessária" : "Processamento de vídeo"}</span></div>
        <h3 class="video-motion-title">${esc(state.title)}</h3>
        <p class="video-motion-subtitle">${esc(state.subtitle)}</p>
        ${timerHtml}
        <div class="video-motion-eq"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="video-motion-progress"><span style="width:${progress}%"></span></div>
        <div class="video-motion-steps">${stepHtml}</div>
      </div>
    </div>
  </div>`;
}

function renderVideoMotion(){
  const box=document.getElementById("video-motion-box");
  if(!box) return;
  const state=videoMotionState();
  if(!state.visible){
    box.style.display="none";
    box.innerHTML="";
    stopCompositionTimerLoop(false);
    return;
  }
  box.style.display="block";
  box.innerHTML=renderMotionMarkup(state);
  if(state.timerActive){
    ensureCompositionTimerStartedAt();
    updateCompositionTimerDom();
    startCompositionTimerLoop();
  }
}

function renderSummaryVideoMotion(hasFinal=false){
  const box=document.getElementById("sum-video-motion-wrap");
  if(!box) return;
  const state=videoMotionState();
  if(hasFinal || !state.visible){
    box.style.display="none";
    box.innerHTML="";
    if(hasFinal) stopCompositionTimerLoop();
    return;
  }
  box.style.display="block";
  box.innerHTML=renderMotionMarkup(state);
  if(state.timerActive){
    ensureCompositionTimerStartedAt();
    updateCompositionTimerDom();
    startCompositionTimerLoop();
  }
}

function renderVideoActionButton(){
  const btn=document.getElementById("btn-generate-video");
  if(!btn) return;
  if(S.video.finalVideoUrl || S.video.status==="ready"){
    btn.disabled=false;
    btn.textContent="Ver vídeo final";
    btn.onclick=goVideoResult;
    return;
  }
  btn.onclick=mockGenerateVideo;
  if(isVideoBusyStatus(S.video.status)){
    btn.disabled=true;
    btn.textContent="Gerando...";
    return;
  }
  btn.disabled=false;
  if((S.video.status==="ready_for_composition" && isComposerPendingCode(S.video.errorCode)) || S.video.status==="composition_pending" || S.video.status==="composition_error"){
    btn.textContent="Tentar compor novamente";
    return;
  }
  if(S.video.status==="composition_queued" || S.video.status==="composition_processing"){
    btn.disabled=true;
    btn.textContent="Compondo...";
    return;
  }
  btn.textContent=(S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error" || (S.video.status==="error" && S.video.jobId)) ? "Tentar novamente" : "Gerar vídeo";
}

function partialClipFailureMessage(failedIndex, savedCount){
  const failed=Number(failedIndex || 0);
  const saved=Number(savedCount || 0);
  if(saved>0 && failed>0){
    const savedLabel=saved===1 ? "O clipe 1 foi salvo" : `Os clipes 1 a ${saved} foram salvos`;
    return `${savedLabel}, mas o clipe ${failed} falhou após as tentativas automáticas. Você pode tentar novamente.`;
  }
  if(failed>0){
    return `O clipe ${failed} falhou após as tentativas automáticas. Você pode tentar novamente.`;
  }
  return "A geração dos clipes foi interrompida. Você pode tentar novamente.";
}

function isComposerPendingCode(code){
  return [
    "COMPOSER_ENDPOINT_MISSING",
    "COMPOSER_API_KEY_MISSING",
    "COMPOSER_JOB_START_ERROR",
    "COMPOSER_STATUS_ERROR",
    "COMPOSER_RENDER_ERROR",
    "COMPOSER_TIMEOUT",
    "FINAL_VIDEO_URL_MISSING",
    "LOCAL_FFMPEG_UNAVAILABLE",
    "FFMPEG_NOT_AVAILABLE"
  ].includes(String(code || ""));
}

function renderFinalVideo(){
  const box=document.getElementById("video-final-box");
  const player=document.getElementById("video-final-player");
  if(!box || !player){
    if(S.video.finalVideoUrl){
      console.warn("STLAI final video target missing", {
        final_video_url_exists:true,
        status:S.video.status,
        composition_status:S.video.compositionStatus,
        render_target_found:false
      });
    }
    return;
  }
	  if(S.video.finalVideoUrl){
	    if(player.getAttribute("src")!==S.video.finalVideoUrl){
	      player.setAttribute("src", S.video.finalVideoUrl);
	      player.load();
	    }
	    player.muted=false;
	    player.defaultMuted=false;
	    player.setAttribute("playsinline","");
	    player.setAttribute("webkit-playsinline","");
	    box.classList.toggle("is-format-vertical", isVideoFormatVertical());
	    box.classList.toggle("is-format-horizontal", !isVideoFormatVertical());
	    box.style.display="block";
  }else{
    player.removeAttribute("src");
    box.style.display="none";
  }
}

function activeVideoClipIndex(){
	  const activeJob=currentClipJob();
	  if(activeJob && (activeJob.status==="generating" || activeJob.status==="retrying")) return Number(activeJob.index || 0);
	  if(activeJob && (activeJob.status==="pending" || activeJob.status==="queued") && (isVideoBusyStatus(S.video.status) || (S.video.jobId && S.video.audioUrl))) return Number(activeJob.index || 0);
  const status=videoStatusForDisplay();
  const match=String(status || "").match(/^(?:generating|retrying)_clip_([1-4])$/);
  if(match) return Number(match[1]);
  return Number(S.video.currentClipIndex || S.video.failedClipIndex || 0);
}

function renderVideoClipsGridMarkup(clips){
  const normalized=Array.isArray(clips) ? clips : [];
  const byIndex={};
  normalized.forEach((clip,idx)=>{
    const index=Number(clip.index || idx + 1);
    if(index>=1 && index<=4) byIndex[index]=clip;
  });

  const jobsByIndex={};
  normalizeClipJobs(S.video.clipJobs, normalized).forEach(job=>{ jobsByIndex[job.index]=job; });
  const activeIndex=activeVideoClipIndex();
  const shouldShowPlaceholders=isVideoBusyStatus(S.video.status) || isVideoBusyStatus(videoStatusForDisplay()) || recoverableVideoErrorStatus(S.video.status) || activeIndex > 0;
  const maxIndex=shouldShowPlaceholders ? 4 : Math.max(0, ...Object.keys(byIndex).map(Number));
  const cards=[];

  for(let index=1; index<=maxIndex; index++){
    const clip=byIndex[index];
    const clipJob=jobsByIndex[index] || {status:clip && clip.url ? "ready" : "pending",attempt:0,error:""};
    if(clip && clip.url){
      const clipUrl=esc(clip.url || "");
      const clipLabel=`clipe-${index}`;
      cards.push(`<div class="video-clip-card">
        <div class="video-clip-title">Clipe ${index}</div>
	        <div class="video-clip-media">
	          <video controls playsinline webkit-playsinline muted preload="metadata" controlsList="nofullscreen nodownload noplaybackrate" disablePictureInPicture src="${clipUrl}"></video>
	          <div class="video-clip-actions">
	            <button type="button" data-video-action="download" data-url="${clipUrl}" data-label="${esc(clipLabel)}">Baixar</button>
	            <button type="button" data-video-action="preview" data-url="${clipUrl}">Ampliar</button>
	          </div>
	        </div>
      </div>`);
      continue;
    }

    if(shouldShowPlaceholders){
      const isActive=index===activeIndex;
      const willRetry=Boolean(clipJob.will_retry || clipJob.willRetry || S.video.willRetry);
      const retrying=clipJob.status==="retrying" || (willRetry && ["pending","queued"].includes(clipJob.status)) || (String(videoStatusForDisplay()).startsWith("retrying_clip_") && isActive);
      const generating=clipJob.status==="generating" || isActive;
      const scheduled=clipJob.status==="scheduled";
      const maxAttempts=Number(clipJob.max_attempts || clipJob.maxAttempts || S.video.maxClipAttempts || 3);
      const attempt=Number(clipJob.attempt || 0);
      const clipActiveOperation=Boolean(clipJob.operation_id_exists || clipJob.operation_id || clipJob.operation_still_processing)
        && !clipJob.url
        && (Boolean(clipJob.operation_still_processing) || ["pending","queued","scheduled","generating","retrying"].includes(String(clipJob.status || "")));
      const failed=!clipActiveOperation && (clipJob.status==="error_final" || clipJob.status==="error" || (recoverableVideoErrorStatus(S.video.status) && index===Number(S.video.failedClipIndex || 0)));
      const finalFailed=failed && !willRetry && attempt >= maxAttempts;
      const operationElapsed=Number(clipJob.operation_elapsed_seconds || clipJob.operationElapsedSeconds || 0);
      const label=finalFailed ? "Erro após tentativas" : (retrying || (willRetry && isActive) ? `Ajustando clipe ${index}` : (generating ? (operationElapsed >= 180 ? `Ainda processando clipe ${index}` : `Gerando clipe ${index}`) : (scheduled ? "Agendado" : "Pendente")));
      cards.push(`<div class="video-clip-card video-clip-card-placeholder ${isActive ? "active" : ""} ${failed ? "error" : ""}">
        <div class="video-clip-title">Clipe ${index}</div>
        <div class="video-clip-placeholder">
          <span></span>
          <strong>${esc(label)}</strong>
        </div>
      </div>`);
    }
  }

  return cards.join("");
}

function bindVideoClipActions(root){
  if(!root || root.dataset.videoActionsBound==="1") return;
  root.dataset.videoActionsBound="1";
  root.addEventListener("click",(event)=>{
    const btn=event.target.closest("[data-video-action]");
    if(!btn || !root.contains(btn)) return;
    event.preventDefault();
    event.stopPropagation();
    const url=btn.dataset.url || "";
    if(btn.dataset.videoAction==="download"){
      downloadVideoAsset(url, btn.dataset.label || "clipe");
      return;
    }
    if(btn.dataset.videoAction==="preview"){
      openVideoAsset(url);
    }
  });
}

function videoClipsGridSignature(clips){
  const normalized=Array.isArray(clips) ? clips : [];
  const clipSig=normalized
    .map((clip,idx)=>`${clip.index || idx + 1}:${clip.url || ""}`)
    .join("|");
  const status=videoStatusForDisplay();
  const jobsSig=normalizeClipJobs(S.video.clipJobs, normalized).map(job=>`${job.index}:${job.status}:${job.attempt}:${job.url || ""}`).join("|");
  const placeholderSig=isVideoBusyStatus(S.video.status) || isVideoBusyStatus(status) || recoverableVideoErrorStatus(S.video.status)
    ? `${status}:${activeVideoClipIndex()}:${S.video.failedClipIndex || 0}`
    : "";
  return `${clipSig}::${jobsSig}::${placeholderSig}`;
}

function renderVideoClips(){
  const box=document.getElementById("video-clips-box");
  const grid=document.getElementById("video-clips-grid");
  if(!box || !grid) return;
  const clips=Array.isArray(S.video.clips) ? S.video.clips : [];
  const html=renderVideoClipsGridMarkup(clips);
  const signature=videoClipsGridSignature(clips);
  grid.classList.toggle("is-format-vertical", isVideoFormatVertical());
  grid.classList.toggle("is-format-horizontal", !isVideoFormatVertical());
  if(!html){
    grid.innerHTML="";
    grid.dataset.sig="";
    box.style.display="none";
    return;
  }

  box.style.display="block";
  if(grid.dataset.sig!==signature){
    grid.innerHTML=html;
    grid.dataset.sig=signature;
  }

  grid.querySelectorAll("video").forEach(video=>{
	    video.muted=true;
	    video.defaultMuted=true;
	    video.setAttribute("playsinline","");
	    video.setAttribute("webkit-playsinline","");
	    video.setAttribute("controlsList","nofullscreen nodownload noplaybackrate");
	    video.setAttribute("disablePictureInPicture","");
	  });
  bindVideoClipActions(grid);
}

function videoFramesFromClips(clips){
  const normalized=Array.isArray(clips) ? clips : [];
  return normalized
    .filter(clip=>clip && clip.prepared_frame_url)
    .map((clip,idx)=>({
      index:Number(clip.index || idx + 1),
      url:clip.prepared_frame_url,
      aspect_ratio:clip.aspect_ratio || S.video.format,
      label:`Imagem ${Number(clip.index || idx + 1)}`,
      width:Number(clip.prepared_frame_width || 0),
      height:Number(clip.prepared_frame_height || 0)
    }));
}

function normalizedVideoFrames(){
  const frames=Array.isArray(S.video.videoFrames) && S.video.videoFrames.length
    ? S.video.videoFrames
    : videoFramesFromClips(S.video.clips);
  const byIndex={};
  frames.forEach((frame,idx)=>{
    const index=Number(frame.index || idx + 1);
    if(index>=1 && index<=4 && frame.url) byIndex[index]={
      index,
      url:frame.url,
      aspect_ratio:frame.aspect_ratio || S.video.format,
      label:`Imagem ${index}`,
      width:Number(frame.width || frame.prepared_frame_width || 0),
      height:Number(frame.height || frame.prepared_frame_height || 0)
    };
  });
  return Object.keys(byIndex).sort((a,b)=>Number(a)-Number(b)).map(key=>byIndex[key]);
}

function renderVideoFramesGridMarkup(frames){
  return frames.map(frame=>`<div class="video-frame-card">
    <div class="video-frame-title">Imagem ${Number(frame.index || 0)}</div>
    <div class="video-frame-media">
      <img src="${esc(frame.url || "")}" alt="${esc(frame.label || "Imagem no formato")}">
      <div class="video-frame-actions">
        <button class="btn bs bsm" type="button" data-frame-action="download" data-url="${esc(frame.url || "")}" data-label="${esc(frame.label || `Imagem ${Number(frame.index || 0)}`)}">Baixar</button>
        <button class="btn bs bsm" type="button" data-frame-action="preview" data-url="${esc(frame.url || "")}">Ampliar</button>
      </div>
    </div>
  </div>`).join("");
}

function bindVideoFrameActions(root){
  if(!root || root.dataset.frameActionsBound==="1") return;
  root.dataset.frameActionsBound="1";
  root.addEventListener("click", event=>{
    const btn=event.target.closest("[data-frame-action]");
    if(!btn || !root.contains(btn)) return;
    event.preventDefault();
    event.stopPropagation();
    const url=btn.dataset.url || "";
    if(!url) return;
    if(btn.dataset.frameAction==="download"){
      dlImg(url, btn.dataset.label || "imagem-formato");
      return;
    }
    if(btn.dataset.frameAction==="preview"){
      openLightbox(url);
    }
  });
}

function videoFramesSignature(frames){
  return `${videoFormatClass()}::${frames.map(frame=>`${frame.index}:${frame.url}`).join("|")}`;
}

function renderVideoFrames(){
  const box=document.getElementById("video-frames-box");
  const grid=document.getElementById("video-frames-grid");
  const title=document.getElementById("video-frames-title");
  if(!box || !grid) return;

  const frames=normalizedVideoFrames();
  if(title) title.textContent=videoFramesTitle();
  grid.classList.toggle("is-format-vertical", isVideoFormatVertical());
  grid.classList.toggle("is-format-horizontal", !isVideoFormatVertical());

  if(!frames.length){
    grid.innerHTML="";
    grid.dataset.sig="";
    box.style.display="none";
    return;
  }

  const sig=videoFramesSignature(frames);
  box.style.display="block";
  if(grid.dataset.sig!==sig){
    grid.innerHTML=renderVideoFramesGridMarkup(frames);
    grid.dataset.sig=sig;
  }
  bindVideoFrameActions(grid);
}

async function generateTestVeoClip(btn){
  const ajaxurl=S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  if(!ajaxurl){
    toast("Não foi possível localizar o endpoint AJAX do WordPress.","error");
    return;
  }

  const selected=selectedVideoImagesForPayload();
  if(!selected.length){
    toast("Selecione ao menos uma imagem para testar o clipe IA.","warn");
    return;
  }

  const originalText=btn ? btn.textContent : "";
  if(btn){
    btn.disabled=true;
    btn.textContent="Testando...";
  }

  S.video.testClipStatus="generating";
  S.video.testClipMessage="Gerando clipe de teste...";
  S.video.testClipUrl="";
  S.video.testClipOperationId="";
  renderVideoStatus();

  try{
    const data=await videoAjaxRequest("stlai_generate_test_veo_clip", {
      selected_images: JSON.stringify(selected.slice(0, 1)),
      format: S.video.format,
      script: S.video.script || "",
      video_language: S.video.language,
      product_name: S.name,
      product_description: S.descTxt || S.desc
    });

    S.video.testClipStatus=data.status || "ready";
    S.video.testClipUrl=data.test_clip_url || "";
    S.video.testClipOperationId=data.operation_id || "";
    S.video.testClipMessage=data.message || "Clipe de teste gerado com sucesso.";
    renderVideoStatus();
    toast(S.video.testClipMessage,"success");
  }catch(err){
    console.warn("Clipe IA de teste", err.data || err);
    S.video.testClipStatus="error";
    S.video.testClipMessage=err.message || "Falha ao gerar clipe de teste.";
    renderVideoStatus();
    toast(S.video.testClipMessage,"error");
  }finally{
    if(btn){
      btn.disabled=false;
      btn.textContent=originalText || "Testar clipe IA";
    }
  }
}

function renderVideoTestClip(){
  const box=document.getElementById("video-test-clip-box");
  const player=document.getElementById("video-test-clip-player");
  if(!box || !player) return;
	  if(S.video.testClipUrl){
	    if(player.getAttribute("src")!==S.video.testClipUrl) player.setAttribute("src", S.video.testClipUrl);
	    player.muted=true;
	    player.defaultMuted=true;
	    player.setAttribute("playsinline","");
	    player.setAttribute("webkit-playsinline","");
	    player.setAttribute("controlsList","nofullscreen nodownload noplaybackrate");
	    player.setAttribute("disablePictureInPicture","");
	    box.style.display="block";
  }else{
    player.removeAttribute("src");
    box.style.display="none";
  }
}

function renderVideoAudio(){
  const box=document.getElementById("video-audio-box");
  const player=document.getElementById("video-audio-player");
  if(!box || !player) return;
  player.removeAttribute("src");
  box.style.display="none";
}

function shortVideoScriptPreview(){
  const script=String(S.video.script || "").replace(/\s+/g," ").trim();
  if(!script) return "Roteiro ainda não preparado.";
  return script.length>180 ? `${script.slice(0,177).trim()}...` : script;
}

function normalizeUgcJob(job={}){
  return {
    ...job,
    ugc_job_id:String(job.ugc_job_id || ""),
    parent_job_id:String(job.parent_job_id || ""),
    preset:String(job.preset || "ugc"),
    label:String(job.label || "UGC"),
    provider:String(job.provider || "muapi"),
    provider_label:String(job.provider_label || ""),
    model:String(job.model || ""),
    status:String(job.status || "processing"),
    image_url:normalizeMediaUrl(job.image_url) || String(job.image_url || ""),
    video_url:normalizeMediaUrl(job.video_url),
    aspect_ratio:String(job.aspect_ratio || "9:16"),
    duration:Number(job.duration || 9),
    resolution:String(job.resolution || "720p"),
    error_message:String(job.error_message || ""),
    created_at:String(job.created_at || ""),
    updated_at:String(job.updated_at || "")
  };
}

function mergeUgcJobs(existing=[], incoming=[]){
  const byId={};
  existing.map(normalizeUgcJob).filter(job=>job.ugc_job_id).forEach(job=>{byId[job.ugc_job_id]=job;});
  incoming.map(normalizeUgcJob).filter(job=>job.ugc_job_id).forEach(job=>{
    const prev=byId[job.ugc_job_id] || {};
    const merged={...prev,...job};
    if(prev.video_url && !job.video_url) merged.video_url=prev.video_url;
    if(prev.status==="ready" && job.status!=="ready") merged.status="ready";
    if(!job.created_at && prev.created_at) merged.created_at=prev.created_at;
    byId[job.ugc_job_id]=merged;
  });
  return Object.values(byId).sort((a,b)=>String(a.created_at || "").localeCompare(String(b.created_at || "")));
}

function ugcConfigEnabled(){
  return Boolean(S.cfg.ugcEnabled);
}

function ugcStatusLabel(status){
  if(status==="ready") return "Pronto";
  if(status==="failed") return "Erro";
  if(status==="queued") return "Fila";
  return "Gerando";
}

function ugcAspectClass(aspect){
  if(aspect==="16:9") return "is-wide";
  if(aspect==="1:1") return "is-square";
  return "";
}

function defaultUgcImage(){
  return [S.imgs4.find(img=>img.key==="hero"), S.imgs4.find(img=>img.key==="capa"), S.imgs4[0]].filter(img=>img && (getImagePublicUrl(img) || getImagePreviewSrc(img)))[0] || S.imgs4.find(img=>getImagePublicUrl(img) || getImagePreviewSrc(img)) || null;
}

function renderUgcSection(){
  const grid=document.getElementById("ugc-jobs-grid");
  const empty=document.getElementById("ugc-empty");
  const warning=document.getElementById("ugc-config-warning");
  const btn=document.getElementById("btn-open-ugc-modal");
  if(!grid) return;
  const jobs=Array.isArray(S.ugcJobs) ? S.ugcJobs.map(normalizeUgcJob) : [];
  if(empty) empty.style.display=jobs.length ? "none" : "flex";
  if(warning) warning.style.display=ugcConfigEnabled() ? "none" : "block";
  if(btn) btn.disabled=!S.imgs4.length;
  grid.innerHTML=jobs.map(job=>{
    const statusClass=job.status==="ready" ? "ready" : (job.status==="failed" ? "failed" : "");
    const mediaClass=ugcAspectClass(job.aspect_ratio);
    const media=job.status==="ready" && job.video_url
      ? `<video src="${escAttr(job.video_url)}" controls playsinline webkit-playsinline preload="metadata"></video>`
      : job.status==="failed"
      ? `<div class="ugc-job-loading"><span>${esc(job.error_message || "Falha ao gerar UGC.")}</span></div>`
      : `<div class="ugc-job-loading"><div class="ugc-mini-spinner"></div><span>Gerando vídeo UGC</span></div>`;
    const actions=job.status==="ready" && job.video_url
      ? `<button class="btn bs bsm" type="button" onclick="downloadVideoAsset('${escAttr(job.video_url)}','ugc-${escAttr(job.preset)}')">Baixar</button><button class="btn bs bsm" type="button" onclick="openVideoAsset('${escAttr(job.video_url)}')">Ampliar</button><button class="btn bs bsm" type="button" onclick="copyMediaLink('${escAttr(job.video_url)}')">Copiar</button><button class="btn bp bsm" type="button" onclick="openUgcModal('${escAttr(job.preset)}')">Variação</button>`
      : job.status==="failed"
      ? `<button class="btn bp bsm" type="button" onclick="retryUgcJob('${escAttr(job.ugc_job_id)}')">Tentar novamente</button>`
      : `<button class="btn bs bsm" type="button" disabled>Processando</button>`;
    const providerLabel=job.provider_label || ({muapi:"MuAPI",atlas:"Atlas Cloud",fal:"Fal.ai",seedance:"Seedance/BytePlus"}[job.provider] || S.cfg.ugcProviderLabel || "UGC");
    return `<div class="ugc-job-card" data-ugc-job="${escAttr(job.ugc_job_id)}">
      <div class="ugc-job-head"><div><strong>${esc(job.label || "UGC")}</strong><span>${esc(job.aspect_ratio)} • ${Number(job.duration || 9)}s • ${esc(job.resolution)} • Provider: ${esc(providerLabel)}</span></div><div class="ugc-job-status ${statusClass}">${ugcStatusLabel(job.status)}</div></div>
      <div class="ugc-job-body"><div class="ugc-job-media ${mediaClass}">${media}</div><div class="ugc-job-actions">${actions}</div></div>
    </div>`;
  }).join("");
  jobs.filter(job=>["queued","processing","running","pending"].includes(job.status) && job.parent_job_id && job.ugc_job_id).forEach(job=>{
    if(!S.ugcPollTimers[job.ugc_job_id]) scheduleUgcPoll(job.parent_job_id,job.ugc_job_id,5000);
  });
}

function renderUgcPresetGrid(){
  const grid=document.getElementById("ugc-preset-grid");
  if(!grid) return;
  const activeTab=S.ugcTab || "all";
  const presets=UGC_PRESETS.filter(p=>activeTab==="all" || p.group===activeTab);
  grid.innerHTML=presets.map(p=>`<button type="button" class="ugc-preset-card ${S.selectedUgcPreset===p.key?"active":""}" data-preset="${escAttr(p.key)}" onclick="selectUgcPreset('${escAttr(p.key)}')"><strong>${esc(p.title)}</strong><span>${esc(p.subtitle)}</span></button>`).join("");
  document.querySelectorAll(".ugc-tab").forEach(tab=>tab.classList.toggle("active", tab.dataset.ugcTab===activeTab));
}

function renderUgcImageList(){
  const list=document.getElementById("ugc-image-list");
  if(!list) return;
  const images=S.imgs4.filter(Boolean);
  if((!S.selectedUgcImage || !(getImagePublicUrl(S.selectedUgcImage) || getImagePreviewSrc(S.selectedUgcImage))) && images.length) selectUgcImage((defaultUgcImage() || images[0])?.key, false);
  list.innerHTML=images.map((img,index)=>{
    const publicUrl=getImagePublicUrl(img);
    const previewSrc=getImagePreviewSrc(img) || publicUrl;
    const needsPublish=!publicUrl && isDataImage(previewSrc);
    const disabled=!publicUrl && !needsPublish;
    return `<button type="button" class="ugc-image-choice stlai-ugc-reference-image stlai-ugc-image ${S.selectedUgcImage?.key===img.key?"active is-selected":""} ${needsPublish?"needs-publish":""}" data-ugc-image="1" data-index="${index}" data-image-index="${index}" data-image-url="${escAttr(publicUrl)}" data-url="${escAttr(publicUrl)}" data-preview-src="${escAttr(previewSrc)}" data-src="${escAttr(previewSrc)}" data-needs-publish="${needsPublish?"1":"0"}" ${disabled?"disabled aria-disabled=\"true\"":""} onclick="selectUgcImage('${escAttr(img.key)}', true, this)">${previewSrc?`<img src="${escAttr(previewSrc)}" alt="${escAttr(img.label || img.key)}">`:""}<span>${esc(img.label || img.key || "Imagem")}${disabled?" indisponível":""}</span></button>`;
  }).join("");
}

function openUgcModal(preset=""){
  if(preset) S.selectedUgcPreset=preset;
  S.ugcModalOpen=true;
  S.ugcAspectRatio=S.ugcAspectRatio || S.cfg.ugcDefaults?.aspectRatio || "9:16";
  S.ugcDuration=Number(S.ugcDuration || S.cfg.ugcDefaults?.duration || 9);
  S.ugcResolution=S.ugcResolution || S.cfg.ugcDefaults?.resolution || "720p";
  const modal=document.getElementById("ugc-modal");
  if(modal) modal.classList.add("show");
  const ar=document.getElementById("ugc-aspect-ratio");
  const dur=document.getElementById("ugc-duration");
  const res=document.getElementById("ugc-resolution");
  if(ar) ar.value=S.ugcAspectRatio;
  if(dur) dur.value=String(S.ugcDuration);
  if(res) res.value=S.ugcResolution;
  renderUgcPresetGrid();
  renderUgcImageList();
}

function closeUgcModal(){
  S.ugcModalOpen=false;
  const modal=document.getElementById("ugc-modal");
  if(modal) modal.classList.remove("show");
}

function setUgcTab(tab){
  S.ugcTab=["all","ugc","commercial"].includes(tab) ? tab : "all";
  renderUgcPresetGrid();
}

function selectUgcPreset(preset){
  if(UGC_PRESETS.some(p=>p.key===preset)) S.selectedUgcPreset=preset;
  renderUgcPresetGrid();
}

function selectUgcImage(key, shouldRender=true, element=null){
  S.selectedUgcImage=S.imgs4.find(img=>img.key===key) || S.selectedUgcImage;
  const selected=S.selectedUgcImage || {};
  const selectedIndex=S.imgs4.findIndex(img=>img && img.key===key);
  const card=element?.closest?.("[data-ugc-image], .stlai-ugc-reference-image, .stlai-ugc-image, .ugc-image-choice") || element;
  let selectedUrl=getImagePublicUrl(card) || getImagePublicUrl(selected, card);
  const previewSrc=getImagePreviewSrc(selected, card) || getImagePreviewSrc(card);
  S.ugc.selectedImageUrl=selectedUrl;
  S.ugc.selectedPreviewSrc=previewSrc;
  S.ugc.selectedImageIndex=selectedIndex;
  S.ugc.selectedImageLabel=String(selected.label || selected.key || "Imagem selecionada");
  console.log("[STLAI UGC] selected image", {public_url:S.ugc.selectedImageUrl, preview_src:S.ugc.selectedPreviewSrc});
  if(shouldRender) renderUgcImageList();
}

function getSelectedUgcImageUrlFromDom(){
  const card=document.querySelector(".stlai-ugc-reference-image.is-selected, .stlai-ugc-image.is-selected, [data-ugc-image].is-selected, .ugc-image-choice.active");
  if(!card) return "";
  return getImagePublicUrl(card);
}

function getSelectedUgcPreviewSrcFromDom(){
  const card=document.querySelector(".stlai-ugc-reference-image.is-selected, .stlai-ugc-image.is-selected, [data-ugc-image].is-selected, .ugc-image-choice.active");
  if(!card) return "";
  return getImagePreviewSrc(card);
}

async function ugcAjaxRequest(action,payload={}){
  const ajaxurl=S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  if(!ajaxurl) throw new Error("Endpoint AJAX do WordPress não encontrado.");
  const formData=new FormData();
  formData.append("action",action);
  formData.append("nonce",S.cfg.ugcNonce || "");
  Object.entries(payload).forEach(([key,value])=>formData.append(key,value == null ? "" : value));
  const response=await fetch(ajaxurl,{method:"POST",body:formData});
  const json=await response.json();
  if(!response.ok || !json.success){
    const data=json?.data || {};
    const message=typeof data==="object" && data.message ? data.message : (typeof data==="string" ? data : `Erro AJAX ${response.status}`);
    const err=new Error(message || "Falha no vídeo UGC.");
    err.data=typeof data==="object" ? data : {message};
    throw err;
  }
  const data=normalizeVideoJobPayload(json.data || {});
  if(data.parent_job_id && !S.video.jobId) S.video.jobId=data.parent_job_id;
  if(Array.isArray(data.ugc_jobs)) S.ugcJobs=mergeUgcJobs(S.ugcJobs,data.ugc_jobs);
  if(data.ugc_job) S.ugcJobs=mergeUgcJobs(S.ugcJobs,[data.ugc_job]);
  return data;
}

async function publishUgcReferenceImage(imageDataBase64,label="Imagem UGC"){
  if(!isDataImage(imageDataBase64)) throw new Error("Imagem de referência base64 inválida.");
  const data=await ugcAjaxRequest("stlai_publish_ugc_reference_image",{
    job_id:S.video.jobId || "",
    image_data_base64:imageDataBase64,
    label
  });
  const publicUrl=publicUrlFromCandidate(data.public_url || data.image_url || data.url);
  if(!publicUrl) throw new Error("Não foi possível publicar a imagem para UGC.");
  return publicUrl;
}

async function ensureUgcPublicImageUrl(selected){
  const card=document.querySelector(".stlai-ugc-reference-image.is-selected, .stlai-ugc-image.is-selected, [data-ugc-image].is-selected, .ugc-image-choice.active");
  const publicUrl=S.ugc.selectedImageUrl || getSelectedUgcImageUrlFromDom() || getImagePublicUrl(selected, card);
  if(publicUrl) return publicUrl;

  const previewSrc=S.ugc.selectedPreviewSrc || getImagePreviewSrc(selected, card) || getSelectedUgcPreviewSrcFromDom();
  if(isDataImage(previewSrc)){
    const label=S.ugc.selectedImageLabel || selected?.label || selected?.key || "Imagem selecionada";
    const publishedUrl=await publishUgcReferenceImage(previewSrc,label);
    S.ugc.selectedImageUrl=publishedUrl;
    if(selected && typeof selected==="object"){
      selected.public_url=publishedUrl;
      selected.image_url=publishedUrl;
    }
    if(card){
      card.dataset.imageUrl=publishedUrl;
      card.dataset.url=publishedUrl;
      card.dataset.needsPublish="0";
      card.classList.remove("needs-publish");
    }
    console.log("[STLAI UGC] published reference image", {preview_src:"data:image/...", public_url:publishedUrl});
    return publishedUrl;
  }

  throw new Error("A imagem precisa ser publicada antes de gerar UGC.");
}

async function startUgcVideo(){
  if(!ugcConfigEnabled()){
    toast("Configure o provider UGC no painel para gerar vídeos.","warn");
    return;
  }
  const selected=S.selectedUgcImage || defaultUgcImage();
  if(!selected){
    toast("Selecione uma imagem válida para gerar UGC.","warn");
    return;
  }
  const ar=document.getElementById("ugc-aspect-ratio");
  const dur=document.getElementById("ugc-duration");
  const res=document.getElementById("ugc-resolution");
  S.ugcAspectRatio=ar?.value || S.ugcAspectRatio || "9:16";
  S.ugcDuration=Number(dur?.value || S.ugcDuration || 9);
  S.ugcResolution=res?.value || S.ugcResolution || "720p";
  const btn=document.getElementById("btn-start-ugc");
  if(btn) btn.disabled=true;
  try{
    const selectedImageUrl=await ensureUgcPublicImageUrl(selected);
    if(!isPublicHttpUrl(selectedImageUrl)){
      throw new Error("A imagem precisa ser publicada antes de gerar UGC.");
    }
    console.log("[STLAI UGC] start payload image_url", selectedImageUrl);
    const data=await ugcAjaxRequest("stlai_start_ugc_video",{
      parent_job_id:S.video.jobId || "",
      preset:S.selectedUgcPreset || "ugc",
      image_url:selectedImageUrl,
      reference_image_url:selectedImageUrl,
      product_image_url:selectedImageUrl,
      selected_image_url:selectedImageUrl,
      selected_image_label:S.ugc.selectedImageLabel || selected.label || selected.key || "Imagem selecionada",
      aspect_ratio:S.ugcAspectRatio,
      duration:S.ugcDuration,
      resolution:S.ugcResolution,
      product_name:S.name || "",
      product_description:S.desc || S.descTxt || ""
    });
    closeUgcModal();
    renderUgcSection();
    if(data.ugc_job_id) scheduleUgcPoll(data.parent_job_id || S.video.jobId, data.ugc_job_id);
    toast("Vídeo UGC iniciado.","success");
  }catch(err){
    toast(err.message || "Falha ao iniciar UGC.","error");
  }finally{
    if(btn) btn.disabled=false;
  }
}

function scheduleUgcPoll(parentJobId,ugcJobId,delay=5000){
  if(!parentJobId || !ugcJobId) return;
  if(S.ugcPollTimers[ugcJobId]) clearTimeout(S.ugcPollTimers[ugcJobId]);
  S.ugcPollTimers[ugcJobId]=setTimeout(()=>pollUgcVideo(parentJobId,ugcJobId),delay);
}

async function pollUgcVideo(parentJobId,ugcJobId){
  try{
    const data=await ugcAjaxRequest("stlai_poll_ugc_video",{parent_job_id:parentJobId,ugc_job_id:ugcJobId});
    renderUgcSection();
    const job=(data.ugc_job || S.ugcJobs.find(j=>j.ugc_job_id===ugcJobId) || {});
    if(job.status==="ready"){
      delete S.ugcPollTimers[ugcJobId];
      toast("Vídeo UGC pronto.","success");
      return;
    }
    if(job.status==="failed"){
      delete S.ugcPollTimers[ugcJobId];
      toast(job.error_message || "Falha ao gerar UGC.","error");
      return;
    }
    scheduleUgcPoll(parentJobId,ugcJobId,5000);
  }catch(err){
    renderUgcSection();
    scheduleUgcPoll(parentJobId,ugcJobId,8000);
  }
}

function retryUgcJob(ugcJobId){
  const job=S.ugcJobs.find(item=>item.ugc_job_id===ugcJobId);
  if(job){
    S.selectedUgcPreset=job.preset || "ugc";
    S.selectedUgcImage=S.imgs4.find(img=>img.url===job.image_url) || defaultUgcImage();
  }
  openUgcModal(S.selectedUgcPreset);
}

function renderSummaryVideo(){
  const card=document.getElementById("sum-video-card");
  const status=document.getElementById("sum-video-status");
  const format=document.getElementById("sum-video-format");
  const narration=document.getElementById("sum-video-narration");
  const script=document.getElementById("sum-video-script");
  const note=document.getElementById("sum-video-note");
  const badge=document.getElementById("sum-video-badge");
  const audioWrap=document.getElementById("sum-video-audio-wrap");
  const audioPlayer=document.getElementById("sum-video-audio-player");
  const finalWrap=document.getElementById("sum-video-final-wrap");
  const finalPlayer=document.getElementById("sum-video-final-player");
  const clipsWrap=document.getElementById("sum-video-clips-wrap");
  const clipsGrid=document.getElementById("sum-video-clips-grid");
  const framesWrap=document.getElementById("sum-video-frames-wrap");
  const framesGrid=document.getElementById("sum-video-frames-grid");
  const framesTitle=document.getElementById("sum-video-frames-title");
  const retryWrap=document.getElementById("sum-video-retry-wrap");
  if(!card || !status || !format || !narration || !script || !note || !badge) return;

  const displayStatus=videoStatusForDisplay();
  const ready=S.video.status==="ready" || displayStatus==="ready";
  const clipsReady=S.video.status==="ready_for_composition" || S.video.status==="clips_ready" || S.video.status==="composition_pending" || S.video.status==="composition_error";
  const partialError=S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error";
  const composing=displayStatus==="composing" || displayStatus==="composing_final_video" || displayStatus==="composition_queued" || displayStatus==="composition_processing" || displayStatus==="composition_waiting" || S.video.compositionStatus==="processing" || S.video.compositionStatus==="waiting";
  const generatingNarration=displayStatus==="generating_audio" || displayStatus==="generating_narration" || displayStatus==="submitting" || displayStatus==="queued";
  const generatingClip=/^(generating|retrying)_clip_[1-4]$/.test(displayStatus) || displayStatus==="generating_clips";
  const inProgress=S.video.status==="submitting" || S.video.status==="queued" || generatingNarration || generatingClip || composing;
  const hasAudio=Boolean(S.video.audioUrl);
  const hasFinal=Boolean(S.video.finalVideoUrl);
  const clips=Array.isArray(S.video.clips) ? S.video.clips : [];
  const hasAssetsForComposition=hasAudio && clips.length>=4 && !hasFinal;
  const compositionError=S.video.status==="composition_error";
  const recoverableError=compositionError || partialError || (S.video.status==="error" && Boolean(S.video.jobId));
  const compositionPending=isComposerPendingCode(S.video.errorCode) || (hasAssetsForComposition && (S.video.status==="ready_for_composition" || S.video.status==="composition_pending"));
  const clipMatch=String(displayStatus || "").match(/^generating_clip_([1-4])$/);
  const retryMatch=String(displayStatus || "").match(/^retrying_clip_([1-4])$/);
  let statusText="Vídeo ainda não gerado.";
  let noteText="Você pode preparar o vídeo no passo 5 quando quiser.";
  let badgeText="Pendente";

  if(hasFinal){
    statusText="Vídeo final pronto";
    noteText="Seu anúncio em vídeo foi gerado com sucesso.";
    badgeText="Pronto";
  }else if(compositionError){
    statusText="Não foi possível concluir o vídeo final.";
    noteText="Os clipes foram preservados. Você pode tentar novamente.";
    badgeText="Erro";
  }else if(compositionPending){
    statusText="Composição final pendente";
    noteText="Narração e clipes preparados. A composição final está pendente.";
    badgeText="Pendente";
  }else if(composing){
    statusText=displayStatus==="composition_waiting" ? "Composição ainda em andamento" : "Compondo vídeo final";
    noteText=displayStatus==="composition_waiting"
      ? "Seu vídeo final ainda está sendo composto. Isso pode levar alguns minutos."
      : "Seu vídeo está sendo produzido. Enquanto isso, você pode revisar as imagens, textos e clipes já criados.";
    badgeText="Gerando";
  }else if(generatingClip){
    statusText=retryMatch ? "Ajustando clipe IA" : (clipMatch ? `Gerando clipe ${clipMatch[1]} de 4...` : "Gerando clipes...");
    noteText=retryMatch
      ? `Refazendo o clipe ${retryMatch[1]} automaticamente. Tentativa ${Math.max(2, Number(S.video.currentClipAttempt || 2))} de 3.`
      : (hasAudio ? "Narração gerada. Os clipes visuais estão sendo preparados em sequência." : "Os clipes visuais estão sendo preparados.");
    badgeText="Gerando";
  }else if(generatingNarration){
    statusText="Gerando narração...";
    noteText="Preparando a narração profissional antes de compor os clipes.";
    badgeText="Gerando";
  }else if(partialError){
    statusText="Clipes parcialmente preparados";
    noteText=S.video.message || partialClipFailureMessage(S.video.failedClipIndex, clips.length);
    badgeText="Pendente";
  }else if(clipsReady){
    statusText="Clipes IA concluídos";
    noteText="Narração e 4 clipes foram preparados. Iniciando composição final.";
    badgeText="Composição pendente";
  }else if(ready){
    statusText=hasAudio ? "Vídeo preparado com narração" : "Vídeo preparado";
    noteText=hasAudio ? "Narração gerada." : "Pipeline preparado.";
    badgeText="Preparado";
  }else if(S.video.status==="error"){
    statusText="Não foi possível preparar o vídeo";
    noteText=S.video.message || "Tente novamente em alguns instantes.";
    badgeText="Erro";
  }else if(inProgress){
    statusText="Preparando vídeo...";
    noteText=S.video.message || "Acompanhe o processamento do vídeo no passo 5.";
    badgeText="Gerando";
  }

  card.classList.toggle("ready", ready || clipsReady || hasFinal || composing || clips.length > 0);
  status.textContent=statusText;
  format.textContent=S.video.format || "-";
  narration.textContent=voiceStyleLabel();
  script.textContent=shortVideoScriptPreview();
  note.textContent=noteText;
  badge.textContent=badgeText;
  if(finalWrap && finalPlayer){
	    if(hasFinal){
	      if(finalPlayer.getAttribute("src")!==S.video.finalVideoUrl){
	        finalPlayer.setAttribute("src", S.video.finalVideoUrl);
	        finalPlayer.load();
	      }
	      finalPlayer.muted=false;
	      finalPlayer.defaultMuted=false;
	      finalPlayer.setAttribute("playsinline","");
	      finalPlayer.setAttribute("webkit-playsinline","");
	      finalWrap.classList.toggle("is-format-vertical", isVideoFormatVertical());
	      finalWrap.classList.toggle("is-format-horizontal", !isVideoFormatVertical());
	      finalWrap.style.display="block";
    }else{
      finalPlayer.removeAttribute("src");
      finalWrap.style.display="none";
    }
  }else if(hasFinal){
    console.warn("STLAI summary final video target missing", {
      final_video_url_exists:true,
      status:S.video.status,
      composition_status:S.video.compositionStatus,
      render_target_found:false
    });
  }
  renderSummaryVideoMotion(hasFinal);
  if(audioWrap && audioPlayer){
    audioPlayer.removeAttribute("src");
    audioWrap.style.display="none";
  }
  if(retryWrap){
    retryWrap.style.display=recoverableError ? "flex" : "none";
  }
  if(clipsWrap && clipsGrid){
    const clipsHtml=renderVideoClipsGridMarkup(clips);
    const clipsSig=videoClipsGridSignature(clips);
    if(clipsHtml){
      clipsWrap.style.display="block";
      clipsGrid.classList.toggle("is-format-vertical", isVideoFormatVertical());
      clipsGrid.classList.toggle("is-format-horizontal", !isVideoFormatVertical());
      if(clipsGrid.dataset.sig!==clipsSig){
        clipsGrid.innerHTML=clipsHtml;
        clipsGrid.dataset.sig=clipsSig;
      }
	      clipsGrid.querySelectorAll("video").forEach(video=>{
	        video.muted=true;
	        video.defaultMuted=true;
	        video.setAttribute("playsinline","");
	        video.setAttribute("webkit-playsinline","");
	        video.setAttribute("controlsList","nofullscreen nodownload noplaybackrate");
	        video.setAttribute("disablePictureInPicture","");
	      });
      bindVideoClipActions(clipsGrid);
    }else{
      clipsGrid.innerHTML="";
      clipsGrid.dataset.sig="";
      clipsWrap.style.display="none";
    }
  }
  if(framesWrap && framesGrid){
    const frames=normalizedVideoFrames();
    if(framesTitle) framesTitle.textContent=videoFramesTitle();
    framesGrid.classList.toggle("is-format-vertical", isVideoFormatVertical());
    framesGrid.classList.toggle("is-format-horizontal", !isVideoFormatVertical());
    if(frames.length){
      const framesSig=videoFramesSignature(frames);
      framesWrap.style.display="block";
      if(framesGrid.dataset.sig!==framesSig){
        framesGrid.innerHTML=renderVideoFramesGridMarkup(frames);
        framesGrid.dataset.sig=framesSig;
      }
      bindVideoFrameActions(framesGrid);
    }else{
      framesGrid.innerHTML="";
      framesGrid.dataset.sig="";
      framesWrap.style.display="none";
    }
  }
}

function selectedVideoImagesForPayload(){
  return S.selVid
    .map(key=>S.imgs4.find(img=>img.key===key))
    .filter(Boolean)
    .map(img=>({url:img.url, label:img.label || img.key}));
}

async function videoAjaxRequest(action, payload={}){
  const ajaxurl=S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  const formData=new FormData();
  formData.append("action", action);
  Object.entries(payload).forEach(([key,value])=>{
    formData.append(key, value == null ? "" : value);
  });

  const response=await fetch(ajaxurl, {
    method:"POST",
    body:formData
  });
  const json=await response.json();
  if(!response.ok || !json.success){
    const data=json?.data || {};
    const message=typeof data==="object" && data.message
      ? data.message
      : (typeof data==="string" ? data : `Erro AJAX ${response.status}`);
    const err=new Error(message || "Não foi possível preparar o vídeo.");
    err.data=typeof data==="object"
      ? data
      : {message, code:"VIDEO_AJAX_ERROR", debug:`HTTP ${response.status}`};
    throw err;
  }
	  const data=json.data || {};
	  return String(action || "").indexOf("stlai_")===0 ? normalizeVideoJobPayload(data) : data;
	}

	function clearVideoPolling(){
	  if(S.video.pollTimer){
	    clearTimeout(S.video.pollTimer);
	    S.video.pollTimer=null;
	  }
	}

function clearVideoClipLaunchers(){
  if(Array.isArray(S.video.clipLaunchTimers)){
    S.video.clipLaunchTimers.forEach(timer=>clearTimeout(timer));
  }
  S.video.clipLaunchTimers=[];
  if(S.video.clipLaunchTimersByIndex && typeof S.video.clipLaunchTimersByIndex==="object"){
    Object.values(S.video.clipLaunchTimersByIndex).forEach(timer=>clearTimeout(timer));
  }
  S.video.clipLaunchTimersByIndex={};
  S.video.clipLaunchStarted=false;
}

function hasActiveClipJobs(){
  return normalizeClipJobs(S.video.clipJobs, S.video.clips).some(job=>["queued","generating","retrying"].includes(job.status));
}

function activeVideoClipJobCount(){
  return normalizeClipJobs(S.video.clipJobs, S.video.clips)
    .filter(job=>["generating","retrying"].includes(job.status) && !job.url && (job.operation_id_exists || job.operation_id || isVideoClipRequestInFlight(job.index)))
    .length;
}

function isVideoClipRequestInFlight(index){
  return Boolean(S.video.clipRequestsInFlight && S.video.clipRequestsInFlight[index]);
}

function scheduleVideoClipStarts(){
  if(!S.video.jobId || !S.video.audioUrl || S.video.finalVideoUrl) return;
  const maxConcurrent=Math.max(4, Number(S.video.maxConcurrentClipGenerations || 4));
  const activeCount=activeVideoClipJobCount();
  const openSlots=Math.max(0, maxConcurrent - activeCount);
  const preferredIndexes=Array.isArray(S.video.nextClipIndexes) ? S.video.nextClipIndexes.map(Number).filter(index=>index>=1 && index<=4) : [];
  const jobsByIndex={};
  normalizeClipJobs(S.video.clipJobs, S.video.clips).forEach(job=>{ jobsByIndex[job.index]=job; });
  const orderedJobs=(preferredIndexes.length ? preferredIndexes.map(index=>jobsByIndex[index]).filter(Boolean) : Object.values(jobsByIndex))
    .filter(job=>job && job.status!=="ready" && job.status!=="error_final" && !job.url && !isVideoClipRequestInFlight(job.index))
    .sort((a,b)=>{
      const aRetry=(a.will_retry || a.retryable || a.status==="retrying") ? 0 : 1;
      const bRetry=(b.will_retry || b.retryable || b.status==="retrying") ? 0 : 1;
      return aRetry-bRetry || Number(a.index)-Number(b.index);
    });

  const liveOperationCandidates=orderedJobs.filter(job=>{
    const hasOperation=Boolean(job.operation_id_exists || job.operation_id);
    return hasOperation && ["generating","retrying","pending"].includes(job.status);
  });
  const startCandidates=orderedJobs.filter(job=>{
    if(liveOperationCandidates.some(item=>Number(item.index)===Number(job.index))) return false;
    if(job.status==="generating" && !(job.operation_id_exists || job.operation_id)) return false;
    return ["scheduled","pending","queued","retrying"].includes(job.status);
  });
  const jobs=[];
  liveOperationCandidates.forEach(job=>{
    if(jobs.some(item=>Number(item.index)===Number(job.index))) return;
    jobs.push(job);
  });
  startCandidates.forEach(job=>{
    if(jobs.filter(item=>!(item.operation_id_exists || item.operation_id)).length>=openSlots) return;
    if(jobs.some(item=>Number(item.index)===Number(job.index))) return;
    jobs.push(job);
  });
  if(!jobs.length) return;

  S.video.clipLaunchStarted=true;
  const staggerMs=Math.max(0, Number(S.video.clipStartStaggerSeconds || 1) * 1000);
  jobs.forEach((job,idx)=>{
    const index=Number(job.index || 0);
    if(index<1 || index>4 || S.video.clipLaunchTimersByIndex?.[index]) return;
    const scheduledMs=parseCompositionDateMs(job.scheduled_start_at || "");
    const fallbackDelay=idx * staggerMs;
    const delayMs=scheduledMs ? Math.max(0, scheduledMs - Date.now()) : fallbackDelay;
    const timer=setTimeout(()=>{
      if(S.video.clipLaunchTimersByIndex) delete S.video.clipLaunchTimersByIndex[index];
      startVideoClip(index);
    }, delayMs);
    S.video.clipLaunchTimersByIndex={...(S.video.clipLaunchTimersByIndex || {}), [index]:timer};
    S.video.clipLaunchTimers.push(timer);
  });
}

function maybeScheduleMissingVideoClips(){
  if(!S.video.jobId || !S.video.audioUrl || S.video.finalVideoUrl) return;
  if(readyVideoClipCount()>=4) return;
  if(recoverableVideoErrorStatus(S.video.status) && !S.video.willRetry) return;
  if(activeVideoClipJobCount()>=Math.max(1, Number(S.video.maxConcurrentClipGenerations || 4))) return;
  if(S.video.nextClipAction && !["generate_missing_clip","retry_stale_clip","retry_clip"].includes(S.video.nextClipAction)) return;
  scheduleVideoClipStarts();
}

function videoPollingDelayMs(){
  const suggested=Number(S.video.nextPollSeconds || 0);
  if(suggested>0) return Math.max(2000, Math.min(12000, suggested * 1000));
  const status=videoStatusForDisplay();
  const elapsed=Number(S.video.composerElapsedSeconds || 0);
  if(status==="composition_queued" || status==="composition_processing" || status==="composition_waiting"){
    return elapsed>=300 ? 10000 : 5000;
  }
  if(status==="generating_clips" || /^generating_clip_[1-4]$/.test(status) || /^retrying_clip_[1-4]$/.test(status)){
    return 3000;
  }
  return 3000;
}

async function startVideoClip(index){
  if(!S.video.jobId || S.video.finalVideoUrl) return;
  index=Number(index || 0);
  if(index<1 || index>4 || isVideoClipRequestInFlight(index)) return;
  S.video.clipRequestsInFlight={...(S.video.clipRequestsInFlight || {}), [index]:true};
  try{
    const data=await videoAjaxRequest("stlai_start_video_clip", {
      job_id:S.video.jobId,
      clip_index:index,
      client_ready_clips:JSON.stringify(clientReadyVideoClips())
    });
    applyVideoState(data, {debug:true});
    renderVideoStatus();
    if(S.step>=6) renderSummaryVideo();
    maybeScheduleMissingVideoClips();
  }catch(err){
    const data=normalizeVideoJobPayload(err.data || {});
    console.warn("Clip generation error", data || err);
    applyVideoState({...data, status:data.status || "clip_generation_error", failed_clip_index:data.failed_clip_index || data.failed_clip || index || 0, code:data.code || data.error_code || ""}, {debug:true});
    S.video.message=data.message || partialClipFailureMessage(S.video.failedClipIndex, readyVideoClipCount());
    renderVideoStatus();
    if(S.step>=6) renderSummaryVideo();
    maybeScheduleMissingVideoClips();
  }finally{
    if(S.video.clipRequestsInFlight){
      delete S.video.clipRequestsInFlight[index];
    }
  }
}

	async function pollVideoStatus(){
  if(!S.video.jobId) return;
  try{
    const data=await videoAjaxRequest("stlai_check_video_status", {
      job_id:S.video.jobId,
      client_ready_clips:JSON.stringify(clientReadyVideoClips())
    });
    applyVideoState(data, {debug:true});
    warnVideoCompositionDiagnostic("poll");
    const activeClipJobs=hasActiveClipJobs();
    const terminalClipError=S.video.status==="clip_generation_error" && !activeClipJobs && !S.video.willRetry;
    if(S.video.status==="ready" || S.video.status==="composition_pending" || S.video.status==="composition_error" || terminalClipError){
      S.video.mockReady=true;
      if(S.video.status==="ready" || S.video.status==="composition_pending" || S.video.status==="composition_error" || terminalClipError) stopVideoProgressLoop();
      renderVideoStatus();
      if(S.step>=6) renderSummaryVideo();
	      if(S.video.status==="composition_error" || terminalClipError){
        toast(S.video.status==="composition_error" ? "Não foi possível concluir o vídeo final." : "Não foi possível gerar todos os clipes.","error");
      }else{
        toast(S.video.status==="ready" ? "Vídeo final preparado." : "Narração e clipes preparados.","success");
      }
      return;
    }
    if(S.video.status==="clips_partial_error"){
      renderVideoStatus();
      if(S.step>=6) renderSummaryVideo();
      toast(S.video.message || partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length),"error");
      return;
    }
    renderVideoStatus();
    if(S.step>=6) renderSummaryVideo();
    maybeScheduleMissingVideoClips();
    S.video.pollTimer=setTimeout(pollVideoStatus, videoPollingDelayMs());
  }catch(err){
    const data=err.data || {};
    console.warn("Video generation error", data || err);
    applyVideoState({...data, status:data.status || (data.failed_clip_index || data.failed_clip ? "clip_generation_error" : "error"), message:data.message || err.message}, {debug:true});
    warnVideoCompositionDiagnostic("poll_error");
    S.video.message=isComposerPendingCode(S.video.errorCode) || S.video.status==="composition_pending"
      ? "Narração e clipes preparados. A composição final está pendente."
      : (S.video.status==="clips_partial_error" || S.video.status==="clip_generation_error"
      ? partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length)
      : (S.video.status==="composition_error"
      ? "Não foi possível concluir o vídeo final. Os clipes foram preservados. Você pode tentar novamente."
      : (err.message || "Falha ao consultar status do vídeo.")));
    stopVideoProgressLoop();
    renderVideoStatus();
    toast(S.video.message,"error");
  }
}

function calcScore() {
  let score = 0;
  let checks = [];
  const svgCheck = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color:var(--mint);flex-shrink:0"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
  const svgCross = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color:var(--coral);flex-shrink:0"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
  const svgWarn = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="color:var(--gold);flex-shrink:0"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

  const allShortTitles = S.titles.every(t => t.length <= 60);
  if (allShortTitles) { score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Todos os títulos otimizados (≤ 60 caracteres)</div>`); }
  else { checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCross} Títulos longos (+ 60 caracteres não ideal para SEO)</div>`); }

  score += 20; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Imagem de Capa com fundo branco (Padrão ML/Shopee)</div>`);

  const hasDims = S.x || S.y || S.z;
  if (hasDims) { score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Imagem com medidas (reduz devoluções)</div>`); }
  else { checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCross} Falta imagem com dimensões do produto</div>`); }

  if (S.descTxt.length > 300) { score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Descrição detalhada (> 300 caracteres)</div>`); }
  else { checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCross} Descrição muito curta</div>`); }

  if (S.imgs4.length >= 8) { score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Alta diversidade de imagens (Plano Premium)</div>`); }
  else { score += 10; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Boa diversidade de imagens (Básico)</div>`); }

  const hasFinalVideo=Boolean(S.video.finalVideoUrl) || S.video.status==="ready" || S.video.compositionStatus==="complete" || S.video.composerStatus==="ready";
  if (S.video.status === "ready" || S.video.status === "ready_for_composition" || S.video.status === "clips_ready" || S.video.status === "composition_pending" || S.video.status === "composition_error" || S.video.finalVideoUrl) {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${isComposerPendingCode(S.video.errorCode) || S.video.status === "composition_pending" || S.video.status === "composition_error" ? svgWarn : svgCheck} ${S.video.finalVideoUrl ? "Vídeo final preparado" : (isComposerPendingCode(S.video.errorCode) || S.video.status === "composition_pending" || S.video.status === "composition_error" ? "Composição final pendente" : "Vídeo preparado para composição")}</div>`);
  } else if (S.video.status === "clips_partial_error" || S.video.status === "clip_generation_error") {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Clipes parcialmente preparados</div>`);
  } else if (S.video.status === "composing_final_video" || S.video.compositionStatus === "processing") {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Compondo vídeo final</div>`);
  } else {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Vídeo ainda não gerado</div>`);
  }

  const scoreBeforeVideoBonus=score;
  let finalVideoScoreBonusApplied=false;
  if(hasFinalVideo){
    score += VIDEO_FINAL_SCORE_BONUS;
    finalVideoScoreBonusApplied=true;
    checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgCheck} Vídeo final gerado (+${VIDEO_FINAL_SCORE_BONUS} pontos)</div>`);
  }
  score=Math.min(100, score);
  S.video.finalVideoScoreBonusApplied=finalVideoScoreBonusApplied;
  S.video.finalVideoScoreBonusValue=VIDEO_FINAL_SCORE_BONUS;
  S.video.scoreBeforeVideoBonus=scoreBeforeVideoBonus;
  S.video.scoreAfterVideoBonus=score;
  S.video.diagnostics={
    ...(S.video.diagnostics || {}),
    composition_elapsed_ui_seconds:compositionElapsedUiSeconds(),
    composition_timer_active:Boolean(S.video.compositionTimer && isVideoCompositionState()),
    final_video_score_bonus_applied:finalVideoScoreBonusApplied,
    final_video_score_bonus_value:VIDEO_FINAL_SCORE_BONUS,
    score_before_video_bonus:scoreBeforeVideoBonus,
    score_after_video_bonus:score
  };

  return { score, checks, scoreBeforeVideoBonus, finalVideoScoreBonusApplied };
}

function renderSummaryScore(){
  const scoreBox=document.getElementById("sum-score");
  if(!scoreBox) return;
  const { score, checks, finalVideoScoreBonusApplied } = calcScore();
  let scoreColor = score >= 80 ? 'var(--mint)' : (score >= 60 ? 'var(--gold)' : 'var(--coral)');
  let scoreHtml = `<div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;">
    <div style="font-size:36px;font-weight:800;color:${scoreColor};font-family:'Syne',sans-serif;">${score}/100</div>
    <div style="font-size:13px;color:var(--tx2);">Sua nota baseada nas melhores práticas dos Marketplaces (Mercado Livre e Shopee).${finalVideoScoreBonusApplied ? "<br>Seu anúncio ganhou pontos extras por ter vídeo final pronto." : ""}</div>
  </div>
  <ul style="list-style:none;padding:0;margin:0;font-size:13px;color:var(--tx);line-height:1.8;">`;
  checks.forEach(c => {
    scoreHtml += `<li>${c}</li>`;
  });
  scoreHtml += `</ul>`;
  scoreBox.innerHTML = scoreHtml;
  scoreBox.style.borderColor = scoreColor;
}

function popSum(){
  // Update credits display
  const cr = S.plan === "premium" ? 80 : 15;
  const remainingCredits = 1000 - cr;
  const disp = document.getElementById("cr-disp");
  if(disp) disp.textContent = `${remainingCredits} créditos`;

  const lm={"pt-BR":"PT-BR","en-US":"EN-US","es-ES":"ES"};
  
  // Update Top Image and Title
  const sumTopImg = document.getElementById("sum-top-img");
  if(sumTopImg && S.imgs.length > 0) sumTopImg.src = S.imgs[0].src;
  
  const sumTopTitle = document.getElementById("sum-top-title");
  if(sumTopTitle && S.titles.length > 0) sumTopTitle.textContent = S.titles[0];

  document.getElementById("sum-meta").innerHTML=`
    <div class="mt2"><div class="mt2-l">Idioma</div><div class="mt2-v">${lm[S.lang]||S.lang}</div></div>
    <div class="mt2"><div class="mt2-l">Plano</div><div class="mt2-v" style="color:${S.plan==='premium'?'var(--gold)':'var(--acc-h)'}">${S.plan==='premium'?'Premium':'Básico'}</div></div>
    <div class="mt2"><div class="mt2-l">Créditos</div><div class="mt2-v" style="color:var(--coral)">${cr}</div></div>
    <div class="mt2"><div class="mt2-l">Data</div><div class="mt2-v" style="font-size:13px">${new Date().toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric'})}</div></div>`;

  renderSummaryScore();
  renderSummaryVideo();
  renderUgcSection();

  const tags=["SEO","Informativo","Benefício","Diferencial"];
  let h='';
  S.titles.forEach((t,i)=>{
    h += `<div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--bd);align-items:flex-start"><span style="font-size:10px;font-weight:700;color:var(--tx3);min-width:86px;padding-top:2px;font-family:'Syne',sans-serif">T${i+1} ${tags[i]||''}</span><span style="font-size:13px;flex:1">${esc(t)}</span><button class="btn bg" style="padding:3px 7px;font-size:10px" onclick="cpTx(${JSON.stringify(t)})" title="Copiar"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button></div>`;
  });
  h += `<div style="padding:10px 0"><div style="font-size:10px;font-weight:700;color:var(--tx3);font-family:'Syne',sans-serif;letter-spacing:1px;text-transform:uppercase;margin-bottom:7px">Descrição</div><div style="font-size:13px;line-height:1.7;color:var(--tx2);white-space:pre-wrap">${esc(S.descTxt)}</div></div>`;
  document.getElementById("sum-tx").innerHTML=h;

  const b1Actions = document.getElementById("b1-actions");
  if(b1Actions) {
    b1Actions.innerHTML = `<button class="btn bs bsm" onclick="dlAll()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Imagens</button>`;
  }

  const g=document.getElementById("sum-g4");
  g.innerHTML="";
  S.imgs4.forEach(img=>{
    const tile = document.createElement("div");
    tile.className = "img4-tile";
    tile.id = `sum-t4-${img.key}`;
    const imageUrl=resolveImageUrl(img);
    const canRegen=Boolean(currentImageTypes().find(type=>type.key===img.key));
    const regenButton=canRegen ? `<button class="btn bs bsm rg-btn" type="button" title="Regerar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>` : "";
    tile.innerHTML = `<img src="${imageUrl}" alt="${esc(img.label)}"><div class="img4-tile-lbl">${esc(img.label)}</div><div class="img4-tile-ov" style="flex-direction:row;gap:5px"><button class="btn bs bsm dl-btn" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button><button class="btn bs bsm lb-btn" type="button" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button>${regenButton}</div>`;
    
    tile.querySelector('.dl-btn').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); triggerDlImg(img.key); });
    tile.querySelector('.lb-btn').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); triggerLightbox(img.key); });
    const regenBtn=tile.querySelector('.rg-btn');
    if(regenBtn) regenBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); regenSingleImage(img.key); });

    g.appendChild(tile);
  });
  if(S.comboUrl) renderCombo(S.comboUrl);
}

function triggerDlImg(key) {
  const img = S.imgs4.find(i => i.key === key);
  if(img) dlImg(resolveImageUrl(img), img.label);
}

function triggerLightbox(key) {
  const img = S.imgs4.find(i => i.key === key);
  if(img) openLightbox(resolveImageUrl(img), "image", img.label || "Imagem ampliada");
}

window.dlImg = dlImg;
window.openLightbox = openLightbox;
window.downloadVideoAsset = downloadVideoAsset;
window.openVideoAsset = openVideoAsset;
window.downloadFinalVideo = downloadFinalVideo;
window.openFinalVideo = openFinalVideo;
window.copyFinalVideoLink = copyFinalVideoLink;
window.dlAll = dlAll;
window.dlCombo = dlCombo;
window.triggerDlImg = triggerDlImg;
window.triggerLightbox = triggerLightbox;
window.regenSingleImage = regenSingleImage;


function switchDrawerTab(tab) {
    document.querySelectorAll('.d-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    
    if (tab === 'calc') {
        document.getElementById('drawer-tab-calc').style.display = 'block';
        document.getElementById('drawer-tab-strat').style.display = 'none';
    } else {
        document.getElementById('drawer-tab-calc').style.display = 'none';
        document.getElementById('drawer-tab-strat').style.display = 'block';
    }
}

function closeMarketDrawer() {
    document.getElementById('stlai-drawer').classList.remove('ativo');
    document.getElementById('stlai-drawer-backdrop').classList.remove('ativo');
}

// Fechar drawer ao clicar fora
document.addEventListener('DOMContentLoaded', () => {
    const bd = document.getElementById('stlai-drawer-backdrop');
    if(bd) {
        bd.addEventListener('click', closeMarketDrawer);
    }
});

function refazerBuscaMercado() {
    const input = document.getElementById("input-nova-busca");
    if (!input || !input.value.trim()) {
        toast("Digite um termo para pesquisar.", "warn");
        return;
    }
    const novoTermo = input.value.trim();
    S.name = novoTermo;
    analyzeMarket(null, novoTermo);
}

async function analyzeMarket(btn, forceTerm = null) {
    const termo = forceTerm || (S.titles && S.titles.length > 0 ? S.titles[0] : S.name);
    if (!termo) {
        toast("Preencha o nome do produto no Passo 2 primeiro.", "warn");
        return;
    }

    // Preenche a barra de nova busca com o termo que está sendo buscado
    const inputNovaBusca = document.getElementById("input-nova-busca");
    if (inputNovaBusca && !forceTerm) {
        inputNovaBusca.value = "";
    }

    document.getElementById('stlai-drawer').classList.add('ativo');
    document.getElementById('stlai-drawer-backdrop').classList.add('ativo');

    document.getElementById('drawer-loading').style.display = 'flex';
    document.getElementById('drawer-results').style.display = 'none';

    const dMsgEl = document.getElementById("drawer-loading-msg");
    if (dMsgEl) dMsgEl.textContent = "Consultando Mercado Livre e Google Shopping...";
    let dmIdx = 0;
    const MARKET_MSGS = [
        "Buscando produtos similares...",
        "Calculando médias de preços...",
        "Extraindo diferenciais do mercado...",
        "Gerando estratégia de vendas por IA..."
    ];
    const drawerInterval = setInterval(() => {
        if(dmIdx < MARKET_MSGS.length) {
            if(dMsgEl) dMsgEl.textContent = MARKET_MSGS[dmIdx++];
        } else {
            dmIdx = 0;
            if(dMsgEl) dMsgEl.textContent = MARKET_MSGS[dmIdx++];
        }
    }, 2500);

    try {
        const formData = new FormData();
        formData.append('action', 'stlai_analisar_mercado');
        formData.append('termo_busca', termo);

        const response = await fetch(window.stlaiConfig.ajaxurl, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.data || 'Erro desconhecido');
        }

        
        const stats = data.data.estatisticas;
        const ia = data.data.ia;
        const produtos = data.data.produtos.map(p => {
            let sourceLabel = p.origem || "";
            if(sourceLabel.toLowerCase().includes('mercado')) sourceLabel = 'Mercado Livre';
            if(sourceLabel.toLowerCase().includes('shopee')) sourceLabel = 'Shopee';
            if(sourceLabel.toLowerCase().includes('amazon')) sourceLabel = 'Amazon';
            return { ...p, origem: sourceLabel };
        });
        
        // Add unique IDs to products
        window.stlaiMarketData.allProducts = produtos.map((p, i) => ({...p, id: 'mp_'+i}));
        
        // By default, activate all origins
        const origins = [...new Set(window.stlaiMarketData.allProducts.map(p => p.origem))];
        window.stlaiMarketData.activeOrigins = new Set(origins);

        document.getElementById('ia-estrategia').textContent = ia.estrategia;
        document.getElementById('ia-tags').innerHTML = (ia.palavras_chave || []).map(t => `<div class="mk-tag">${esc(t)}</div>`).join('');

        renderMarketFilters();
        renderMarketGrid();

        document.getElementById('drawer-loading').style.display = 'none';
        document.getElementById('drawer-results').style.display = 'block';
        clearInterval(drawerInterval);

        // Pre-fill simulator
        setCalcSimulado('med');

        toast("Análise de mercado concluída!", "success");

    } catch(e) {
        clearInterval(drawerInterval);
        console.error(e);
        document.getElementById('drawer-loading').innerHTML = `<p style="color:var(--coral); padding:40px;">Erro ao buscar dados: ${e.message}</p>`;
    }
}


function downloadEverything() {
  toast("Preparando arquivo ZIP...", "info");
  
  let content = "STLAI VISION ADS - EXPORTAÇÃO\n\n";
  content += "=== TÍTULOS ===\n";
  S.titles.forEach((t, i) => content += `${i+1}. ${t}\n`);
  content += "\n=== DESCRIÇÃO ===\n" + S.descTxt + "\n";
  
  const iaEstrategia = document.getElementById("ia-estrategia")?.textContent || "";
  if (iaEstrategia) {
    content += "\n=== ESTRATÉGIA DE MERCADO ===\n" + iaEstrategia + "\n";
    const tagsEls = document.querySelectorAll("#ia-tags .mk-tag");
    if(tagsEls.length) {
        content += "\nTags Sugeridas: " + Array.from(tagsEls).map(e => e.textContent).join(', ') + "\n";
    }
  } else {
    const mkDiv = document.getElementById("market-results");
    if (mkDiv && mkDiv.style.display !== "none" && mkDiv.innerText.includes("Faixa de Preço")) {
      content += "\n=== ANÁLISE DE MERCADO ===\n" + mkDiv.innerText.replace(/Ver no Mercado Livre/g,'').replace(/Ver na Shopee/g,'').trim();
    }
  }

  import('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js').then(async (JSZipModule) => {
    const JSZip = window.JSZip || JSZipModule.default || JSZipModule;
    if(!JSZip) {
        toast("Erro ao carregar compactador ZIP.", "error");
        return;
    }
    const zip = new JSZip();
    
    zip.file("stlai-anuncio-completo.txt", content);
    
    const imgFolder = zip.folder("imagens");
    
    const fetchImageAsBlob = async (url) => {
      const response = await fetch(url);
      return await response.blob();
    };

    try {
        if(S.imgs4.length) {
          for(let i=0; i<S.imgs4.length; i++) {
              const img = S.imgs4[i];
              const blob = await fetchImageAsBlob(img.url);
              imgFolder.file(`stlai-${String(img.label).replace(/\s+/g,"-").toLowerCase()}.jpg`, blob);
          }
        }
        
        if(S.comboUrl) {
            const comboBlob = await fetchImageAsBlob(S.comboUrl);
            imgFolder.file("stlai-combo-2x2.jpg", comboBlob);
        }
        
        const zipContent = await zip.generateAsync({type:"blob"});
        const zipUrl = URL.createObjectURL(zipContent);
        const zipA = document.createElement("a");
        zipA.href = zipUrl;
        zipA.download = "stlai-pacote-anuncio.zip";
        zipA.click();
        URL.revokeObjectURL(zipUrl);
        toast("Download do ZIP concluído!", "success");
    } catch(e) {
        toast("Erro ao empacotar imagens: " + e.message, "error");
    }
    
  }).catch(err => {
      toast("Falha ao importar JSZip.", "error");
      console.error(err);
  });
}


function cpAll(){
  const all = S.titles.join("\n\n") + "\n\n---\n\n" + S.descTxt;
  navigator.clipboard.writeText(all).then(()=>toast("Copiado!","success"));
}

function resetApp() {
  document.getElementById('reset-md').classList.add('show');
}
function performReset() {
  location.reload();
}
function cpEl(id){
  const e=document.getElementById(id);
  if(!e)return;
  navigator.clipboard.writeText(e.innerText||e.textContent).then(()=>toast("Copiado!","success"));
}
function cpTx(t){navigator.clipboard.writeText(t).then(()=>toast("Copiado!","success"));}

function toast(msg,type='info'){
  const ic={success:'✓',error:'✕',info:'ℹ',warn:'⚠'};
  const el=document.createElement('div');
  el.className=`toast ${type}`;
  el.innerHTML=`<span class="ti">${ic[type]||'ℹ'}</span><span>${esc(msg)}</span>`;
  document.getElementById('tw').appendChild(el);
  requestAnimationFrame(()=>el.classList.add('show'));
  setTimeout(()=>{el.classList.remove('show');setTimeout(()=>el.remove(),350);},4000);
}

function ensureCfg(showToastOnFail=true){
  if(S.cfg.txtApi === "openai" || S.cfg.imgApi === "openai") {
    if(!S.cfg.apiKey && showToastOnFail) toast("Falta a chave OpenAI. Configure no painel WP.","warn");
    if(!S.cfg.apiKey) return false;
  }
  if(S.cfg.txtApi === "gemini") {
    if(!S.cfg.geminiKey && showToastOnFail) toast("Falta a chave Gemini. Configure no painel WP.","warn");
    if(!S.cfg.geminiKey) return false;
  }
  return true;
}

async function apiGenerateText(prompt, imgs=[]){
  if(S.cfg.txtApi === "gemini") {
    const parts = [{ text: prompt }];
    imgs.forEach(img => {
      parts.push({ inlineData: { mimeType: img.mime || "image/jpeg", data: img.b64 } });
    });
    const baseUrl = S.cfg.geminiUrl || "https://generativelanguage.googleapis.com";
    const r = await fetch(`${baseUrl}/v1beta/models/${S.cfg.geminiTextModel}:generateContent?key=${S.cfg.geminiKey}`, {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ contents: [{ parts }] })
    });
    const d = await r.json();
    if(!r.ok) throw new Error(d.error?.message || `HTTP ${r.status}`);
    return d.candidates?.[0]?.content?.parts?.[0]?.text || "";
  } else {
    // OpenAI
    const content = [{ type:"text", text: prompt }];
    imgs.forEach(img => {
      content.push({ type:"image_url", image_url: { url: img.src } });
    });
    const baseUrl = S.cfg.url || "https://api.openai.com/v1";
    const r = await fetch(`${baseUrl}/chat/completions`, {
      method:"POST",
      headers:{
        "Content-Type":"application/json",
        "Authorization":`Bearer ${S.cfg.apiKey}`
      },
      body:JSON.stringify({
        model:S.cfg.textModel,
        messages:[{ role:"user", content }]
      })
    });
    const d = await r.json();
    if(!r.ok) throw new Error(d.error?.message || `HTTP ${r.status}`);
    return extractResponseText(d);
  }
}

function extractResponseText(d){
  if(d.choices && d.choices[0]?.message?.content) return d.choices[0].message.content.trim();
  if(typeof d.output_text === "string" && d.output_text.trim()) return d.output_text.trim();
  const parts = Array.isArray(d.output) ? d.output.flatMap(o => Array.isArray(o.content) ? o.content : []) : [];
  return parts.map(p => {
    if(typeof p.text === "string") return p.text;
    if(typeof p.output_text === "string") return p.output_text;
    if(typeof p?.text?.value === "string") return p.text.value;
    return "";
  }).join("\n").trim();
}

async function apiGenerateImage(prompt, file){
  if(S.cfg.imgApi === "gemini") {
    return geminiImageViaWordPress(prompt);
  } else {
    // OpenAI
    const imageUrl = typeof file === "string" ? file : file.src || "";
    try{
      return await openAIImageEdit(prompt, imageUrl);
    }catch(err){
      if (isSafetyError(err)) {
        toast("A referência visual foi bloqueada pelo safety. Tentando geração segura...", "warn");
        const fallbackPrompt = buildSafeImageFallbackPrompt(prompt);
        return openAIImageGenerate(fallbackPrompt);
      }
      throw err;
    }
  }
}

async function geminiImageViaWordPress(prompt){
  const ajaxurl = S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  if(!ajaxurl) throw new Error("Endpoint AJAX do WordPress não encontrado.");

  const form = new FormData();
  form.append("action", "stlai_generate_gemini_image");
  form.append("prompt", prompt);

  const r = await fetch(ajaxurl, {
    method: "POST",
    body: form,
    credentials: "same-origin"
  });
  const d = await r.json().catch(() => null);
  if(!r.ok || !d?.success) {
    throw new Error(d?.data?.message || d?.message || `HTTP ${r.status}`);
  }
  if(!d.data?.url) {
    throw new Error("Resposta vazia da IA Gemini (imagem).");
  }
  return d.data.url;
}

async function openAIImageEdit(prompt, imageUrl){
  const body = {
    model: S.cfg.imageModel,
    prompt,
    size: S.cfg.imgResolution || "1024x1024",
    n: 1,
    images: [{ image_url: imageUrl }]
  };
	  const imageQuality=configuredImageQuality();
	  if (imageQuality !== "auto") {
	     body.quality = imageQuality;
	  }
  
  const baseUrl = S.cfg.url || "https://api.openai.com/v1";
  const r = await fetch(`${baseUrl}/images/edits`, {
    method:"POST",
    headers:{
      "Content-Type":"application/json",
      "Authorization":`Bearer ${S.cfg.apiKey}`
    },
    body: JSON.stringify(body)
  });
  const d = await r.json();
  if(!r.ok) throw new Error(d.error?.message || `HTTP ${r.status}`);
  return extractImageResult(d);
}

async function openAIImageGenerate(prompt){
  const body = {
    model: S.cfg.imageModel,
    prompt,
    size: S.cfg.imgResolution || "1024x1024",
    n: 1
  };
	  const imageQuality=configuredImageQuality();
	  if (imageQuality !== "auto") {
	     body.quality = imageQuality;
	  }

  const baseUrl = S.cfg.url || "https://api.openai.com/v1";
  const r = await fetch(`${baseUrl}/images/generations`, {
    method:"POST",
    headers:{
      "Content-Type":"application/json",
      "Authorization":`Bearer ${S.cfg.apiKey}`
    },
    body: JSON.stringify(body)
  });
  const d = await r.json();
  if(!r.ok) throw new Error(d.error?.message || `HTTP ${r.status}`);
  return extractImageResult(d);
}

function extractImageResult(d){
  const item = d.data?.[0];
  if(item?.b64_json) return `data:image/png;base64,${item.b64_json}`;
  if(item?.url) return item.url;
  throw new Error("A API não retornou imagem.");
}

function isSafetyError(err){
  const msg = String(err?.message || "").toLowerCase();
  return msg.includes("safety") || msg.includes("rejected") || msg.includes("policy");
}

function buildSafeImageFallbackPrompt(originalPrompt){
  const prompt = S.cfg.promptFallback || window.stlaiConfig.promptFallback;
  return fillTpl(prompt, {
    name: sanitizeProductText(S.name || "produto decorativo"),
    desc: sanitizeProductText(S.desc || "uso cotidiano"),
    feat: sanitizeProductText(S.feat || "acabamento premium"),
    originalPrompt: sanitizeProductText(originalPrompt)
  });
}

function sanitizeProductText(text){
  return String(text || "")
    .replace(/\b(pokemon|pokémon|disney|marvel|pixar|naruto|goku|mickey|minnie|batman|superman|spider-man|homem-aranha|star wars)\b/gi, "produto licenciado")
    .replace(/\s+/g, " ")
    .trim();
}

async function resizeSquare(url, size){
  if(!size || size === 1024) return url;
  const img = await loadImage(url);
  const canvas = document.getElementById("offscreen-canvas");
  canvas.width = size; canvas.height = size;
  const ctx = canvas.getContext("2d");
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0,0,size,size);
  ctx.drawImage(img,0,0,size,size);
  return canvas.toDataURL("image/jpeg",0.95);
}

function loadImage(url){
  return new Promise((resolve,reject)=>{
    const img = new Image();
    img.onload = ()=>resolve(img);
    img.onerror = reject;
    img.src = url;
  });
}

function parseJSON(txt){
  try{
    const cleaned = String(txt || "").replace(/```json|```/g,"").trim();
    const start = cleaned.indexOf("{");
    const end = cleaned.lastIndexOf("}");
    const raw = start >= 0 && end >= 0 ? cleaned.slice(start, end+1) : cleaned;
    return JSON.parse(raw);
  }catch{
    return null;
  }
}

function esc(s){
  return String(s ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");
}
function escAttr(s){return String(s ?? "").replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}
function openLightbox(url, type="image", label="Imagem ampliada") {
  const lb = document.getElementById('lightbox');
  const img=document.getElementById('lightbox-img');
  const video=document.getElementById('lightbox-video');
  const fallback=document.getElementById('lightbox-fallback');
  const clean=type==="video" ? normalizeMediaUrl(url) : resolveImageUrl(url);
  const isVideo=type==="video" || /\.(mp4|webm|mov|m4v)(\?|#|$)/i.test(clean);
  const showFallback=()=>{
    if(img){
      img.removeAttribute("src");
      img.style.display="none";
    }
    if(video){
      video.pause();
      video.removeAttribute("src");
      video.style.display="none";
    }
    if(fallback) fallback.style.display="flex";
  };
  if(!lb) return;
  if(fallback) fallback.style.display="none";
  if(!clean){
    showFallback();
    lb.classList.add('show');
    return;
  }
  if(img){
    img.style.display=isVideo ? "none" : "block";
    img.onerror=showFallback;
    img.alt=label || "Imagem ampliada";
    img.title=label || "Imagem ampliada";
    if(isVideo){
      img.removeAttribute("src");
    }else{
      img.src=clean;
    }
  }
  if(video){
    video.style.display=isVideo ? "block" : "none";
    if(isVideo && video.getAttribute("src")!==clean){
      video.setAttribute("src",clean);
      video.load();
    }else if(!isVideo){
      video.pause();
      video.removeAttribute("src");
    }
  }
  lb.classList.add('show');
}
function closeLightbox(e, force=false) {
  if (force || e.target.id === 'lightbox') {
    const video=document.getElementById('lightbox-video');
    const img=document.getElementById('lightbox-img');
    const fallback=document.getElementById('lightbox-fallback');
    if(video) video.pause();
    if(img){
      img.removeAttribute("src");
      img.style.display="none";
      img.onerror=null;
    }
    if(fallback) fallback.style.display="none";
    document.getElementById('lightbox').classList.remove('show');
  }
}

function downloadEverything() {
  toast("Preparando arquivo ZIP...", "info");
  
  let content = "STLAI VISION ADS - EXPORTAÇÃO\n\n";
  content += "=== TÍTULOS ===\n";
  S.titles.forEach((t, i) => content += `${i+1}. ${t}\n`);
  content += "\n=== DESCRIÇÃO ===\n" + S.descTxt + "\n";
  
  const iaEstrategia = document.getElementById("ia-estrategia")?.textContent || "";
  if (iaEstrategia) {
    content += "\n=== ESTRATÉGIA DE MERCADO ===\n" + iaEstrategia + "\n";
    const tagsEls = document.querySelectorAll("#ia-tags .mk-tag");
    if(tagsEls.length) {
        content += "\nTags Sugeridas: " + Array.from(tagsEls).map(e => e.textContent).join(', ') + "\n";
    }
  } else {
    const mkDiv = document.getElementById("market-results");
    if (mkDiv && mkDiv.style.display !== "none" && mkDiv.innerText.includes("Faixa de Preço")) {
      content += "\n=== ANÁLISE DE MERCADO ===\n" + mkDiv.innerText.replace(/Ver no Mercado Livre/g,'').replace(/Ver na Shopee/g,'').trim();
    }
  }

  import('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js').then(async (JSZipModule) => {
    const JSZip = window.JSZip || JSZipModule.default || JSZipModule;
    if(!JSZip) {
        toast("Erro ao carregar compactador ZIP.", "error");
        return;
    }
    const zip = new JSZip();
    
    zip.file("stlai-anuncio-completo.txt", content);
    
    const imgFolder = zip.folder("imagens");
    
    const fetchImageAsBlob = async (url) => {
      const response = await fetch(url);
      return await response.blob();
    };

    try {
        if(S.imgs4.length) {
          for(let i=0; i<S.imgs4.length; i++) {
              const img = S.imgs4[i];
              const blob = await fetchImageAsBlob(img.url);
              imgFolder.file(`stlai-${String(img.label).replace(/\s+/g,"-").toLowerCase()}.jpg`, blob);
          }
        }
        
        if(S.comboUrl) {
            const comboBlob = await fetchImageAsBlob(S.comboUrl);
            imgFolder.file("stlai-combo-2x2.jpg", comboBlob);
        }
        
        const zipContent = await zip.generateAsync({type:"blob"});
        const zipUrl = URL.createObjectURL(zipContent);
        const zipA = document.createElement("a");
        zipA.href = zipUrl;
        zipA.download = "stlai-pacote-anuncio.zip";
        zipA.click();
        URL.revokeObjectURL(zipUrl);
        toast("Download do ZIP concluído!", "success");
    } catch(e) {
        toast("Erro ao empacotar imagens: " + e.message, "error");
    }
    
  }).catch(err => {
      toast("Falha ao importar JSZip.", "error");
      console.error(err);
  });
}

window.stlaiMarketData = {
    allProducts: [],
    activeOrigins: new Set(),
    currentMedio: 0
};

function renderMarketFilters() {
    const filtersDiv = document.getElementById('market-filters');
    filtersDiv.innerHTML = '';
    
    // Get unique origins
    const origins = [...new Set(window.stlaiMarketData.allProducts.map(p => p.origem))];
    
    origins.forEach(origem => {
        const isActive = window.stlaiMarketData.activeOrigins.has(origem);
        const btn = document.createElement('button');
        btn.className = `filter-btn ${isActive ? 'ativo' : ''}`;
        btn.textContent = origem;
        btn.onclick = () => toggleMarketOrigin(origem);
        filtersDiv.appendChild(btn);
    });
}

function toggleMarketOrigin(origem) {
    if(window.stlaiMarketData.activeOrigins.has(origem)) {
        window.stlaiMarketData.activeOrigins.delete(origem);
    } else {
        window.stlaiMarketData.activeOrigins.add(origem);
    }
    renderMarketFilters();
    renderMarketGrid();
}

function removeMarketProduct(id) {
    window.stlaiMarketData.allProducts = window.stlaiMarketData.allProducts.filter(p => p.id !== id);
    renderMarketGrid();
}

function formatMoney(val) {
    let numStr = Number(val).toFixed(2);
    let parts = numStr.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return parts.join(',');
}

function recalcularEstatisticas(visibleProducts) {
    if(visibleProducts.length === 0) {
        document.getElementById('calc-min').textContent = 'R$ 0,00';
        document.getElementById('calc-medio').textContent = 'R$ 0,00';
        document.getElementById('calc-max').textContent = 'R$ 0,00';
        window.stlaiMarketData.currentMedio = 0;
        window.stlaiMarketData.currentStats = { min: 0, medio: 0, max: 0 };
        updateMarginCalculator();
        return;
    }
    
    const precos = visibleProducts.map(p => p.preco);
    const min = Math.min(...precos);
    const max = Math.max(...precos);
    const sum = precos.reduce((a, b) => a + b, 0);
    const medio = sum / precos.length;
    
    window.stlaiMarketData.currentMedio = medio;
    window.stlaiMarketData.currentStats = { min, medio, max };

    document.getElementById('calc-min').textContent = 'R$ ' + formatMoney(min);
    document.getElementById('calc-medio').textContent = 'R$ ' + formatMoney(medio);
    document.getElementById('calc-max').textContent = 'R$ ' + formatMoney(max);
    
    const sMin = document.getElementById('strat-preco-min');
    if(sMin) sMin.textContent = 'R$ ' + formatMoney(min);
    const sMed = document.getElementById('strat-preco-med');
    if(sMed) sMed.textContent = 'R$ ' + formatMoney(medio);
    const sMax = document.getElementById('strat-preco-max');
    if(sMax) sMax.textContent = 'R$ ' + formatMoney(medio * 1.25);
    
    updateMarginCalculator();
}

function renderMarketGrid() {
    const grid = document.getElementById('grid-produtos');
    grid.innerHTML = '';
    
    const visibleProducts = window.stlaiMarketData.allProducts.filter(p => window.stlaiMarketData.activeOrigins.has(p.origem));
    
    if(visibleProducts.length > 0) {
        visibleProducts.forEach(p => {
            const sourceClass = String(p.origem).toLowerCase().includes('mercado') ? 'ml' : 'gs';
            grid.innerHTML += `
                <div class="prod-card">
                    <button class="prod-del" title="Remover produto" onclick="removeMarketProduct('${p.id}')">Remover</button>
                    <img src="${p.imagem}" alt="Produto" class="prod-img" onerror="this.src='https://via.placeholder.com/160x160?text=Sem+Foto'">
                    <div class="prod-info">
                        <span class="prod-origem ${sourceClass}">${esc(p.origem)}</span>
                        <h4 class="prod-title">${esc(p.titulo)}</h4>
                        <div class="prod-price">R$ ${formatMoney(p.preco)}</div>
                        <a href="${escAttr(p.link)}" target="_blank" class="prod-link">Ver Anúncio</a>
                    </div>
                </div>
            `;
        });
    } else {
        grid.innerHTML = '<p style="color:var(--tx2); padding:20px 0;">Nenhum produto listado nos filtros ativos.</p>';
    }
    
    recalcularEstatisticas(visibleProducts);
}

function setCalcSimulado(type) {
    if(!window.stlaiMarketData.currentStats) return;
    const stats = window.stlaiMarketData.currentStats;
    const input = document.getElementById('calc-simulado');
    if (!input) return;
    if (type === 'min') input.value = stats.min.toFixed(2);
    if (type === 'med') input.value = stats.medio.toFixed(2);
    if (type === 'max') input.value = stats.max.toFixed(2);
    updateMarginCalculator();
}

function updateMarginCalculator() {
    const custo = parseFloat(document.getElementById('calc-custo').value) || 0;
    const emb = parseFloat(document.getElementById('calc-emb').value) || 0;
    const frete = parseFloat(document.getElementById('calc-frete').value) || 0;
    const taxa = parseFloat(document.getElementById('calc-taxa').value) || 0;
    const impostos = parseFloat(document.getElementById('calc-impostos').value) || 0;
    const precoSimulado = parseFloat(document.getElementById('calc-simulado').value) || 0;
    
    const custoFixoTotal = custo + emb + frete;
    const descontos = precoSimulado * ((taxa / 100) + (impostos / 100));
    const lucro = precoSimulado - custoFixoTotal - descontos;
    const margem = precoSimulado > 0 ? (lucro / precoSimulado) * 100 : 0;
    
    const resEl = document.getElementById('calc-lucro');
    if(resEl) {
        resEl.textContent = 'R$ ' + formatMoney(lucro);
        if(lucro >= 0) {
            resEl.classList.remove('negative'); resEl.classList.add('positive');
        } else {
            resEl.classList.remove('positive'); resEl.classList.add('negative');
        }
    }
    
    const margemEl = document.getElementById('calc-margem');
    if(margemEl) margemEl.textContent = formatMoney(margem).replace('R$', '').trim() + '%';
}

// Add event listeners for calculator
document.addEventListener('DOMContentLoaded', () => {
    const cCusto = document.getElementById('calc-custo');
    const cEmb = document.getElementById('calc-emb');
    const cFrete = document.getElementById('calc-frete');
    const cTaxa = document.getElementById('calc-taxa');
    const cImpostos = document.getElementById('calc-impostos');
    const cSimulado = document.getElementById('calc-simulado');
    if(cCusto) cCusto.addEventListener('input', updateMarginCalculator);
    if(cEmb) cEmb.addEventListener('input', updateMarginCalculator);
    if(cFrete) cFrete.addEventListener('input', updateMarginCalculator);
    if(cTaxa) cTaxa.addEventListener('input', updateMarginCalculator);
    if(cImpostos) cImpostos.addEventListener('input', updateMarginCalculator);
    if(cSimulado) cSimulado.addEventListener('input', updateMarginCalculator);
});
