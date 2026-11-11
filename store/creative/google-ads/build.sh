#!/usr/bin/env bash
# Dựng lại toàn bộ ảnh Demand Gen (Google Ads) cho app iOS vào out/.
#   ai/   = ảnh nền màn hình xanh tạo bằng ChatGPT (tài khoản Plus của Tuấn, 11/11/2026)
#   src/  = ảnh chụp màn hình app thật (bản App Store)
# Kích thước: ngang 1200x628 (1.91:1), vuông 1200x1200, dọc 960x1200 (4:5).
set -euo pipefail
cd "$(dirname "$0")"
rm -f out/*.jpg
bash render.sh
while read -r bg shot w h name; do
  [[ -z "$bg" || "$bg" == \#* ]] && continue
  bash compose.sh "ai/$bg" "src/$shot" "$w" "$h" "out/$name-${w}x${h}.jpg"
done <<'LIST'
# nền                 màn hình       w     h     tên
bed-2x3.png          detail.png     960   1200  bed-detail
bed-2x3.png          discover.png   1200  1200  bed-discover
bed-3x2-small.png    detail.png     1200  628   bed-detail
cafe-2x3.png         discover.png   960   1200  cafe-discover
cafe-2x3.png         detail.png     1200  1200  cafe-detail
cafe-3x2.png         discover.png   1200  628   cafe-discover
flatlay-2x3.png      audio.png      960   1200  flatlay-audio
flatlay-2x3.png      audio.png      1200  1200  flatlay-audio
flatlay-3x2.png      audio.png      1200  628   flatlay-audio
LIST
ls -la out | awk 'NR>1 {print $5, $9}'
