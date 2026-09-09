#!/usr/bin/env bash
#
# =============================================================================
# Stories — script deploy backend Laravel lên VPS Ubuntu
# =============================================================================
#
# Chạy lần đầu: xem DEPLOY.md (script này KHÔNG cài server, chỉ cập nhật code
# cho một hệ thống đã dựng xong).
#
# Cách dùng:
#   sudo bash /var/www/stories/deploy/deploy.sh
#
# Ghi đè cấu hình bằng biến môi trường:
#   sudo REPO_DIR=/srv/stories HEALTH_URL=https://api.truyen.vn/up \
#        bash /srv/stories/deploy/deploy.sh
#
# Hoặc tạo file /etc/stories-deploy.env (được nạp tự động nếu tồn tại):
#   REPO_DIR=/var/www/stories
#   HEALTH_URL=https://api.tunastory.com/up
#
# Cờ tuỳ chọn:
#   SKIP_MAINTENANCE=1   không bật chế độ bảo trì (API không bị 503 lúc deploy)
#   SKIP_MIGRATE=1       bỏ qua php artisan migrate
#   ALLOW_DIRTY=1        cho phép deploy khi working tree có thay đổi chưa commit
#
# Script dừng ngay khi có lỗi (set -e). Nếu lỗi giữa chừng, chế độ bảo trì được
# tự động tắt lại qua trap EXIT.
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# CẤU HÌNH — sửa mặc định ở đây hoặc truyền qua biến môi trường
# -----------------------------------------------------------------------------

# Nạp file cấu hình ngoài nếu có (tiện khi không muốn sửa file trong git).
if [[ -f /etc/stories-deploy.env ]]; then
    # shellcheck disable=SC1091
    source /etc/stories-deploy.env
fi

# Thư mục GỐC của repo (nơi có .git). Backend nằm trong repo con `backend/`.
REPO_DIR="${REPO_DIR:-/var/www/stories}"

# Thư mục ứng dụng Laravel (nơi có file `artisan`).
APP_DIR="${APP_DIR:-$REPO_DIR/backend}"

GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-main}"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"

# User/group mà php-fpm và queue worker chạy dưới. Mọi lệnh artisan chạy bằng
# user này để file sinh ra (log, cache, view compiled) không bị root chiếm quyền.
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"

PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"
QUEUE_SERVICE="${QUEUE_SERVICE:-stories-queue.service}"
SCHEDULER_TIMER="${SCHEDULER_TIMER:-stories-scheduler.timer}"

# Health check sau khi deploy. /up là health endpoint có sẵn của Laravel
# (khai báo ở bootstrap/app.php: health: '/up').
HEALTH_URL="${HEALTH_URL:-https://api.tunastory.com/up}"
# Endpoint API thật mà app mobile gọi — kiểm tra luôn cả DB.
API_HEALTH_URL="${API_HEALTH_URL:-https://api.tunastory.com/api/home}"

SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"

# -----------------------------------------------------------------------------
# TIỆN ÍCH
# -----------------------------------------------------------------------------

readonly C_OK=$'\033[0;32m'
readonly C_WARN=$'\033[0;33m'
readonly C_ERR=$'\033[0;31m'
readonly C_DIM=$'\033[0;36m'
readonly C_OFF=$'\033[0m'

step()  { printf '\n%s==> %s%s\n' "$C_DIM" "$*" "$C_OFF"; }
info()  { printf '    %s\n' "$*"; }
ok()    { printf '    %s✔ %s%s\n' "$C_OK" "$*" "$C_OFF"; }
warn()  { printf '    %s! %s%s\n' "$C_WARN" "$*" "$C_OFF" >&2; }
die()   { printf '\n%s✘ LỖI: %s%s\n' "$C_ERR" "$*" "$C_OFF" >&2; exit 1; }

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "Không tìm thấy lệnh '$1'. Cài đặt rồi chạy lại."
}

IS_ROOT=0
[[ "$(id -u)" -eq 0 ]] && IS_ROOT=1

# Chạy một lệnh dưới quyền APP_USER khi script đang là root.
run_as_app() {
    if [[ $IS_ROOT -eq 1 ]] && id -u "$APP_USER" >/dev/null 2>&1; then
        sudo -u "$APP_USER" -- "$@"
    else
        "$@"
    fi
}

# Chạy lệnh cần quyền root (systemctl, chown...).
as_root() {
    if [[ $IS_ROOT -eq 1 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

artisan() {
    run_as_app "$PHP_BIN" "$APP_DIR/artisan" "$@" --no-interaction
}

# Chỉ restart service khi unit thực sự tồn tại trên máy.
restart_unit() {
    local unit="$1"
    if systemctl cat "$unit" >/dev/null 2>&1; then
        as_root systemctl restart "$unit"
        ok "đã restart $unit"
    else
        warn "bỏ qua $unit (chưa cài unit này — xem DEPLOY.md)"
    fi
}

MAINTENANCE_ON=0

cleanup() {
    local rc=$?
    if [[ $MAINTENANCE_ON -eq 1 ]]; then
        printf '\n'
        step "Tắt chế độ bảo trì"
        artisan up || warn "không tắt được chế độ bảo trì. Chạy tay: php artisan up"
        MAINTENANCE_ON=0
    fi
    if [[ $rc -ne 0 ]]; then
        printf '\n%s✘ Deploy THẤT BẠI (exit %s). Xem log phía trên.%s\n' "$C_ERR" "$rc" "$C_OFF" >&2
    fi
    return $rc
}
trap cleanup EXIT

# Kiểm tra URL trả về HTTP 200, thử lại vài lần vì php-fpm vừa restart.
check_url() {
    local url="$1"
    local tries="${2:-10}"
    local code i

    for ((i = 1; i <= tries; i++)); do
        code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url" 2>/dev/null || echo 000)"
        if [[ "$code" == "200" ]]; then
            ok "$url -> HTTP 200"
            return 0
        fi
        info "lần $i/$tries: $url -> HTTP $code, thử lại sau 3s..."
        sleep 3
    done

    warn "$url -> HTTP $code sau $tries lần thử"
    return 1
}

# -----------------------------------------------------------------------------
# 0. KIỂM TRA TRƯỚC KHI CHẠY
# -----------------------------------------------------------------------------
step "Kiểm tra môi trường"

need_cmd git
need_cmd curl
[[ -x "$PHP_BIN" ]] || need_cmd "$PHP_BIN"
[[ -x "$COMPOSER_BIN" ]] || need_cmd "$COMPOSER_BIN"

[[ -d "$REPO_DIR/.git" ]] || die "Không thấy repo git tại $REPO_DIR (đặt biến REPO_DIR cho đúng)."
[[ -f "$APP_DIR/artisan" ]] || die "Không thấy $APP_DIR/artisan (đặt biến APP_DIR cho đúng)."
[[ -f "$APP_DIR/.env" ]] || die "Không thấy $APP_DIR/.env. Xem DEPLOY.md để tạo file .env trước."

if [[ $IS_ROOT -eq 0 ]] && ! command -v sudo >/dev/null 2>&1; then
    die "Script cần quyền root (systemctl, chown). Chạy bằng: sudo bash $0"
fi

info "repo      : $REPO_DIR"
info "app       : $APP_DIR"
info "branch    : $GIT_REMOTE/$GIT_BRANCH"
info "php       : $($PHP_BIN -r 'echo PHP_VERSION;')"
info "chạy dưới : $(id -un)  (artisan chạy dưới: $APP_USER)"
ok "môi trường hợp lệ"

# Cảnh báo nếu .env vẫn ở chế độ debug — không chặn deploy, chỉ nhắc.
if grep -qE '^APP_DEBUG=(true|1)' "$APP_DIR/.env"; then
    warn "APP_DEBUG đang bật TRUE trong .env — production phải đặt APP_DEBUG=false"
fi
if grep -qE '^APP_ENV=local' "$APP_DIR/.env"; then
    warn "APP_ENV=local trong .env — production phải đặt APP_ENV=production"
fi

# -----------------------------------------------------------------------------
# 1. LẤY CODE MỚI
# -----------------------------------------------------------------------------
step "Lấy code mới từ $GIT_REMOTE/$GIT_BRANCH"

if [[ -n "$(git -C "$REPO_DIR" status --porcelain)" ]]; then
    if [[ "$ALLOW_DIRTY" == "1" ]]; then
        warn "working tree có thay đổi chưa commit — vẫn tiếp tục vì ALLOW_DIRTY=1"
    else
        git -C "$REPO_DIR" status --short
        die "Working tree tại $REPO_DIR không sạch. Commit/stash trước, hoặc chạy lại với ALLOW_DIRTY=1."
    fi
fi

BEFORE_SHA="$(git -C "$REPO_DIR" rev-parse --short HEAD)"

git -C "$REPO_DIR" fetch --prune "$GIT_REMOTE"
git -C "$REPO_DIR" checkout "$GIT_BRANCH"
git -C "$REPO_DIR" pull --ff-only "$GIT_REMOTE" "$GIT_BRANCH"

AFTER_SHA="$(git -C "$REPO_DIR" rev-parse --short HEAD)"

if [[ "$BEFORE_SHA" == "$AFTER_SHA" ]]; then
    info "không có commit mới (đang ở $AFTER_SHA) — vẫn chạy lại các bước build cho chắc"
else
    ok "$BEFORE_SHA -> $AFTER_SHA"
    git -C "$REPO_DIR" --no-pager log --oneline "$BEFORE_SHA..$AFTER_SHA" | head -20
fi

# -----------------------------------------------------------------------------
# 2. BẬT CHẾ ĐỘ BẢO TRÌ
# -----------------------------------------------------------------------------
if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
    step "Bật chế độ bảo trì"
    # --retry để client/app biết thử lại sau 15s thay vì coi là lỗi vĩnh viễn.
    if artisan down --retry=15; then
        MAINTENANCE_ON=1
        ok "app đang ở chế độ bảo trì (API trả 503 trong lúc deploy)"
    else
        warn "không bật được chế độ bảo trì — tiếp tục deploy trực tiếp"
    fi
else
    step "Bỏ qua chế độ bảo trì (SKIP_MAINTENANCE=1)"
fi

# -----------------------------------------------------------------------------
# 3. CÀI DEPENDENCY PHP
# -----------------------------------------------------------------------------
step "composer install (production)"

# --no-dev: bỏ phpunit/faker/pint... khỏi máy chủ.
# --optimize-autoloader: sinh classmap, giảm chi phí autoload mỗi request.
COMPOSER_ALLOW_SUPERUSER=1 \
    "$COMPOSER_BIN" install \
    --working-dir="$APP_DIR" \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

ok "dependency đã cài"

# -----------------------------------------------------------------------------
# 4. QUYỀN FILE
# -----------------------------------------------------------------------------
# Làm NGAY SAU composer (composer chạy dưới quyền root nên bootstrap/cache có thể
# bị root chiếm) và TRƯỚC mọi lệnh artisan chạy dưới quyền www-data.
step "Đặt lại quyền file"

as_root chown -R "$APP_USER:$APP_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
as_root chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# .env chứa DB password + OPENAI_API_KEY: chỉ owner đọc được.
as_root chown "$APP_USER:$APP_GROUP" "$APP_DIR/.env"
as_root chmod 600 "$APP_DIR/.env"

ok "storage/, bootstrap/cache/ thuộc $APP_USER; .env quyền 600"

# -----------------------------------------------------------------------------
# 5. MIGRATE DATABASE
# -----------------------------------------------------------------------------
if [[ "$SKIP_MIGRATE" != "1" ]]; then
    step "php artisan migrate --force"
    # --force vì môi trường production sẽ hỏi xác nhận nếu thiếu cờ này.
    artisan migrate --force
    ok "migration xong"
else
    step "Bỏ qua migrate (SKIP_MIGRATE=1)"
fi

# -----------------------------------------------------------------------------
# 6. SYMLINK STORAGE
# -----------------------------------------------------------------------------
step "Kiểm tra symlink public/storage"

if [[ -e "$APP_DIR/public/storage" ]]; then
    info "public/storage đã tồn tại -> $(readlink -f "$APP_DIR/public/storage" 2>/dev/null || echo '?')"
else
    artisan storage:link
    ok "đã tạo public/storage -> storage/app/public"
fi

# -----------------------------------------------------------------------------
# 7. BUILD CACHE
# -----------------------------------------------------------------------------
step "Build lại cache config/route/view"

# Phải clear TRƯỚC khi cache lại: config:cache đọc .env, mà nếu file config cũ
# còn nằm trong bootstrap/cache thì giá trị .env mới sẽ không được nạp.
artisan config:clear
artisan route:clear
artisan view:clear

artisan config:cache
artisan route:cache
artisan view:cache

# Xoá cache ứng dụng (CACHE_STORE=database -> bảng `cache`). Không bắt buộc,
# nhưng tránh dữ liệu cũ sau khi đổi code. Không để lỗi ở đây làm hỏng deploy.
artisan cache:clear || warn "cache:clear thất bại (bỏ qua)"

ok "cache đã build"

# -----------------------------------------------------------------------------
# 8. KHỞI ĐỘNG LẠI DỊCH VỤ
# -----------------------------------------------------------------------------
step "Khởi động lại dịch vụ"

# Báo cho worker đang chạy thoát êm sau khi xong job hiện tại (opcache/code cũ
# vẫn nằm trong tiến trình worker nếu không làm bước này).
artisan queue:restart || warn "queue:restart thất bại (bỏ qua)"

restart_unit "$PHP_FPM_SERVICE"
restart_unit "$QUEUE_SERVICE"

# Timer scheduler không cần restart khi deploy, chỉ kiểm tra còn bật không.
if systemctl cat "$SCHEDULER_TIMER" >/dev/null 2>&1; then
    if systemctl is-active --quiet "$SCHEDULER_TIMER"; then
        ok "$SCHEDULER_TIMER đang chạy"
    else
        warn "$SCHEDULER_TIMER KHÔNG chạy. Bật lại: sudo systemctl enable --now $SCHEDULER_TIMER"
    fi
else
    warn "chưa cài $SCHEDULER_TIMER (xem DEPLOY.md)"
fi

# nginx không cần restart khi chỉ đổi code PHP; chỉ reload nếu config còn hợp lệ.
if systemctl cat nginx.service >/dev/null 2>&1; then
    if as_root nginx -t >/dev/null 2>&1; then
        as_root systemctl reload nginx
        ok "đã reload nginx"
    else
        warn "nginx -t báo lỗi cấu hình — KHÔNG reload. Kiểm tra bằng: sudo nginx -t"
    fi
fi

# -----------------------------------------------------------------------------
# 9. TẮT BẢO TRÌ (trước health check, nếu không sẽ nhận 503)
# -----------------------------------------------------------------------------
if [[ $MAINTENANCE_ON -eq 1 ]]; then
    step "Tắt chế độ bảo trì"
    artisan up
    MAINTENANCE_ON=0
    ok "app đã online"
fi

# -----------------------------------------------------------------------------
# 10. HEALTH CHECK
# -----------------------------------------------------------------------------
step "Health check"

HEALTH_FAILED=0

check_url "$HEALTH_URL" 10 || HEALTH_FAILED=1
check_url "$API_HEALTH_URL" 5 || HEALTH_FAILED=1

if [[ $HEALTH_FAILED -eq 1 ]]; then
    warn "Health check KHÔNG đạt. Kiểm tra:"
    warn "  sudo tail -50 $APP_DIR/storage/logs/laravel.log"
    warn "  sudo tail -50 /var/log/nginx/stories.error.log"
    warn "  sudo systemctl status $PHP_FPM_SERVICE $QUEUE_SERVICE"
    die "Deploy đã chạy xong nhưng site không trả HTTP 200."
fi

printf '\n%s✔ DEPLOY THÀNH CÔNG — %s đang chạy commit %s%s\n\n' \
    "$C_OK" "$HEALTH_URL" "$AFTER_SHA" "$C_OFF"
