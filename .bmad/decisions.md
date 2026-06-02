Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/decisions.md pelo conteúdo abaixo:

# Decisions — STLAI Vision Ads Pro

## Objetivo deste documento

Este documento registra decisões técnicas e de produto tomadas durante o desenvolvimento do plugin STLAI Vision Ads Pro.

Ele deve ser atualizado sempre que uma decisão relevante for tomada.

O objetivo é evitar perda de contexto entre sessões, impedir retrabalho e garantir que o Codex/Antigravity entenda por que o projeto segue determinada direção.

## Como usar este documento

Sempre que uma decisão importante for tomada, adicionar uma entrada seguindo o formato:

```txt
## YYYY-MM-DD — Título da decisão

### Decisão

Descrição objetiva da decisão.

### Motivo

Por que essa decisão foi tomada.

### Impacto

O que essa decisão muda na arquitetura, no código ou no produto.

### Status

Decidido / Pendente / Revisar depois

## 2026-05-19 - Campos administrativos de video e audio

### Decisao

Adicionar configuracoes administrativas para o MVP de video comercial e ElevenLabs dentro da pagina Config. de IA, salvando tudo em `stlai_vision_ads_pro_settings`.

Campos definidos para video comercial:

- `videoProvider`
- `videoModel`
- `videoApiKey`
- `videoBaseUrl`

Campos definidos para narracao / ElevenLabs:

- `audioProvider`
- `elevenLabsApiKey`
- `elevenLabsVoiceEmotional`
- `elevenLabsVoicePersuasive`
- `elevenLabsModel`
- `elevenLabsDefaultLanguage`

Campos reservados para UGC futuro:

- `ugcProvider`
- `seedanceApiKey`
- `seedanceBaseUrl`
- `seedanceModel`
- `muApiKey`
- `muApiBaseUrl`

### Motivo

A primeira story precisava preparar a configuracao segura dos providers antes de qualquer chamada a Veo, ElevenLabs, Seedance ou MuAPI.

### Impacto

O painel admin passa a armazenar as credenciais e modelos futuros. Nenhuma chave nova foi localizada para o frontend e nenhuma integracao externa foi criada nesta story.

### Status

Decidido

## 2026-05-24 - Erro final de clipe não domina operação Veo ativa

### Decisao

Enquanto qualquer clipe comercial tiver `operation_id` ativo, sem URL e em `generating`/`processing`, o estado público do job deve continuar como `generating_clips`.

`clip_generation_error` e mensagens como "após 3 tentativas" só podem aparecer quando não houver operação ativa em processamento e o clipe realmente estiver em `error_final` com `attempt >= max_attempts`.

Erros antigos preservados no job, como `failed_clip_index`, `retry_reason` ou `error_final_reason`, devem ser suprimidos da resposta pública enquanto operações Veo válidas ainda estiverem em andamento.

### Motivo

Com os 4 clipes paralelos, uma resposta antiga podia preservar `clip_generation_error` mesmo quando os 4 `operation_id` já estavam vivos e processando. Isso gerava falso erro visual sem falha real.

### Impacto

Backend, storage e frontend passam a normalizar status por precedência: vídeo final, composição, clipes prontos, operações ativas/scheduled/retrying, e somente depois erro final real.

Diagnostics passam a indicar `global_status_before_normalization`, `global_status_after_normalization`, `stale_error_suppressed`, `active_operations_suppress_error`, `final_error_allowed` e `final_error_blockers`.

### Status

Decidido

## 2026-05-24 - UX de vídeo e organização das Configurações de IA

### Decisao

A etapa de preparação de vídeo remove o botão público "Testar clipe IA"; o endpoint/função interna pode continuar existindo para desenvolvimento, mas não aparece para o usuário final.

O botão "Acompanhar resultado" passa a levar ao topo da página Resultado, não diretamente à seção de vídeo.

A seleção de imagens para vídeo foi padronizada para exatamente 4 imagens. A galeria continua exibindo até 8 imagens geradas, mas o vídeo usa 4 imagens, uma para cada clipe.

A página Configurações de IA foi reorganizada em blocos profissionais: Provedores de Texto, Provedores de Imagem, Inteligência de Mercado, Vídeo Comercial, Composição Final, Narração / ElevenLabs e UGC Futuro.

Configurações de vídeo comercial e provider de vídeo comercial ficam em um bloco único. Os campos de modelo e credenciais aparecem conforme o provider selecionado. Gemini Veo é marcado como ativo no MVP; Fal.ai, Atlas Cloud e MuAPI aparecem como futuros.

### Motivo

O botão de teste confundia o fluxo final, o atalho de resultado pulava o contexto geral do resultado, a seleção 4 a 8 não refletia o pipeline atual de 4 clipes, e o admin misturava campos de provider/modelo sem contexto.

### Impacto

As option keys existentes foram preservadas para compatibilidade. Novos campos futuros de provider podem ser salvos, mas não alteram o fluxo funcional atual. Providers não implementados ficam visualmente identificados como "em breve" ou custom dependente de contrato compatível.

### Status

Decidido

## 2026-05-22 - Timer visível e zoom sem asset quebrado

### Decisao

A etapa de composição final deve exibir o timer visual ao vivo em todos os cards públicos que mostram estados de composição, incluindo o painel de status do passo 5, o card animado e o resumo.

Botões de zoom/ampliar da galeria usam ícone inline/CSS, e o lightbox não depende de imagem/asset externo para representar o zoom.

### Motivo

O usuário via "Compondo vídeo final", mas o timer podia não aparecer quando apenas o painel de status estava visível. Na galeria, o lightbox podia exibir uma imagem quebrada com o texto alternativo "Zoom" quando a URL estava vazia ou inválida.

### Impacto

O frontend usa `renderCompositionTimer` como helper único para o bloco de tempo decorrido e atualiza o DOM por `data-stlai-composition-timer`. O lightbox agora esconde a imagem sem URL, limpa `src` ao fechar e mostra fallback elegante "Imagem indisponível" quando necessário.

### Status

Decidido

## 2026-05-22 - Timer de composição atualizado em tempo real

### Decisao

O timer visual de "Tempo decorrido" durante a composição final deve ser atualizado no frontend por um intervalo local de 1 segundo, sem depender do polling AJAX.

O frontend fixa um timestamp único por job/composição usando, nesta ordem, `composer_started_at`, `composer_elapsed_seconds` ou um timestamp local. O valor não é resetado a cada polling/re-render. O DOM do timer é atualizado diretamente por atributos `data-stlai-composition-timer` e `data-stlai-composition-timer-note`.

### Motivo

O timer podia travar em `00:01` porque a atualização dependia de re-render do card e do estado recebido por polling, causando resets visuais em vez de contagem contínua.

### Impacto

Durante `composition_queued`, `composition_processing` e `composition_waiting`, o usuário vê o timer avançar em tempo real. O intervalo é único e é limpo quando o vídeo final fica pronto, quando a composição completa ou quando ocorre erro final real.

### Status

Decidido

## 2026-05-22 - Identidade visual STLAI Seller

### Decisao

A identidade visual pública do plugin foi ajustada para o design system STLAI/STLFLIX.

Tokens principais aplicados: `#9B51E6` como primary, `#ECF5FE` como secondary, `#5166E6` como accent/link, `#000000` como background, `#FFFFFF` como texto principal e Inter como fonte.

O nome visual público da ferramenta foi atualizado de "Vision Ads" para "Seller", formando a marca de topo "STLAI Seller".

### Motivo

O produto público deve conversar com a identidade oficial STLFLIX/STLAI sem introduzir risco no pipeline antes da entrega.

### Impacto

A troca é visual. Slugs, classes, handles, opções de banco, nomes de pasta e nomes técnicos internos continuam iguais para compatibilidade.

### Status

Decidido

## 2026-05-22 - Clipes IA com stagger paralelo de 1 segundo

### Decisao

A geração comercial dos 4 clipes passa a usar `MAX_CONCURRENT_CLIP_GENERATIONS = 4` e `CLIP_START_STAGGER_SECONDS = 1`.

Após a narração estar pronta, o job registra os quatro clipes com `scheduled_start_at`: clipe 1 imediatamente, clipe 2 após 1s, clipe 3 após 2s e clipe 4 após 3s. O frontend dispara os requests nessa cadência, e cada clipe mantém `clip_job` independente.

Cada clipe preserva `operation_id`, tentativas, retry individual e merge monotônico. Clipe pronto nunca é regenerado. A composição final só inicia quando os 4 clipes tiverem URL.

### Motivo

Gerar um clipe por vez deixava o fluxo lento. O Veo trabalha com operações assíncronas por clipe; iniciar as quatro operações com stagger reduz o tempo total sem concentrar todos os requests exatamente no mesmo segundo.

### Impacto

Diagnostics passam a expor `max_concurrent_clip_generations`, `clip_start_stagger_seconds`, `clip_generation_mode`, `scheduled_clip_indexes`, `started_clip_indexes`, `started_clip_indexes_this_tick`, `next_clip_indexes`, `active_generating_count`, `clips_ready_count` e `missing_clips`.

O frontend mantém timers por índice (`clipLaunchTimersByIndex`) para que um novo polling não cancele os envios já planejados dos clipes 2, 3 e 4.

### Status

Decidido

## 2026-05-24 - Operações Veo assíncronas e keepalive do renderer

### Decisao

Os 4 clipes comerciais Veo continuam com `MAX_CONCURRENT_CLIP_GENERATIONS = 4` e `CLIP_START_STAGGER_SECONDS = 1`, mas o caminho principal passa a separar o início da operação do polling.

O provider Veo expõe `start_clip_operation`, que cria a operação e retorna `operation_id` rapidamente, e `poll_clip_operation`, que consulta a operação existente até retornar `processing`, `ready` ou erro controlado. O job salva `operation_id` por clipe e não cria uma nova operação para o mesmo índice enquanto houver operação ativa.

Tentativas contam operações iniciadas, não polls. `VEO_OPERATION_PROCESSING` mantém o clipe em `generating` e não consome nova tentativa.

A composição final continua usando o renderer externo atual no Render, sem alteração no contrato de `/render`.

O renderer Node ganhou `GET /ping`, sem autenticação, para permitir keepalive externo temporário no Render Free.

### Motivo

Polls bloqueantes dentro da criação do clipe faziam os clipes parecerem sequenciais. Separar criação e polling permite iniciar as quatro operações quase juntas e acompanhar cada uma de forma independente.

Render Free pode dormir após inatividade; o `/ping` permite usar cron-job.org para reduzir cold start sem executar FFmpeg.

### Impacto

O AJAX passa a expor `polling_operation_indexes` junto de `scheduled_clip_indexes`, `started_clip_indexes_this_tick`, `operation_id_exists`, `operation_elapsed_seconds` e `attempt_counts_operations_not_polls`.

O frontend continua refletindo o estado por card: `scheduled` como "Agendado", `generating` como "Gerando clipe X" e processamentos longos como "Ainda processando clipe X".

### Status

Decidido

## 2026-05-24 - Paralelismo 16:9 e prompt de produto imutável

### Decisao

O fluxo de clipes comerciais não tem regra diferente por formato: `9:16` e `16:9` usam `MAX_CONCURRENT_CLIP_GENERATIONS = 4` e `CLIP_START_STAGGER_SECONDS = 1`.

Não deve existir limite especial de 2 clipes para `16:9`, chunk em pares ou dependência de clipe anterior ficar pronto para iniciar o próximo.

O prompt do Veo passa a tratar o produto como item sagrado: forma, material, cor, tamanho, textura, detalhes, rosto, cabelo, roupa, pose, base, suporte, acessórios e identidade não podem mudar. Produto não pode ser redesenhado, estilizado, substituído, deformado, transformado ou receber partes/props novos.

O vídeo deve animar a cena inteira de forma sutil e realista: câmera, parallax, luz ambiente, reflexos, flores, tecidos, velas e pessoas ao fundo podem ter micro movimento somente se já existirem na imagem. O produto permanece estável, nítido e idêntico.

### Motivo

Alguns clipes 16:9 pareciam sair em dois lotes e alguns prompts ainda permitiam alteração visual do produto ou animação restrita demais ao objeto. O resultado comercial precisa ser vivo sem sacrificar fidelidade.

### Impacto

Diagnostics passam a expor `aspect_ratio` no job e em `clip_jobs_summary`. O teste 16:9 deve permitir confirmar 4 `operation_id` ativos por `polling_operation_indexes` e `clip_jobs_summary[].operation_id_exists`.

### Status

Decidido

## 2026-05-22 - Timer de composição e bônus de score do vídeo final

### Decisao

A etapa de composição final exibe um timer visual no frontend durante `composition_queued`, `composition_processing`, `composition_waiting` e estados equivalentes de composição.

O timer usa `composer_started_at`/`composer_elapsed_seconds` quando vierem do backend; se esses campos não existirem, o frontend cria um timestamp local para a composição atual.

O score do anúncio recebe um bônus único de 10 pontos quando o vídeo final é gerado com sucesso (`final_video_url`, `status=ready` ou `composition_status=complete`).

### Motivo

Renders externos podem levar alguns minutos, especialmente em infraestrutura free/cold start. O timer reduz incerteza para o usuário. O score deve refletir que um anúncio com vídeo final pronto está mais completo do que um anúncio apenas com imagens/texto.

### Impacto

O frontend mantém diagnostics locais seguros: `composition_elapsed_ui_seconds`, `composition_timer_active`, `final_video_score_bonus_applied`, `final_video_score_bonus_value`, `score_before_video_bonus` e `score_after_video_bonus`.

### Status

Decidido

## 2026-05-22 - Video Provider Hub e resolução comercial

### Decisao

O admin passa a separar a configuração de vídeo comercial em um hub de provider: `commercialVideoProvider`, `commercialVideoModel`, `commercialVideoOutputResolution`, `commercialVideoCustomEndpoint`, `commercialVideoCustomApiKey` e `commercialVideoCustomModel`.

O provider padrão do MVP é `gemini_veo`, modelo `veo-3.1-lite-generate-preview`, resolução `720p`. Valores inválidos voltam para defaults seguros. Fal.ai, Atlas Cloud, MuAPI e Custom podem ser salvos, mas ainda retornam erro controlado `VIDEO_PROVIDER_NOT_IMPLEMENTED` quando usados.

Os prompts dos clipes comerciais devem ser realistas e limpos: sem glitter, partículas roxas, brilho mágico, overlays, texto, watermark, logo, REC, HUD, interface de câmera, lens dirt, partículas decorativas, fumaça, neon, color cast, purple tint, lens flare exagerado ou mudança de identidade/função do produto.

### Motivo

O pipeline já funciona, mas precisava de controle de custo/qualidade e de uma camada preparada para outros providers sem quebrar o Gemini Veo atual.

### Impacto

Jobs de vídeo passam a registrar `video_provider`, `video_model`, `output_resolution`, `requested_resolution`, `effective_resolution` e `resolution_fallback_reason`. Diagnostics AJAX expõem apenas esses campos seguros, nunca API keys.

### Status

Decidido

## 2026-05-22 - Operações Veo longas não consomem tentativas

### Decisao

`VEO_OPERATION_PROCESSING` e o antigo `VEO_OPERATION_TIMEOUT` passam a significar operação viva em processamento, não falha final da tentativa. O `operation_id` do Veo deve ficar persistido em `clip_jobs[index].operation_id` e as próximas consultas devem reutilizar essa operação.

Tentativas de clipe contam operações iniciadas, não polls internos. Consultar a mesma operação 10, 20 ou 30 vezes continua sendo a mesma tentativa.

Timeouts por resolução:

- 720p: soft 180s, hard 600s.
- 1080p: soft 300s, hard 900s.

Soft timeout apenas muda a mensagem para "ainda em processamento"; hard timeout permite encerrar a operação e iniciar nova tentativa, respeitando o limite de 3 operações.

### Motivo

Veo em 1080p e modelos fast pode demorar mais que 10 polls internos. Tratar isso como erro consumia as 3 tentativas e gerava `error_final` mesmo com a operação ainda viva.

### Impacto

`clip_jobs_summary` expõe diagnostics seguros de operação: `operation_id_exists`, `operation_id`, `operation_poll_count`, `operation_elapsed_seconds`, `clip_operation_soft_timeout_seconds`, `clip_operation_hard_timeout_seconds`, `operation_still_processing` e `attempt_counts_operations_not_polls`.

### Status

Decidido

## 2026-05-22 - Geração paralela controlada dos combos 2x2 de imagem

### Decisao

A galeria de 8 imagens comerciais não deve ser tratada como 8 requests independentes. A arquitetura real gera combos 2x2 e recorta cada combo em 4 imagens individuais no frontend.

O fluxo passa a disparar os combos 2x2 com concorrência controlada:

- `MAX_CONCURRENT_IMAGE_COMBO_GENERATIONS = 2`.
- Combo 1 gera imagens 1 a 4.
- Combo 2 gera imagens 5 a 8.
- No MVP, os dois combos podem começar juntos.
- Assim que um combo termina, ele é recortado em 4 partes e essas imagens aparecem no grid sem esperar o outro combo.

Cada combo tem estado próprio: `pending`, `generating`, `ready`, `cropping`, `cropped`, `retrying` e `error_final`, com até 3 tentativas.

### Motivo

O fluxo sequencial esperava o combo 1 terminar e ser recortado antes de iniciar o combo 2, aumentando o tempo total de geração premium. Como são apenas 2 requests reais, faz sentido rodá-los juntos com limite explícito.

### Impacto

O frontend mantém `imageComboJobs` e `imageDiagnostics` com resumo dos combos, imagens prontas e concorrência. Imagens já renderizadas são preservadas por `key/index` e não voltam para loading quando outro combo atualiza.

### Status

Decidido

## 2026-05-21 - Estado monotônico de vídeo e clipes

### Decisao

O estado de vídeo/clipes é monotônico. Um clipe que chegou a `ready` com `url` nunca pode voltar para `pending`, `queued`, `generating`, `retrying` ou desaparecer da UI.

O backend deve fazer merge seguro de updates parciais antes de salvar o job: `clips` e `clip_jobs` são mesclados por `index`, respostas parciais não removem itens prontos, e `ready + url` tem prioridade máxima. O status geral deve ser derivado do estado real: vídeo final pronto, composição em andamento, 4 clipes prontos, clipes parciais, áudio pronto e narração.

O frontend também deve fazer merge, não replace bruto. Respostas AJAX antigas podem acrescentar URLs novas, mas não podem reduzir progresso, apagar clipes, apagar `final_video_url` ou voltar o status visual para uma fase mais antiga.

### Motivo

Com geração semi-paralela, polling e múltiplos `admin-ajax`, respostas podem chegar fora de ordem. Sem merge monotônico, um response antigo pode sobrescrever um job mais novo e fazer players prontos sumirem.

### Impacto

O contrato público passa a expor `job_version`, `updated_at`, `clips_ready_count` e `missing_clips`. A composição final é tratada como idempotente: se já existe `render_job_id` ou composição em fila/processamento, não se inicia uma segunda composição.

### Status

Decidido

## 2026-05-21 - Composição final não pode ficar em fila indefinidamente

### Decisao

Depois que os 4 clipes e a narração existem, a composição externa deve iniciar, salvar `render_job_id` quando assíncrona e ser acompanhada por `GET /render/:render_job_id` até `ready` ou `error`.

O plugin deve aceitar dois contratos do renderer:

- síncrono: `success=true`, `status=ready`, `final_video_url`;
- assíncrono: `success=true`, `status=queued|processing`, `render_job_id`.

`composition_queued` sem `render_job_id` por mais de 90 segundos vira `composition_error` recuperável. `composition_processing` acima de `videoComposerTimeout` também vira `composition_error`. Erros temporários ao consultar status não apagam `render_job_id`, clipes ou áudio antes do timeout.

Se um job antigo estiver preso em `composition_queued` sem `render_job_id`, mas ainda tiver `audio_url` e 4 clipes prontos, o próximo polling deve recuperar automaticamente o fluxo chamando `POST /render`, sem exigir clique e sem regenerar áudio/clipes.

O endpoint do renderer pode ser configurado como base do serviço ou como `/render`; o plugin normaliza ambos para o contrato correto. Configurar `/health` é erro claro `COMPOSER_ENDPOINT_INVALID`.

### Motivo

No teste real, os clipes ficavam prontos, mas o usuário permanecia por muitos minutos em "Vídeo na fila de composição". Esse estado precisa ter saída clara: pronto, processando, erro recuperável ou timeout.

### Impacto

O botão "Tentar novamente" deve retomar apenas a composição quando áudio e 4 clipes já existem. Logs seguros e campos de diagnóstico AJAX passam a registrar início, aceite, status, host do endpoint, polling, timeout e resultado da composição sem vazar API key.

### Status

Decidido

## 2026-05-21 - Diagnóstico obrigatório e retry terminal de clipes

### Decisao

Toda resposta AJAX de vídeo deve trazer `diagnostics` seguro com `job_id`, status, presença de áudio, quantidade de clipes prontos, clipes faltantes, status de composição, `render_job_id`, existência de vídeo final, modo/host/path do composer, tempo decorrido, contador de polling, último erro e `composition_start_blocker`.

A decisão de composição fica centralizada em `maybe_start_or_poll_composition`: se existe `final_video_url`, o job é `ready`; se falta áudio ou clipe, não compõe e informa blocker; se existem áudio + 4 clipes e não existe `render_job_id`, chama `POST /render`; se existe `render_job_id`, consulta `GET /render/:id`.

Retries automáticos de clipe têm fim explícito. Após 3 tentativas, o clipe fica `error_final` e não volta para `retrying`, `generating` ou `pending` até ação do usuário. "Tentar novamente" reseta apenas clipes `error_final`/faltantes ou, se os 4 clipes já existem, tenta apenas a composição.

### Motivo

O teste real mostrou dois sintomas perigosos: loop visual de retry em clipe e polling infinito em `composition_queued`. Ambos precisam de estados terminais claros e diagnóstico direto no DevTools.

### Impacto

O frontend consegue exibir "Erro após tentativas" sem motion infinito, e o DevTools mostra exatamente por que a composição não iniciou: falta áudio, faltam clipes, endpoint ausente, já há `render_job_id` para polling ou erro/timeout de composição.

### Status

Decidido

## 2026-05-21 - Clipes prontos são reconstruídos por URL em qualquer fonte do job

### Decisao

O estado real de clipes deve ser derivado por index a partir de `clips`, `partial_clips` e `clip_jobs`. Se qualquer uma dessas fontes tiver URL válida para um index, esse clipe é considerado `ready` e deve entrar no array `clips` persistido.

`clips_ready_count` e `missing_clips` devem ser recalculados sempre pelo estado normalizado. Clipes podem chegar fora de ordem e o merge deve preservar todos os anteriores.

`clip_jobs` sem URL em `generating` ou `retrying` por mais de 120 segundos são considerados stale. Se ainda há tentativa disponível, voltam para `pending` para nova tentativa automática; se já atingiram 3 tentativas, viram `error_final`.

Quando `videoComposerMode` estiver vazio, mas o endpoint externo estiver configurado, o modo público/diagnóstico deve cair para `external_service`.

### Motivo

O diagnóstico real mostrou `clips_ready_count=1` e `missing_clips=[1,2,3]`, embora a UI/Network indicasse que clipes haviam chegado. Isso apontou perda de persistência/merge entre arrays parciais e `clip_jobs`.

### Impacto

Um clipe com URL não some mais do storage por resposta parcial. Jobs presos em `generating` deixam de bloquear a composição indefinidamente. Assim que os 4 indexes tiverem URL, a composição externa pode iniciar.

### Status

Decidido

## 2026-05-21 - Polling de status executa próximo clipe pendente

### Decisao

Quando o job está em `generating_clips`, possui `audio_url`, tem menos de 4 clipes prontos e existe clipe `pending`, `queued` ou `retrying`, o endpoint de polling deve executar automaticamente o próximo clipe necessário.

A ordem é sempre o menor index faltante/processável. Antes de gerar, o backend relê o job, confirma que o clipe não ficou `ready` em outra chamada e respeita lock simples por status: `generating/retrying` com menos de 120s não é duplicado. `generating/retrying` stale volta ao fluxo de retry. `error_final` bloqueia novas tentativas automáticas até o usuário clicar em "Tentar novamente".

### Motivo

O diagnóstico mostrou `next_clip_action=generate_missing_clip`, mas o polling apenas retornava estado. Isso mantinha `missing_clips` indefinidamente mesmo quando o backend sabia qual ação tomar.

### Impacto

O pipeline volta a andar sozinho: polling gera clipe 2, depois 3, depois 4, salva cada URL e inicia composição ao completar 4 clipes.

### Status

Decidido

## 2026-05-21 - Reconciliação de clipes prontos entre frontend e backend

### Decisao

O backend continua sendo a fonte da verdade para iniciar composição, mas o polling pode receber `client_ready_clips` do frontend para recuperar clipes que já apareceram na UI e foram perdidos por race condition no storage.

Cada clipe recebido do cliente só é aceito se tiver `index` entre 1 e 4 e URL pertencente ao diretório de uploads do próprio WordPress, com extensão de vídeo esperada. URLs externas arbitrárias são descartadas.

Ao aceitar um clipe, o backend salva imediatamente por `index` em `clips`, `partial_clips` e `clip_jobs[index]` como `ready`, recalcula `clips_ready_count` e `missing_clips`, incrementa `job_version` e, se os 4 clipes + áudio existirem, inicia a composição.

### Motivo

O diagnóstico real mostrou divergência: a UI preservava 4 players prontos, mas o transient do WordPress retornava apenas um clipe e bloqueava a composição com `missing_clips`. A reconciliação evita que respostas AJAX parciais ou fora de ordem deixem o backend permanentemente atrasado.

### Impacto

As respostas AJAX passam a expor diagnóstico de reconciliação: `backend_clips_ready_count`, `client_ready_clips_received`, `client_ready_clips_accepted`, `reconciled_clips_ready_count`, `reconciled_missing_clips` e `reconciliation_used`.

### Status

Decidido

## 2026-05-21 - VEO_INVALID_RESPONSE sem URI de vídeo é retryable

### Decisao

Erros do Veo em que a operação conclui mas não traz URI de vídeo (`VEO_INVALID_RESPONSE`, "URI do vídeo ausente", `generatedVideos` vazio, "operation completed without video") são temporários até prova contrária.

Enquanto `attempt < 3`, o clipe deve ficar em `retrying`/fluxo automático, com `will_retry=true`, `retryable=true` e `retry_reason=veo_completed_without_video_uri`. Só após a terceira tentativa real falhada o clipe vira `error_final`.

### Motivo

No teste real, o Veo retornou operação concluída sem URI e o plugin marcou `error_final` na primeira tentativa, mesmo com `max_attempts=3`. Esse tipo de resposta pode ser intermitente e precisa consumir as tentativas automáticas antes de pedir ação do usuário.

### Impacto

O frontend não mostra erro final nem botão "Tentar novamente" enquanto houver retry automático. Os outros clipes podem continuar gerando em paralelo/semi-paralelo, e o diagnóstico AJAX passa a expor `retryable`, `retry_reason`, `will_retry`, `max_clip_attempts` e `error_final_reason`.

### Status

Decidido

## 2026-05-21 - Pending antigo de clipe também é stale

### Decisao

Qualquer `clip_job` sem URL em `pending`, `retrying` ou `generating` com `started_at` antigo deve ser tratado como stale após 75 segundos.

Um clipe `pending` com erro como "Tentativa anterior ficou sem resposta" é retryable enquanto `attempt < 3`. Ele não pode ficar passivo indefinidamente; o polling deve escolher esse clipe como próximo, marcar `next_clip_reason=pending_stale_retry` ou `pending_retryable_error` e disparar nova geração automática.

`active_generating_count` conta apenas clipes `generating` sem URL e com idade menor que o threshold. `pending`, `retrying`, stale, `ready` e `error_final` não bloqueiam o próximo processamento.

### Motivo

No teste real, clipes ficaram em `pending`, `attempt=1`, `started_at` antigo e sem URL, mas `is_stale=false`. Isso deixou `missing_clips` preso e impediu a composição final.

### Impacto

Jobs com clipes antigos pendentes voltam a andar sozinhos: o próximo polling reprocessa o menor índice faltante, preserva clipes já prontos por merge monotônico e inicia composição quando os 4 clipes estiverem `ready`.

### Status

Decidido

## 2026-05-21 - Clipes semi-paralelos, URLs limpas, narração sem tags e música opcional

### Decisao

Ao iniciar o vídeo, o WordPress cria os 4 `clip_jobs` e o frontend dispara a geração dos 4 clipes em requisições AJAX independentes, com 1 segundo de diferença entre cada início. O polling acompanha estado, clipes prontos e composição final; ele não deve iniciar clipes implicitamente.

URLs de mídia recebidas do renderer devem ser normalizadas antes de salvar/usar, removendo barras escapadas como `\/`.

Nenhuma marcação de emoção entre colchetes deve ser enviada para o ElevenLabs. A emoção da narração deve vir de texto natural em português, pontuação, ritmo e frases humanas, sem tags como `thoughtful`, `warmly` ou `short pause`.

Vídeos comerciais não podem ter REC, HUD, overlays, glitter, partículas, confete, texto, watermark, sujeira de lente, film grain artificial ou efeitos mágicos. O prompt do Veo deve negar esses elementos explicitamente.

Música de fundo é responsabilidade do renderer, opcional e controlada por env: `ENABLE_BACKGROUND_MUSIC`, `BACKGROUND_MUSIC_URL` e `BACKGROUND_MUSIC_VOLUME`. Se a música falhar, o render continua com voz pura.

O admin passa a ter `imageQuality` com opções `auto`, `high`, `medium`, `low`; quando suportado pelo provider de imagem, esse valor é enviado na geração.

### Motivo

Gerar 4 clipes em sequência alonga muito o tempo total. As tags emocionais estavam sendo faladas pela voz. URLs escapadas impediam o player de carregar o vídeo final. Música de fundo precisa ser mixada no FFmpeg, não no TTS.

### Impacto

O pipeline fica semi-paralelo no navegador, cada clipe aparece conforme termina, composição começa apenas com 4 clipes prontos, e o renderer pode mixar música em volume baixo sem quebrar o fluxo.

### Status

Decidido

## 2026-05-19 - Video mock exibido no resumo

### Decisao

Exibir no passo 6 um bloco de video que reflete o estado mock preparado no passo 5, sem exigir video real e sem mostrar nomes tecnicos de providers.

### Motivo

O usuario precisa enxergar no resumo que o pipeline de video ja foi preparado, incluindo formato, narracao e previa do roteiro, mesmo antes da integracao real com APIs externas.

### Impacto

O resumo passa a mostrar estado pendente quando o video ainda nao foi preparado e estado "Video preparado" quando `S.video.status` e `ready`. A mudanca usa apenas estado frontend existente e nao altera backend, admin ou integracoes reais.

### Status

Decidido

## 2026-05-19 - Backend mock inicial de video

### Decisao

Criar a primeira estrutura backend de video em `includes/video/` com storage, service e controller AJAX mock, sem integrar providers externos.

### Motivo

Antes de chamar APIs reais, o plugin precisa validar o contrato frontend/backend do passo 5, incluindo payload, status, polling e estados de erro, mantendo as chaves de API fora do frontend.

### Impacto

O plugin passa a ter endpoints AJAX para criar job mock, consultar status e buscar resultado mock. Os jobs sao armazenados temporariamente em transients e validam imagens selecionadas, narracao, formato e roteiro. O frontend deixa de usar apenas mock local e passa a testar o fluxo real com `admin-ajax.php`, sem chamar APIs externas.

### Status

Decidido

## 2026-05-19 - Roteiro editavel e labels publicos no passo 5

### Decisao

Manter nomes tecnicos de providers fora da interface publica do wizard e adicionar um roteiro editavel de narracao no passo 5.

### Motivo

O usuario final deve ver uma experiencia comercial simples e profissional, sem exposicao de nomes tecnicos como providers/modelos. O roteiro editavel permite revisar a copy antes da futura geracao real de audio e video.

### Impacto

O passo 5 passa a mostrar "Geracao IA Premium" e "Voz profissional IA" na interface publica. O frontend gera um roteiro local baseado em produto, descricao, caracteristicas, titulos e estilo de narracao, preservando edicoes manuais no estado `S.video`. Nenhuma API ou backend foi acionado.

### Status

Decidido

## 2026-05-19 - Passo 5 como preparacao visual de video

### Decisao

Transformar o passo 5 em uma tela operacional de preparacao de video, mantendo a geracao real como mock ate a criacao da integracao com APIs.

### Motivo

O wizard precisava deixar de apresentar uma area bloqueada com blur e passar a mostrar de forma clara quais imagens, narracao, formato e pipeline serao usados na futura geracao comercial.

### Impacto

O frontend passa a ter o estado `S.video` com `status`, `format` e `mockReady`, alem de funcoes para selecionar formato e simular a preparacao do pipeline. A mudanca nao chama Veo, ElevenLabs, backend ou AJAX, e nao exige video gerado para acessar o resumo.

### Status

Decidido

## 2026-05-19 - Regra de selecao de imagens para video comercial

### Decisao

Alterar a selecao de imagens do passo 4 para exigir minimo de 4 e maximo de 8 imagens para o video comercial.

### Motivo

O pipeline futuro do MVP comercial usara 4 clipes de 8 segundos e precisa de um conjunto maior de imagens selecionadas pelo usuario para alimentar a composicao do video.

### Impacto

O frontend passa a bloquear o avanco para o passo 5 quando houver menos de 4 imagens selecionadas e bloqueia novas selecoes acima de 8 imagens. A mudanca nao ativa geracao real de video nem chama APIs externas.

### Status

Decidido

## 2026-05-19 - Escolha de estilo de narracao no passo 2

### Decisao

Adicionar a escolha de narracao do video diretamente no passo 2 do wizard, com duas opcoes iniciais:

- `persuasiva`
- `emocional`

O valor padrao do estado frontend passa a ser:

```js
voiceStyle: "persuasiva"
```

### Motivo

O pipeline de video comercial precisa receber o tipo de narracao escolhido pelo usuario antes da geracao futura de audio com ElevenLabs.

### Impacto

O frontend passa a coletar a preferencia de narracao em `colForm()`, mas a informacao ainda nao chama nenhuma API e nao altera a geracao de video, audio, texto ou imagem.

### Status

Decidido

## 2026-05-19 - ElevenLabs como primeira integracao real do pipeline de video

### Decisao

Integrar primeiro a geracao real de audio com ElevenLabs no backend PHP, usando o roteiro editavel do passo 5, antes de integrar Veo ou composicao final de video.

### Motivo

A narracao e um ativo independente, mais simples de validar e essencial para definir a duracao futura do video final. Manter a chamada no backend evita expor a API key e permite salvar o arquivo em `uploads/stlai-vision-audio/`.

### Impacto

Foi criado `STLAI_ElevenLabs_Provider`, carregado pelo arquivo principal do plugin. O service de video passa a validar o payload, gerar audio real via ElevenLabs, salvar `audio_url` no job e continuar mockando o restante do pipeline. O frontend exibe um player de audio no passo 5 e no resumo quando a narracao existe. Veo, Seedance, MuAPI, OpenAI e Gemini nao sao chamados para video nesta etapa.

### Status

Decidido

## 2026-05-19 - Erros estruturados para ElevenLabs

### Decisao

Retornar erros de narracao como objeto estruturado com `message`, `code` e `debug` seguro no AJAX.

### Motivo

O retorno anterior usava string simples e escondia o motivo real de falhas HTTP da ElevenLabs, dificultando o diagnostico na aba Network.

### Impacto

O provider passa a mapear erros de configuracao, payload, conexao, HTTP, resposta invalida e salvamento de audio. O AJAX propaga o objeto estruturado sem expor API key ou headers sensiveis, e o frontend registra o detalhe seguro no console.

### Status

Decidido

## 2026-05-19 - Teste real isolado do Veo 3.1 Lite

### Decisao

Criar um endpoint AJAX separado, `stlai_generate_test_veo_clip`, para validar o provider Veo 3.1 Lite com apenas 1 clipe de teste a partir da primeira imagem selecionada para video.

### Motivo

Antes de gerar 4 clipes finais e compor o video narrado, era necessario validar API key, base URL, payload REST, operacao assíncrona, polling, retorno do video, salvamento em uploads e exibicao segura no passo 5.

### Impacto

Foi criado `STLAI_Veo_Provider`, carregado pelo arquivo principal do plugin. O provider usa `videoApiKey`, `videoModel` e `videoBaseUrl` do admin, envia a imagem como `image.inlineData`, faz polling backend curto e salva o MP4 em `uploads/stlai-vision-video/`. O frontend ganhou um botao temporario "Testar clipe IA" e um player separado para `test_clip_url`. O fluxo principal continua usando audio ElevenLabs + mock, sem 4 clipes, sem Seedance, sem MuAPI e sem composicao final.

### Status

Decidido

## 2026-05-19 - Payload de imagem do Veo Lite sem inlineData

### Decisao

Usar `image.bytesBase64Encoded` e `image.mimeType` no payload REST do teste Veo 3.1 Lite, removendo completamente `inlineData` e `inline_data`.

### Motivo

O modelo `veo-3.1-lite-generate-preview` retornou HTTP 400 informando que `inlineData` nao e suportado nesse payload.

### Impacto

O provider continua baixando/converterndo a primeira imagem selecionada para base64 puro no backend, mas agora envia a imagem no formato aceito pela story. O debug seguro passou a informar modelo, aspect ratio, MIME detectado e `imagePayload=image.bytesBase64Encoded`, sem incluir API key, headers ou base64.

### Status

Decidido

## 2026-05-19 - Formatos MVP e áudio final do vídeo comercial

### Decisao

O MVP de video comercial usara apenas `9:16` e `16:9`. A opcao `1:1` foi removida do passo 5 e qualquer estado antigo `1:1` deve cair para `9:16`.

O áudio nativo dos clipes Veo não será usado no vídeo final; a composição final usará apenas ElevenLabs.

### Motivo

O formato quadrado nao sera usado no MVP de video. Nos testes, o Veo tambem pode gerar interpretacoes funcionais e/ou audio nativo indesejado, enquanto o pipeline oficial depende de clipes visuais comerciais e narracao controlada via ElevenLabs.

### Impacto

O frontend mostra somente os formatos vertical e horizontal. O provider Veo faz fallback seguro para `9:16` se receber `1:1`. O prompt do clipe de teste foi reforcado para tratar o produto como chaveiro decorativo impresso em 3D, sem inventar funcao de abridor, ferramenta ou brinquedo, e pede clipe visual silencioso sem fala, musica, voz ou efeitos sonoros.

### Status

Decidido

## 2026-05-19 - Frame preparado e clipes Veo tratados como visual-only

### Decisao

Antes de chamar o Veo, a imagem selecionada deve ser convertida no backend para um frame no aspect ratio final (`9:16` ou `16:9`) e salva em `uploads/stlai-vision-video/frames/`.

O clipe Veo deve ser tratado como visual-only. Se FFmpeg estiver disponivel, o audio nativo deve ser removido com `-c:v copy -an`; se nao estiver, o teste continua e o player frontend permanece muted.

### Motivo

O teste mostrou que enviar uma imagem base quadrada ou em proporcao inadequada fazia o video comecar em formato errado antes de se adaptar. O teste tambem mostrou audio/musica nativa indesejada, enquanto o pipeline oficial usara somente narracao ElevenLabs.

### Impacto

O provider passa a depender de Imagick ou GD para preparar o frame antes do envio. Se nenhum estiver disponivel, retorna `IMAGE_PREPROCESSOR_UNAVAILABLE`. O debug seguro inclui URL/dimensoes do frame preparado e informa se FFmpeg removeu o audio ou se o player deve permanecer muted. Nenhuma nova API foi integrada e a composicao final continua fora do escopo.

### Status

Decidido

## 2026-05-19 - Quatro clipes Veo antes da composição final

### Decisao

O botão "Gerar vídeo" passa a gerar a narração ElevenLabs e, em seguida, 4 clipes visuais Veo sequenciais de 8 segundos, salvando os resultados no job em `clips`.

Os quatro papéis definidos são:

- apresentação geral
- uso/contexto
- detalhe/acabamento
- hero/fechamento

### Motivo

A geração de 1 clipe real já validou API key, payload, polling, salvamento e preview. A próxima etapa incremental é validar o conjunto visual completo de 4 clipes antes de implementar a composição final com áudio.

### Impacto

O backend passa a chamar o Veo 4 vezes em sequência dentro do job de vídeo. Os clipes são salvos em `uploads/stlai-vision-video/`, retornados ao frontend e exibidos no passo 5 como players muted. O status final é `ready_for_composition`; a composição final, fade, download do vídeo composto e inserção da narração no vídeo continuam fora desta story.

### Status

Decidido

## 2026-05-19 - Clipes Veo como tomada unica da imagem selecionada

### Decisao

Os 4 clipes Veo devem tratar a imagem selecionada como a cena inteira e a referencia visual principal. Cada clipe deve ser uma tomada unica continua de 8 segundos, sem troca de cena, cortes, fades internos, transicoes, montagem, before/after, novo local, novo produto ou transformacao do objeto.

O roteiro/copy deve ser usado apenas como contexto comercial, nunca como comando visual literal. A referencia visual da imagem tem prioridade sobre qualquer texto.

### Motivo

Nos testes da Story 7B, alguns clipes inventaram produtos ou funcoes, transformaram o chaveiro em outro objeto e trocaram de cena dentro dos 8 segundos. O fade/transicao deve acontecer apenas na composicao final entre clipes, nao dentro de cada clipe gerado.

### Impacto

O prompt do provider Veo foi reforcado para bloquear mudanca de cena interna, novos objetos, nova funcao e transformacao do produto. As direcoes dos quatro papéis continuam existindo, mas agora variam apenas o movimento de camera de forma sutil: zoom lento, drift leve, parallax suave e produto majoritariamente parado.

### Status

Decidido

## 2026-05-19 - Composicao final com FFmpeg e narracao ElevenLabs

### Decisao

O pipeline de video passa a compor automaticamente o video final depois que a narracao ElevenLabs e os 4 clipes Veo estiverem prontos.

A composicao final usa FFmpeg para:

- ordenar os clipes 1, 2, 3 e 4;
- repetir a sequencia quando a narracao for maior que a duracao base dos clipes;
- cortar o excedente para terminar junto com a narracao;
- aplicar fade curto apenas entre clipes;
- ignorar qualquer audio nativo dos clipes;
- usar somente a narracao ElevenLabs como trilha de audio.

### Motivo

O MVP precisa entregar um video final narrado e visualizavel no passo 5 e no resumo, mantendo os clipes individuais disponiveis para diagnostico e evolucao futura.

### Impacto

O job passa por `composing_final_video` e finaliza em `ready` quando o arquivo composto e salvo em `uploads/stlai-vision-video/`. O frontend passa a exibir o video final no passo 5 e no passo 6, alem dos 4 clipes individuais muted.

### Status

Decidido

## 2026-05-19 - Preservar apresentacao e suporte do produto nos prompts Veo

### Decisao

Os prompts Veo devem preservar nao apenas o produto, mas tambem sua apresentacao exata na imagem de referencia: suporte, base, gancho, superficie, ponto de fixacao e posicao de exibicao.

O modelo nao deve mostrar mao retirando, levantando, puxando, pendurando, colocando, encaixando ou transformando o uso do produto, salvo se essa interacao ja estiver claramente presente na imagem fonte.

### Motivo

Um dos clipes ainda podia alucinar uso indevido ou alterar a interpretacao do suporte/base do produto, especialmente no clipe de fechamento.

### Impacto

O prompt fica mais restritivo para reduzir acao indevida dentro dos 8 segundos e manter o produto ancorado exatamente como aparece na referencia.

### Status

Decidido

## 2026-05-19 - Resumo deve refletir composicao em andamento

### Decisao

O passo 6 deve mostrar o estado real do job de video mesmo quando o video final ainda nao estiver pronto, incluindo geracao de narracao, geracao dos clipes e `composing_final_video`.

### Motivo

Durante a composicao final, o resumo nao pode parecer um estado vazio ou pendente generico se a narracao e os clipes ja existem. O usuario precisa entender que o video final esta sendo preparado.

### Impacto

O frontend passa a renderizar "Compondo video final...", "Gerando narracao..." ou "Gerando clipe X de 4..." conforme `S.video.status`, mantendo a exibicao dos clipes individuais quando existirem e exibindo o video final assim que `finalVideoUrl` estiver disponivel.

### Status

Decidido

## 2026-05-19 - Falha parcial de clipes deve preservar progresso

### Decisao

Quando a geracao de um dos 4 clipes Veo falhar, o job deve ficar em `clips_partial_error`, preservar os clipes ja gerados e retornar erro estruturado com:

- `failed_clip_index`
- `failed_clip_role`
- `partial_clips`
- `job_id`
- `audio_url`
- `debug` seguro

Uma nova tentativa com o mesmo `job_id` deve reutilizar a narracao existente e pular clipes ja prontos, continuando do primeiro clipe faltante.

### Motivo

Falhas de provider podem ocorrer depois de ativos caros ja terem sido gerados. O usuario precisa conseguir diagnosticar a falha e tentar novamente sem perder narracao ou clipes prontos.

### Impacto

O frontend passa a manter clipes parciais visiveis, mostrar "Tentar novamente" e registrar `Video generation error` no console. O backend nao chama ElevenLabs novamente quando o job ja tem `audio_url`, nao regenera clipes ja prontos e so inicia a composicao final quando os 4 clipes existem.

### Status

Decidido

## 2026-05-19 - FFmpeg configuravel e composicao pendente sem perda de ativos

### Decisao

Adicionar `ffmpegPath` nas configuracoes administrativas e usar uma deteccao robusta de FFmpeg antes da composicao final.

A deteccao deve tentar, nesta ordem:

- caminho configurado em `ffmpegPath`;
- `/usr/bin/ffmpeg`;
- `/usr/local/bin/ffmpeg`;
- `command -v ffmpeg`.

Cada candidato deve ser validado com `ffmpeg -version`.

### Motivo

Em alguns servidores, o FFmpeg existe mas nao aparece no PATH do PHP/FPM, ou funcoes de execucao podem estar desabilitadas. O pipeline precisa diagnosticar esse caso sem perder clipes e narracao ja gerados.

### Impacto

Falhas de FFmpeg retornam `FFMPEG_NOT_AVAILABLE` com debug seguro, incluindo caminhos testados e disponibilidade de `exec`, `proc_open` e `shell_exec`. O debug pode indicar a causa interna `FFMPEG_EXEC_DISABLED`, `FFMPEG_NOT_FOUND` ou `FFMPEG_NOT_EXECUTABLE`.

Quando o FFmpeg nao esta disponivel, o job permanece em `ready_for_composition`, com `composition_status=pending`, mantendo audio e 4 clipes. O frontend mostra composicao final pendente e permite tentar compor novamente depois que o caminho/servidor for corrigido.

### Status

Decidido

## 2026-05-20 - Composição externa como caminho principal

### Decisao

A hospedagem atual em cPanel compartilhado não permite instalar nem usar FFmpeg local em `/usr/bin/ffmpeg` ou `/usr/local/bin/ffmpeg`.

A composição final do vídeo passa a usar um serviço externo de renderização com FFmpeg como caminho principal, configurado por:

- `videoComposerMode`
- `videoComposerEndpoint`
- `videoComposerApiKey`
- `videoComposerTimeout`

O modo `external_service` é o padrão.

### Motivo

O plano de hospedagem não oferece FFmpeg local e o suporte confirmou que esse recurso não pode ser habilitado nesse ambiente. A composição precisa acontecer fora do WordPress para preservar o MVP em hospedagem compartilhada.

### Impacto

Depois que a narração ElevenLabs e os 4 clipes Veo estiverem prontos, o backend envia apenas URLs públicas e metadados seguros para o serviço externo. Se a composição falhar, áudio e clipes permanecem salvos no job e o frontend exibe composição final pendente ou com erro, sem apagar ativos já gerados.

### Status

Decidido

## 2026-05-20 - Composição externa assíncrona e leve para Render Free

### Decisao

O microserviço `stlai-video-renderer` passa a compor vídeos de forma assíncrona. O `POST /render` apenas cria um job externo e retorna `render_job_id`; o plugin consulta `GET /render/{render_job_id}` durante o polling.

Para o MVP em Render Free, a qualidade padrão é `preview`, com saída `720x1280` para `9:16` e `1280x720` para `16:9`. A concatenação simples é o padrão. `xfade` fica opcional via `ENABLE_XFADE=true` e restrito ao modo `full`, com fallback para concatenação simples se falhar.

### Motivo

O Render Free derrubou a instância por exceder 512 MB durante a composição com filtros pesados. O fluxo precisava retornar rápido para o WordPress e reduzir o consumo de memória do FFmpeg.

### Impacto

O plugin passa a salvar `render_job_id`, `composer_status`, `composer_mode` e `composer_provider`, usando os estados `composition_queued`, `composition_processing`, `composition_error` e `ready`. Áudio e clipes continuam preservados em qualquer falha de composição.

### Status

Decidido

## 2026-05-20 - UX motion para processamento de vídeo

### Decisao

Adicionar um bloco visual animado no passo 5 e no resumo para representar o processamento do vídeo, com preview em blur, ponto pulsante, barra com shimmer, equalizer e etapas do pipeline:

- Narração
- Clipes IA
- Composição
- Finalização

### Motivo

O usuário não percebia claramente que o vídeo estava sendo gerado, especialmente durante filas e composição externa assíncrona.

### Impacto

O frontend passa a mapear `generating_audio`, `generating_clip_*`, `composition_queued`, `composition_processing` e `composition_error` para estados visuais `done`, `active` e `pending`, mantendo áudio e clipes visíveis enquanto o vídeo final ainda não existe.

### Status

Decidido

## 2026-05-20 - Resultado final como tela pública de entrega

### Decisao

A etapa 6 deixa de ser apresentada publicamente como "Resumo" e passa a ser "Resultado final".

Ao clicar em "Gerar vídeo", o usuário é levado para o Resultado final assim que o job é aceito/iniciado. Essa tela passa a ser o ponto principal de acompanhamento da geração, exibindo motion enquanto narração, clipes e composição avançam, e trocando para o player principal quando `final_video_url` existir.

O áudio separado da narração não aparece para o usuário final. Ele continua existindo no job para composição, mas a interface pública mostra apenas o vídeo final e os 4 clipes preparados.

### Motivo

A página final não é apenas um resumo; ela é a entrega do anúncio criado. O usuário precisa acompanhar a geração no lugar onde receberá o resultado, sem encontrar um player de áudio separado que não faz parte da entrega final.

### Impacto

O frontend usa "Resultado final" na navegação e nos títulos públicos. Em erro de composição, a página mostra "Não foi possível concluir o vídeo final", informa que os clipes foram preservados e oferece "Tentar novamente" para recompor sem regenerar áudio/clipes quando eles já existem.

### Status

Decidido

## 2026-05-20 - Fade opcional com fallback obrigatório no renderer

### Decisao

O plugin pode solicitar fade enviando `enable_fade=true`, mas o renderer só tenta `xfade` quando o ambiente estiver configurado para isso. No Render Free, o padrão permanece `RENDER_OUTPUT_QUALITY=preview` e `ENABLE_XFADE=false`, usando concatenação simples.

Se `ENABLE_XFADE=true` e o xfade falhar, o job não deve falhar por causa da transição. O renderer deve cair automaticamente para concatenação simples e retornar `fallback_used=concat_without_fade`.

### Motivo

O fade melhora a estética, mas filtros `xfade` são mais pesados e podem exceder memória em planos pequenos. A composição final é mais importante que a transição.

### Impacto

O contrato de status passa a expor `transition_used` e `fallback_used`. O job só falha se a concatenação simples também falhar.

### Status

Decidido

## 2026-05-20 - Retry automático obrigatório por clipe Veo

### Decisao

Cada clipe Veo deve ter até 3 tentativas dentro do mesmo fluxo antes de retornar erro ao frontend.

O backend deve tentar novamente automaticamente para falhas temporárias como HTTP 429, 500, 502, 503, 504, timeouts, operation timeout, resposta vazia/inválida e erros transitórios de transporte. Erros permanentes de configuração, validação, imagem inválida ou formato inválido não devem ser repetidos.

Durante retry, o job deve expor `retrying_clip_1`, `retrying_clip_2`, `retrying_clip_3` ou `retrying_clip_4`, além de `current_clip_index`, `current_clip_attempt`, `clip_retry_count` e `last_clip_error`.

### Motivo

Falhas intermitentes do Veo podem se resolver em uma nova tentativa imediata. O usuário não deve precisar clicar duas vezes para resolver uma instabilidade temporária do provider.

### Impacto

O erro público de clipe só aparece depois que todas as tentativas daquele clipe falham. O estado final recuperável passa a ser `clip_generation_error`. O botão "Tentar novamente" retoma do ponto de falha: não regenera narração existente, não regenera clipes já prontos e tenta apenas o que falta; se os 4 clipes já existem, tenta apenas a composição.

### Status

Decidido

## 2026-05-20 - Progresso visual contínuo por fase

### Decisao

O frontend deve manter uma barra de progresso visual estimada enquanto o backend está ocupado, mesmo sem nova resposta AJAX.

A barra cresce lentamente dentro do teto da fase atual, não volta para trás, não chega a 100 antes de `ready` e mantém shimmer/motion para não parecer travada.

### Motivo

Geração de narração e clipes pode demorar em uma request longa do WordPress. Sem uma animação temporal local, a interface parece parada mesmo com o backend trabalhando.

### Impacto

O frontend usa progresso visual por fase:

- narração: 8-24;
- clipe 1: 25-37;
- clipe 2: 38-51;
- clipe 3: 52-65;
- clipe 4: 66-78;
- fila de composição: 80-84;
- composição: 85-96;
- pronto: 100.

### Status

Decidido

## 2026-05-20 - Geração de vídeo permanece no passo 5

### Decisao

Ao clicar em "Gerar vídeo", o usuário deve permanecer no passo 5. O fluxo não redireciona automaticamente para o Resultado final.

O botão do passo 5 para Resultado final passa a receber destaque visual quando houver job de vídeo em andamento, final pronto ou erro recuperável. Ao clicar nesse botão, o frontend navega para o passo 6 e rola diretamente até a seção de vídeo.

### Motivo

No teste real, o redirecionamento automático quebrou a expectativa de controle do usuário. O usuário deve decidir quando deseja acompanhar o processamento na página final.

### Impacto

O polling continua ativo no passo 5. O CTA muda para "Acompanhar resultado" durante geração e "Ver resultado final" quando o vídeo está pronto. A seção de vídeo do Resultado final permanece o ponto de acompanhamento quando o usuário escolhe abri-la.

### Status

Decidido

## 2026-05-20 - Galeria do passo 4 focada em seleção

### Decisao

Na galeria de imagens do passo 4, os botões de hover para download, ampliar e regenerar devem ficar ocultos/removidos. O card deve priorizar seleção/desseleção para vídeo.

Na galeria do Resultado final, os botões de ampliar, baixar e regenerar podem existir, desde que funcionem. Botões sem ação não devem aparecer.

### Motivo

No passo 4, o usuário está selecionando imagens para vídeo e pode clicar acidentalmente em botões sobrepostos. No Resultado final, a galeria já é uma área de revisão/exportação e pode oferecer ações de imagem.

### Impacto

O passo 4 mantém o check visual de seleção. O Resultado final renderiza botões próprios e mantém eventos de download, lightbox e regeneração.

### Status

Decidido

## 2026-05-20 - Status granular de clipes e música futura

### Decisao

O job de vídeo deve expor `progress_hint`, `current_clip_index` e `current_clip_attempt` para que a interface acompanhe melhor a geração de cada clipe. O frontend pode corrigir visualmente status atrasados usando esses campos e a quantidade de clipes já salvos.

Música de fundo fica para uma story futura e será implementada no renderer/FFmpeg, não misturada diretamente no TTS. A opção padrão futura deve ser desligada, com presets suaves como `suave`, `comercial` e `emocional`, volume baixo entre 5% e 12%.

### Motivo

O fluxo PHP ainda pode executar chamadas longas; sem sinais granulares, a UI fica presa em "Gerando narração" mesmo quando os clipes já estão em andamento. A música precisa ser controlada na composição final para preservar a narração ElevenLabs limpa.

### Impacto

A UI passa a inferir fases de clipe por `current_clip_index`, `progress_hint` e `clips.length`, sem exigir uma refatoração completa do pipeline nesta story. Música de fundo permanece apenas documentada e não foi implementada.

### Status

Decidido

## 2026-05-20 - Frames preparados para vídeo e enquadramento seguro

### Decisao

Os frames preparados para o Veo devem ser salvos no job e exibidos publicamente em uma seção separada chamada "Imagens no formato", com o formato escolhido no título: "Imagens no formato 9:16" ou "Imagens no formato 16:9".

Os players dos clipes devem respeitar o formato escolhido. Em 9:16, cards e players usam proporção vertical; em 16:9, usam proporção horizontal.

O preparo do frame para Veo deve preservar o produto inteiro com margem segura, em vez de cortar a imagem para preencher o aspect ratio. O prompt 16:9 deve pedir câmera mais aberta, produto centralizado e espaço seguro acima/abaixo para evitar cortar cabeça, topo, base ou partes importantes.

### Motivo

O usuário precisa ver não só as imagens quadradas originais, mas também os frames realmente enviados ao pipeline de vídeo. No teste real, vídeos verticais apareciam em cards largos e o horizontal podia cortar partes importantes do produto.

### Impacto

O contrato do job passa a retornar `video_frames`. A interface separa galeria original, clipes preparados e imagens preparadas para vídeo. O provider Veo gera frames com encaixe seguro e reforça o prompt para manter o produto inteiro visível.

### Status

Decidido

## 2026-05-20 - Imagens no formato como asset final

### Decisao

A seção pública deixa de usar o termo técnico "frame" e passa a se chamar "Imagens no formato 9:16" ou "Imagens no formato 16:9".

Essas imagens são assets úteis para o usuário baixar e usar em anúncios, stories, reels e marketplaces, não uma área de debug. Elas devem ser exatamente os inputs formatados enviados ao provider de vídeo.

O vídeo não pode começar com uma imagem quadrada quando o formato escolhido é 9:16 ou 16:9. O provider deve receber apenas a imagem já preparada no aspect ratio final, e o prompt deve exigir que o primeiro frame do vídeo respeite essa imagem formatada.

### Motivo

No teste real, o clipe parecia começar quadrado e depois abrir para o formato final. Isso quebra a percepção de qualidade e indica que o input visual precisa nascer no aspect ratio escolhido.

### Impacto

O preparo de imagem para vídeo usa crop/recomposição nativa no aspect ratio final. É proibido usar square foreground com blurred background, padding visível, barras ou moldura como resultado final. A UI mostra "Imagem 1" a "Imagem 4", com baixar e ampliar funcionais.

### Status

Decidido

## 2026-05-20 - Proibição de moldura blur em imagens no formato

### Decisao

"Imagens no formato" são assets comerciais reais, não previews técnicos. O input do vídeo deve ser exatamente a imagem já preparada no formato final.

Fica proibido usar como resultado final: imagem quadrada centralizada sobre fundo desfocado, imagem quadrada sobre fundo esticado, padding visível, barras laterais, moldura perceptível ou placeholder. Quando a imagem original não estiver no aspect ratio escolhido, o preparo deve usar crop/recomposição nativa, priorizando produto inteiro quando possível e aceitando recorte de fundo/cenário.

### Motivo

A solução com foreground quadrado e blur ainda parecia uma imagem quadrada dentro de um canvas maior, e fazia o clipe nascer com aparência errada para anúncios em 9:16 ou 16:9.

### Impacto

O frame enviado ao Veo passa a preencher integralmente o formato escolhido. O vídeo deve começar com esse frame real no formato final, sem transição de quadrado para vertical/horizontal.

### Status

Decidido

## 2026-05-20 - Clipes incrementais, retry automático e narração performática

### Decisao

A geração dos 4 clipes Veo não deve depender de uma única requisição PHP longa. No WordPress atual, o caminho adotado é incremental por polling: o job registra 4 `clip_jobs`, cada consulta de status processa o próximo clipe pendente, salva o resultado assim que ficar pronto e só inicia a composição quando os 4 clipes estiverem `ready`.

Cada clipe deve tentar até 3 vezes antes de pedir ação do usuário. Falhas temporárias como HTTP 408, 409, 429, 500, 502, 503, 504, timeout, resposta vazia ou indisponibilidade temporária entram em retry automático com backoff. O botão "Tentar novamente" retoma do ponto de falha: preserva narração, frames e clipes prontos, tentando apenas clipes faltantes/erro ou, se os 4 já existirem, apenas composição.

O roteiro público permanece limpo em `script_public`. A voz recebe `script_narration` interno limpo de tags literais; emoção deve vir de português natural, pontuação, quebras de frase e ritmo humano, sem marcações entre colchetes que possam ser faladas.

### Motivo

Gerar 4 clipes em sequência prende o usuário em uma etapa longa, aumenta risco de timeout e impede a UI de mostrar avanço real. A narração também precisava soar mais UGC/storytelling sem poluir a copy que o usuário revisa.

### Impacto

O frontend passa a acompanhar `clip_jobs`, `missing_clips` e quantidade de clipes prontos para mostrar placeholders, retries e progresso por clipe. A geração fica semi-paralela do ponto de vista da experiência: cada clipe aparece assim que termina, sem esperar todos. A narração fica mais expressiva no áudio, mantendo a copy pública limpa.

### Status

Decidido

## 2026-05-20 - Composer rápido e vídeo limpo sem overlays

### Decisao

No Render Free, a composição final usa modo rápido por padrão: `RENDER_OUTPUT_QUALITY=preview`, `FAST_COMPOSE=true` e `ENABLE_XFADE=false`.

A transição padrão do MVP é corte simples (`transition_used = "cut"`). O `xfade` fica opcional para instâncias maiores, com fallback obrigatório para corte simples (`fallback_used = "cut_without_fade"`) caso falhe.

Vídeos comerciais gerados pelo Veo não podem conter overlays: REC, camera HUD, viewfinder, timestamp, watermark, legendas, textos, ícones, badges, interface de celular/câmera ou qualquer UI artificial.

### Motivo

A composição com filtros/fades pesados estava demorando mais de 5 minutos e podia falhar em instância pequena. Para o MVP, estabilidade e velocidade importam mais que transição sofisticada. Além disso, overlay de câmera/REC quebra a estética comercial limpa do anúncio.

### Impacto

O renderer passa a tentar concatenação rápida em uma única passagem de FFmpeg, com preview 9:16 em `406x720` por padrão e 16:9 em `1280x720`. O prompt do Veo ganhou restrições anti-REC/HUD em todos os clipes.

### Status

Decidido

## 2026-05-21 - URL final é estado terminal no frontend

### Decisao

Quando qualquer resposta AJAX trouxer `final_video_url` válido, `final_video_url_exists=true`, `status=ready`, `composition_status=complete` ou `composer_status=ready`, o frontend deve normalizar o estado do vídeo como `ready`.

`final_video_url` tem prioridade máxima no merge monotônico. Respostas posteriores sem URL ou com status de composição anterior não podem apagar a URL final, reduzir progresso, reativar motion de composição ou reiniciar polling.

### Motivo

O backend já pode concluir a composição e retornar o vídeo final enquanto a UI ainda conserva um estado visual antigo como `composition_processing`. Sem normalização terminal, o usuário vê "Compondo vídeo final" mesmo com o MP4 pronto.

### Impacto

Ao receber a URL final, o frontend para polling/timers, marca progresso em 100%, esconde o motion de processamento e renderiza o player principal no passo 5 e no Resultado final. Clipes, imagens no formato e roteiro continuam preservados.

### Status

Decidido

## 2026-05-21 - Retry Veo antes de erro final

### Decisao

Falhas de clipe Veo com `VEO_INVALID_RESPONSE`, URI de vídeo ausente, resposta vazia, operação concluída sem vídeo, timeout ou HTTP 408/409/429/5xx são retryable. Um clipe só pode virar `error_final` depois de 3 tentativas reais ou quando houver erro claramente permanente.

Jobs antigos que tenham `error_final` prematuro com `attempt < 3` e erro retryable devem ser recuperados automaticamente para `pending/retrying`, sem ação manual do usuário.

Atualização 2026-05-22: o fluxo comercial atual usa `max_concurrent_clip_generations = 4` com `clip_start_stagger_seconds = 1`. O backend ainda bloqueia duplicação por índice e operações não stale do mesmo clipe, mas não limita o fluxo comercial a 1 ou 2 clipes ativos.

### Motivo

O Veo pode concluir uma operação sem devolver URI de vídeo. Esse caso é intermitente e uma nova tentativa costuma resolver, então não deve interromper o pipeline na primeira falha.

### Impacto

O pipeline preserva clipes prontos, mantém `generating_clips` enquanto houver retry automático e só exibe erro final após esgotar tentativas. A UI mostra "Ajustando clipe X" durante retry e continua polling.

### Status

Decidido

## 2026-05-21 - Concorrência controlada anterior e player vertical compacto

### Decisao

Decisão superseded em 2026-05-22 para clipes comerciais. O limite anterior de até 2 clipes ativos foi substituído por `MAX_CONCURRENT_CLIP_GENERATIONS = 4` com stagger de 1 segundo. O frontend agenda os 4 `start_clip` por índice e o backend mantém trava por índice para evitar duplicidade de `operation_id`.

O player final 9:16 deve ser exibido como preview vertical compacto, com card centralizado e largura máxima de 360px para o vídeo no desktop. O player final 16:9 fica limitado a 800px.

### Motivo

Um clipe por vez deixava o fluxo lento demais. A estratégia final adotada usa 4 operações comerciais assíncronas com stagger de 1 segundo para reduzir tempo total sem concentrar todos os requests no mesmo instante. O player vertical precisava deixar de parecer um canvas grande com laterais pretas.

### Impacto

Diagnostics atuais refletem `max_concurrent_clip_generations=4`, `clip_start_stagger_seconds=1`, `clip_generation_mode=staggered_parallel`, `scheduled_clip_indexes`, `started_clip_indexes_this_tick`, `next_clip_indexes` e `active_generating_count`. A UI pode mostrar os 4 placeholders agendados/gerando. O player final vertical fica menor, centralizado e com `object-fit: cover` dentro de um container 9:16.

### Status

Decidido

## 2026-05-21 - Player final compacto e ações próprias

### Decisao

A UI pública do vídeo final deve tratar o player como preview premium, não como mídia em tela cheia dentro da página.

Para 9:16, o player final fica centralizado em formato mobile preview, com largura controlada. Para 16:9, o player pode ser mais largo, mas sempre limitado ao card.

Os clipes preparados e o vídeo final devem ter ações próprias de interface para baixar e ampliar/visualizar, sem depender de menus nativos do navegador.

### Motivo

O player final grande demais quebrava o ritmo visual da página e escondia ações. Os controles nativos de vídeo variam por navegador e podem ocultar download/ampliação.

### Impacto

O frontend renderiza botões consistentes de baixar, ampliar e copiar link no vídeo final, e baixar/ampliar em cada clipe preparado. O lightbox existente passa a abrir vídeos em overlay controlado.

### Status

Decidido
## 2026-05-22 - Story 10B: script TTS sempre presente

### Decisao

Todo job de vídeo deve ter `script_public` e `script_tts`. `script_public` é a única versão mostrada ao usuário; `script_tts`/`script_narration` é interno e usado como fonte da narração ElevenLabs.

Jobs antigos ou respostas parciais sem `script_tts` devem ser reconciliados automaticamente a partir do `script_public`.

Para PT-BR, roteiros devem evitar termos importados quando houver equivalente natural. `topper`, `cake topper` e `personalized topper` viram "topo de bolo" ou "topo de bolo personalizado" em textos de roteiro/copy.

### Motivo

O response ainda podia trazer `script_tts_exists=false`, e a copy soava técnica demais. Isso prejudica a qualidade emocional da narração e a localização em PT-BR.

### Impacto

O frontend gera uma copy mais narrativa, o backend garante o par de scripts e o AJAX expõe apenas flags seguras: `script_public_exists`, `script_tts_exists`, `script_tts_used_for_tts` e `narration_language`.

### Status

Decidido

## 2026-05-22 - Soft timeout da composição externa

### Decisao

Quando existe `render_job_id`, o timeout configurado do composer passa a ser soft timeout. Ao ultrapassar esse limite, o job não vira `composition_error`; ele entra em `composition_waiting`/`waiting`, mantém `render_job_id` e continua consultando `GET /render/:render_job_id`.

O hard timeout do MVP é `1200` segundos. Só nesse limite, ou em erro explícito do renderer, a composição vira erro recuperável.

O frontend reduz polling de composição: cerca de 5s durante `queued/processing/waiting` e cerca de 10s após 5 minutos.

### Motivo

Render Free/cold start pode demorar mais que 300s para concluir a composição. Marcar erro aos 300s perde a oportunidade de recuperar `final_video_url` quando o render termina depois.

### Impacto

Diagnostics passam a expor `soft_timeout_seconds`, `soft_timeout_reached`, `hard_timeout_seconds`, `hard_timeout_reached`, `next_poll_seconds`, `external_render_status`, `external_render_checked_at` e `render_job_id_exists`.

### Status

Decidido

## 2026-05-22 - Idioma de narração e script TTS separado

### Decisao

A aba de vídeo deve permitir escolher o idioma da narração: Português (`pt-BR`), Inglês (`en-US`), Espanhol (`es-ES`) e Francês (`fr-FR`). O padrão é `pt-BR`.

O roteiro público exibido ao usuário é `script_public`: texto limpo, editável e sem marcações de direção de voz. O texto interno de performance é `script_tts`/`script_narration`: pode conter marcações ocultas de emoção/pausa e deve ficar salvo no job, mas não aparece na UI pública.

Como já houve regressão de tags faladas no áudio, o provider ElevenLabs deve remover tags literais antes de enviar o texto final à API. A emoção deve permanecer por pontuação, quebras de frase e cadência natural.

Em `pt-BR`, termos importados como `topper`, `cake topper` e `Topper Personalizado` devem ser normalizados para "topo de bolo" ou "topo de bolo personalizado" quando estiverem em roteiro/copy, sem mexer em URLs, IDs ou nomes técnicos.

### Motivo

A narração precisa soar humana e localizada, sem inglês residual em anúncios brasileiros e sem expor tags técnicas ao usuário.

### Impacto

O payload de vídeo passa a carregar `video_language`, `narration_language`, `narration_style`, `script_public` e `script_tts`. Jobs antigos continuam válidos: ausência de idioma assume `pt-BR`; ausência de `script_tts` usa `script_public` como base.

### Status

Decidido

## 2026-05-24 - Galeria com zoom robusto e imagem técnica/informativa

### Decisao

O modal de ampliar imagem deve usar o mesmo resolvedor de URL usado pelo download, aceitando `full_url`, `url`, `image_url`, `download_url`, `src`, URLs `https`, `data:image` e `blob`. Se a imagem pode ser baixada, ela também deve poder ser ampliada.

O fluxo de imagens passa a incluir automaticamente arte técnica/informativa. A imagem 1 permanece como fundo branco. Quando existem dimensões, a imagem 2 é "Medidas" e a imagem 3 é "Informações Técnicas". Quando não existem dimensões, a imagem 2 é "Características". As demais imagens seguem como ambientadas, detalhe, benefício e hero conforme o plano.

### Motivo

As imagens recortadas do combo 2x2 podem existir como `data:image`, que o modal descartava, embora o download funcionasse. Além disso, anúncios de marketplace precisam de uma imagem mais funcional, capaz de comunicar medidas, características, uso e benefícios sem exigir edição manual.

### Impacto

Os prompts do combo 2x2 agora são montados por quadrante e reforçam a preservação obrigatória do produto: não alterar formato, cor, estrutura, material, acabamento, textura, proporção, identidade ou função, e não inventar medidas ou especificações técnicas. A futura regeneração manual pode reaproveitar os tipos `dims` e `info`.

### Status

Decidido

## 2026-05-24 - Slots informativos limitados na galeria

### Decisao

A galeria permite no máximo uma imagem informativa (`informative_features`) e, quando houver medidas reais, uma imagem técnica de medidas (`technical_dimensions`).

Todos os demais slots são imagens visuais/comerciais sem texto sobreposto: capa, ambientadas, detalhe, benefício visual e hero. O slot "Destaque — Benefício" deve comunicar valor pela cena e composição, não por palavras, bullets, ícones ou títulos.

### Motivo

A primeira implementação da imagem informativa reforçou o contexto técnico no prompt geral do combo 2x2, e modelos de imagem passaram a espalhar textos e blocos informativos em outros quadrantes.

### Impacto

Cada slot de imagem agora carrega `role` e `allows_text`. Apenas `technical_dimensions` e `informative_features` podem ter texto. Slots visuais recebem instruções negativas explícitas contra texto, títulos, legendas, bullets, números, medidas, selos, stickers, banners, tipografia e elementos de interface.

### Status

Decidido

## 2026-05-24 - Roteiro de narração com quatro estilos

### Decisao

A narração do vídeo foi expandida para quatro estilos: `persuasiva`, `emocional`, `demonstrativa` e `premium`. O padrão da UI passa a ser `emocional`.

`script_public` continua sendo o roteiro limpo exibido ao usuário. `script_tts` continua interno e otimizado para voz, com marcações discretas de direção quando útil.

O roteiro de narração não deve reutilizar texto técnico/SEO de marketplace de forma bruta. Nomes de produto devem ser normalizados para evitar repetições ruins como "topo de bolo de Bolo Personalizado". Números e dimensões que permanecerem na narração devem ser escritos em forma falada, como "vinte por dez por sete centímetros".

### Motivo

A copy da narração estava misturando descrição técnica, SEO e roteiro falado, gerando frases artificiais e pouco humanas.

### Impacto

O frontend gera roteiros locais por estilo, com linguagem mais natural e estrutura de gancho, apresentação, benefício, diferencial e fechamento. O backend aceita os quatro estilos e normaliza scripts antigos/novos antes de gerar o áudio.

### Status

Decidido

## 2026-05-25 - Prompt editável para clipes IA Veo

### Decisao

O prompt dos clipes IA foi exposto no painel de Prompts como "Vídeo — Clipes IA", usando a chave `video_clip_generation_prompt`.

Clipes 16:9 devem iniciar com o produto inteiro visível, câmera aberta e margem segura para cabeça, base, laterais e detalhes importantes. O prompt também reforça movimento natural do ambiente para evitar efeito de foto estática com apenas movimento de câmera.

Regras obrigatórias de preservação do produto são anexadas pelo backend mesmo quando o prompt é customizado no admin.

### Motivo

Alguns clipes horizontais começavam cortados e parte dos vídeos ficava visualmente parada. Além disso, o prompt precisava ficar editável para testes sem abrir risco de remover proteções críticas.

### Impacto

O Veo recebe o prompt customizado com placeholders e, em seguida, um bloco obrigatório que protege identidade do produto, bloqueia crop inicial em 16:9, remove overlays/efeitos e exige movimento realista no ambiente.

### Status

Decidido

## 2026-05-25 - Prompts separados para clipes IA 9:16 e 16:9

### Decisao

Os clipes IA passam a usar prompts editáveis separados por formato:

- `video_clip_generation_prompt_vertical` para 9:16.
- `video_clip_generation_prompt_horizontal` para 16:9.

O prompt horizontal é mais rígido porque parte de uma imagem 1:1 e o modelo tende a inventar informação ao expandir a cena. O bloco obrigatório anexado pelo backend reforça identidade visual do produto, bloqueia mudança de rosto, cabelo, cor, roupa, base, proporções, acabamento e impede `identity drift` entre clipes.

### Motivo

Os clipes 16:9 estavam alterando características do produto entre os quatro vídeos, enquanto o 9:16 já estava com boa consistência. Separar os prompts permite manter o vertical estável e endurecer somente o horizontal.

### Impacto

O fluxo escolhe a chave de prompt pelo `format` do job antes de chamar o Veo. Cada clipe continua recebendo placeholders por índice/função, mas as regras de preservação do produto são idênticas e obrigatórias para todos os clipes do lote.

### Status

Decidido

## 2026-05-25 - Mostrar/ocultar campos sensíveis no admin

### Decisao

Campos sensíveis no painel de Configurações de IA agora possuem botão de mostrar/ocultar valor.

### Motivo

As API keys e secrets já eram mascaradas, mas o admin não tinha uma forma visual segura de conferir o valor digitado antes de salvar ou testar integrações.

### Impacto

O helper `stlai_render_password_field` renderiza o input como `password` por padrão e adiciona um botão local de alternância para `text`/`password`, preservando os mesmos option names e sem expor valores em JavaScript global ou logs.

### Status

Decidido

## 2026-06-01 - UGC entra como módulo paralelo ao vídeo comercial

### Decisao

Adicionar o primeiro módulo real de "Vídeos UGC" na página Resultado, depois da seção de vídeo comercial, sem alterar o pipeline comercial existente.

O UGC usa um serviço próprio (`STLAI_UGC_Job_Service`), um provider MuAPI próprio (`STLAI_MuAPI_UGC_Provider`) e salva os itens em `ugc_jobs` dentro do job principal de vídeo.

### Motivo

UGC precisa evoluir rápido para testes de produto e comparação de providers, mas não pode criar risco para o fluxo comercial já funcional com Veo, ElevenLabs e renderer.

### Impacto

A UI pública passa a ter modal de geração UGC com presets, imagem de referência, formato, duração e resolução. O backend ganha endpoints `stlai_start_ugc_video`, `stlai_poll_ugc_video` e `stlai_get_ugc_jobs`.

API keys continuam exclusivamente no backend. O frontend recebe apenas `ugcEnabled`, provider público, defaults e nonce.

### Status

Decidido

## 2026-06-01 - Prompts UGC editáveis por preset

### Decisao

Prompts de UGC ficam editáveis na página de Prompts do admin, separados por preset:

- `ugc_prompt_social`
- `ugc_prompt_tutorial`
- `ugc_prompt_unboxing`
- `ugc_prompt_product_review`
- `ugc_prompt_virtual_try_on`
- `ugc_prompt_hyper_motion`
- `ugc_prompt_tv_spot`
- `ugc_prompt_wild_card`
- `ugc_prompt_pro_virtual_try_on`

### Motivo

Cada estilo de vídeo UGC exige intenção diferente. Deixar tudo em um único prompt tornaria os testes menos controláveis e mais arriscados.

### Impacto

O backend combina prompt do preset, contexto do produto, imagem de referência e regras de segurança antes de enviar ao provider. Futuramente outros providers podem reaproveitar os mesmos presets.

### Status

Decidido

## 2026-06-02 - UGC multi-provider por adapter backend

### Decisao

O UGC passa a suportar MuAPI, Atlas Cloud, Fal.ai e Seedance/BytePlus por adapters backend separados.

O provider é escolhido no admin via `ugcProvider`. A UI pública continua igual e não permite escolher provider.

### Motivo

Precisamos comparar custo, qualidade e disponibilidade de providers UGC sem duplicar fluxo, seção, modal, prompts ou storage.

### Impacto

`STLAI_UGC_Job_Service` passa a resolver o provider por factory interna. Cada provider implementa start/poll e normaliza operação, status e vídeo para o contrato comum do UGC.

API keys seguem apenas no backend. Jobs armazenam metadados operacionais seguros, como provider, modelo, operation_id, status_url, result_url e endpoint usado sem host secreto/chaves.

### Status

Decidido

## 2026-06-02 - Endpoints UGC variáveis ficam configuráveis

### Decisao

Atlas Cloud, Fal.ai e Seedance/BytePlus usam endpoints configuráveis para start/poll quando o contrato pode variar entre contas, filas ou wrappers.

Defaults:

- Atlas: `https://api.atlascloud.ai`, `/api/v1/model/generateVideo`, `/api/v1/predictions/{id}`.
- Fal.ai: `https://fal.run`, `bytedance/seedance-2.0/image-to-video`, `/{model}/requests/{id}`.
- Seedance direto: exige Base URL, Endpoint e Poll Endpoint preenchidos no admin.

### Motivo

Os providers podem expor Seedance por wrappers diferentes. Deixar polling hardcoded demais criaria falso erro em produção.

### Impacto

O admin tem campos de endpoint por provider. Fal.ai usa `status_url` retornada pela API quando existir; caso contrário monta a URL pelo endpoint configurado.

### Status

Decidido
