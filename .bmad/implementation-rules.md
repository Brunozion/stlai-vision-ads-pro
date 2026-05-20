Preencha apenas o arquivo:

.bmad/implementation-rules.md

Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/implementation-rules.md pelo conteúdo abaixo:

# Implementation Rules — STLAI Vision Ads Pro

## Objetivo deste documento

Este documento define as regras obrigatórias para qualquer implementação futura no plugin STLAI Vision Ads Pro.

O objetivo é impedir regressões, proteger o fluxo existente e garantir que a funcionalidade de vídeo seja implementada de forma incremental, modular e segura.

Este arquivo deve ser lido antes de qualquer alteração no código.

## Regra principal

Não reescrever o plugin inteiro.

A implementação deve ser feita em etapas pequenas, sempre preservando o que já funciona.

O plugin atual já possui:

- fluxo visual de 6 etapas;
- upload de imagens;
- leitura visual com IA;
- preenchimento automático de contexto;
- geração de títulos e descrição;
- aprovação de textos;
- geração de imagens comerciais;
- seleção de imagens para vídeo;
- placeholder visual do passo 5;
- resumo e exportação;
- painel administrativo de configurações.

Nada disso deve ser removido ou quebrado.

## Arquivos que não devem ser alterados sem necessidade

Evitar alterações desnecessárias em:

- stlai-vision-ads-pro.php
- frontend/shortcode.php
- assets/js/app.js
- assets/css/style.css
- admin/settings-page.php
- includes/market-analysis.php

Se for necessário alterar algum deles, explicar antes:

1. por que precisa alterar;
2. o que será alterado;
3. quais riscos existem;
4. como testar depois.

## Regra de documentação antes de código

Antes de alterar qualquer arquivo PHP, JS ou CSS, o Codex deve ler:

- .bmad/project-brief.md
- .bmad/architecture.md
- .bmad/active-context.md
- .bmad/video-pipeline.md
- .bmad/implementation-rules.md
- .bmad/api-contracts.md
- .bmad/decisions.md

Depois deve responder com:

1. resumo do contexto atual;
2. arquivos que pretende alterar;
3. plano da alteração;
4. riscos;
5. checklist de teste.

Só depois da aprovação o código deve ser alterado.

## Regra de uma story por vez

Implementar apenas uma tarefa por vez.

Não misturar em uma única alteração:

- admin settings;
- frontend;
- backend;
- providers;
- composer;
- CSS;
- resumo;
- UGC futuro.

Cada story deve ter objetivo claro.

Exemplo correto:

```txt
Story: adicionar campos de configuração do ElevenLabs no admin.