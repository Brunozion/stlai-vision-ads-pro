# STATE.md — STLFLIX: onde paramos

> Atualize ISTO no FIM de cada sessão. Leia ISTO no COMEÇO da próxima.
> Objetivo: retomar sem reler o repo inteiro.

**Última atualização:** 2026-06-02

## Foco atual (uma frase)
STLAI Vision Ads Pro está evoluindo o módulo UGC para multi-provider, mantendo o
fluxo comercial existente intacto.

## Feito recentemente
- Criado `build.sh`: gera UM zip limpo do plugin em `dist/` (com bump opcional de versão).
- Eliminados os ~56 zips antigos da raiz (Git já é o histórico).
- `.gitignore` ajustado: ignora `dist/`, `*.zip`, `.env*` e `.DS_Store`.
- Prints organizados: `docs/pricing-apis/` (preços de API) e `docs/vision-flow/`
  (telas do fluxo). Pasta antiga "STLAI SELLER PRICING" removida.
- Tudo commitado e enviado para o GitHub (branch main).
- Implementado o primeiro módulo "Vídeos UGC" no Resultado do plugin principal:
  provider MuAPI, modal de presets, AJAX start/poll/list, storage em `ugc_jobs`,
  prompts editáveis no admin e configuração segura sem expor API key no frontend.
- UGC evoluído para multi-provider: MuAPI, Atlas Cloud, Fal.ai e Seedance/BytePlus,
  com adapters backend próprios, provider escolhido no admin e prompts/presets
  compartilhados.
- MuAPI UGC simplificado para seguir o padrão Open Generative AI: Base URL default
  `https://api.muapi.ai`, start em `/api/v1/{model}`, image_url pública enviada
  direto e `upload_file` apenas como fallback.

## Fluxo de trabalho atual
1. Pedir alteração ao Codex no VS Code.
2. Rodar `./build.sh` (ou `./build.sh patch`) → 1 zip em `dist/`.
3. Subir o zip no WordPress para testar.
4. `git commit && git push`.
5. (Renderer) deploy no Render — ainda manual; ver próximos passos.

## Próximos passos
1. Testar o UGC em WordPress provider por provider: MuAPI, Atlas Cloud, Fal.ai e
   Seedance/BytePlus. Atlas/Fal têm endpoints de polling configuráveis; Seedance
   direto exige Base URL, Endpoint e Poll Endpoint do contrato BytePlus usado.
2. Conectar o repo ao Render para auto-deploy do renderer a cada push na main
   (eliminar o deploy manual).
3. Avaliar adaptar o `build.sh` para empacotar também o `stlai-seller-cost-dashboard`
   (passar o nome do plugin como parâmetro) — só quando for empacotá-lo.
4. (Opcional) Renomear arquivos de `docs/` que têm `:` ou emoji no nome, trocando
   por `-`, para evitar problemas em alguns sistemas. Não urgente.

## Trabalho em duas máquinas (notebook + PC)
Este projeto NÃO vai pro Drive. Sincroniza por Git:
- Ao começar: `git pull`
- Ao terminar: `git add . && git commit -m "..." && git push`
- No PC, na primeira vez: `git clone` do repositório.

## Para retomar, diga à IA:
"Leia AGENTS.md e STATE.md e continue de onde paramos. Não releia o repo todo.
Veja também os últimos commits com git log --oneline -10."
