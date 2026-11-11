#!/usr/bin/env bash
# Xuất ảnh điện thoại cho Demand Gen: phones.html -> out/phones-{1200x628,1200x1200,960x1200}.jpg
# Vẽ ở 2x rồi thu về đúng kích thước, lưu JPEG (Google Ads giới hạn 5 MB mỗi ảnh).
set -euo pipefail
cd "$(dirname "$0")"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
mkdir -p out
for spec in landscape:1200:628 square:1200:1200 portrait:960:1200; do
  IFS=: read -r layout w h <<<"$spec"
  "$CHROME" --headless=new --hide-scrollbars --force-device-scale-factor=2 --allow-file-access-from-files \
    --window-size="$w,$h" --virtual-time-budget=3000 \
    --user-data-dir="$tmp/profile" --screenshot="$tmp/$layout.png" "file://$PWD/phones.html?layout=$layout" >/dev/null 2>&1
  sips -z "$h" "$w" "$tmp/$layout.png" --out "$tmp/$layout-1x.png" >/dev/null
  sips -s format jpeg -s formatOptions 92 "$tmp/$layout-1x.png" --out "out/phones-${w}x${h}.jpg" >/dev/null
done
sips -g pixelWidth -g pixelHeight out/phones-*.jpg | paste - - - | sed 's|^.*/||'
ls -la out/phones-*.jpg | awk '{print $5, $9}'
