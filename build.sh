#!/usr/bin/env bash
# build.sh — empacota o plugin WordPress em UM zip limpo.
# Rode da pasta STLFLIX (onde fica a pasta do plugin).
#
# Uso:
#   ./build.sh              -> empacota a versão atual (lida do cabeçalho do .php)
#   ./build.sh patch        -> sobe 1.3.17 -> 1.3.18, depois empacota
#   ./build.sh minor        -> sobe 1.3.17 -> 1.4.0,  depois empacota
#   ./build.sh major        -> sobe 1.3.17 -> 2.0.0,  depois empacota
#
# O zip sai em dist/stlai-vision-ads-pro-<versao>.zip
# Só a pasta do plugin entra no zip (o renderer vai pro Render, não pro WordPress).

set -e

PLUGIN_DIR="stlai-vision-ads-pro"
MAIN_FILE="$PLUGIN_DIR/$PLUGIN_DIR.php"
OUT_DIR="dist"

# --- checagens básicas ---
if [ ! -d "$PLUGIN_DIR" ]; then
  echo "Erro: não achei a pasta '$PLUGIN_DIR'. Rode este script de dentro da pasta STLFLIX."
  exit 1
fi
if [ ! -f "$MAIN_FILE" ]; then
  echo "Erro: não achei o arquivo principal '$MAIN_FILE'."
  exit 1
fi

# --- lê a versão atual do cabeçalho do plugin ---
CURRENT=$(grep -i "Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')
if [ -z "$CURRENT" ]; then
  echo "Erro: não consegui ler a versão (linha 'Version: x.y.z') em $MAIN_FILE."
  exit 1
fi

# --- bump opcional ---
BUMP="${1:-}"
if [ -n "$BUMP" ]; then
  IFS='.' read -r MA MI PA <<< "$CURRENT"
  case "$BUMP" in
    patch) PA=$((PA+1)) ;;
    minor) MI=$((MI+1)); PA=0 ;;
    major) MA=$((MA+1)); MI=0; PA=0 ;;
    *) echo "Argumento inválido: '$BUMP'. Use patch, minor ou major."; exit 1 ;;
  esac
  NEW="$MA.$MI.$PA"
  # atualiza a linha Version no cabeçalho (in-place, com backup .bak temporário)
  sed -i.bak -E "s/(Version:[[:space:]]*)[0-9]+\.[0-9]+\.[0-9]+/\1$NEW/I" "$MAIN_FILE"
  rm -f "$MAIN_FILE.bak"
  # mantém a constante interna alinhada para bust de cache dos assets
  sed -i.bak -E "s/(STLAI_VISION_ADS_PRO_VERSION', ')[0-9]+\.[0-9]+\.[0-9]+/\1$NEW/" "$MAIN_FILE"
  rm -f "$MAIN_FILE.bak"
  echo "Versão: $CURRENT -> $NEW"
  VERSION="$NEW"
else
  VERSION="$CURRENT"
  echo "Versão atual: $VERSION (sem bump)"
fi

# --- gera o zip limpo ---
mkdir -p "$OUT_DIR"
# limpa builds antigos: dist/ guarda só o zip mais recente (o histórico fica no Git)
rm -f "$OUT_DIR/$PLUGIN_DIR-"*.zip
ZIP="$OUT_DIR/$PLUGIN_DIR-$VERSION.zip"

zip -r -q "$ZIP" "$PLUGIN_DIR" \
  -x "*/.git/*" "*/node_modules/*" "*/.DS_Store" "*.zip" "*/.env" "*/.claude/*" "*/.bmad/*"

echo "Pronto: $ZIP"
echo "Suba este zip no WordPress. (O Git já guarda o histórico — não precisa salvar zips antigos.)"
