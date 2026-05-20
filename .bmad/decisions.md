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
