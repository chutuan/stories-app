#!/usr/bin/env bash
#
# Sinh /etc/nginx/cloudflare-real-ip.conf từ danh sách dải IP Cloudflare công bố.
#
# VÌ SAO CẦN: khi bật proxy Cloudflare, mọi request tới origin đều xuất phát từ
# máy biên của Cloudflare, nên $remote_addr là IP của Cloudflare chứ không phải
# của người đọc. Hệ quả không chỉ là log vô dụng:
#
#   - limit_req gom theo $binary_remote_addr sẽ nhốt chung tất cả người đọc đi qua
#     cùng một POP vào một hạn mức, trong khi kẻ scrape đổi POP là thoát.
#   - Mọi thống kê theo IP đều sai. Ba lần tải trang của CÙNG một người đi qua ba
#     POP khác nhau sẽ được đếm thành ba người.
#
# AN TOÀN: real_ip_header chỉ được tin khi request ĐẾN TỪ dải IP trong
# set_real_ip_from. Ai gõ thẳng vào IP gốc (147.182.221.253) không nằm trong dải
# đó, nên header CF-Connecting-IP họ tự bịa sẽ bị bỏ qua. Đừng bao giờ thay danh
# sách này bằng 0.0.0.0/0 — làm vậy là cho bất kỳ ai tự khai mình là IP nào cũng
# được, và mọi luật chặn theo IP mất hiệu lực.
#
# Cloudflare có đổi dải, nên chạy lại định kỳ (xem cron ở cuối DEPLOY.md).
set -euo pipefail

OUT=/etc/nginx/cloudflare-real-ip.conf
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

V4="$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v4)"
V6="$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v6)"

# Không ghi đè bằng file rỗng nếu mạng hỏng — thà giữ danh sách cũ còn hơn mở toang.
if [ -z "$V4" ] || [ -z "$V6" ]; then
    echo "Không tải được dải IP Cloudflare, giữ nguyên $OUT" >&2
    exit 1
fi

{
    echo "# Sinh tự động bởi deploy/cloudflare-real-ip.sh — ĐỪNG sửa tay."
    echo "# Nguồn: https://www.cloudflare.com/ips-v4 và ips-v6"
    echo
    for ip in $V4 $V6; do echo "set_real_ip_from $ip;"; done
    echo
    echo "real_ip_header CF-Connecting-IP;"
} > "$TMP"

install -m 0644 "$TMP" "$OUT"
echo "Đã ghi $OUT ($(grep -c set_real_ip_from "$OUT") dải)"

nginx -t && systemctl reload nginx && echo "nginx đã nạp lại"
