# API Contracts — STLAI Vision Ads Pro

## 1. Objetivo

Este arquivo documenta os contratos atuais entre o frontend do wizard, os endpoints AJAX do WordPress e os serviços internos de vídeo do plugin.

Ele reflete o estado do projeto após as Stories 4, 5, 6, 7A, 7A.2 e 7B:

- geração de narração real com ElevenLabs;
- geração de clipe real isolado com Veo para teste;
- geração sequencial de 4 clipes visuais com Veo;
- vídeo final composto ainda pendente.

## 2. Princípios gerais

- O frontend chama apenas endpoints AJAX do WordPress.
- API keys ficam exclusivamente no backend.
- O frontend público não deve expor nomes técnicos ou credenciais de providers.
- Não expor API keys, headers sensíveis, payloads completos com base64 ou detalhes internos de autenticação.
- O frontend público não deve depender diretamente de Veo, ElevenLabs, Seedance ou MuAPI.
- Erros retornados ao frontend devem ser seguros: mensagem amigável, código e debug resumido sem segredos.
- O vídeo final composto ainda não existe nesta etapa; o retorno atual prepara áudio e clipes visuais para composição futura.

## 3. Endpoints AJAX atuais

Todos os endpoints usam `admin-ajax.php` e são registrados com versões autenticadas e públicas (`wp_ajax_` e `wp_ajax_nopriv_`) quando usados pelo shortcode público.

Endpoints atuais:

- `stlai_create_video_job`
- `stlai_check_video_status`
- `stlai_get_video_result`
- `stlai_generate_test_veo_clip`

## 4. Contratos por endpoint

### 4.1 `stlai_create_video_job`

Objetivo:

Criar um job de vídeo, gerar narração com ElevenLabs e gerar 4 clipes visuais mudos com Veo, ainda sem compor o vídeo final.

Entrada esperada:

```json
{
  "action": "stlai_create_video_job",
  "selected_images": ["https://..."],
  "narration_type": "persuasiva",
  "format": "9:16",
  "script": "Texto da narração",
  "product_name": "Nome do produto",
  "product_description": "Descrição do produto"
}
```

Campos:

- `selected_images`: array de imagens selecionadas pelo usuário. O fluxo principal espera de 4 a 8 imagens.
- `narration_type`: `persuasiva` ou `emocional`.
- `format`: `9:16` ou `16:9` no MVP. Estado legado `1:1` deve cair para `9:16`.
- `script`: texto de narração enviado ao ElevenLabs.
- `product_name`: nome do produto.
- `product_description`: descrição do produto.

Resposta de sucesso:

```json
{
  "success": true,
  "data": {
    "job_id": "stlai_video_...",
    "status": "ready_for_composition",
    "progress": 96,
    "message": "4 clipes gerados. Composição final será feita na próxima etapa.",
    "audio_url": "https://.../uploads/stlai-vision-audio/....mp3",
    "clips": [
      {
        "index": 1,
        "role": "apresentacao_geral",
        "label": "Clipe 1 — Apresentação geral",
        "url": "https://.../uploads/stlai-vision-video/....mp4",
        "duration": 8,
        "muted": true
      }
    ],
    "final_video_url": "",
    "thumbnail_url": "",
    "composition_status": "pending",
    "format": "9:16",
    "narration_type": "persuasiva"
  }
}
```

Resposta de erro:

```json
{
  "success": false,
  "data": {
    "message": "Mensagem amigável",
    "code": "VEO_CLIP_2_ERROR",
    "debug": "Resumo seguro",
    "job_id": "stlai_video_...",
    "failed_clip": 2,
    "clips": []
  }
}
```

Status possíveis durante o fluxo:

- `queued`
- `generating_audio`
- `generating_clip_1`
- `generating_clip_2`
- `generating_clip_3`
- `generating_clip_4`
- `clips_ready`
- `ready_for_composition`
- `error`

Observações:

- Os 4 clipes são gerados sequencialmente.
- Clipes já gerados podem ser preservados no job se uma etapa posterior falhar.
- O vídeo final composto ainda não é gerado neste endpoint.

### 4.2 `stlai_check_video_status`

Objetivo:

Consultar o estado atual de um job já criado.

Entrada esperada:

```json
{
  "action": "stlai_check_video_status",
  "job_id": "stlai_video_..."
}
```

Resposta de sucesso:

```json
{
  "success": true,
  "data": {
    "job_id": "stlai_video_...",
    "status": "generating_clip_3",
    "progress": 78,
    "message": "Gerando clipe 3 de 4...",
    "audio_url": "https://...",
    "clips": [],
    "final_video_url": "",
    "thumbnail_url": "",
    "composition_status": "pending"
  }
}
```

Resposta de erro:

```json
{
  "success": false,
  "data": {
    "message": "Job de video nao encontrado.",
    "code": "JOB_NOT_FOUND",
    "debug": ""
  }
}
```

Status possíveis:

- `queued`
- `generating_audio`
- `generating_clip_1`
- `generating_clip_2`
- `generating_clip_3`
- `generating_clip_4`
- `clips_ready`
- `ready_for_composition`
- `ready`
- `error`
- `cancelled`

Observações:

- `ready_for_composition` significa que áudio e clipes estão prontos, mas a composição final ainda não foi executada.
- `ready` fica reservado para o fluxo final composto ou para compatibilidade com o mock anterior.
- `cancelled` é status reservado para contrato futuro.

### 4.3 `stlai_get_video_result`

Objetivo:

Obter o resultado persistido de um job.

Entrada esperada:

```json
{
  "action": "stlai_get_video_result",
  "job_id": "stlai_video_..."
}
```

Resposta de sucesso:

```json
{
  "success": true,
  "data": {
    "job_id": "stlai_video_...",
    "status": "ready_for_composition",
    "progress": 96,
    "message": "4 clipes gerados. Composição final será feita na próxima etapa.",
    "audio_url": "https://...",
    "clips": [],
    "final_video_url": "",
    "thumbnail_url": "",
    "composition_status": "pending"
  }
}
```

Resposta de erro:

```json
{
  "success": false,
  "data": {
    "message": "Job de video nao encontrado.",
    "code": "JOB_NOT_FOUND",
    "debug": ""
  }
}
```

Status possíveis:

- `ready_for_composition`
- `ready`
- `error`
- demais status persistidos do job, se a consulta ocorrer antes do fim do processamento.

Observações:

- `final_video_url` e `thumbnail_url` ainda são pendentes.
- A composição final com áudio será definida em story futura.

### 4.4 `stlai_generate_test_veo_clip`

Objetivo:

Gerar um único clipe real de teste com Veo a partir da primeira imagem selecionada, sem acionar o fluxo completo dos 4 clipes.

Entrada esperada:

```json
{
  "action": "stlai_generate_test_veo_clip",
  "selected_images": ["https://..."],
  "format": "9:16",
  "script": "Texto ou roteiro opcional",
  "product_name": "Nome do produto",
  "product_description": "Descrição do produto"
}
```

Resposta de sucesso:

```json
{
  "success": true,
  "data": {
    "status": "ready",
    "message": "Clipe de teste gerado com sucesso.",
    "test_clip_url": "https://.../uploads/stlai-vision-video/....mp4",
    "operation_id": "operations/...",
    "debug": {
      "model": "veo-3.1-lite-generate-preview",
      "aspectRatio": "9:16",
      "mimeType": "image/jpeg",
      "uses_image_bytesBase64Encoded": true,
      "prepared_frame_url": "https://.../uploads/stlai-vision-video/frames/....jpg"
    }
  }
}
```

Resposta de erro:

```json
{
  "success": false,
  "data": {
    "message": "Nao foi possivel gerar o clipe de teste.",
    "code": "VEO_HTTP_ERROR",
    "debug": "HTTP 400 — resumo seguro sem API key"
  }
}
```

Status possíveis:

- `ready`
- `error`

Observações:

- O teste usa apenas a primeira imagem selecionada.
- O player de teste deve vir `muted`.
- O nome técnico do provider não deve aparecer como texto público.

## 5. Status atuais do job

- `queued`: job criado e aguardando processamento.
- `generating_audio`: narração ElevenLabs em geração.
- `generating_clip_1`: geração do clipe 1.
- `generating_clip_2`: geração do clipe 2.
- `generating_clip_3`: geração do clipe 3.
- `generating_clip_4`: geração do clipe 4.
- `clips_ready`: clipes visuais disponíveis.
- `ready_for_composition`: áudio e 4 clipes prontos; composição final pendente.
- `ready`: resultado final pronto ou compatibilidade com fluxo mock anterior.
- `error`: job falhou.
- `cancelled`: reservado para cancelamento futuro.

## 6. Payloads atuais

Campos usados pelo fluxo de vídeo:

- `selected_images`: imagens selecionadas pelo usuário.
- `narration_type`: estilo de narração, `persuasiva` ou `emocional`.
- `format`: formato do vídeo, `9:16` ou `16:9`.
- `script`: roteiro/narração.
- `product_name`: nome do produto.
- `product_description`: descrição do produto.

Regras atuais:

- O MVP de vídeo aceita apenas `9:16` e `16:9`.
- `1:1` está fora do MVP e deve cair para `9:16` se aparecer por estado legado.
- O frontend não deve enviar nem receber API keys.
- O backend deve sanitizar entradas antes de usar providers externos.

## 7. Retornos atuais

Campos comuns retornados pelos endpoints de vídeo:

- `job_id`: identificador do job salvo em transient.
- `status`: status atual do processamento.
- `progress`: progresso numérico aproximado.
- `message`: mensagem amigável para UI.
- `audio_url`: URL pública do áudio ElevenLabs.
- `clips`: lista pública dos clipes gerados.
- `final_video_url`: pendente; ainda vazio até a composição final.
- `thumbnail_url`: pendente; ainda vazio até a composição final.
- `composition_status`: `pending` enquanto a composição final não existir.
- `format`: formato normalizado.
- `narration_type`: tipo de narração usado.
- `error_code`: código de erro, quando houver falha.
- `error_message`: mensagem de erro segura.
- `debug`: resumo seguro para diagnóstico.

Contrato público de item em `clips`:

```json
{
  "index": 1,
  "role": "apresentacao_geral",
  "label": "Clipe 1 — Apresentação geral",
  "url": "https://...",
  "duration": 8,
  "muted": true
}
```

Observação:

- Caminhos locais (`path`) podem existir no job interno, mas não devem ser expostos desnecessariamente ao frontend público.

## 8. Contrato do ElevenLabs

Objetivo:

Gerar a narração real do anúncio a partir do `script`.

Contrato atual:

- Provider executado somente no backend.
- Gera `audio_url` real.
- Salva arquivos em `uploads/stlai-vision-audio/`.
- Não expõe API key no frontend.
- Usa voice ID configurado para narração emocional ou persuasiva.
- Usa modelo configurado ou fallback seguro do provider.
- Retorna dados públicos suficientes para o job, sem headers sensíveis.

Entradas principais:

- texto da narração;
- `narration_type`.

Saída interna esperada:

```json
{
  "audio_url": "https://.../uploads/stlai-vision-audio/....mp3",
  "audio_path": "/.../uploads/stlai-vision-audio/....mp3",
  "provider": "elevenlabs",
  "model": "eleven_multilingual_v2",
  "voice_id": "..."
}
```

## 9. Contrato do serviço externo de composição

Objetivo:

Compor o vídeo final fora do WordPress, usando os 4 clipes visuais e a narração ElevenLabs já gerados.

Configuração administrativa:

- `videoComposerMode`: `external_service` ou `local_ffmpeg`; padrão `external_service`.
- `videoComposerEndpoint`: endpoint HTTP do serviço externo.
- `videoComposerApiKey`: chave enviada apenas pelo backend em header seguro.
- `videoComposerTimeout`: timeout da requisição, sugestão `300`.

Requisição para iniciar composição:

```http
POST {videoComposerEndpoint}
Content-Type: application/json
Authorization: Bearer {videoComposerApiKey}
```

Body:

```json
{
  "job_id": "stlai_video_xxx",
  "format": "9:16",
  "audio_url": "https://...",
  "clips": [
    {
      "index": 1,
      "role": "apresentacao_geral",
      "url": "https://..."
    },
    {
      "index": 2,
      "role": "uso_contexto",
      "url": "https://..."
    },
    {
      "index": 3,
      "role": "detalhe_acabamento",
      "url": "https://..."
    },
    {
      "index": 4,
      "role": "hero_fechamento",
      "url": "https://..."
    }
  ],
  "transition": "fade",
  "fade_duration": 0.4,
  "repeat_clips_until_audio_ends": true,
  "trim_to_audio_duration": true,
  "remove_clip_audio": true
}
```

Resposta de sucesso inicial:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "queued",
  "message": "Composição recebida e iniciada."
}
```

Consulta de status:

```http
GET {videoComposerEndpoint}/{render_job_id}
Authorization: Bearer {videoComposerApiKey}
```

Resposta em processamento:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "processing",
  "progress": 40,
  "message": "Compondo vídeo final..."
}
```

Resposta pronta:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "ready",
  "progress": 100,
  "final_video_url": "https://...",
  "duration": 72,
  "message": "Vídeo final composto com sucesso."
}
```

Resposta de erro:

```json
{
  "success": false,
  "render_job_id": "render_xxx",
  "status": "error",
  "message": "Mensagem amigável",
  "code": "COMPOSER_RENDER_ERROR",
  "debug": "Resumo seguro"
}
```

Códigos estruturados do composer:

- `COMPOSER_ENDPOINT_MISSING`
- `COMPOSER_API_KEY_MISSING`
- `COMPOSER_JOB_START_ERROR`
- `COMPOSER_STATUS_ERROR`
- `COMPOSER_RENDER_ERROR`
- `COMPOSER_TIMEOUT`
- `FINAL_VIDEO_URL_MISSING`
- `LOCAL_FFMPEG_UNAVAILABLE`

Persistência no job ao iniciar:

- `render_job_id`
- `composer_status`
- `composer_mode`
- `composer_provider`

Persistência no job quando ficar pronto:

- `final_video_url`
- `final_video_duration`
- `composer_mode`
- `composer_provider`
- `composed_at`

Comportamento em falha:

- manter `audio_url`;
- manter os 4 itens em `clips`;
- não apagar ativos;
- usar `composition_queued` ou `composition_processing` durante o polling;
- usar `composition_error` para falhas de renderização/status;
- permitir nova tentativa de composição com os mesmos ativos.

Configuração recomendada do renderer no Render Free:

- `RENDER_OUTPUT_QUALITY=preview`
- `ENABLE_XFADE=false`
- saída `9:16` em `720x1280`
- saída `16:9` em `1280x720`
- concatenação simples como padrão para reduzir uso de memória.

## 10. Contrato do Veo

Objetivo:

Gerar clipes visuais mudos para o pipeline de vídeo.

Contrato atual:

- Provider executado somente no backend.
- Modelo esperado no MVP: `veo-3.1-lite-generate-preview`.
- Base URL esperada: `https://generativelanguage.googleapis.com/v1beta`.
- Salva clipes em `uploads/stlai-vision-video/`.
- Salva frames preparados em `uploads/stlai-vision-video/frames/`.
- Formatos aceitos no MVP: `9:16` e `16:9`.
- `1:1` está fora do MVP e cai para `9:16` quando aparecer por estado legado.
- Frames enviados ao provider são preparados no aspect ratio final.
- O preparo de frame usa imagem única full-frame com cover crop central.
- Não usar contain, fundo desfocado, imagem duplicada, picture-in-picture, reflection ou bordas pretas.
- Clipes devem ser tratados como visual only.
- Se FFmpeg estiver disponível, o áudio nativo do clipe deve ser removido.
- Se FFmpeg não estiver disponível, o frontend deve exibir players `muted`.
- Os players dos clipes no frontend devem ficar `muted` por padrão.

Payload externo conceitual usado no Veo:

```json
{
  "instances": [
    {
      "prompt": "Prompt estruturado e restritivo",
      "image": {
        "bytesBase64Encoded": "...",
        "mimeType": "image/jpeg"
      }
    }
  ],
  "parameters": {
    "durationSeconds": 8,
    "aspectRatio": "9:16"
  }
}
```

Regras importantes:

- Nunca enviar `inlineData` ou `inline_data`.
- `bytesBase64Encoded` deve conter somente base64 puro, sem prefixo `data:image/...;base64,`.
- `mimeType` aceitos: `image/jpeg`, `image/png`, `image/webp`.
- Não logar base64 completo.
- Debug seguro pode informar modelo, aspect ratio, mime type, uso de `image.bytesBase64Encoded` e frame preparado.

## 11. Contrato dos 4 clipes

Os 4 clipes são gerados sequencialmente, com duração de 8 segundos cada.

Clipe 1:

- Role: `apresentacao_geral`
- Label: `Clipe 1 — Apresentação geral`
- Direção: produto parado, destaque geral, câmera suave, leve zoom in ou zoom out, visual limpo.

Clipe 2:

- Role: `uso_contexto`
- Label: `Clipe 2 — Uso / contexto`
- Direção: produto em contexto de uso como chaveiro decorativo para chaves, bolsas, mochila ou acessório, sem inventar função.

Clipe 3:

- Role: `detalhe_acabamento`
- Label: `Clipe 3 — Detalhe / acabamento`
- Direção: foco em textura, acabamento, material, detalhes do produto, aparência 3D/impressa e qualidade visual.

Clipe 4:

- Role: `hero_fechamento`
- Label: `Clipe 4 — Hero / fechamento`
- Direção: take final premium, produto valorizado, composição bonita, iluminação comercial e movimento suave.

Restrições comuns dos prompts:

- O produto deve permanecer com identidade, formato, cor, material, textura e design preservados.
- Se o produto for um chaveiro, tratar sempre como chaveiro decorativo.
- Não transformar o produto em abridor de garrafa, ferramenta, brinquedo para pets ou gadget funcional.
- Não inventar função nova.
- Manter o produto quase parado.
- Evitar animação exagerada, partes móveis ou mudança de pose.
- Usar apenas movimento sutil de câmera, zoom lento e parallax leve.
- Sem narração, fala, música ou efeitos sonoros.
- Sem logos, texto, legendas, marcas d'água ou pessoas, salvo se já presentes na imagem de referência.

## 12. Serviço externo de composição assíncrona

O WordPress envia os 4 clipes e a narração ElevenLabs para um serviço externo com FFmpeg. O serviço é assíncrono para evitar timeout e reduzir risco de estouro de memória em hospedagens pequenas.

### POST `/render`

Headers:

```http
Content-Type: application/json
Authorization: Bearer {RENDER_API_KEY}
```

Body:

```json
{
  "job_id": "stlai_video_xxx",
  "format": "9:16",
  "audio_url": "https://...",
  "clips": [
    {"index": 1, "role": "apresentacao_geral", "url": "https://..."},
    {"index": 2, "role": "uso_contexto", "url": "https://..."},
    {"index": 3, "role": "detalhe_acabamento", "url": "https://..."},
    {"index": 4, "role": "hero_fechamento", "url": "https://..."}
  ],
  "transition": "fade",
  "enable_fade": true,
  "fade_duration": 0.4,
  "repeat_clips_until_audio_ends": true,
  "trim_to_audio_duration": true,
  "remove_clip_audio": true
}
```

Resposta inicial:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "queued",
  "message": "Composição recebida e iniciada."
}
```

### GET `/render/{render_job_id}`

Enquanto processando:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "processing",
  "progress": 82,
  "final_video_url": "",
  "duration": 0,
  "transition_used": "",
  "fallback_used": "",
  "message": "Compondo vídeo final..."
}
```

Quando pronto:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "ready",
  "progress": 100,
  "final_video_url": "https://video-render.seudominio.com/renders/arquivo.mp4",
  "duration": 72,
  "transition_used": "concat",
  "fallback_used": "",
  "message": "Vídeo final composto com sucesso."
}
```

Quando o xfade estiver ativo e funcionar, `transition_used` deve ser `xfade`. Se o xfade falhar, o serviço deve tentar concatenação simples automaticamente e retornar `transition_used: "concat"` e `fallback_used: "concat_without_fade"`.

Erro:

```json
{
  "success": false,
  "render_job_id": "render_xxx",
  "status": "error",
  "code": "COMPOSER_RENDER_ERROR",
  "message": "Não foi possível compor o vídeo final.",
  "debug": "Resumo seguro"
}
```

Estados consumidos pelo plugin:

- `composition_queued`
- `composition_processing`
- `composition_error`
- `ready`

## 13. Estados de retry automático dos clipes

Cada clipe Veo deve ter até 3 tentativas antes de pedir ação do usuário:

- tentativa 1 normal;
- retry 1 após 2 segundos;
- retry 2 após 4 segundos.

Estados novos:

- `retrying_clip_1`
- `retrying_clip_2`
- `retrying_clip_3`
- `retrying_clip_4`
- `clip_generation_error`

Campos públicos do job relacionados ao retry:

```json
{
  "current_clip_index": 4,
  "current_clip_attempt": 2,
  "clip_retry_count": 1,
  "last_clip_error": "Resumo seguro do erro anterior",
  "failed_clip_index": 4,
  "failed_clip_role": "hero_fechamento"
}
```

Debug seguro de falha final:

- `failed_clip_index`
- `clip_attempt`
- `max_attempts`
- `retryable`
- `last_error_code`
- `last_error_summary`

O botão "Tentar novamente" deve retomar do ponto de falha: reutiliza narração existente, pula clipes já prontos e tenta apenas os clipes faltantes. Se os 4 clipes existirem, tenta apenas composição.

## 14. Erros conhecidos

ElevenLabs:

- `MISSING_ELEVENLABS_API_KEY`
- `MISSING_VOICE_ID`
- `EMPTY_NARRATION_TEXT`
- `NARRATION_TOO_LONG`
- `ELEVENLABS_HTTP_ERROR`
- `ELEVENLABS_REQUEST_ERROR`
- `ELEVENLABS_INVALID_RESPONSE`
- `AUDIO_SAVE_ERROR`

Veo e imagem:

- `MISSING_VIDEO_API_KEY`
- `MISSING_VIDEO_MODEL`
- `MISSING_VIDEO_BASE_URL`
- `MISSING_SELECTED_IMAGE`
- `IMAGE_FETCH_ERROR`
- `INVALID_IMAGE_MIME_TYPE`
- `INVALID_VIDEO_FORMAT`
- `IMAGE_PREPROCESSOR_UNAVAILABLE`
- `VEO_REQUEST_ERROR`
- `VEO_HTTP_ERROR`
- `VEO_OPERATION_TIMEOUT`
- `VEO_INVALID_RESPONSE`
- `VIDEO_SAVE_ERROR`
- `FFMPEG_UNAVAILABLE_AUDIO_NOT_STRIPPED`
- `FFMPEG_AUDIO_STRIP_ERROR`

4 clipes:

- `VEO_CLIP_1_ERROR`
- `VEO_CLIP_2_ERROR`
- `VEO_CLIP_3_ERROR`
- `VEO_CLIP_4_ERROR`
- `VEO_FOUR_CLIPS_ERROR`
- `VIDEO_CLIP_SAVE_ERROR`

Job:

- `JOB_NOT_FOUND`
- `MISSING_JOB_ID`
- `NO_SELECTED_IMAGES`
- `INSUFFICIENT_SELECTED_IMAGES`
- `INVALID_NARRATION_TYPE`
- `INVALID_VIDEO_FORMAT`
- `EMPTY_SCRIPT`

Composição externa:

- `COMPOSER_JOB_START_ERROR`
- `COMPOSER_STATUS_ERROR`
- `COMPOSER_RENDER_ERROR`
- `COMPOSER_TIMEOUT`
- `COMPOSER_ENDPOINT_MISSING`
- `COMPOSER_API_KEY_MISSING`
- `COMPOSER_REQUEST_ERROR`
- `COMPOSER_HTTP_ERROR`
- `COMPOSER_INVALID_RESPONSE`
- `FINAL_VIDEO_URL_MISSING`
- `LOCAL_FFMPEG_UNAVAILABLE`

Observação:

- Alguns códigos são reservados para contrato e podem não ser emitidos por todos os caminhos atuais.

## 15. Fora do escopo atual

Ainda não faz parte do contrato implementado:

- thumbnail final real;
- integração Seedance;
- integração MuAPI;
- UGC;
- providers alternativos públicos no frontend.

## 16. Progresso granular de clipes

Os endpoints de vídeo podem retornar campos auxiliares para acompanhamento visual:

```json
{
  "status": "generating_clip_2",
  "progress": 44,
  "progress_hint": 38,
  "current_clip_index": 2,
  "current_clip_attempt": 1,
  "clip_retry_count": 0,
  "clips": []
}
```

Regras:

- `progress_hint` é um piso visual seguro para a fase atual.
- `current_clip_index` indica o clipe em geração ou retry.
- `current_clip_attempt` indica a tentativa atual, de 1 a 3.
- Se `status` chegar atrasado como `generating_audio` ou `generating_narration`, o frontend pode inferir `generating_clip_X` quando `current_clip_index` ou `clips.length` indicar uma fase mais avançada.
- Com 4 clipes prontos e sem `final_video_url`, a UI deve mostrar composição em fila/processamento, não narração.

## 17. Imagens no formato para vídeo

Os endpoints de job podem retornar `video_frames`, com as imagens já preparadas no aspect ratio usado para gerar cada clipe no Veo. Publicamente, esses assets são exibidos como "Imagens no formato 9:16" ou "Imagens no formato 16:9":

```json
{
  "video_frames": [
    {
      "index": 1,
      "url": "https://.../uploads/stlai-vision-video/frames/stlai-veo-frame-xxx.jpg",
      "aspect_ratio": "9:16",
      "label": "Imagem 1",
      "width": 1080,
      "height": 1920
    }
  ]
}
```

Regras:

- `video_frames` é separado da galeria quadrada original.
- Cada item representa exatamente a imagem formatada enviada ao Veo para aquele clipe.
- Se a imagem formatada não existir, a UI não deve quebrar e deve apenas ocultar a seção.
- O vídeo não deve começar com uma imagem quadrada quando o usuário escolheu 9:16 ou 16:9.
- A imagem formatada não pode ser square foreground sobre blurred background, padding, barras ou moldura. Deve preencher o aspect ratio escolhido como asset comercial final.
- Os clipes também podem carregar metadados auxiliares `prepared_frame_url`, `prepared_frame_width`, `prepared_frame_height` e `aspect_ratio`.

## 18. Clip jobs incrementais e scripts de narração

Os endpoints de vídeo podem retornar estado granular por clipe. `script_public` é a copy limpa exibida ao usuário. `script_narration` é interno do job e não deve ser exposto no frontend.

```json
{
  "job_id": "stlai_video_xxx",
  "status": "generating_clips",
  "script_public": "Texto limpo exibido na UI.",
  "clip_jobs": [
    {
      "index": 1,
      "status": "ready",
      "attempt": 1,
      "url": "https://.../clip-1.mp4",
      "error": null
    },
    {
      "index": 2,
      "status": "generating",
      "attempt": 1,
      "url": "",
      "error": null
    },
    {
      "index": 3,
      "status": "pending",
      "attempt": 0,
      "url": "",
      "error": null
    },
    {
      "index": 4,
      "status": "pending",
      "attempt": 0,
      "url": "",
      "error": null
    }
  ],
  "clip_statuses": {
    "1": "ready",
    "2": "generating",
    "3": "pending",
    "4": "pending"
  },
  "clip_attempts": {
    "1": 1,
    "2": 1,
    "3": 0,
    "4": 0
  },
  "missing_clips": [2, 3, 4],
  "progress": 35,
  "progress_hint": 35
}
```

Status por clipe:

- `pending`
- `generating`
- `retrying`
- `ready`
- `error`

Status do job relacionados:

- `generating_clips`
- `generating_clip_1`
- `generating_clip_2`
- `generating_clip_3`
- `generating_clip_4`
- `retrying_clip_1`
- `retrying_clip_2`
- `retrying_clip_3`
- `retrying_clip_4`
- `clip_generation_error`

Regras:

- Cada clipe tem até 3 tentativas antes de virar `error`.
- Falhas temporárias incluem HTTP 408, 409, 429, 500, 502, 503, 504, timeout, resposta vazia e indisponibilidade temporária.
- O frontend deve exibir clipes `ready` imediatamente e placeholders para `pending`, `generating`, `retrying` e `error`.
- O progresso visual deve usar clipes prontos: base 25%, clipe 1 35%, clipe 2 50%, clipe 3 65%, clipe 4 78%, composição 82-96%, pronto 100%.
- `script_narration` pode conter marcações internas como `[thoughtful]`, `[short pause]`, `[warmly]`, `[excited]`, mas nunca deve aparecer na interface pública.

## 19. Renderer fast compose

O serviço externo de composição pode retornar campos de performance e transição:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "ready",
  "progress": 100,
  "final_video_url": "https://video-render.seudominio.com/renders/stlai-final-render_xxx.mp4",
  "duration": 72,
  "render_time_seconds": 38.5,
  "transition_used": "cut",
  "fallback_used": "",
  "fast_compose": true,
  "message": "Vídeo final composto com sucesso."
}
```

Campos:

- `render_time_seconds`: tempo total da composição no renderer.
- `transition_used`: `cut` no modo rápido/preview; `xfade` apenas quando explicitamente habilitado e suportado.
- `fallback_used`: vazio quando não houve fallback; `cut_without_fade` quando o xfade falhou; `normalized_cut` quando o concat rápido precisou voltar para normalização.
- `fast_compose`: booleano indicando que o renderer usou o caminho rápido.

Health check:

```json
{
  "ok": true,
  "ffmpeg": true,
  "quality": "preview",
  "xfade": false,
  "fast_compose": true
}
```

Regras:

- No Render Free, o padrão recomendado é `RENDER_OUTPUT_QUALITY=preview`, `FAST_COMPOSE=true`, `ENABLE_XFADE=false`.
- Preview 9:16 usa `406x720` por padrão.
- Preview 16:9 usa `1280x720` por padrão.
- O áudio original dos clipes deve ser ignorado; apenas a narração ElevenLabs é mapeada para o MP4 final.
- O vídeo final deve repetir os 4 clipes até cobrir a duração da narração e cortar exatamente na duração do áudio.
- Clipes Veo devem ser comerciais e limpos: sem REC, HUD, viewfinder, watermark, timestamp, texto, ícones, badges ou overlays.
