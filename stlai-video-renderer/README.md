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
ENABLE_XFADE=false
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
  "xfade": false
}
```

Se `ffmpeg` vier `false`, instale FFmpeg/FFprobe ou configure `FFMPEG_PATH` e `FFPROBE_PATH` no ambiente.

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
ENABLE_XFADE=false
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
  -e ENABLE_XFADE=false \
  stlai-video-renderer
```

Variáveis necessárias no serviço online:

- `PORT`: porta interna usada pelo Express. Padrão `3000`.
- `RENDER_API_KEY`: chave secreta enviada pelo plugin no header `Authorization: Bearer`.
- `PUBLIC_BASE_URL`: URL pública do serviço, por exemplo `https://video-render.seudominio.com`.
- `MAX_RENDER_SECONDS`: tempo máximo de renderização antes de abortar, por exemplo `300`.
- `RENDER_OUTPUT_QUALITY`: `preview` para Render Free ou `full` para renderização maior.
- `ENABLE_XFADE`: `false` por padrão. Use `true` apenas no modo `full` se houver memória suficiente.

O container instala FFmpeg e FFprobe via `apt-get`, não copia `.env`, não copia `node_modules` e ignora arquivos gerados em `temp/` e `renders/` durante o build.

## Segurança

- `/render` exige `Authorization: Bearer`.
- O serviço não aceita caminhos locais vindos do cliente.
- Só URLs `http` ou `https` são aceitas.
- Arquivos temporários são baixados para `temp/` com nomes únicos e removidos ao final.
- O MP4 final fica em `renders/`.
- A API key não é logada nem retornada.
- Erros retornam mensagem, código e debug resumido, sem stack trace completo.
- Jobs assíncronos são salvos em `temp/jobs/{render_job_id}.json`.

## Observações técnicas

- Em `RENDER_OUTPUT_QUALITY=preview`, a composição usa concatenação simples por padrão.
- Em `preview`, `9:16` gera `720x1280` e `16:9` gera `1280x720`.
- Em `RENDER_OUTPUT_QUALITY=full`, `9:16` gera `1080x1920` e `16:9` gera `1920x1080`.
- `xfade` fica desligado por padrão. Se `ENABLE_XFADE=true` e o modo for `full`, o serviço tenta fade entre clipes; se falhar, cai automaticamente para concatenação simples.
- O vídeo é escalado com `force_original_aspect_ratio=increase` e `crop`, evitando distorção.
- O áudio nativo dos clipes é ignorado; apenas o áudio ElevenLabs é mapeado no MP4 final.
