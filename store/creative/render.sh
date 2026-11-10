#!/usr/bin/env bash
# Xuất universal.html thành PNG 5244x2950 không alpha để tải lên App Store Connect > Asset Library.
# Trang vẽ ở 2622x1475 CSS px, chụp với tỉ lệ điểm ảnh 2 => đúng 5244x2950.
#   bash store/creative/render.sh            -> universal-5244x2950.png
#   bash store/creative/render.sh guides     -> universal-guides.png (có khung hai vùng cắt, để soát)
set -euo pipefail
cd "$(dirname "$0")"

CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
url="file://$PWD/universal.html"
out="universal-5244x2950.png"
if [[ "${1:-}" == "guides" ]]; then url="$url#guides"; out="universal-guides.png"; fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# Đừng thêm --disable-gpu: vẽ bằng CPU thì bóng mờ lớn của máy bị lộ đường ráp giữa các ô 256px.
"$CHROME" --headless=new --hide-scrollbars --force-device-scale-factor=2 \
  --window-size=2622,1475 --virtual-time-budget=3000 \
  --user-data-dir="$tmp/profile" --screenshot="$tmp/raw.png" "$url" >/dev/null 2>&1

swift flatten.swift "$tmp/raw.png" "$out"
sips -g pixelWidth -g pixelHeight -g hasAlpha "$out" | tail -3
