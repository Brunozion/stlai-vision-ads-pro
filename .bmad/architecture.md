Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/architecture.md pelo conteúdo abaixo:

# Architecture — STLAI Vision Ads Pro

## Objetivo deste documento

Este documento descreve a arquitetura atual do plugin STLAI Vision Ads Pro e a arquitetura planejada para a implementação da etapa de vídeo.

A finalidade é orientar o Codex/Antigravity para que qualquer alteração seja incremental, segura e compatível com o código existente.

Antes de qualquer alteração técnica, este arquivo deve ser lido junto com:

- .bmad/project-brief.md
- .bmad/active-context.md
- .bmad/video-pipeline.md
- .bmad/implementation-rules.md
- .bmad/api-contracts.md
- .bmad/decisions.md

## Estrutura atual do plugin

A estrutura principal atual do plugin é:

```txt
stlai-vision-ads-pro/
  ├── admin/
  │   └── settings-page.php
  ├── assets/
  │   ├── css/
  │   │   └── style.css
  │   └── js/
  │       └── app.js
  ├── frontend/
  │   └── shortcode.php
  ├── includes/
  │   └── market-analysis.php
  └── stlai-vision-ads-pro.php