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
