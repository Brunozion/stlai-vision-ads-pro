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
  "ffmpeg": true
}
```

Se `ffmpeg` vier `false`, instale FFmpeg/FFprobe ou configure `FFMPEG_PATH` e `FFPROBE_PATH` no ambiente.

## Testar render com curl

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

Resposta de sucesso:

```json
{
  "success": true,
  "final_video_url": "http://localhost:3000/renders/stlai-final-....mp4",
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

## Segurança

- `/render` exige `Authorization: Bearer`.
- O serviço não aceita caminhos locais vindos do cliente.
- Só URLs `http` ou `https` são aceitas.
- Arquivos temporários são baixados para `temp/` com nomes únicos e removidos ao final.
- O MP4 final fica em `renders/`.
- A API key não é logada nem retornada.
- Erros retornam mensagem, código e debug resumido, sem stack trace completo.

## Observações técnicas

- A composição usa `xfade` com fade padrão de `0.4s` entre clipes.
- Para `9:16`, a saída é normalizada em `1080x1920`.
- Para `16:9`, a saída é normalizada em `1920x1080`.
- O vídeo é escalado com `force_original_aspect_ratio=increase` e `crop`, evitando distorção.
- O áudio nativo dos clipes é ignorado; apenas o áudio ElevenLabs é mapeado no MP4 final.
