Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/video-pipeline.md pelo conteúdo abaixo:

# Video Pipeline — STLAI Vision Ads Pro

## Objetivo deste documento

Este documento descreve o pipeline de geração de vídeo do plugin STLAI Vision Ads Pro.

Ele deve orientar a futura implementação do passo 5 do wizard, garantindo que a funcionalidade de vídeo seja criada de forma incremental, segura e modular.

A implementação inicial será focada em vídeo comercial automático com:

- Veo 3.1 Lite
- modelo técnico: veo-3.1-lite-generate-preview
- ElevenLabs para narração
- composição final com repetição de clipes e fade

Atualização 2026-05-21: no plugin atual, os 4 clipes são disparados por AJAX semi-paralelo, com 1 segundo de diferença entre cada início. O polling acompanha o job e a composição, mas não deve iniciar clipes automaticamente. A composição externa pode mixar música de fundo opcional via renderer, nunca via ElevenLabs.

Atualização 2026-05-21: a composição final externa não pode ficar indefinidamente em fila. O `POST /render` pode responder `ready` com `final_video_url` ou responder `queued/processing` com `render_job_id`; nesse caso o WordPress consulta `GET /render/:render_job_id`. Timeouts viram `composition_error` recuperável, preservando narração, clipes e imagens no formato para tentar novamente só a composição.

Atualização 2026-05-21: se um job legado ficar em `composition_queued` sem `render_job_id`, mas tiver áudio e 4 clipes prontos, o polling deve iniciar `POST /render` automaticamente. A resposta AJAX deve expor diagnóstico seguro de composição para depuração no DevTools.

UGC com Seedance 2.0 / BytePlus ModelArk e MuAPI fica reservado para roadmap futuro.

## Escopo do MVP

O MVP inicial de vídeo deve permitir que o usuário:

1. Escolha o tipo de narração.
2. Selecione de 4 a 8 imagens geradas.
3. Gere 4 clipes comerciais de 8 segundos.
4. Gere uma narração com ElevenLabs.
5. Monte um vídeo final narrado.
6. Visualize o vídeo final.
7. Baixe o vídeo final.
8. Veja o vídeo final também no resumo.

## Fora do escopo do MVP

Não implementar agora:

- UGC com Seedance 2.0.
- MuAPI.
- editor manual de timeline.
- escolha avançada de avatar.
- múltiplos locutores por vídeo.
- legendas automáticas.
- histórico completo de vídeos.
- billing real por créditos.
- renderização distribuída.
- painel avançado de templates UGC.

## Entrada do pipeline

O pipeline de vídeo deve receber dados já existentes no fluxo do plugin.

Dados principais:

- nome do produto
- descrição/contexto
- características
- títulos aprovados
- idioma
- plano
- imagens geradas
- imagens selecionadas para vídeo
- tipo de narração escolhido
- configurações do plugin/admin

## Dados vindos do passo 2

No passo 2, o usuário deverá escolher o tipo de narração.

Tipos iniciais:

- emocional
- persuasiva

Valor padrão:

persuasiva

Estado frontend sugerido:

```js
S.voiceStyle = "persuasiva";
