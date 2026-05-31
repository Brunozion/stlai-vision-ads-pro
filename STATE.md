# STATE.md — STLFLIX: onde paramos

> Atualize ISTO no FIM de cada sessão. Leia ISTO no COMEÇO da próxima.
> Objetivo: retomar sem reler o repo inteiro.

**Última atualização:** 2026-05-31

## Foco atual (uma frase)
Organizamos o repositório (build automatizado + docs) e agora vamos terminar de
otimizar o fluxo de deploy (Render auto-deploy).

## Feito recentemente
- Criado `build.sh`: gera UM zip limpo do plugin em `dist/` (com bump opcional de versão).
- Eliminados os ~56 zips antigos da raiz (Git já é o histórico).
- `.gitignore` ajustado: ignora `dist/`, `*.zip`, `.env*` e `.DS_Store`.
- Prints organizados: `docs/pricing-apis/` (preços de API) e `docs/vision-flow/`
  (telas do fluxo). Pasta antiga "STLAI SELLER PRICING" removida.
- Tudo commitado e enviado para o GitHub (branch main).

## Fluxo de trabalho atual
1. Pedir alteração ao Codex no VS Code.
2. Rodar `./build.sh` (ou `./build.sh patch`) → 1 zip em `dist/`.
3. Subir o zip no WordPress para testar.
4. `git commit && git push`.
5. (Renderer) deploy no Render — ainda manual; ver próximos passos.

## Próximos passos
1. Conectar o repo ao Render para auto-deploy do renderer a cada push na main
   (eliminar o deploy manual).
2. Avaliar adaptar o `build.sh` para empacotar também o `stlai-seller-cost-dashboard`
   (passar o nome do plugin como parâmetro) — só quando for empacotá-lo.
3. (Opcional) Renomear arquivos de `docs/` que têm `:` ou emoji no nome, trocando
   por `-`, para evitar problemas em alguns sistemas. Não urgente.

## Trabalho em duas máquinas (notebook + PC)
Este projeto NÃO vai pro Drive. Sincroniza por Git:
- Ao começar: `git pull`
- Ao terminar: `git add . && git commit -m "..." && git push`
- No PC, na primeira vez: `git clone` do repositório.

## Para retomar, diga à IA:
"Leia AGENTS.md e STATE.md e continue de onde paramos. Não releia o repo todo.
Veja também os últimos commits com git log --oneline -10."
