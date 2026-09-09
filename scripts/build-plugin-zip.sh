#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
PLUGIN_SLUG=tn-content-planner
mkdir -p dist
rm -rf "dist/$PLUGIN_SLUG"
rm -f "dist/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG.zip"
cp -R "$PLUGIN_SLUG" "dist/$PLUGIN_SLUG"
find "dist/$PLUGIN_SLUG" -name '.DS_Store' -delete
(cd dist && zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG")
cp "dist/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG.zip"
