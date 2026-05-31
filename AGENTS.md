# AGENTS.md — STLFLIX (monorepo)

> Lido por Codex, Claude e outras IAs ao abrir a pasta. Memória DURÁVEL do repo.
> Mantenha CURTO: visão geral + ponteiros. O que muda a cada sessão fica em STATE.md.
> Se uma peça ficar complexa, criar um AGENTS.md dentro da pasta dela (o mais
> próximo do arquivo editado prevalece).
> Idioma de trabalho: português (Brasil).

## 1. O que é o STLFLIX
Conjunto de ferramentas (MVP) para vender produtos de impressão 3D em marketplaces.
Feito em WordPress (plugins) por velocidade de desenvolvimento. NÃO roda em
produção — é o MVP do produto que irá para produção.
Repositório: GitHub Brunozion/stlai-vision-ads-pro (branch main).

## 2. As três peças (todas neste repo)
- **stlai-vision-ads-pro/** — plugin principal. Cria anúncios "vencedores" em
  fluxo de 6 passos: Upload → Contexto (Vision lê a imagem) → Textos →
  Imagens (8 individuais 1:1 + combo 2x2) → Vídeo (4 clipes + narração) →
  Resultado (score + ativos). Planos: Básico (15 créditos) / Premium (80).
- **stlai-seller-cost-dashboard/** — plugin de pricing. Calcula o custo de
  geração em produção para apresentar ao CEO.
- **stlai-video-renderer/** — serviço Node hospedado no Render (API web service)
  que junta os vídeos do fluxo com o áudio.

## 3. Stack e provedores de IA
PHP / JavaScript / CSS (WordPress); Node (renderer).
IAs: OpenAI (texto + GPT Image 2), Google Gemini (Veo 3.1 vídeo, nano banana),
ElevenLabs (áudio), Fal.ai / ByteDance Seedance (UGC/vídeo).
Usa BMAD (.bmad) + Claude (.claude) no repo.

## 4. Referências de contexto (em docs/)
- Preços das APIs dos provedores: `docs/pricing-apis/`
- Telas do fluxo da Vision (passos 1-6, combos, UGC): `docs/vision-flow/`

## 5. Build e deploy
- **Empacotar o plugin principal:** rodar `./build.sh` na raiz (lê a versão do
  cabeçalho do .php e gera UM zip limpo em `dist/`). `./build.sh patch` sobe a
  versão antes. Só a pasta do plugin entra no zip.
- **WordPress:** subir o zip de `dist/` manualmente para testar.
- **Render (renderer):** deploy a partir do push na main (ver STATE.md se já está
  com auto-deploy configurado).
- O `build.sh` é o ÚNICO responsável por versão+zip. Não criar zips manualmente.

## 6. Convenções
- Idioma das respostas: português (BR).
- Versão no padrão semântico (ex: 1.3.17), no cabeçalho do .php do plugin.
- `dist/`, `*.zip`, `.env*` e `.DS_Store` ficam fora do Git (ver .gitignore).

## 7. Regras de ouro (o que NÃO fazer)
- Nunca commitar `.env` ou chaves de API (o repositório é público).
- Não salvar zips antigos — o Git já é o histórico; o `dist/` guarda só o atual.
- Mexer no fluxo de vídeo do plugin sem lembrar que ele depende do renderer (Render).

## 8. Onde estão as coisas
- Onde paramos / próximos passos: `STATE.md`
- Contexto visual: `docs/`
- Build: `build.sh` → saída em `dist/`
