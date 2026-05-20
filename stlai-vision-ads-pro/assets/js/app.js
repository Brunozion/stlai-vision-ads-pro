

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
  voiceStyle: "persuasiva",
  titles: [],
  descTxt: "",
  textApproved: false,
  imgs4: [],
  comboUrl: null,
  selVid: [],
  video: {
    status: "idle",
    format: "16:9",
    mockReady: false,
    script: "",
    scriptEdited: false,
    scriptVoiceStyle: "",
    jobId: "",
    progress: 0,
    message: "",
    audioUrl: "",
    finalVideoUrl: "",
    finalVideoDuration: 0,
    thumbnailUrl: "",
    clips: [],
    failedClipIndex: 0,
    failedClipRole: "",
    errorCode: "",
    compositionStatus: "pending",
    testClipUrl: "",
    testClipOperationId: "",
    testClipStatus: "idle",
    testClipMessage: "",
    pollTimer: null
  },
  cfg: window.stlaiConfig || {}
};

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
  S.wt = d.weight || S.wt;
  S.feat = d.features || S.feat;
  S.volt = d.voltage || S.volt;
  const sv=(id,v)=>{const e=document.getElementById(id); if(e && v){ e.value=v; e.style.borderColor="var(--mint)"; setTimeout(()=>e.style.borderColor="",1600); }};
  sv("f-nm", d.name || "");
  let dc = d.description || "";
  if(d.material) dc += `${dc ? "\n" : ""}Material: ${d.material}.`;
  if(d.color) dc += `${dc ? "\n" : ""}Cor: ${d.color}.`;
  sv("f-dc", dc);
  sv("f-wt", d.weight || "");
  sv("f-ft", d.features || "");
  const vm = {"110v":"v1","220v":"v2","bivolt":"v3","n/a":"v4"};
  const vk = vm[String(d.voltage || "N/A").toLowerCase()];
  if(vk) document.getElementById(vk).checked = true;
  if(!silent) toast("Campos preenchidos pela IA. Revise antes de continuar.","success");
}

function currentImageTypes(){
  const hasDims = S.x || S.y || S.z;
  const basic = [
    { key:"capa", label:"Capa — Fundo Branco", scene: S.cfg.sceneCapa || window.stlaiConfig.sceneCapa },
    hasDims ? 
      { key:"dims", label:"Medidas — Fundo Branco", scene: fillTpl(S.cfg.sceneDims || window.stlaiConfig.sceneDims, { dimsTxt: dimsText() }) } : 
      { key:"amb1", label:"Ambientada — Uso 1", scene: S.cfg.sceneAmb1 || window.stlaiConfig.sceneAmb1 },
    { key:"amb2", label:"Ambientada — Uso 2", scene: S.cfg.sceneAmb2 || window.stlaiConfig.sceneAmb2 },
    { key:"amb3", label:"Ambientada — Uso 3", scene: S.cfg.sceneAmb3 || window.stlaiConfig.sceneAmb3 }
  ];

  const premium = [
    ...basic,
    { key:"amb4", label:"Ambientada — Uso 4", scene: S.cfg.sceneAmb4 || window.stlaiConfig.sceneAmb4 },
    { key:"detail", label:"Detalhe — Acabamento", scene: S.cfg.sceneDetail || window.stlaiConfig.sceneDetail },
    { key:"feature", label:"Destaque — Benefício", scene: S.cfg.sceneFeature || window.stlaiConfig.sceneFeature },
    { key:"hero", label:"Hero — Cena Final", scene: S.cfg.sceneHero || window.stlaiConfig.sceneHero }
  ];

  return S.plan === "premium" ? premium : basic;
}

function syncImageStepCopy(){
  const count = currentImageTypes().length;
  const stepSub = document.getElementById("img-step-sub");
  const blockLabel = document.getElementById("img-block1-label");
  const blockDesc = document.getElementById("img-block1-desc");
  if(stepSub) stepSub.textContent = `${count} imagens individuais 1:1 + 1 imagem combo 2x2. Clique para selecionar para vídeo.`;
  if(blockLabel) blockLabel.innerHTML = `Bloco 1 — ${count} imagens individuais <span>1:1 QUADRADO</span>`;
  if(blockDesc) blockDesc.textContent = S.plan === "premium"
    ? "Fundo branco + cenas ambientadas + destaque de acabamento e benefício"
    : "Fundo branco + 3 ambientadas em contexto de uso";
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
  S.voiceStyle=voiceEl?voiceEl.value:"persuasiva";
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
  return {"pt-BR":"português do Brasil","en-US":"English","es-ES":"español"}[S.lang] || "português do Brasil";
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
  S.imgs4=[]; S.comboUrl=null; S.selVid=[];
  document.getElementById("vsc").textContent="0/8";

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

  let prog = 0;
  const pbInterval = setInterval(() => {
    if(prog < 90) prog += 2;
    if(pb) pb.style.width = prog + "%";
    if(pMsg) pMsg.innerHTML = `Gerando suas imagens... <span>${prog}%</span>`;
  }, 1000);

  try{
    let hasError = false;
    
    // --- BATCH 1 (Imagens 1 a 4) ---
    const batch1 = imageTypes.slice(0, 4);
    
    
    batch1.forEach(t => {
      const tile=document.getElementById(`t4-${t.key}`);
      if(tile) tile.innerHTML=`<div class="img-ph-pulse"></div><div class="img-ph"><div style="width:30px;height:30px;border:3px solid transparent;border-top-color:var(--acc);border-bottom-color:var(--acc);border-radius:50%;animation:sp 1s linear infinite;margin-bottom:8px"></div></div>`;
    });
    
    try {
        const masterUrl1 = await genBatchImage(1, ref);
        const croppedUrls1 = await splitImageInto4(masterUrl1);
        for(let i=0; i<batch1.length; i++){
           const t = batch1[i];
           const url = await resizeSquare(croppedUrls1[i], getSelectedSquarePx());
           setTimeout(() => {
             renderTile4(t, url);
           }, i * 300);
           S.imgs4.push({ key:t.key, label:t.label, url });
        }
        await delay(batch1.length * 300);
    } catch(err) {
        console.error("Erro no Bloco 1", err);
        hasError = true;
        batch1.forEach(t => renderTileError(t, err.message));
    }

    // --- BATCH 2 (Imagens 5 a 8, se Premium) ---
    if (imageTypes.length > 4) {
        const batch2 = imageTypes.slice(4, 8);
        
        
        batch2.forEach(t => {
          const tile=document.getElementById(`t4-${t.key}`);
          if(tile) tile.innerHTML=`<div class="img-ph-pulse"></div><div class="img-ph"><div style="width:30px;height:30px;border:3px solid transparent;border-top-color:var(--acc);border-bottom-color:var(--acc);border-radius:50%;animation:sp 1s linear infinite;margin-bottom:8px"></div></div>`;
        });
        
        try {
            const masterUrl2 = await genBatchImage(2, ref);
            const croppedUrls2 = await splitImageInto4(masterUrl2);
            for(let i=0; i<batch2.length; i++){
               const t = batch2[i];
               const url = await resizeSquare(croppedUrls2[i], getSelectedSquarePx());
               setTimeout(() => {
                 renderTile4(t, url);
               }, i * 300);
               S.imgs4.push({ key:t.key, label:t.label, url });
            }
            await delay(batch2.length * 300);
        } catch(err) {
            console.error("Erro no Bloco 2", err);
            hasError = true;
            batch2.forEach(t => renderTileError(t, err.message));
        }
    }

    if(S.imgs4.length >= 4) {
      msg.textContent = "Montando imagem combo 2×2...";
      
      
      
      S.comboUrl = await buildCombo2x2(comboSourceImages());
      renderCombo(S.comboUrl);
      
    } else {
      
    }

        clearInterval(pbInterval);
    if(pb) pb.style.width = "100%";
    if(pb) pb.classList.add("done");
    if(pMsg) pMsg.innerHTML = hasError ? "Imagens geradas com avisos." : "Imagens prontas! <span>100%</span>";
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

async function genMoreImages(btn) {
  if(!ensureCfg()) return;
  const ref = S.imgs[0];
  if(!ref) return;

  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<div style="width:14px;height:14px;border:2px solid transparent;border-top-color:#000;border-bottom-color:#000;border-radius:50%;animation:sp 1s linear infinite;margin-right:6px"></div> Gerando mais 4 imagens...`;

  const prodName = S.name || "produto";
  const scene = fillTpl(S.cfg.promptBatchMore || window.stlaiConfig.promptBatchMore, { name: prodName });

  try {
    const prompt = fillTpl(S.cfg.imagePrompt, {
      scene,
      name: prodName,
      desc: S.desc || "não informado",
      feat: S.feat || "não informado"
    });
    
    const masterUrl = await apiGenerateImage(prompt, ref);
    const croppedUrls = await splitImageInto4(masterUrl);
    
    const sumG4 = document.getElementById("sum-g4");
    const grid4 = document.getElementById("grid4");

    for(let i=0; i<4; i++){
       const url = await resizeSquare(croppedUrls[i], getSelectedSquarePx());
       const tKey = "extra_" + Date.now() + "_" + i;
       const tLabel = "Ambientada Extra " + (S.imgs4.length + 1);
       S.imgs4.push({ key: tKey, label: tLabel, url });
       
       const tileHtml = `<div class="img4-tile" id="t4-${tKey}"><img src="${url}" alt="${esc(tLabel)}" style="opacity:0; transition:opacity 0.6s ease" onload="this.style.opacity=1"><div class="img4-tile-lbl">${esc(tLabel)}</div><div class="img4-tile-ov" style="flex-direction:row;gap:5px"><button class="btn bs bsm" onclick="dlImg('${url}','${escAttr(tLabel)}')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button><button class="btn bs bsm" onclick="openLightbox('${url}')" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button></div></div>`;
       
       if (sumG4) sumG4.insertAdjacentHTML('beforeend', tileHtml);
       if (grid4) grid4.insertAdjacentHTML('beforeend', tileHtml);
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

function getBatchScene(batchNum) {
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
  tile.classList.remove("gen", "error");
  tile.innerHTML=`<img src="${url}" alt="${esc(t.label)}" style="opacity:0; transition:opacity 0.6s ease" onload="this.style.opacity=1"><div class="img4-tile-lbl">${esc(t.label)}</div><div class="img4-tile-ov" style="flex-direction:row;gap:5px"><button class="btn bs bsm" onclick="window.triggerDlImg('${t.key}')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button><button class="btn bs bsm" onclick="window.triggerLightbox('${t.key}')" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button><button class="btn bs bsm" onclick="window.regenSingleImage('${t.key}')" title="Regerar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button></div>`;
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
  if (preferred.length === 4) return preferred.map(img => img.url);
  return S.imgs4.slice(0, 4).map(img => img.url);
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
    if(S.selVid.length>=8){toast("Máximo 8 imagens.","warn");return;}
    S.selVid.push(key);tile.classList.add("fv");
  }
  document.getElementById("vsc").textContent=`${S.selVid.length}/8`;
}

function goVideoStep(){
  if(S.selVid.length<4){
    toast("Selecione pelo menos 4 imagens para gerar o vídeo.","warn");
    return;
  }
  go(5);
}

function dlImg(url,label){
  const a=document.createElement("a");
  a.href=url;
  a.download=`stlai-${String(label).replace(/\s+/g,"-").toLowerCase()}.jpg`;
  a.click();
  toast(`Download: ${label}`,"info");
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
  if(!S.video.script || (!S.video.scriptEdited && S.video.scriptVoiceStyle!==S.voiceStyle)){
    S.video.script=buildVideoScript();
    S.video.scriptEdited=false;
    S.video.scriptVoiceStyle=S.voiceStyle;
  }
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
  return S.voiceStyle==="emocional" ? "Emocional" : "Persuasiva";
}

function cleanScriptPiece(value, fallback){
  const txt=String(value || "").replace(/\s+/g," ").trim();
  return txt || fallback;
}

function buildVideoScript(){
  const name=cleanScriptPiece(S.name, "este produto");
  const desc=cleanScriptPiece(S.descTxt || S.desc, "uma peça pensada para deixar a rotina mais prática e bonita");
  const feat=cleanScriptPiece(S.feat, "acabamento cuidadoso, visual marcante e uso funcional");
  const title=cleanScriptPiece((S.titles || []).find(t=>String(t || "").trim()), name);
  if(S.voiceStyle==="emocional"){
    return `Às vezes, são os pequenos detalhes que tornam o dia mais especial. ${name} traz charme, cuidado e personalidade em uma peça feita para acompanhar momentos simples com mais alegria. ${desc} Com ${feat}, é uma escolha cheia de presença para quem valoriza significado, criatividade e um toque especial no cotidiano.`;
  }
  return `Conheça ${name}, uma peça criada para unir praticidade, estilo e personalidade no seu dia a dia. ${desc} Com ${feat}, ela valoriza sua rotina e transforma um item simples em uma escolha inteligente. ${title} é ideal para quem busca um produto funcional, bonito e pronto para se destacar.`;
}

function renderVideoScript(){
  const scriptEl=document.getElementById("video-script-text");
  if(scriptEl && scriptEl.value!==S.video.script) scriptEl.value=S.video.script;
}

function updateVideoScript(value){
  S.video.script=String(value || "");
  S.video.scriptEdited=true;
  renderVideoStatus();
}

function regenerateVideoScript(){
  S.video.script=buildVideoScript();
  S.video.scriptEdited=false;
  S.video.scriptVoiceStyle=S.voiceStyle;
  renderVideoScript();
  renderVideoStatus();
  toast("Roteiro regenerado localmente.","success");
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

async function mockGenerateVideo(){
  const ajaxurl=S.cfg.ajaxurl || window.stlaiConfig?.ajaxurl;
  if(!ajaxurl){
    toast("Não foi possível localizar o endpoint AJAX do WordPress.","error");
    return;
  }

  const selected=selectedVideoImagesForPayload();
  if(selected.length<4 || selected.length>8){
    toast("Selecione de 4 a 8 imagens para gerar o vídeo.","warn");
    return;
  }

  if(!String(S.video.script || "").trim()){
    toast("Revise o roteiro da narração antes de gerar o vídeo.","warn");
    return;
  }

  clearVideoPolling();
  const retryingPartial=S.video.status==="clips_partial_error" && Boolean(S.video.jobId);
  const retryingComposition=S.video.status==="ready_for_composition" && Boolean(S.video.jobId) && S.video.clips.length>=4 && Boolean(S.video.audioUrl) && !S.video.finalVideoUrl;
  const retryingReusable=retryingPartial || retryingComposition;
  const existingJobId=S.video.jobId || "";
  const existingAudioUrl=S.video.audioUrl || "";
  const existingClips=Array.isArray(S.video.clips) ? S.video.clips : [];
  S.video.status="submitting";
  S.video.mockReady=false;
  S.video.progress=0;
  S.video.jobId=retryingReusable ? existingJobId : "";
  S.video.audioUrl=retryingReusable ? existingAudioUrl : "";
  S.video.finalVideoUrl="";
  S.video.finalVideoDuration=0;
  S.video.thumbnailUrl="";
  S.video.clips=retryingReusable ? existingClips : [];
  S.video.failedClipIndex=0;
  S.video.failedClipRole="";
  S.video.errorCode="";
  S.video.compositionStatus="pending";
  S.video.message=retryingComposition
    ? "Tentando compor o vídeo final novamente..."
    : (retryingPartial ? "Tentando novamente a partir do clipe pendente..." : "Gerando narração e preparando pipeline...");
  renderVideoStatus();

  try{
    const data=await videoAjaxRequest("stlai_create_video_job", {
      job_id: retryingReusable ? existingJobId : "",
      selected_images: JSON.stringify(selected),
      narration_type: S.voiceStyle,
      format: S.video.format,
      script: S.video.script,
      product_name: S.name,
      product_description: S.descTxt || S.desc
    });

    S.video.jobId=data.job_id || "";
    S.video.status=data.status || "queued";
    S.video.progress=Number(data.progress || 10);
    S.video.message=data.message || "Job de vídeo criado.";
    S.video.audioUrl=data.audio_url || "";
    S.video.finalVideoUrl=data.final_video_url || "";
    S.video.finalVideoDuration=Number(data.final_video_duration || 0);
    S.video.thumbnailUrl=data.thumbnail_url || "";
    S.video.clips=Array.isArray(data.clips) ? data.clips : [];
    S.video.failedClipIndex=Number(data.failed_clip_index || 0);
    S.video.failedClipRole=data.failed_clip_role || "";
    S.video.errorCode=data.error_code || "";
    S.video.compositionStatus=data.composition_status || "pending";
    renderVideoStatus();
    toast(S.video.finalVideoUrl ? "Vídeo final preparado com sucesso." : (S.video.clips.length===4 ? "4 clipes gerados com sucesso." : (S.video.audioUrl ? "Narração gerada com sucesso." : "Job de vídeo criado com sucesso.")),"success");
    if(S.video.status==="ready" || S.video.status==="ready_for_composition" || S.video.status==="clips_ready"){
      S.video.mockReady=true;
      return;
    }
    pollVideoStatus();
  }catch(err){
    const data=err.data || {};
    console.warn("Video generation error", data || err);
    if(data.job_id) S.video.jobId=data.job_id;
    if(data.audio_url) S.video.audioUrl=data.audio_url;
    if(Array.isArray(data.partial_clips)) S.video.clips=data.partial_clips;
    else if(Array.isArray(data.clips)) S.video.clips=data.clips;
    S.video.failedClipIndex=Number(data.failed_clip_index || data.failed_clip || 0);
    S.video.failedClipRole=data.failed_clip_role || "";
    S.video.errorCode=data.code || data.error_code || "";
    S.video.status=data.status || (S.video.failedClipIndex ? "clips_partial_error" : "error");
    S.video.compositionStatus=data.composition_status || "pending";
    S.video.message=S.video.errorCode==="FFMPEG_NOT_AVAILABLE"
      ? "Os 4 clipes e a narração foram gerados. Para criar o vídeo final, ative o FFmpeg no servidor."
      : (S.video.status==="clips_partial_error"
      ? partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length)
      : (err.message || "Falha ao criar job de vídeo."));
    renderVideoStatus();
    toast(S.video.message,"error");
  }
}

function renderVideoStatus(){
  const box=document.getElementById("video-status-box");
  const title=document.getElementById("video-status-title");
  const copy=document.getElementById("video-status-copy");
  if(!box || !title || !copy) return;
  const voice=voiceStyleLabel();
  box.classList.toggle("ready", S.video.status==="ready" || S.video.status==="prepared" || S.video.status==="ready_for_composition" || S.video.status==="clips_ready");
  renderVideoActionButton();
  renderVideoTestClip();
  renderVideoClips();
  renderFinalVideo();
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
  if(S.video.status==="submitting"){
    title.textContent="Gerando narração...";
    copy.textContent="Enviando imagens, formato e roteiro para preparar o pipeline.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="queued"){
    title.textContent=`Pipeline iniciado (${S.video.progress || 10}%).`;
    copy.textContent=S.video.message || "Aguardando processamento do vídeo.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="generating_audio" || S.video.status==="generating_narration"){
    title.textContent=S.video.audioUrl ? "Narração gerada com sucesso." : `Gerando narração (${S.video.progress || 40}%).`;
    copy.textContent=S.video.message || "Preparando o áudio da narração para o pipeline.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="generating_clips"){
    title.textContent=`Preparando clipes comerciais (${S.video.progress || 40}%).`;
    copy.textContent=S.video.message || `Formato ${S.video.format}, narração ${voice} e roteiro revisado.`;
    renderVideoAudio();
    return;
  }
  if(/^generating_clip_[1-4]$/.test(S.video.status)){
    const clipNum=S.video.status.slice(-1);
    title.textContent=`Gerando clipe ${clipNum} de 4 (${S.video.progress || 50}%).`;
    copy.textContent=S.video.message || "Os clipes visuais estão sendo gerados em sequência.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="composing" || S.video.status==="composing_final_video"){
    title.textContent=`Compondo vídeo final (${S.video.progress || 88}%).`;
    copy.textContent=S.video.message || "Aplicando a narração e as transições entre os clipes.";
    renderVideoAudio();
    return;
  }
  if(S.video.status==="ready_for_composition" || S.video.status==="clips_ready"){
    title.textContent=S.video.clips.length>=4 ? "4 clipes gerados." : "Aguardando todos os clipes.";
    copy.textContent=S.video.errorCode==="FFMPEG_NOT_AVAILABLE"
      ? "Os 4 clipes e a narração foram gerados. Para criar o vídeo final, ative o FFmpeg no servidor."
      : (S.video.clips.length>=4
      ? "4 clipes gerados. Composição final será feita na próxima etapa."
      : "Aguardando todos os clipes para compor o vídeo final.");
    renderVideoAudio();
    return;
  }
  if(S.video.status==="clips_partial_error"){
    title.textContent="Geração parcial salva.";
    copy.textContent=S.video.message || partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length);
    renderVideoAudio();
    return;
  }
  if(S.video.status==="ready" || S.video.status==="prepared"){
    title.textContent=S.video.finalVideoUrl ? "Vídeo final preparado." : `Pipeline preparado com roteiro revisado, formato ${S.video.format} e narração ${voice}.`;
    copy.textContent=S.video.finalVideoUrl
      ? "Narração aplicada com sucesso. 4 clipes compostos com transições entre eles."
      : (S.video.audioUrl ? "Narração gerada com sucesso." : (S.video.message || "Pipeline preparado."));
    renderVideoAudio();
    return;
  }
  if(S.video.status==="error"){
    title.textContent="Não foi possível preparar o vídeo.";
    copy.textContent=S.video.message || "Tente novamente em alguns instantes.";
    renderVideoAudio();
    return;
  }
  title.textContent="Pronto para preparar o vídeo.";
  copy.textContent=`Formato selecionado: ${S.video.format}. Narração ${voice}. Roteiro preparado para revisão.`;
  renderVideoAudio();
}

function renderVideoActionButton(){
  const btn=document.getElementById("btn-generate-video");
  if(!btn) return;
  if(S.video.status==="submitting" || /^generating_clip_[1-4]$/.test(S.video.status) || S.video.status==="generating_audio" || S.video.status==="generating_narration" || S.video.status==="composing_final_video"){
    btn.disabled=true;
    btn.textContent="Gerando...";
    return;
  }
  btn.disabled=false;
  if(S.video.status==="ready_for_composition" && S.video.errorCode==="FFMPEG_NOT_AVAILABLE"){
    btn.textContent="Tentar compor novamente";
    return;
  }
  btn.textContent=S.video.status==="clips_partial_error" ? "Tentar novamente" : "Gerar vídeo";
}

function partialClipFailureMessage(failedIndex, savedCount){
  const failed=Number(failedIndex || 0);
  const saved=Number(savedCount || 0);
  if(saved>0 && failed>0){
    const savedLabel=saved===1 ? "O clipe 1 foi salvo" : `Os clipes 1 a ${saved} foram salvos`;
    return `${savedLabel}, mas o clipe ${failed} falhou. Você pode tentar novamente.`;
  }
  if(failed>0){
    return `O clipe ${failed} falhou. Você pode tentar novamente.`;
  }
  return "A geração dos clipes foi interrompida. Você pode tentar novamente.";
}

function renderFinalVideo(){
  const box=document.getElementById("video-final-box");
  const player=document.getElementById("video-final-player");
  if(!box || !player) return;
  if(S.video.finalVideoUrl){
    if(player.getAttribute("src")!==S.video.finalVideoUrl) player.setAttribute("src", S.video.finalVideoUrl);
    player.muted=false;
    player.defaultMuted=false;
    box.style.display="block";
  }else{
    player.removeAttribute("src");
    box.style.display="none";
  }
}

function renderVideoClips(){
  const box=document.getElementById("video-clips-box");
  const grid=document.getElementById("video-clips-grid");
  if(!box || !grid) return;
  const clips=Array.isArray(S.video.clips) ? S.video.clips : [];
  if(!clips.length){
    grid.innerHTML="";
    box.style.display="none";
    return;
  }

  box.style.display="block";
  grid.innerHTML=clips.map(clip=>{
    const label=clip.label || `Clipe ${clip.index || ""}`;
    const url=clip.url || "";
    return `<div class="video-clip-card">
      <div class="video-clip-title">${esc(label)}</div>
      <video controls playsinline muted preload="metadata" src="${esc(url)}"></video>
    </div>`;
  }).join("");

  grid.querySelectorAll("video").forEach(video=>{
    video.muted=true;
    video.defaultMuted=true;
  });
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
  if(S.video.audioUrl){
    if(player.getAttribute("src")!==S.video.audioUrl) player.setAttribute("src", S.video.audioUrl);
    box.style.display="block";
  }else{
    player.removeAttribute("src");
    box.style.display="none";
  }
}

function shortVideoScriptPreview(){
  const script=String(S.video.script || "").replace(/\s+/g," ").trim();
  if(!script) return "Roteiro ainda não preparado.";
  return script.length>180 ? `${script.slice(0,177).trim()}...` : script;
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
  if(!card || !status || !format || !narration || !script || !note || !badge) return;

  const ready=S.video.status==="ready";
  const clipsReady=S.video.status==="ready_for_composition" || S.video.status==="clips_ready";
  const partialError=S.video.status==="clips_partial_error";
  const composing=S.video.status==="composing" || S.video.status==="composing_final_video" || S.video.compositionStatus==="processing";
  const generatingNarration=S.video.status==="generating_audio" || S.video.status==="generating_narration";
  const generatingClip=/^generating_clip_[1-4]$/.test(S.video.status) || S.video.status==="generating_clips";
  const inProgress=S.video.status==="submitting" || S.video.status==="queued" || generatingNarration || generatingClip || composing;
  const hasAudio=Boolean(S.video.audioUrl);
  const hasFinal=Boolean(S.video.finalVideoUrl);
  const clips=Array.isArray(S.video.clips) ? S.video.clips : [];
  const hasAssetsForComposition=hasAudio && clips.length>=4 && !hasFinal;
  const ffmpegPending=S.video.errorCode==="FFMPEG_NOT_AVAILABLE" || (hasAssetsForComposition && S.video.status==="ready_for_composition");
  const clipMatch=String(S.video.status || "").match(/^generating_clip_([1-4])$/);
  let statusText="Vídeo ainda não gerado.";
  let noteText="Você pode preparar o vídeo no passo 5 quando quiser.";
  let badgeText="Pendente";

  if(hasFinal){
    statusText="Vídeo final preparado";
    noteText="Narração aplicada com sucesso. 4 clipes compostos com transições entre eles.";
    badgeText="Pronto";
  }else if(ffmpegPending){
    statusText="Composição final pendente";
    noteText="Narração gerada. 4 clipes preparados. Ative o FFmpeg para gerar o vídeo final com narração.";
    badgeText="Pendente";
  }else if(composing){
    statusText="Compondo vídeo final...";
    noteText="Compondo vídeo final com a narração aplicada e fade apenas entre os clipes.";
    badgeText="Gerando";
  }else if(generatingClip){
    statusText=clipMatch ? `Gerando clipe ${clipMatch[1]} de 4...` : "Gerando clipes...";
    noteText=hasAudio ? "Narração gerada. Os clipes visuais estão sendo preparados em sequência." : "Os clipes visuais estão sendo preparados.";
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
    statusText="4 clipes preparados";
    noteText=hasAudio ? "Narração gerada. Aguardando composição final." : "Clipes gerados. Aguardando composição final.";
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
      if(finalPlayer.getAttribute("src")!==S.video.finalVideoUrl) finalPlayer.setAttribute("src", S.video.finalVideoUrl);
      finalWrap.style.display="block";
    }else{
      finalPlayer.removeAttribute("src");
      finalWrap.style.display="none";
    }
  }
  if(audioWrap && audioPlayer){
    if(hasAudio){
      if(audioPlayer.getAttribute("src")!==S.video.audioUrl) audioPlayer.setAttribute("src", S.video.audioUrl);
      audioWrap.style.display="block";
    }else{
      audioPlayer.removeAttribute("src");
      audioWrap.style.display="none";
    }
  }
  if(clipsWrap && clipsGrid){
    if(clips.length){
      clipsWrap.style.display="block";
      clipsGrid.innerHTML=clips.map(clip=>{
        const label=clip.label || `Clipe ${clip.index || ""}`;
        const url=clip.url || "";
        return `<div class="video-clip-card">
          <div class="video-clip-title">${esc(label)}</div>
          <video controls playsinline muted preload="metadata" src="${esc(url)}"></video>
        </div>`;
      }).join("");
      clipsGrid.querySelectorAll("video").forEach(video=>{
        video.muted=true;
        video.defaultMuted=true;
      });
    }else{
      clipsGrid.innerHTML="";
      clipsWrap.style.display="none";
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
  return json.data || {};
}

function clearVideoPolling(){
  if(S.video.pollTimer){
    clearTimeout(S.video.pollTimer);
    S.video.pollTimer=null;
  }
}

async function pollVideoStatus(){
  if(!S.video.jobId) return;
  try{
    const data=await videoAjaxRequest("stlai_check_video_status", {job_id:S.video.jobId});
    S.video.status=data.status || S.video.status;
    S.video.progress=Number(data.progress || S.video.progress || 0);
    S.video.message=data.message || S.video.message;
    S.video.audioUrl=data.audio_url || S.video.audioUrl || "";
    S.video.clips=Array.isArray(data.clips) ? data.clips : (S.video.clips || []);
    if(Array.isArray(data.partial_clips) && data.partial_clips.length) S.video.clips=data.partial_clips;
    S.video.failedClipIndex=Number(data.failed_clip_index || S.video.failedClipIndex || 0);
    S.video.failedClipRole=data.failed_clip_role || S.video.failedClipRole || "";
    S.video.errorCode=data.error_code || S.video.errorCode || "";
    S.video.compositionStatus=data.composition_status || S.video.compositionStatus || "pending";
    S.video.finalVideoUrl=data.final_video_url || "";
    S.video.finalVideoDuration=Number(data.final_video_duration || S.video.finalVideoDuration || 0);
    S.video.thumbnailUrl=data.thumbnail_url || "";
    if(S.video.status==="ready" || S.video.status==="ready_for_composition" || S.video.status==="clips_ready"){
      S.video.mockReady=true;
      renderVideoStatus();
      toast(S.video.status==="ready" ? "Vídeo final preparado." : "4 clipes gerados com sucesso.","success");
      return;
    }
    if(S.video.status==="clips_partial_error"){
      renderVideoStatus();
      toast(S.video.message || partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length),"error");
      return;
    }
    renderVideoStatus();
    S.video.pollTimer=setTimeout(pollVideoStatus, 1200);
  }catch(err){
    const data=err.data || {};
    console.warn("Video generation error", data || err);
    if(Array.isArray(data.partial_clips)) S.video.clips=data.partial_clips;
    else if(Array.isArray(data.clips)) S.video.clips=data.clips;
    S.video.failedClipIndex=Number(data.failed_clip_index || data.failed_clip || 0);
    S.video.failedClipRole=data.failed_clip_role || "";
    S.video.errorCode=data.code || data.error_code || "";
    S.video.status=data.status || (S.video.failedClipIndex ? "clips_partial_error" : "error");
    S.video.message=S.video.errorCode==="FFMPEG_NOT_AVAILABLE"
      ? "Os 4 clipes e a narração foram gerados. Para criar o vídeo final, ative o FFmpeg no servidor."
      : (S.video.status==="clips_partial_error"
      ? partialClipFailureMessage(S.video.failedClipIndex, S.video.clips.length)
      : (err.message || "Falha ao consultar status do vídeo."));
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

  if (S.video.status === "ready" || S.video.status === "ready_for_composition" || S.video.status === "clips_ready" || S.video.finalVideoUrl) {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${S.video.errorCode==="FFMPEG_NOT_AVAILABLE" ? svgWarn : svgCheck} ${S.video.finalVideoUrl ? "Vídeo final preparado" : (S.video.errorCode==="FFMPEG_NOT_AVAILABLE" ? "Composição final pendente" : "Vídeo preparado para composição")}</div>`);
  } else if (S.video.status === "clips_partial_error") {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Clipes parcialmente preparados</div>`);
  } else if (S.video.status === "composing_final_video" || S.video.compositionStatus === "processing") {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Compondo vídeo final</div>`);
  } else {
     score += 15; checks.push(`<div style="display:flex;gap:8px;align-items:center">${svgWarn} Vídeo ainda não gerado</div>`);
  }

  return { score, checks };
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

  const { score, checks } = calcScore();
  let scoreColor = score >= 80 ? 'var(--mint)' : (score >= 60 ? 'var(--gold)' : 'var(--coral)');
  
  let scoreHtml = `<div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;">
    <div style="font-size:36px;font-weight:800;color:${scoreColor};font-family:'Syne',sans-serif;">${score}/100</div>
    <div style="font-size:13px;color:var(--tx2);">Sua nota baseada nas melhores práticas dos Marketplaces (Mercado Livre e Shopee).</div>
  </div>
  <ul style="list-style:none;padding:0;margin:0;font-size:13px;color:var(--tx);line-height:1.8;">`;
  
  checks.forEach(c => {
    scoreHtml += `<li>${c}</li>`;
  });
  scoreHtml += `</ul>`;
  
  document.getElementById("sum-score").innerHTML = scoreHtml;
  document.getElementById("sum-score").style.borderColor = scoreColor;
  renderSummaryVideo();

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
    tile.innerHTML = `<div class="img-ph-pulse"></div><img src="${img.url}" alt="${esc(img.label)}"><div class="img4-tile-lbl">${esc(img.label)}</div><div class="img4-tile-ov" style="flex-direction:row;gap:5px"><button class="btn bs bsm dl-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button><button class="btn bs bsm lb-btn" title="Ampliar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></button><button class="btn bs bsm rg-btn" title="Regerar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button></div>`;
    
    tile.querySelector('.dl-btn').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); triggerDlImg(img.key); });
    tile.querySelector('.lb-btn').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); triggerLightbox(img.key); });
    tile.querySelector('.rg-btn').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); regenSingleImage(img.key); });

    g.appendChild(tile);
  });
  if(S.comboUrl) renderCombo(S.comboUrl);
}

function triggerDlImg(key) {
  const img = S.imgs4.find(i => i.key === key);
  if(img) dlImg(img.url, img.label);
}

function triggerLightbox(key) {
  const img = S.imgs4.find(i => i.key === key);
  if(img) openLightbox(img.url);
}

window.dlImg = dlImg;
window.openLightbox = openLightbox;
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
  if(S.cfg.txtApi === "gemini" || S.cfg.imgApi === "gemini") {
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
    const baseUrl = S.cfg.geminiUrl || "https://generativelanguage.googleapis.com";
    let body, url;
    if(S.cfg.geminiImageModel.includes('gemini')) {
      // Formato para modelos Gemini com suporte a imagem
      url = `${baseUrl}/v1beta/models/${S.cfg.geminiImageModel}:generateContent?key=${S.cfg.geminiKey}`;
      body = { 
        contents: [{ parts: [{ text: prompt }] }],
        generationConfig: { responseModalities: ["IMAGE"] }
      };
    } else {
      // Formato Imagen
      url = `${baseUrl}/v1beta/models/${S.cfg.geminiImageModel}:predict?key=${S.cfg.geminiKey}`;
      body = { instances: [{ prompt }], parameters: { sampleCount: 1 } };
    }
    
    const r = await fetch(url, {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    });
    const d = await r.json();
    if(!r.ok) throw new Error(d.error?.message || `HTTP ${r.status}`);
    
    if (d.predictions && d.predictions[0]?.bytesBase64Encoded) {
       return `data:image/png;base64,${d.predictions[0].bytesBase64Encoded}`;
    }
    if (d.candidates && d.candidates[0]?.content?.parts?.[0]?.inlineData) {
       const inline = d.candidates[0].content.parts[0].inlineData;
       return `data:${inline.mimeType};base64,${inline.data}`;
    }
    
    throw new Error("Resposta vazia da IA Gemini (Imagem).");
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

async function openAIImageEdit(prompt, imageUrl){
  const body = {
    model: S.cfg.imageModel,
    prompt,
    size: S.cfg.imgResolution || "1024x1024",
    n: 1,
    images: [{ image_url: imageUrl }]
  };
  if (S.cfg.imgQuality) {
     body.quality = S.cfg.imgQuality;
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
  if (S.cfg.imgQuality) {
     body.quality = S.cfg.imgQuality;
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
function escAttr(s){return String(s ?? "").replace(/'/g,"&#39;");}
function openLightbox(url) {
  const lb = document.getElementById('lightbox');
  document.getElementById('lightbox-img').src = url;
  lb.classList.add('show');
}
function closeLightbox(e, force=false) {
  if (force || e.target.id === 'lightbox') {
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
