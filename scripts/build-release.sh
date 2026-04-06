#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

composer install --no-dev --optimize-autoloader

VERSION="$(php -r '$h=file_get_contents("keeal-for-woocommerce.php"); preg_match("/Version:\\s*(\\S+)/", $h, $m); echo $m[1] ?? "0";')"
PLUGIN_DIR="keeal-for-woocommerce"
ZIP_NAME="keeal-for-woocommerce-${VERSION}.zip"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

rsync -a \
  --exclude '.git' \
  --exclude '*.zip' \
  --exclude '.DS_Store' \
  --exclude '.idea' \
  --exclude '.vscode' \
  "$ROOT/" "$STAGE/$PLUGIN_DIR/"

( cd "$STAGE" && zip -qr "$ROOT/$ZIP_NAME" "$PLUGIN_DIR" )

echo "Created: $ROOT/$ZIP_NAME"
