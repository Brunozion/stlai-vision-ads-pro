# STLAI Video Renderer

Microserviço externo de composição de vídeo para o plugin STLAI Vision Ads Pro.

Ele recebe 4 clipes + áudio ElevenLabs, baixa os arquivos em `temp/`, compõe o MP4 final com FFmpeg e publica o resultado em `/renders`.

## Requisitos

- Node.js 18+
- FFmpeg e FFprobe instalados no servidor
- Um domínio ou subdomínio apontando para este serviço em produção

## Instalação local

```bash
cd stlai-video-renderer
npm install
cp .env.example .env
```

Edite `.env`:

```env
PORT=3000
RENDER_API_KEY=uma-chave-forte-aqui
PUBLIC_BASE_URL=http://localhost:3000
MAX_RENDER_SECONDS=300
RENDER_OUTPUT_QUALITY=preview
FAST_COMPOSE=true
ENABLE_XFADE=false
RENDER_PREVIEW_WIDTH_9_16=720
RENDER_PREVIEW_HEIGHT_9_16=1280
RENDER_PREVIEW_WIDTH_16_9=1280
RENDER_PREVIEW_HEIGHT_16_9=720
ENABLE_BACKGROUND_MUSIC=false
BACKGROUND_MUSIC_URL=
BACKGROUND_MUSIC_VOLUME=0.06
```

## Rodar localmente

```bash
npm run dev
```

Ou:

```bash
npm start
```

## Testar health check

```bash
curl http://localhost:3000/health
```

Resposta esperada:

```json
{
  "ok": true,
  "ffmpeg": true,
  "quality": "preview",
  "xfade": false,
  "fast_compose": true,
  "background_music": false,
  "background_music_volume": 0
}
```

Se `ffmpeg` vier `false`, instale FFmpeg/FFprobe ou configure `FFMPEG_PATH` e `FFPROBE_PATH` no ambiente.

## Keepalive no Render Free

O Render Free pode suspender o serviço após inatividade. Para reduzir cold start temporariamente, use um cron externo chamando o endpoint público de ping.

Endpoint:

```bash
curl https://stlai-video-renderer.onrender.com/ping
```

Resposta esperada:

```json
{
  "ok": true,
  "service": "stlai-video-renderer",
  "ts": "2026-05-23T00:00:00.000Z"
}
```

Configuração sugerida no cron-job.org:

- Tipo: HTTP GET
- URL: `https://stlai-video-renderer.onrender.com/ping`
- Intervalo: a cada 10 minutos
- Timeout: padrão
- Headers: nenhum header especial; não precisa `Authorization`

Esse ping não melhora a CPU do plano gratuito. Ele apenas ajuda a evitar que o serviço durma e reduz o cold start antes da composição.

## Testar render assíncrono com curl

Iniciar composição:

```bash
curl -X POST http://localhost:3000/render \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer uma-chave-forte-aqui" \
  -d '{
    "job_id": "stlai_video_teste",
    "format": "9:16",
    "audio_url": "https://seu-dominio.com/audio.mp3",
    "clips": [
      { "index": 1, "role": "apresentacao_geral", "url": "https://seu-dominio.com/clip1.mp4" },
      { "index": 2, "role": "uso_contexto", "url": "https://seu-dominio.com/clip2.mp4" },
      { "index": 3, "role": "detalhe_acabamento", "url": "https://seu-dominio.com/clip3.mp4" },
      { "index": 4, "role": "hero_fechamento", "url": "https://seu-dominio.com/clip4.mp4" }
    ],
    "transition": "fade",
    "enable_fade": true,
    "fade_duration": 0.4,
    "repeat_clips_until_audio_ends": true,
    "trim_to_audio_duration": true,
    "remove_clip_audio": true
  }'
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

O `POST /render` é assíncrono: ele valida o payload, cria `render_job_id`, grava `temp/jobs/{render_job_id}.json` e responde rápido. O FFmpeg roda em background e o status deve ser acompanhado por `GET /render/:render_job_id`.

Consultar status:

```bash
curl http://localhost:3000/render/render_xxx \
  -H "Authorization: Bearer uma-chave-forte-aqui"
```

Enquanto processando:

```json
{
  "success": true,
  "render_job_id": "render_xxx",
  "status": "processing",
  "progress": 40,
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
  "final_video_url": "http://localhost:3000/renders/stlai-final-render_xxx.mp4",
  "duration": 72,
  "render_time_seconds": 38.5,
  "transition_used": "cut",
  "fallback_used": "",
  "fast_compose": true,
  "message": "Vídeo final composto com sucesso."
}
```

## Configurar no plugin WordPress

No painel do STLAI Vision Ads Pro, configure:

- `videoComposerMode`: `external_service`
- `videoComposerEndpoint`: `https://DOMINIO/render`
- `videoComposerApiKey`: a mesma chave definida em `RENDER_API_KEY`
- `videoComposerTimeout`: `300`

Não coloque a API key no frontend público.

## Instalar FFmpeg no Ubuntu

```bash
sudo apt update
sudo apt install -y ffmpeg
ffmpeg -version
ffprobe -version
```

## Deploy simples em VPS

1. Envie a pasta `stlai-video-renderer` para a VPS.
2. Instale dependências:

```bash
npm install --omit=dev
```

3. Configure `.env`:

```env
PORT=3000
RENDER_API_KEY=uma-chave-longa-e-secreta
PUBLIC_BASE_URL=https://video-render.seudominio.com
MAX_RENDER_SECONDS=300
RENDER_OUTPUT_QUALITY=preview
FAST_COMPOSE=true
ENABLE_XFADE=false
RENDER_PREVIEW_WIDTH_9_16=720
RENDER_PREVIEW_HEIGHT_9_16=1280
RENDER_PREVIEW_WIDTH_16_9=1280
RENDER_PREVIEW_HEIGHT_16_9=720
ENABLE_BACKGROUND_MUSIC=false
BACKGROUND_MUSIC_URL=
BACKGROUND_MUSIC_VOLUME=0.06
```

4. Rode com PM2:

```bash
npm install -g pm2
pm2 start server.js --name stlai-video-renderer
pm2 save
pm2 startup
```

5. Coloque Nginx como proxy reverso para `http://127.0.0.1:3000`.

Exemplo de bloco Nginx:

```nginx
server {
    server_name video-render.seudominio.com;

    client_max_body_size 2m;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 360s;
    }
}
```

Depois habilite HTTPS com Certbot ou outro gerenciador de certificado.

## Deploy com Docker

Build da imagem:

```bash
cd stlai-video-renderer
docker build -t stlai-video-renderer .
```

Rodar localmente:

```bash
docker run --rm -p 3000:3000 \
  -e PORT=3000 \
  -e RENDER_API_KEY=uma-chave-forte-aqui \
  -e PUBLIC_BASE_URL=http://localhost:3000 \
  -e MAX_RENDER_SECONDS=300 \
  -e RENDER_OUTPUT_QUALITY=preview \
  -e FAST_COMPOSE=true \
  -e ENABLE_XFADE=false \
  -e RENDER_PREVIEW_WIDTH_9_16=720 \
  -e RENDER_PREVIEW_HEIGHT_9_16=1280 \
  -e RENDER_PREVIEW_WIDTH_16_9=1280 \
  -e RENDER_PREVIEW_HEIGHT_16_9=720 \
  -e ENABLE_BACKGROUND_MUSIC=false \
  -e BACKGROUND_MUSIC_VOLUME=0.06 \
  stlai-video-renderer
```

Variáveis necessárias no serviço online:

- `PORT`: porta interna usada pelo Express. Padrão `3000`.
- `RENDER_API_KEY`: chave secreta enviada pelo plugin no header `Authorization: Bearer`.
- `PUBLIC_BASE_URL`: URL pública do serviço, por exemplo `https://video-render.seudominio.com`.
- `MAX_RENDER_SECONDS`: tempo máximo de renderização antes de abortar, por exemplo `300`.
- `RENDER_OUTPUT_QUALITY`: `preview` para Render Free ou `full` para renderização maior.
- `FAST_COMPOSE`: `true` por padrão. Usa concatenação rápida em uma passagem de FFmpeg, recomendado para Render Free.
- `ENABLE_XFADE`: `false` por padrão. Use `true` apenas em instância maior e com `FAST_COMPOSE=false`.
- `RENDER_PREVIEW_WIDTH_9_16` / `RENDER_PREVIEW_HEIGHT_9_16`: resolução do preview vertical. Padrão `720x1280`. Em Render Free com pouca memória, use `540x960` ou `406x720`.
- `RENDER_PREVIEW_WIDTH_16_9` / `RENDER_PREVIEW_HEIGHT_16_9`: resolução do preview horizontal. Padrão `1280x720`.
- `ENABLE_BACKGROUND_MUSIC`: `false` por padrão. Quando `true`, o renderer tenta mixar uma música de fundo configurada.
- `BACKGROUND_MUSIC_URL`: URL `http`/`https` do arquivo de música. Se vazio, nenhuma música é adicionada.
- `BACKGROUND_MUSIC_VOLUME`: volume da música de fundo. Padrão `0.06`; recomendado entre `0.05` e `0.12`.

O container instala FFmpeg e FFprobe via `apt-get`, não copia `.env`, não copia `node_modules` e ignora arquivos gerados em `temp/` e `renders/` durante o build.

## Segurança

- `/render` exige `Authorization: Bearer`.
- O serviço não aceita caminhos locais vindos do cliente.
- Só URLs `http` ou `https` são aceitas.
- Arquivos temporários são baixados para `temp/` com nomes únicos e removidos ao final.
- O MP4 final fica em `renders/`.
- A API key não é logada nem retornada.
- Erros retornam mensagem, código e debug resumido, sem stack trace completo.
- Jobs assíncronos são salvos em `temp/jobs/{render_job_id}.json`. Se o JSON não estiver disponível mas o arquivo final `renders/stlai-final-{render_job_id}.mp4` existir, `GET /render/:render_job_id` retorna `ready` com a URL final.
- Os logs seguros mostram `render_job_id`, quantidade de clipes, presença de áudio, duração detectada, início/fim do FFmpeg, `render_time_seconds`, `final_video_url` e erro resumido. A API key não é logada.

## Observações técnicas

- Em `RENDER_OUTPUT_QUALITY=preview`, a composição usa concatenação simples por padrão.
- Em `FAST_COMPOSE=true`, o renderer concatena os clipes originais e aplica escala/corte, corte na duração da narração e áudio final em uma única passagem do FFmpeg. Se o concat direto falhar, faz fallback para normalização dos clipes e concatenação simples.
- Em `preview`, `9:16` gera `720x1280` por padrão e `16:9` gera `1280x720`. Para Render Free, `406x720` continua sendo o fallback mais leve via env.
- Em `RENDER_OUTPUT_QUALITY=full`, `9:16` gera `1080x1920` e `16:9` gera `1920x1080`.
- `transition_used` é `"cut"` por padrão no MVP preview.
- `xfade` fica desligado por padrão. Para ativar fade com segurança, use `RENDER_OUTPUT_QUALITY=full`, `FAST_COMPOSE=false`, `ENABLE_XFADE=true` e envie `enable_fade: true` no POST. Se o xfade falhar, o job continua com corte simples e retorna `fallback_used: "cut_without_fade"`.
- O vídeo é escalado com `force_original_aspect_ratio=increase` e `crop`, evitando distorção.
- O áudio nativo dos clipes é ignorado; a narração ElevenLabs é sempre a faixa principal, em AAC 128k no preview.
- Música de fundo é opcional e só entra se `ENABLE_BACKGROUND_MUSIC=true` e `BACKGROUND_MUSIC_URL` estiver configurada. Se o download ou mixagem falhar, o renderer não derruba o job: compõe com voz pura e retorna `fallback_used: "music_unavailable_voice_only"`.
- Se o WordPress ficar muito tempo em `composition_queued`, `composition_processing` ou `composition_waiting`, verifique os logs do renderer pelo `render_job_id` e consulte `GET /render/:render_job_id`. O plugin deve manter polling em soft timeout e só transformar em erro no hard timeout ou em erro explícito do renderer, preservando áudio e clipes.

## Render Free recomendado

Para reduzir risco de timeout e memória no Render Free:

```env
RENDER_OUTPUT_QUALITY=preview
FAST_COMPOSE=true
ENABLE_XFADE=false
RENDER_PREVIEW_WIDTH_9_16=406
RENDER_PREVIEW_HEIGHT_9_16=720
RENDER_PREVIEW_WIDTH_16_9=1280
RENDER_PREVIEW_HEIGHT_16_9=720
ENABLE_BACKGROUND_MUSIC=false
BACKGROUND_MUSIC_URL=
BACKGROUND_MUSIC_VOLUME=0.06
```

Esse modo prioriza estabilidade: transição em corte simples, H.264 baseline no preview, `preset ultrafast`, `crf 28`, 30fps e áudio AAC 128k. O fade/xfade fica para instâncias maiores ou produção.
