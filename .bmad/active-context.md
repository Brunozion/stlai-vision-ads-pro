Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/active-context.md pelo conteúdo abaixo:

# Active Context — STLAI Vision Ads Pro

## Estado atual

Estamos preparando o plugin STLAI Vision Ads Pro para receber a funcionalidade real de vídeo.

O plugin já está baixado localmente e aberto no Antigravity/Codex.

A pasta `.bmad` já foi criada na raiz do workspace e está sendo usada como memória permanente do projeto.

Arquivos já criados na memória do projeto:

- .bmad/project-brief.md
- .bmad/architecture.md
- .bmad/active-context.md
- .bmad/video-pipeline.md
- .bmad/implementation-rules.md
- .bmad/api-contracts.md
- .bmad/decisions.md

## Situação do plugin

O plugin atual já possui o fluxo principal visual e funcional em 6 etapas:

1. Upload das imagens do produto.
2. Leitura visual com IA e configuração do contexto.
3. Geração e aprovação de títulos e descrição.
4. Geração de imagens comerciais.
5. Tela de vídeo ainda como placeholder.
6. Resumo e exportação.

A etapa 5 já existe visualmente, mas ainda não gera vídeo real.

## Objetivo atual da fase

Antes de implementar código, estamos documentando a arquitetura e as regras do projeto.

A implementação real ainda não começou.

Não devemos alterar arquivos PHP, JS ou CSS até que os arquivos `.bmad` principais estejam preenchidos.

## Decisão principal tomada

A funcionalidade de vídeo será implementada dentro do plugin atual, não como projeto separado.

A implementação será modular e segura.

A lógica pesada de vídeo ficará no backend PHP, em um módulo próprio dentro de:

includes/video/

O frontend `app.js` deve controlar apenas:

- estado da interface
- seleção do usuário
- chamadas AJAX
- polling de status
- exibição de preview
- exibição de erro
- liberação de download

## Pipeline aprovado para o MVP de vídeo comercial

O MVP inicial de vídeo comercial seguirá este fluxo:

1. Usuário escolhe o tipo de narração no passo 2.
2. Usuário seleciona de 4 a 8 imagens no passo 4.
3. Sistema gera 4 clipes de vídeo com Veo 3.1 Lite.
4. Cada clipe terá 8 segundos.
5. Sistema gera narração com ElevenLabs.
6. Sistema compõe o vídeo final com os 4 clipes.
7. Se o áudio for maior que 32 segundos, os clipes se repetem em sequência.
8. O vídeo final deve aplicar fade ou transição suave entre clipes.
9. O vídeo final deve ser cortado na duração exata da narração.
10. O usuário poderá visualizar e baixar o vídeo final.
11. O vídeo final também aparecerá no resumo.

## Tipos iniciais de narração

Inicialmente teremos dois tipos:

- emocional
- persuasiva

O padrão será:

persuasiva

## Providers definidos

Providers do MVP:

- Veo 3.1 Lite para vídeo comercial.
- ElevenLabs para narração.

Providers futuros planejados:

- Seedance 2.0 via BytePlus ModelArk para UGC.
- MuAPI como provider opcional de teste/comparação.
- API direta de fornecedores UGC sempre que possível.

## Roadmap futuro de UGC

UGC não faz parte do MVP inicial.

A arquitetura, porém, deve ficar preparada para suportar UGC no futuro.

Tipos futuros de UGC:

- unboxing
- demonstração de uso
- review curto
- comparação
- apresentação de benefício
- vídeo estilo criador de conteúdo
- oferta direta
- vídeo educativo curto

Referência futura:

https://docs.byteplus.com/en/docs/ModelArk/1520757

## Regras de segurança atuais

- Não expor API keys no frontend.
- Não enviar ElevenLabs API key no `wp_localize_script`.
- Não enviar chaves de vídeo/UGC no `wp_localize_script`.
- Usar backend PHP para chamadas sensíveis.
- Usar AJAX para comunicação frontend/backend.
- Sanitizar inputs.
- Validar quantidade de imagens.
- Registrar erros sem vazar segredos.

## Próxima etapa de documentação

Após este arquivo, os próximos arquivos a preencher são:

1. .bmad/video-pipeline.md
2. .bmad/implementation-rules.md
3. .bmad/api-contracts.md
4. .bmad/decisions.md

## Próxima etapa de implementação futura

A implementação só deve começar depois que todos os documentos principais estiverem preenchidos.

Primeira implementação futura provável:

- adicionar campos de configuração de vídeo e ElevenLabs no admin/settings-page.php

Mas ainda não implementar agora.

## Instrução para o Codex

Antes de qualquer alteração futura no código, o Codex deve:

1. Ler todos os arquivos `.bmad`.
2. Resumir o contexto.
3. Listar quais arquivos pretende alterar.
4. Explicar o plano.
5. Aguardar aprovação antes de modificar PHP, JS ou CSS.

Depois de preencher o arquivo, confirme:
- que apenas .bmad/active-context.md foi alterado;
- que nenhum arquivo PHP, JS ou CSS foi modificado.

## 2026-05-19 - Story 1 concluida

Story implementada:

- Adicionar configuracoes administrativas para video comercial e ElevenLabs.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/admin/settings-page.php
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Adicionada a secao Configuracoes de Video Comercial na pagina Config. de IA.
- Adicionados os campos videoProvider, videoModel, videoApiKey e videoBaseUrl.
- Adicionada a secao Configuracoes de Narracao / ElevenLabs.
- Adicionados os campos audioProvider, elevenLabsApiKey, elevenLabsVoiceEmotional, elevenLabsVoicePersuasive, elevenLabsModel e elevenLabsDefaultLanguage.
- Adicionada a secao Configuracoes Futuras de UGC para roadmap, sem uso no MVP.
- Adicionados os campos ugcProvider, seedanceApiKey, seedanceBaseUrl, seedanceModel, muApiKey e muApiBaseUrl.
- Mantido o armazenamento em stlai_vision_ads_pro_settings.
- Mantido o sanitize/merge existente.
- Nenhum campo novo foi exposto no wp_localize_script.

Arquivos que nao foram alterados:

- assets/js/app.js
- assets/css/style.css
- frontend/shortcode.php
- stlai-vision-ads-pro.php
- includes/market-analysis.php
- includes/video/

Status:

- Story 1 concluida.
- Proxima story recomendada: adicionar escolha de narracao emocional/persuasiva no passo 2, ainda sem chamar APIs externas.

## 2026-05-19 - Story 2A concluida

Story implementada:

- Adicionar escolha de tipo de narracao no passo 2 do wizard.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Adicionada a secao "Narracao do video" no passo 2.
- Adicionadas as opcoes Persuasiva e Emocional com radio buttons.
- Definido o valor padrao visual como `persuasiva`.
- Adicionado `voiceStyle: "persuasiva"` ao estado global `S`.
- Atualizada `colForm()` para coletar `input[name="voice_style"]` e manter `persuasiva` como fallback.
- Adicionado CSS pequeno para manter os cards de narracao alinhados ao visual atual.

O que nao foi feito:

- Nenhuma chamada para ElevenLabs.
- Nenhuma chamada para Veo.
- Nenhuma integracao de API.
- Nenhuma alteracao em admin/backend.

## 2026-05-20 - Story 8B concluida

Story implementada:

- Preparar composição final via serviço externo de renderização com FFmpeg.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/admin/settings-page.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-composer-provider.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/frontend/shortcode.php
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- Adicionada a seção administrativa "Configuração de Composição de Vídeo".
- Adicionados `videoComposerMode`, `videoComposerEndpoint`, `videoComposerApiKey` e `videoComposerTimeout`.
- Criado `STLAI_Video_Composer_Provider` para enviar áudio e 4 clipes ao serviço externo.
- Definido `external_service` como modo padrão de composição.
- O provider envia `Authorization: Bearer` apenas no backend.
- O job salva `final_video_url`, `final_video_duration`, `composer_mode`, `composer_provider` e `composed_at` quando a composição externa retorna sucesso.
- Se a composição falhar, o job fica em `composition_pending` ou `composition_error`, preservando narração e clipes.
- O frontend mostra vídeo final quando existir e mantém áudio/clipes separados abaixo.
- Sem vídeo final, o frontend mostra composição pendente e mantém ativos visíveis.

O que nao foi feito:

- Nenhuma alteração nos providers ElevenLabs ou Veo.
- Nenhuma alteração em geração de textos, imagens, Seedance ou MuAPI.
- Nenhuma API key foi exposta no frontend.

Status:

- Story 8B concluida.

## 2026-05-19 - Story 2B concluida

Story implementada:

- Ajustar selecao de imagens para video no passo 4, mudando a regra antiga para 4 a 8 imagens.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Atualizados os textos do passo 4 para orientar selecao de 4 a 8 imagens.
- Atualizado o contador de selecao de video para `0/8` e `X/8`.
- Alterado o limite maximo de selecao para 8 imagens.
- Adicionada validacao antes de ir ao passo 5, exigindo pelo menos 4 imagens.
- Removido o fallback visual antigo que levava 3 imagens nao selecionadas para o preview de video.

O que nao foi feito:

- Nenhuma chamada para Veo.
- Nenhuma chamada para ElevenLabs.
- Nenhuma chamada para Seedance ou MuAPI.
- Nenhuma integracao de API.
- Nenhuma alteracao em admin/backend.

Status:

- Story 2B concluida.
- Proxima story recomendada: preparar o passo 5 para exibir preview/download do video comercial quando a geracao real for implementada.

## 2026-05-19 - Story 3 concluida

Story implementada:

- Transformar o passo 5 de video em uma tela visual real de preparacao, ainda sem chamar APIs.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Removido o visual bloqueado com blur e texto antigo de "em breve" no passo 5.
- Adicionada tela de preparacao com imagens selecionadas para video.
- Adicionada exibicao do tipo de narracao escolhido no passo 2.
- Adicionado resumo visual do pipeline: 4 clipes, 8 segundos cada, Veo 3.1 Lite e ElevenLabs.
- Adicionadas opcoes de formato: 16:9 Horizontal, 9:16 Vertical e 1:1 Quadrado.
- Adicionado estado frontend `S.video` com status, formato e flag mock.
- Adicionadas funcoes frontend para selecionar formato, renderizar status e simular preparacao de video.
- O botao "Gerar video" apenas prepara o mock e informa que a integracao de API vira depois.
- O botao "Ver resumo" continua levando para o passo 6 sem exigir video gerado.

O que nao foi feito:

- Nenhuma chamada para Veo.
- Nenhuma chamada para ElevenLabs.
- Nenhuma chamada para backend ou AJAX.
- Nenhuma alteracao em admin/backend.
- Nenhuma integracao real de video.

Status:

- Story 3 concluida.
- Proxima story recomendada: atualizar o resumo do passo 6 para refletir o estado preparado do video quando necessario, ainda sem gerar video real.

## 2026-05-19 - Story 3B concluida

Story implementada:

- Refinar a UX do passo 5 e adicionar roteiro editavel da narracao, sem chamar APIs.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Removidos nomes tecnicos de providers da interface publica do passo 5.
- A interface publica agora usa "Geracao IA Premium" e "Voz profissional IA".
- Adicionada area "Roteiro da narracao" com textarea editavel.
- Adicionado botao "Regenerar roteiro" com geracao local/mock.
- O estado `S.video` agora preserva `script`, `scriptEdited` e o estilo usado no roteiro.
- `popVid()` gera roteiro inicial local quando necessario e preserva edicoes manuais.
- Se a narracao mudar e o usuario ainda nao tiver editado o roteiro, o roteiro pode ser regenerado automaticamente ao entrar no passo 5.
- O mock "Gerar video" agora menciona formato, narracao e roteiro revisado, sem citar providers.

O que nao foi feito:

- Nenhuma chamada para API de video.
- Nenhuma chamada para API de audio.
- Nenhuma chamada AJAX/backend.
- Nenhuma alteracao em admin/backend.

Status:

- Story 3B concluida.
- Proxima story recomendada: refletir o estado e roteiro de video no resumo do passo 6, ainda sem geracao real.

## 2026-05-19 - Story 4 concluida

Story implementada:

- Criar a estrutura backend inicial de video em `includes/video/` com endpoints AJAX mock, sem chamar APIs externas.

Arquivos criados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php

Arquivos alterados nesta story:

- stlai-vision-ads-pro/stlai-vision-ads-pro.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Criado storage mock de jobs de video usando transient.
- Criado service de jobs com validacao de payload.
- Criados endpoints AJAX:
  - `stlai_create_video_job`
  - `stlai_check_video_status`
  - `stlai_get_video_result`
- Registradas versoes `wp_ajax_` e `wp_ajax_nopriv_` dos endpoints para manter o shortcode publico funcionando.
- O arquivo principal passou apenas a carregar os novos arquivos de video.
- O botao "Gerar video" no frontend agora cria job mock via AJAX.
- O frontend envia imagens selecionadas, tipo de narracao, formato, roteiro, nome e descricao do produto.
- O frontend salva `S.video.jobId`, atualiza status visual e faz polling mock ate `ready`.
- O status mock progride por `queued`, `generating_clips`, `composing` e `ready`.

O que nao foi feito:

- Nenhuma chamada para Veo.
- Nenhuma chamada para ElevenLabs.
- Nenhuma chamada para Seedance, MuAPI, OpenAI ou Gemini para video.
- Nenhuma integracao real de video.
- Nenhuma alteracao em admin/settings-page.php.
- Nenhuma alteracao em includes/market-analysis.php.
- Nenhuma alteracao em CSS.

Status:

- Story 4 concluida.
- Proxima story recomendada: conectar o resultado mock do video ao passo 6/resumo, ainda sem video real.

## 2026-05-19 - Story 5 concluida

Story implementada:

- Integrar o resultado mock do video no passo 6 - Resumo, sem chamar API externa e sem gerar video real.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Substituido o placeholder antigo de video no resumo por um bloco real de status.
- Adicionados os IDs `sum-video-card`, `sum-video-status`, `sum-video-format`, `sum-video-narration`, `sum-video-script` e `sum-video-note`.
- `popSum()` agora renderiza o estado de video com base em `S.video.status`, `S.video.format`, `S.voiceStyle`, `S.video.script` e `S.video.jobId`.
- Quando o job mock esta `ready`, o resumo mostra "Video preparado", formato, narracao, previa curta do roteiro e o label "Pronto para integracao".
- Quando o video nao foi preparado, o resumo mostra o estado discreto "Video ainda nao gerado.".
- O score passou a refletir visualmente se o video mock esta preparado ou pendente, sem alterar agressivamente a pontuacao.

O que nao foi feito:

- Nenhuma chamada para Veo.
- Nenhuma chamada para ElevenLabs.
- Nenhuma chamada para Seedance, MuAPI, OpenAI ou Gemini para video.
- Nenhuma geracao real de video.
- Nenhuma alteracao em admin/backend.
- Nenhuma alteracao em `includes/video/`.

Status:

- Story 5 concluida.
- Proxima story recomendada: preparar o contrato de resultado final do video para quando houver URL real de preview/download.

## 2026-05-19 - Story 6 concluida

Story implementada:

- Integrar ElevenLabs real para gerar audio da narracao a partir do roteiro editavel do passo 5, sem chamar Veo e sem compor video final.

Arquivos criados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-elevenlabs-provider.php

Arquivos alterados nesta story:

- stlai-vision-ads-pro/stlai-vision-ads-pro.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Criado provider backend `STLAI_ElevenLabs_Provider`.
- O provider le configuracoes de ElevenLabs em `stlai_vision_ads_pro_settings`.
- A voz e escolhida por `narration_type`, com fallback entre voz persuasiva e emocional.
- O modelo usa `elevenLabsModel` ou fallback `eleven_multilingual_v2`.
- O roteiro e validado com limite de 2500 caracteres.
- A chamada sensivel usa `wp_remote_post` no backend, sem expor API key no frontend.
- O audio retornado e salvo em `uploads/stlai-vision-audio/`.
- O job de video agora gera narracao real antes de continuar o pipeline mock.
- O AJAX retorna `audio_url` quando a narracao e gerada.
- O passo 5 exibe player de audio quando `audio_url` existe.
- O passo 6/resumo exibe a narracao gerada quando disponivel.

O que nao foi feito:

- Nenhuma chamada para Veo.
- Nenhuma chamada para Seedance.
- Nenhuma chamada para MuAPI.
- Nenhuma chamada para OpenAI ou Gemini para video.
- Nenhuma composicao real de video.
- Nenhuma alteracao em `admin/settings-page.php`.
- Nenhuma alteracao em `includes/market-analysis.php`.

Status:

- Story 6 concluida.
- Proxima story recomendada: integrar a etapa real de geracao de clipes comerciais de video, mantendo a composicao final ainda controlada e testavel.

## 2026-05-19 - Ajuste diagnostico ElevenLabs

Problema corrigido:

- A falha de narracao retornava erro generico no AJAX, dificultando diagnostico na aba Network.

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-elevenlabs-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Provider ElevenLabs agora retorna `WP_Error` com `code`, `message` e `debug` seguro.
- AJAX agora retorna `wp_send_json_error` com objeto estruturado em vez de string simples.
- Frontend usa `data.message` e registra `console.warn("Video audio error", data)`.
- Debug seguro pode conter HTTP status e mensagem resumida da ElevenLabs, sem API key ou headers sensiveis.

Status:

- Diagnostico de erros ElevenLabs melhorado.

## 2026-05-19 - Story 7A concluida

Story implementada:

- Teste real isolado do provider Veo 3.1 Lite no backend para gerar apenas 1 clipe de teste.

Arquivos criados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php

Arquivos alterados nesta story:

- stlai-vision-ads-pro/stlai-vision-ads-pro.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Criado `STLAI_Veo_Provider` para ler `videoApiKey`, `videoModel` e `videoBaseUrl` de `stlai_vision_ads_pro_settings`.
- Implementada validacao segura de configuracao e da primeira imagem selecionada em `S.selVid`.
- Implementada chamada REST para `models/{videoModel}:predictLongRunning` com `x-goog-api-key` no header, sem expor chave no frontend.
- Implementado polling backend curto com 10 tentativas e intervalo de 5 segundos.
- Implementado download do video retornado pela operacao e salvamento em `wp_upload_dir()/stlai-vision-video/`.
- Criado endpoint AJAX separado `stlai_generate_test_veo_clip`.
- Adicionado botao discreto "Testar clipe IA" no passo 5.
- Adicionado player local para "Clipe IA de teste" quando `test_clip_url` retorna.
- Mantido o fluxo principal de audio/mock em `stlai_create_video_job` sem gerar 4 clipes e sem composicao final.

O que nao foi feito:

- Nenhuma chamada Seedance.
- Nenhuma chamada MuAPI.
- Nenhuma composicao final com FFmpeg.
- Nenhuma alteracao na logica de geracao de textos ou imagens.
- Nenhuma exposicao da Video API Key via `wp_localize_script`.

Status:

- Story 7A concluida.
- Proxima story recomendada: decidir se o polling longo deve migrar para endpoint de check assíncrono antes de iniciar os 4 clipes finais.

## 2026-05-19 - Ajuste payload Veo Lite

Problema corrigido:

- O endpoint Veo retornou `VEO_HTTP_ERROR` com HTTP 400 informando que `inlineData` nao e suportado pelo modelo `veo-3.1-lite-generate-preview`.

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Removido o uso de `image.inlineData` no payload do provider Veo.
- O payload de imagem agora usa `image.bytesBase64Encoded` com base64 puro e `image.mimeType`.
- Adicionado erro `INVALID_IMAGE_MIME_TYPE` para MIME fora de `image/jpeg`, `image/png` ou `image/webp`.
- Adicionado debug seguro com modelo, aspect ratio, MIME detectado e confirmacao do payload `image.bytesBase64Encoded`.
- O debug nao inclui API key, headers sensiveis ou base64 da imagem.

Status:

- Ajuste aplicado e validado com lint.

## 2026-05-19 - Story 7A.1 concluida

Story implementada:

- Ajustar o teste real do Veo antes da geracao dos 4 clipes finais.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Removida a opcao visual `1:1 Quadrado` do passo 5.
- Mantidos apenas os formatos `9:16 Vertical` e `16:9 Horizontal` no MVP de video.
- JS passa a normalizar qualquer formato antigo diferente de `16:9` para `9:16`.
- Provider Veo passa a fazer fallback seguro de `1:1` para `9:16`.
- Texto publico do formato atualizado para "Escolha o formato ideal para o vídeo do anúncio."
- Prompt do Veo reescrito em secoes `Product`, `Purpose`, `Visual direction` e `Strict restrictions`.
- Prompt reforca que o produto e um chaveiro decorativo impresso em 3D, nao abridor, nao ferramenta e nao brinquedo para pets.
- Prompt pede clipe visual silencioso, sem musica, voz, fala ou efeitos sonoros.

O que nao foi feito:

- Nenhuma API nova foi integrada.
- Nenhuma alteracao em ElevenLabs.
- Nenhuma alteracao em Seedance, MuAPI ou composicao final.

Status:

- Story 7A.1 concluida.

## 2026-05-19 - Story 7A.2 concluida

Story implementada:

- Corrigir o teste visual do Veo antes da geracao dos 4 clipes reais.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- O provider agora prepara uma imagem/frame no aspect ratio final antes de enviar ao Veo.
- Frames preparados sao salvos em `wp_upload_dir()/stlai-vision-video/frames/`.
- O frame preparado usa `9:16` ou `16:9`, preserva proporcao do produto e preenche o fundo com versao cover/desfocada da propria imagem.
- Se nao houver Imagick ou GD, o provider retorna `IMAGE_PREPROCESSOR_UNAVAILABLE`.
- O debug seguro passa a incluir `prepared_frame_url`, largura, altura, aspect ratio e processor, sem base64.
- Apos baixar o MP4 do Veo, o provider tenta remover audio nativo com FFmpeg usando `-c:v copy -an`.
- Se FFmpeg nao existir, o teste nao bloqueia: retorna o video original e registra `FFMPEG_UNAVAILABLE_AUDIO_NOT_STRIPPED`.
- O player do clipe de teste fica `muted` por padrao no frontend.
- O prompt foi ajustado para estilo de gravacao natural de produto: produto parado, zoom suave, leve movimento, sem nova cena drastica e sem inventar funcao.

O que nao foi feito:

- Nenhuma API nova foi integrada.
- Nenhuma alteracao no provider ElevenLabs.
- Nenhuma alteracao em Seedance, MuAPI, storage ou composicao final.

Status:

- Story 7A.2 concluida.

## 2026-05-19 - Story 7B concluida

Story implementada:

- Evoluir o teste isolado do Veo para gerar os 4 clipes reais do pipeline visual, ainda sem composição final.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- `STLAI_Veo_Provider` ganhou método `generate_clip()` reaproveitando a preparação de frame full-frame/cover crop e remoção de áudio quando FFmpeg existir.
- `STLAI_Video_Job_Service::create_job()` agora gera áudio ElevenLabs e depois gera 4 clipes Veo sequencialmente.
- Os papéis dos clipes são: apresentação geral, uso/contexto, detalhe/acabamento e hero/fechamento.
- Cada clipe usa a imagem correspondente pela ordem selecionada pelo usuário, considerando as 4 primeiras imagens selecionadas.
- O job salva `clips` com index, role, label, url, path, duração 8s e `muted: true`.
- O status final passa a ser `ready_for_composition`, com `composition_status: pending`.
- O frontend exibe uma grade com 4 players muted no passo 5.
- O resumo passa a indicar "4 clipes preparados", narração gerada e composição final pendente.

O que não foi feito:

- Nenhuma composição final com FFmpeg.
- Nenhum fade/transição entre clipes.
- Nenhum áudio ElevenLabs embutido em vídeo final.
- Nenhuma integração Seedance ou MuAPI.

Status:

- Story 7B concluida.

## 2026-05-19 - Story 7B.1 concluida

Story implementada:

- Ajustar prompts dos 4 clipes Veo para manter cada clipe como animacao sutil da imagem selecionada.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- O prompt do Veo agora define a imagem selecionada como cena inteira e referencia visual principal.
- O roteiro/copy passa a ser declarado apenas como contexto de marketing, sem permitir criar cenas a partir dele.
- Todos os prompts bloqueiam troca de cena, cortes, fades internos, transicoes, montagem, before/after, novo local e novo produto.
- Reforcadas as proibicoes contra transformar chaveiro em topo de bolo, abridor, ferramenta, brinquedo ou outro objeto.
- As direcoes dos 4 papéis foram ajustadas para variar apenas movimento/camera sutil, sempre usando a imagem como esta.

O que nao foi feito:

- Nenhuma composicao final.
- Nenhum fade entre clipes.
- Nenhuma alteracao em ElevenLabs.
- Nenhuma integracao Seedance ou MuAPI.

Status:

- Story 7B.1 concluida.

## 2026-05-19 - Story 8 concluida

Story implementada:

- Compor o video final narrado a partir dos 4 clipes Veo e da narracao ElevenLabs.

Arquivos alterados nesta story:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- FFmpeg foi encontrado no ambiente em `/opt/homebrew/bin/ffmpeg`.
- O prompt do Veo foi reforcado para preservar a apresentacao exata do produto, suporte/base/posicao e impedir interacoes como retirar, levantar, puxar, pendurar, encaixar ou colocar o item.
- O prompt recebeu uma secao explicita de ancoragem do produto com as regras literais para nao destacar o produto do suporte ou posicao exibida.
- Depois da narracao e dos 4 clipes, o job entra em `composing_final_video`.
- A composicao final usa FFmpeg, ignora audio nativo dos clipes e mapeia apenas a narracao ElevenLabs.
- Os 4 clipes sao encadeados em ordem com fade curto apenas entre clipes.
- Se a narracao for maior que a sequencia base, a sequencia 1-2-3-4 e repetida ate cobrir o audio e o excedente e cortado no final.
- O video final e salvo em `wp_upload_dir()/stlai-vision-video/`.
- O job finaliza como `ready`, com `composition_status=complete`, `final_video_url`, `final_video_path` e `final_video_duration`.
- O passo 5 exibe o player do video final quando pronto, mantendo narracao e 4 clipes individuais.
- O passo 6 exibe o video final principal e uma secao com os 4 clipes separados muted.
- O passo 6 agora tambem reflete estados intermediarios reais como geracao de narracao, geracao de clipes e `composing_final_video`.

O que nao foi feito:

- Nenhuma integracao Seedance.
- Nenhuma integracao MuAPI.
- Nenhuma alteracao no admin.
- Nenhuma nova geracao de imagens.
- Nenhuma trilha musical automatica.

Status:

- Story 8 concluida.

## 2026-05-19 - Correção de robustez para falha parcial de clipes

Problema corrigido:

- Quando a geracao falhava no clipe 2, o fluxo marcava erro generico, o console indicava "Video audio error" e a nova tentativa perdia contexto de audio/clipes ja gerados.

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Falha em clipe agora usa `status=clips_partial_error` em vez de apagar o progresso.
- O erro AJAX retorna `failed_clip_index`, `failed_clip_role`, `partial_clips`, `job_id`, `audio_url` e `debug` seguro.
- O debug seguro do Veo inclui role, aspect ratio, MIME, prepared frame URL/path, HTTP status/mensagem quando houver e operation id quando existir.
- O job preserva clipes ja gerados em `clips` e `partial_clips`.
- Nova tentativa envia `job_id` e o backend reutiliza `audio_url` existente, sem chamar ElevenLabs novamente.
- Nova tentativa pula clipes ja prontos e continua do primeiro clipe faltante.
- O frontend trocou o log para `Video generation error`.
- O passo 5 continua exibindo clipes parciais e o botao muda para "Tentar novamente" em `clips_partial_error`.
- A composicao final so roda quando os 4 clipes estao prontos.

O que nao foi feito:

- Nenhuma alteracao em Seedance.
- Nenhuma alteracao em MuAPI.
- Nenhuma alteracao no admin.
- Nenhuma alteracao na geracao de textos ou imagens.

Status:

- Correção concluida.

## 2026-05-19 - Correção de infraestrutura FFmpeg

Problema corrigido:

- Os 4 clipes e a narração podiam ser gerados, mas a composição final falhava com "FFmpeg não está disponível para compor o vídeo final" por depender apenas de `command -v ffmpeg`.

Arquivos alterados:

- stlai-vision-ads-pro/admin/settings-page.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Adicionado o campo administrativo `ffmpegPath` em Configuracoes de Video Comercial.
- O campo permite configurar manualmente o caminho do FFmpeg sem expor esse valor no frontend.
- A deteccao de FFmpeg agora tenta `ffmpegPath`, `/usr/bin/ffmpeg`, `/usr/local/bin/ffmpeg` e `command -v ffmpeg`.
- Cada candidato e validado com `ffmpeg -version`.
- O diagnostico seguro informa funcoes PHP disponiveis/indisponiveis e caminhos testados.
- Falhas de FFmpeg retornam erro estruturado `FFMPEG_NOT_AVAILABLE`, com detalhes internos seguros como `FFMPEG_EXEC_DISABLED`, `FFMPEG_NOT_FOUND` ou `FFMPEG_NOT_EXECUTABLE` no debug.
- Se a composicao final nao puder rodar por FFmpeg, o job volta para `ready_for_composition`, mantem `audio_url`, mantem os 4 clipes e fica com `composition_status=pending`.
- O frontend mostra que os ativos foram gerados e que a composicao final esta pendente por FFmpeg, sem esconder clipes, narracao ou resumo.
- O botao permite tentar compor novamente usando o mesmo `job_id`.

O que nao foi feito:

- Nenhuma alteracao no provider ElevenLabs.
- Nenhuma alteracao no provider Veo.
- Nenhuma alteracao em Seedance.
- Nenhuma alteracao em MuAPI.
- Nenhuma alteracao na geracao de imagens ou textos.

Status:

- Correção concluida.

## 2026-05-20 - Correção de composição assíncrona e memory-safe

Problema corrigido:

- O Render Free derrubou o `stlai-video-renderer` por exceder 512 MB durante a composição final com FFmpeg.

Arquivos alterados:

- stlai-video-renderer/server.js
- stlai-video-renderer/README.md
- stlai-video-renderer/temp/jobs/.gitkeep
- stlai-vision-ads-pro/includes/video/class-stlai-video-composer-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- `POST /render` agora inicia um job assíncrono e retorna `render_job_id`.
- Criado `GET /render/{render_job_id}` para consultar `queued`, `processing`, `ready` ou `error`.
- Jobs do renderer são salvos em `temp/jobs/`.
- `RENDER_OUTPUT_QUALITY=preview` usa `720x1280` ou `1280x720`.
- `ENABLE_XFADE=false` deixa concatenação simples como padrão.
- `ENABLE_XFADE=true` fica opcional no modo `full`, com fallback para concatenação simples.
- O plugin salva `render_job_id` e faz polling pelo backend.
- O frontend mostra composição em andamento e mantém áudio/clipes visíveis.

Status:

- Correção concluida.

## 2026-05-20 - UX motion de processamento de vídeo

Arquivos alterados:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- stlai-video-renderer/.env.example
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- Adicionado bloco motion no passo 5 para estados de geração/composição.
- Adicionado bloco motion na seção de vídeo do resumo.
- Criada barra de progresso com shimmer e progresso estimado por estado.
- Criados steps visuais: Narração, Clipes IA, Composição e Finalização.
- Criado preview em blur com overlay animado.
- Criado estado visual de erro para `composition_error`.
- Confirmado modo leve do renderer por padrão: `RENDER_OUTPUT_QUALITY=preview` e `ENABLE_XFADE=false`.

Status:

- Correção concluida.

## 2026-05-20 - Fluxo final de vídeo e fade seguro

Arquivos alterados:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- stlai-vision-ads-pro/includes/video/class-stlai-video-composer-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-video-renderer/server.js
- stlai-video-renderer/README.md
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- A etapa 6 pública foi renomeada de "Resumo" para "Resultado final".
- Ao clicar em "Gerar vídeo", o usuário é levado para a tela Resultado final assim que o job é aceito/iniciado.
- A tela Resultado final mostra motion durante narração, clipes, fila e composição.
- O player de narração separado foi removido da interface pública; o áudio permanece apenas como ativo interno do job.
- A tela Resultado final mostra player principal quando `final_video_url` existe, grade com 4 clipes preparados e botão "Tentar novamente" em erro de composição.
- A barra de progresso foi ajustada por fase: narração, clipes 1-4, clipes prontos, fila, processamento e pronto.
- O plugin envia `enable_fade=true` para o renderer, mas o renderer só usa xfade quando `ENABLE_XFADE=true` e o modo efetivo permitir.
- O renderer registra `transition_used` e `fallback_used`; se xfade falhar, usa concatenação simples sem falhar o job.

Status:

- Correção concluida.

## 2026-05-20 - Retry automático de clipes e progresso contínuo

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- Cada clipe Veo agora tem até 3 tentativas antes de retornar erro ao frontend.
- Retries automáticos aguardam 2 segundos antes da tentativa 2 e 4 segundos antes da tentativa 3.
- Erros temporários como 429, 5xx, timeout, operation timeout, resposta vazia/inválida e falhas transitórias de transporte são retryable.
- Erros permanentes de configuração, validação, imagem e formato inválido não são repetidos.
- O job salva `current_clip_index`, `current_clip_attempt`, `clip_retry_count` e `last_clip_error`.
- Adicionados estados `retrying_clip_1` a `retrying_clip_4` e `clip_generation_error`.
- O botão "Tentar novamente" retoma do ponto de falha e preserva roteiro, narração, clipes já gerados e formato.
- A barra de progresso visual agora cresce localmente por tempo e fase, sem voltar para trás e sem chegar a 100 antes de `ready`.
- Cards de clipes mostram placeholders de geração/retry para clipes ainda ausentes.

Status:

- Correção concluida.

## 2026-05-20 - Ajustes UX do fluxo de vídeo e galerias

Arquivos alterados:

- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md

O que foi feito:

- "Gerar vídeo" não redireciona mais automaticamente para Resultado final.
- O usuário permanece no passo 5 enquanto o job segue rodando e o polling continua.
- O botão de Resultado no passo 5 ganha motion/glow quando há vídeo em geração, pronto ou erro recuperável.
- O botão muda para "Acompanhar resultado" durante processamento e "Ver resultado final" quando pronto.
- Ao clicar no botão, o frontend abre o passo 6 e rola até a seção de vídeo.
- Corrigidos os steps do motion para acender "Clipes IA" durante geração/retry de clipes.
- Os players de clipes preparados deixam de ser recriados a cada tick do motion, evitando flicker.
- A galeria do passo 4 deixa de exibir botões de hover; cards servem para seleção de vídeo.
- A galeria do Resultado final mantém botões funcionais de baixar, ampliar e regenerar.

Status:

- Correção concluida.

## 2026-05-20 - Acompanhamento granular real dos clipes

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/assets/js/app.js
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- O job passou a salvar e retornar `progress_hint` junto com `progress`.
- O frontend usa `current_clip_index`, `current_clip_attempt`, `progress_hint` e `clips.length` para corrigir status visual atrasado.
- A barra de progresso mantém crescimento por fase e não volta para trás.
- Placeholders dos clipes mostram "Pendente", "Gerando clipe X" ou "Tentando novamente".
- Música de fundo ficou apenas documentada como decisão futura para o renderer/FFmpeg.

Status:

- Correção concluida.

## 2026-05-20 - Frames preparados e layout por formato

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-video-storage.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-ajax.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- O job passa a armazenar e retornar `video_frames`.
- Cada clipe pode trazer `prepared_frame_url`, dimensões do frame e `aspect_ratio`.
- O passo 5 e o Resultado final exibem "Imagens no formato 9:16/16:9" separadas da galeria quadrada.
- Os clipes e frames usam layout vertical para 9:16 e horizontal para 16:9.
- O preparo de frames para Veo passou a encaixar o produto com margem segura, sem crop agressivo.
- O prompt do Veo foi reforçado para manter produto inteiro visível, especialmente no formato horizontal.

Status:

- Correção concluida.

## 2026-05-20 - Imagens no formato e primeiro frame correto

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- stlai-vision-ads-pro/includes/video/class-stlai-video-job-service.php
- stlai-vision-ads-pro/frontend/shortcode.php
- stlai-vision-ads-pro/assets/js/app.js
- stlai-vision-ads-pro/assets/css/style.css
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- O preparo de imagem para Veo agora cria um frame final nativo no aspect ratio escolhido usando crop/recomposição, sem quadrado central sobre blur.
- O provider continua enviando o `base64` do frame preparado ao Veo, não a imagem quadrada original.
- O prompt do Veo reforça que o primeiro frame deve bater com a imagem formatada e manter o mesmo aspect ratio até o final.
- A UI pública passou a usar "Imagens no formato 9:16/16:9".
- Cards passaram a exibir "Imagem 1" a "Imagem 4", com botões de baixar e ampliar.

Status:

- Correção concluida.

## 2026-05-20 - Correção regressão frame quadrado com blur

Arquivos alterados:

- stlai-vision-ads-pro/includes/video/class-stlai-veo-provider.php
- .bmad/active-context.md
- .bmad/decisions.md
- .bmad/api-contracts.md

O que foi feito:

- Removida a estratégia de foreground quadrado com fundo desfocado.
- O frame formatado passa a ser gerado por crop/recomposição nativa no aspect ratio final.
- A preparação GD prefere crop inteligente com heurística simples de foco; fallback é crop central cover.
- O fallback Imagick usa crop nativo no aspect ratio final, sem blur/moldura.
- O prompt reforça que não pode revelar square source framing, smaller centered square ou blurred background framing.

Status:

- Correção concluida.
