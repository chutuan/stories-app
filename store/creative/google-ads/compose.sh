#!/usr/bin/env bash
# Ghép màn hình app vào ảnh nền màn hình xanh, xuất JPEG đúng kích thước.
#   bash compose.sh ai/bed-2x3.png src/reader.png 960 1200 out/bed-reader-960x1200.jpg
set -euo pipefail
cd "$(dirname "$0")"
bg="$1"; shot="$2"; w="$3"; h="$4"; out="$5"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
url="file://$PWD/compose.html?bg=$bg&shot=$shot&w=$w&h=$h"
# Lần chạy đầu của Chrome đôi khi chụp DOM trước khi script xong: thử lại tối đa 5 lần.
info=""
for i in 1 2 3 4 5; do
  info="$("$CHROME" --headless=new --allow-file-access-from-files --virtual-time-budget=30000 --window-size="$w,$h" \
    --user-data-dir="$tmp/p$i" --dump-dom "$url" 2>/dev/null | grep -o '<pre id="debug">[^<]*' | sed 's/<pre id="debug">//' || true)"
  [[ -n "$info" ]] && break
done
case "$info" in *error*|"") echo "LỖI $bg: ${info:-không có kết quả}" >&2; exit 1;; esac
"$CHROME" --headless=new --hide-scrollbars --force-device-scale-factor=2 --allow-file-access-from-files \
  --virtual-time-budget=30000 --window-size="$w,$h" --user-data-dir="$tmp/shot" --screenshot="$tmp/raw.png" "$url" >/dev/null 2>&1
mkdir -p "$(dirname "$out")"
sips -z "$h" "$w" "$tmp/raw.png" --out "$tmp/1x.png" >/dev/null
sips -s format jpeg -s formatOptions 92 "$tmp/1x.png" --out "$out" >/dev/null
echo "$out $(stat -f%z "$out")B $info"
