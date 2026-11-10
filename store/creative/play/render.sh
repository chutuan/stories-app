#!/usr/bin/env bash
# Xuất ảnh cho Google Play:
#   feature.html -> feature-1024x500.png (feature graphic, không alpha)
#   ../../../mobile/assets/images/icon.png -> icon-512.png (icon trang Play)
set -euo pipefail
cd "$(dirname "$0")"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
"$CHROME" --headless=new --hide-scrollbars --force-device-scale-factor=2 --allow-file-access-from-files \
  --window-size=1024,500 --virtual-time-budget=3000 \
  --user-data-dir="$tmp/profile" --screenshot="$tmp/raw.png" "file://$PWD/feature.html" >/dev/null 2>&1
sips -z 500 1024 "$tmp/raw.png" --out "$tmp/half.png" >/dev/null
swift ../flatten.swift "$tmp/half.png" feature-1024x500.png
sips -z 512 512 ../../../mobile/assets/images/icon.png --out icon-512.png >/dev/null
sips -g pixelWidth -g pixelHeight -g hasAlpha feature-1024x500.png icon-512.png | grep -v "^/"
