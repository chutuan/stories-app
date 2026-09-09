# DEPLOY — Đưa backend Stories lên VPS Ubuntu

Runbook đi từ **một VPS Ubuntu trắng** đến **API chạy thật qua HTTPS**.
Không dùng Docker. Stack: **nginx + php-fpm 8.2 + MySQL + systemd**.

Phần triển khai app mobile (EAS Build / TestFlight / Play Internal Testing) nằm ở
**mục 14** cuối tài liệu này.

| | |
|---|---|
| **Thời gian** | ~45–60 phút cho lần đầu |
| **Yêu cầu VPS** | Ubuntu 22.04 hoặc 24.04 LTS, tối thiểu 2 GB RAM / 2 vCPU / 25 GB SSD |
| **Cần có sẵn** | 1 domain đã trỏ bản ghi `A` về IP của VPS, quyền `sudo`, khoá SSH |

> **RAM:** 1 GB chạy được nhưng rất sát, vì `composer install` và việc sinh audio TTS
> (ghép file MP3 bằng ffmpeg) đều ngốn bộ nhớ. Nếu chỉ có 1 GB thì bật swap 2 GB.

---

## 0. Quy ước placeholder

Mọi lệnh bên dưới dùng các giá trị mẫu sau. **Thay hết bằng giá trị thật của bạn**
trước khi chạy:

| Placeholder | Ý nghĩa | Ví dụ thật |
|---|---|---|
| `tunastory.com` | Domain của API + trang admin | `api.truyenhay.vn` |
| `/var/www/stories` | Thư mục repo (chứa `.git`) | giữ nguyên cũng được |
| `/var/www/stories/backend` | Thư mục Laravel (chứa `artisan`) | |
| `git@github.com:chutuan/stories-app.git` | Repo git | |
| `<MAT_KHAU_DB>` | Mật khẩu user MySQL của app | sinh bằng `openssl rand -base64 24` |
| `<MAT_KHAU_ADMIN>` | Mật khẩu đăng nhập trang admin | sinh bằng `openssl rand -base64 18` |

**Không gõ mật khẩu thật vào bất kỳ file nào được commit.** Chỉ đặt trong
`/var/www/stories/backend/.env` trên máy chủ.

---

## 1. Chuẩn bị VPS

Đăng nhập bằng SSH rồi cập nhật hệ thống:

```bash
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone Asia/Ho_Chi_Minh
```

Bật tường lửa (làm **trước** khi mở dịch vụ ra ngoài):

```bash
sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'      # mở cả 80 và 443
sudo ufw --force enable
sudo ufw status
```

---

## 2. Cài đặt gói

### 2.1 PHP 8.2 + extension

Ubuntu 22.04 mặc định có PHP 8.1, Ubuntu 24.04 có PHP 8.3. Project yêu cầu
`"php": "^8.2"` (`backend/composer.json`), nên dùng PPA của Ondřej để lấy đúng 8.2:

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y \
  php8.2-fpm php8.2-cli \
  php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-opcache
```

Vì sao cần từng extension:

| Extension | Dùng để làm gì |
|---|---|
| `mysql` (pdo_mysql) | Kết nối MySQL — bắt buộc |
| `mbstring`, `xml`, `curl`, `zip` | Yêu cầu chuẩn của Laravel + composer |
| `curl` | Gọi OpenAI TTS (`App\Services\ChapterAudioGenerator`) |
| **`gd`** | **Sinh ảnh bìa truyện.** `backend/database/seeders/covers/generate-covers.php` vẽ toàn bộ artwork bằng GD và tự thoát với lỗi `Thiếu extension GD.` nếu không có. Repo đã commit sẵn 10 file `.jpg` nên seed vẫn chạy được khi thiếu GD, nhưng sẽ **không vẽ lại bìa được** |
| `bcmath`, `intl` | Không bắt buộc cho code hiện tại, nhưng nhiều package Laravel giả định có sẵn |
| `opcache` | Tăng tốc PHP đáng kể trên production |

Kiểm tra:

```bash
php -v                       # phải ra PHP 8.2.x
php -m | grep -E 'gd|pdo_mysql|mbstring|curl|zip'
```

### 2.2 ffmpeg — BẮT BUỘC cho tính năng giọng đọc AI

```bash
sudo apt install -y ffmpeg
ffmpeg -version | head -1
```

**Vì sao:** endpoint `/v1/audio/speech` của OpenAI giới hạn ~4096 ký tự mỗi request,
nên `ChapterAudioGenerator` cắt nội dung chương thành nhiều đoạn, gọi TTS từng đoạn,
rồi **nối các file MP3 lại thành một file duy nhất**. Đoạn nối nằm ở
`backend/app/Services/ChapterAudioGenerator.php:287`:

```php
$ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
```

Nếu **không tìm thấy ffmpeg**, code rơi vào nhánh dự phòng **nối nhị phân thô** —
file vẫn phát được nhưng thường bị lỗi vặt (nhảy tiếng ở mối nối, thời lượng hiển thị
sai vì header MP3 của đoạn đầu không khớp tổng độ dài). Có ffmpeg thì file sạch.

### 2.3 nginx, MySQL, công cụ khác

```bash
sudo apt install -y nginx mysql-server git unzip curl
```

### 2.4 Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
# Đối chiếu hash với bảng trên https://composer.github.io/pubkeys.html trước khi chạy
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php
composer --version
```

> **Không cần Node.js / npm trên máy chủ.** Trang admin dùng Bootstrap 5 nạp từ CDN
> (`backend/resources/views/admin/layout.blade.php`), không có bước build Vite nào
> cần chạy khi deploy.

---

## 3. Tạo database và user MySQL riêng

**Tuyệt đối không cho ứng dụng dùng user `root`.** Nếu app bị SQL injection hoặc lộ
`.env`, user `root` cho phép đọc/ghi *mọi* database và trong nhiều cấu hình còn ghi
được file ra đĩa.

Chạy bảo mật MySQL trước:

```bash
sudo mysql_secure_installation
```

Tạo DB và user:

```bash
sudo mysql
```

```sql
CREATE DATABASE stories CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- QUAN TRỌNG: `.env` dùng DB_HOST=127.0.0.1 nên PDO kết nối qua TCP, không qua
-- socket. Với MySQL, 'stories'@'localhost' và 'stories'@'127.0.0.1' là HAI tài
-- khoản khác nhau; 'localhost' chỉ khớp kết nối TCP khi server còn bật phân giải
-- tên ngược. Tạo cả hai để không phụ thuộc vào `skip_name_resolve`.
CREATE USER 'stories'@'localhost' IDENTIFIED BY '<MAT_KHAU_DB>';
CREATE USER 'stories'@'127.0.0.1' IDENTIFIED BY '<MAT_KHAU_DB>';

-- Chỉ cấp quyền trên đúng database của app, và chỉ từ localhost.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON stories.* TO 'stories'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON stories.* TO 'stories'@'127.0.0.1';

FLUSH PRIVILEGES;
EXIT;
```

> Cần cả `CREATE/ALTER/INDEX/DROP` vì `php artisan migrate` tạo và sửa bảng.
>
> Nếu muốn chỉ một tài khoản: đổi `DB_HOST` trong `.env` thành `localhost` rồi chỉ
> tạo `'stories'@'localhost'` (khi đó PDO dùng unix socket).

Xác nhận MySQL **chỉ nghe trên localhost** (mặc định của Ubuntu, nhưng phải kiểm tra):

```bash
sudo ss -lntp | grep 3306        # phải là 127.0.0.1:3306, KHÔNG phải 0.0.0.0:3306
```

Kiểm tra user mới đăng nhập được:

```bash
mysql -u stories -p -e "SELECT DATABASE(), CURRENT_USER();" stories                 # qua socket
mysql -u stories -p -h 127.0.0.1 -e "SELECT DATABASE(), CURRENT_USER();" stories    # qua TCP — đúng đường Laravel dùng
```

---

## 4. Lấy code và cấu hình ứng dụng

### 4.1 Clone

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/chutuan/stories-app.git /var/www/stories
cd /var/www/stories/backend
```

> Nếu repo private, dùng SSH deploy key: tạo khoá bằng `ssh-keygen -t ed25519 -C
> "vps-stories"`, thêm public key vào GitHub → repo → Settings → Deploy keys, rồi
> clone bằng `git@github.com:chutuan/stories-app.git`.

### 4.2 Cài dependency

```bash
cd /var/www/stories/backend
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
```

- `--no-dev` bỏ phpunit / faker / pint / sail khỏi máy chủ.
- `--optimize-autoloader` sinh classmap, giảm chi phí autoload mỗi request.

### 4.3 Tạo file `.env`

```bash
cd /var/www/stories/backend
sudo cp .env.production.example .env
```

`.env.production.example` đã đặt sẵn mọi giá trị an toàn cho production
(`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
`LOG_LEVEL=warning`, ...). Mở `sudo nano .env` và **điền những ô còn trống + sửa domain**:

```dotenv
APP_KEY=                                   # để trống, bước 4.4 sinh tự động
APP_URL=https://tunastory.com        # PHẢI là domain thật + https, không có / ở cuối

DB_DATABASE=stories
DB_USERNAME=stories
DB_PASSWORD=<MAT_KHAU_DB>                  # mật khẩu tạo ở mục 3

# Tài khoản quản trị khởi tạo (Database\Seeders\AdminUserSeeder đọc qua config/admin.php).
# Trên APP_ENV=production, THIẾU ADMIN_PASSWORD thì seeder ném RuntimeException
# thay vì tạo tài khoản mật khẩu yếu — đây là hành vi cố ý, đừng tìm cách lách.
ADMIN_EMAIL=admin@tunastory.com
ADMIN_PASSWORD=<MAT_KHAU_ADMIN>

# Giọng đọc AI. Để trống nếu chưa dùng — nút "Tạo giọng đọc AI" sẽ báo lỗi rõ ràng
# thay vì tạo job hỏng.
OPENAI_API_KEY=sk-...

# Giới hạn tần suất API công khai, request/phút/IP (config/api.php). Giữ mặc định
# nếu chưa có lý do cụ thể để đổi.
API_RATE_LIMIT=60
API_RATE_LIMIT_READ=180

# CORS (config/cors.php). API chỉ đọc và app mobile không bị CORS ràng buộc, nên '*'
# chấp nhận được. Nếu sau này có web front-end thì liệt kê origin cụ thể, cách nhau
# bằng dấu phẩy: https://tunastory.com,https://www.tunastory.com
CORS_ALLOWED_ORIGINS=*
```

Kiểm tra lại vài giá trị bắt buộc trước khi đi tiếp:

```bash
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_USERNAME|ADMIN_PASSWORD)=' /var/www/stories/backend/.env
```

> ### `APP_URL` sai là hỏng ảnh bìa và audio
> `App\Support\PublicFileUrl` dựng URL file media bằng `Storage::disk('public')->url()`,
> mà hàm này ghép trên `APP_URL`. Nếu `APP_URL` vẫn là `http://localhost:8001`, API sẽ
> trả về `cover_url` / `audio_url` trỏ về localhost → **app mobile hiện ảnh trắng và
> không phát được audio**, dù backend hoàn toàn "chạy bình thường". Phải là
> `https://` + domain thật, **không có dấu `/` ở cuối**.

Khoá quyền file (chứa mật khẩu DB và API key):

```bash
sudo chown www-data:www-data /var/www/stories/backend/.env
sudo chmod 600 /var/www/stories/backend/.env
```

### 4.4 Sinh app key, migrate, seed

```bash
cd /var/www/stories/backend
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan migrate --seed --force
sudo -u www-data php artisan storage:link
```

`migrate --seed` chạy `AdminUserSeeder` + `CategorySeeder` + `StorySeeder` (tạo tài
khoản admin, 8 thể loại, và 10 truyện mẫu (47 chương) kèm ảnh bìa copy vào
`storage/app/public/stories/`).

> Nếu seeder dừng với lỗi *"Thiếu biến môi trường ADMIN_PASSWORD"* → bạn chưa điền
> `ADMIN_PASSWORD` ở bước 4.3. Điền vào rồi chạy lại `php artisan db:seed --force`.

### 4.5 Quyền thư mục

```bash
cd /var/www/stories/backend
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

`storage/` phải ghi được vì đây là nơi lưu log, session, cache view, **ảnh bìa và file
MP3 do admin upload / TTS sinh ra** (`storage/app/public/`).

---

## 5. Chỉnh `php.ini` — bắt buộc, nếu không sẽ không upload được MP3

Mặc định PHP giới hạn `upload_max_filesize = 2M` và `post_max_size = 8M`. Form upload
audio trong admin chấp nhận file tới **100 MB** (validate `max:102400` KB ở
`backend/app/Http/Controllers/Admin/ChapterController.php:102`). Không sửa `php.ini`
thì mọi file MP3 thực tế đều **bị PHP chặn im lặng** — form quay về trang cũ, không có
thông báo lỗi hữu ích.

Sửa file FPM:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
upload_max_filesize = 120M
post_max_size = 120M
memory_limit = 512M

; Đủ để nhận hết một file MP3 100 MB trên đường truyền chậm rồi ghi vào storage/.
; KHÔNG cần đặt tới hàng nghìn giây: việc sinh giọng đọc AI đã chạy ở queue worker
; (tiến trình CLI riêng), không nằm trong request web.
max_execution_time = 300
max_input_time = 300
```

`post_max_size` phải **>= `upload_max_filesize`**, nếu không PHP âm thầm cắt request.

Sửa thêm pool FPM để timeout đồng bộ với nginx:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
request_terminate_timeout = 300
```

Áp dụng:

```bash
sudo systemctl restart php8.2-fpm
php -i | grep -E 'upload_max_filesize|post_max_size'   # CLI, để tham khảo
```

> **Ba chỗ timeout phải khớp nhau:** `fastcgi_read_timeout` (nginx) =
> `request_terminate_timeout` (php-fpm) = `max_execution_time` (php.ini) = **300s**.
> Chỉ cần một chỗ nhỏ hơn là lần upload MP3 lớn bị cắt giữa chừng.
>
> Timeout dài của việc sinh audio **không liên quan tới ba giá trị này**: job chạy
> trong `php artisan queue:work --timeout=900`, là tiến trình CLI hoàn toàn tách khỏi
> php-fpm.

---

## 6. Cấu hình nginx + HTTPS

### 6.1 Cài server block

```bash
sudo cp /var/www/stories/deploy/nginx.conf /etc/nginx/sites-available/stories

# Thay placeholder domain (bắt buộc)
sudo sed -i 's/stories\.example\.com/DOMAIN-THAT-CUA-BAN/g' /etc/nginx/sites-available/stories

# Chỉ chạy dòng dưới NẾU bạn đặt code ở chỗ khác /var/www/stories/backend
# sudo sed -i 's#/var/www/stories/backend#/srv/stories/backend#g' /etc/nginx/sites-available/stories

sudo ln -sfn /etc/nginx/sites-available/stories /etc/nginx/sites-enabled/stories
sudo rm -f /etc/nginx/sites-enabled/default
sudo mkdir -p /var/www/certbot

sudo nginx -t && sudo systemctl reload nginx
```

Kiểm tra nhanh qua HTTP (lúc này chưa có TLS):

```bash
curl -i http://tunastory.com/up          # kỳ vọng HTTP/1.1 200
curl -s http://tunastory.com/api/home | head -c 300
```

Nếu `/up` trả 502: php-fpm chưa chạy hoặc sai đường dẫn socket.
Kiểm tra `ls -l /run/php/php8.2-fpm.sock` và `sudo tail -50 /var/log/nginx/stories.error.log`.

### 6.2 HTTPS bằng certbot — BẮT BUỘC

> **iOS chặn HTTP thường.** App Transport Security của Apple chặn mọi kết nối
> `http://` từ app. Không có cách vá phía JavaScript, và bản build TestFlight sẽ
> **không gọi được API** nếu backend chỉ có HTTP. Bước này không phải tuỳ chọn.

```bash
sudo apt install -y certbot python3-certbot-nginx

sudo certbot --nginx -d tunastory.com \
     --redirect --agree-tos -m admin@tunastory.com --no-eff-email
```

Certbot tự sửa file `/etc/nginx/sites-available/stories`: thêm block `443 ssl`, copy
cấu hình app sang, và thêm redirect `80 -> 443`. Không cần sửa tay.

> Nếu muốn tự làm bằng `certbot certonly --webroot -w /var/www/certbot`, file
> `deploy/nginx.conf` đã có sẵn block `443` viết đầy đủ ở cuối, chỉ cần bỏ comment.

Kiểm tra:

```bash
curl -I http://tunastory.com/up          # kỳ vọng 301 -> https://
curl -I https://tunastory.com/up         # kỳ vọng 200
sudo systemctl status certbot.timer            # gia hạn tự động
sudo certbot renew --dry-run
```

### 6.3 Trỏ app mobile về domain thật

Trong `mobile/app.config.js`, API URL đọc từ biến môi trường
`EXPO_PUBLIC_API_URL` (mặc định vẫn là `http://localhost:8001/api` cho môi trường dev).
Khi build EAS cho TestFlight/Play, đặt:

```
EXPO_PUBLIC_API_URL=https://tunastory.com/api
```

Chi tiết các bước build và nộp store: **mục 14** cuối tài liệu này.

---

## 7. Bật queue worker và scheduler

```bash
sudo cp /var/www/stories/deploy/stories-queue.service      /etc/systemd/system/
sudo cp /var/www/stories/deploy/stories-scheduler.service  /etc/systemd/system/
sudo cp /var/www/stories/deploy/stories-scheduler.timer    /etc/systemd/system/

# Chỉ chạy khối dưới NẾU bạn đặt code ở chỗ khác /var/www/stories/backend
# sudo sed -i 's#/var/www/stories/backend#/srv/stories/backend#g' \
#      /etc/systemd/system/stories-queue.service \
#      /etc/systemd/system/stories-scheduler.service

sudo systemctl daemon-reload
sudo systemctl enable --now stories-queue.service
sudo systemctl enable --now stories-scheduler.timer
```

Kiểm tra:

```bash
sudo systemctl status stories-queue
sudo systemctl list-timers stories-scheduler.timer
sudo journalctl -u stories-queue -n 30 --no-pager
```

> ### `stories-queue` là BẮT BUỘC, không phải tuỳ chọn
> Nút **"Tạo giọng đọc AI"** trong admin chỉ đẩy `App\Jobs\GenerateChapterAudio` vào
> hàng đợi (đặt `chapters.audio_status = 'queued'`) rồi trả về ngay. Chính worker này
> mới gọi OpenAI TTS và ghép MP3 bằng ffmpeg.
>
> **Worker không chạy = mọi chương kẹt vĩnh viễn ở trạng thái "Đang chờ"**, admin
> không thấy lỗi gì cả. Đây là kiểu hỏng im lặng khó đoán nhất, nên hãy kiểm tra
> `systemctl is-active stories-queue` mỗi lần deploy (script `deploy.sh` đã tự restart
> service này).
>
> `--timeout=900` trong unit khớp đúng `public int $timeout = 900` khai báo trong job;
> `--tries=2` khớp `public int $tries = 2`. Đổi một bên thì phải đổi bên kia.
>
> **Về scheduler:** `routes/console.php` hiện **chưa khai báo task định kỳ nào**, nên
> timer sẽ chạy không tải. Vẫn nên bật sẵn — nó vô hại và sẽ hoạt động ngay khi có task
> đầu tiên (dọn audio mồ côi, thống kê lượt đọc...).

Nếu thích cron hơn systemd timer, xem hướng dẫn trong `deploy/stories-scheduler.timer`
(chỉ dùng **một** trong hai cách).

---

## 8. Kiểm tra lần cuối

```bash
# Health endpoint có sẵn của Laravel (khai báo ở bootstrap/app.php)
curl -i https://tunastory.com/up

# Các endpoint app mobile thật sự gọi
curl -s https://tunastory.com/api/home       | head -c 400; echo
curl -s https://tunastory.com/api/categories | head -c 300; echo
curl -s https://tunastory.com/api/stories    | head -c 300; echo

# Rate limit đang hoạt động (phải thấy header X-RateLimit-*)
curl -sI https://tunastory.com/api/home | grep -i ratelimit

# File tĩnh có cache header dài
curl -sI "https://tunastory.com/storage/stories/the-janitor-owns-the-company.jpg" \
  | grep -iE 'cache-control|content-type'
```

Mở trình duyệt: `https://tunastory.com/admin/login` → đăng nhập bằng
`ADMIN_EMAIL` / `ADMIN_PASSWORD` đã đặt ở `.env`.

Kiểm tra tính năng nặng nhất — sinh giọng đọc AI. Vào một chương, bấm
**Tạo giọng đọc AI**: trang phải trả về **ngay lập tức** với trạng thái *Đang chờ*
(nếu nó treo vài phút thì job chưa được đưa vào hàng đợi). Sau đó xem worker làm việc:

```bash
sudo journalctl -u stories-queue -f
```

Trạng thái chạy đúng thứ tự `queued -> processing -> done`. Nếu đứng mãi ở *Đang chờ*:
worker chưa chạy (`sudo systemctl status stories-queue`). Nếu ra *Thất bại*: xem
`storage/logs/laravel.log`, thường là thiếu `OPENAI_API_KEY` hoặc hết hạn mức OpenAI.

---

## 9. Quy trình cập nhật về sau

Mọi lần deploy tiếp theo chỉ cần một lệnh:

```bash
sudo bash /var/www/stories/deploy/deploy.sh
```

Nếu đường dẫn hoặc domain khác mặc định, tạo `/etc/stories-deploy.env` **một lần**:

```bash
sudo tee /etc/stories-deploy.env >/dev/null <<'EOF'
REPO_DIR=/var/www/stories
GIT_BRANCH=main
PHP_FPM_SERVICE=php8.2-fpm
HEALTH_URL=https://tunastory.com/up
API_HEALTH_URL=https://tunastory.com/api/home
EOF
sudo chmod 600 /etc/stories-deploy.env
```

Script làm tuần tự: kiểm tra môi trường → bật chế độ bảo trì → `git pull --ff-only` →
`composer install --no-dev --optimize-autoloader` → đặt lại quyền file →
`migrate --force` → `storage:link` (nếu thiếu) → `config:clear`/`route:clear`/`view:clear`
rồi `config:cache`/`route:cache`/`view:cache` → `queue:restart` → restart php-fpm và
queue worker → reload nginx → tắt bảo trì → health check `/up` và `/api/home`.
Lỗi ở bất kỳ bước nào cũng làm script dừng và **tự tắt chế độ bảo trì** qua `trap EXIT`.

Cờ tuỳ chọn:

```bash
sudo SKIP_MAINTENANCE=1 bash /var/www/stories/deploy/deploy.sh   # không cho API 503
sudo SKIP_MIGRATE=1     bash /var/www/stories/deploy/deploy.sh   # không chạy migration
sudo ALLOW_DIRTY=1      bash /var/www/stories/deploy/deploy.sh   # bỏ qua kiểm tra git sạch
```

### Quay lui khi hỏng

```bash
cd /var/www/stories
git log --oneline -10
git checkout <SHA_TỐT>
sudo ALLOW_DIRTY=1 SKIP_MIGRATE=1 bash deploy/deploy.sh
```

Migration **không tự quay lui**. Nếu bản lỗi đã đổi cấu trúc DB, phải khôi phục từ
bản dump ở mục 11.

---

## 10. CHECKLIST BẢO MẬT — làm hết trước khi mở public

Kiểm tra từng dòng, **không bỏ qua dòng nào**.

- [ ] **`APP_DEBUG=false`** trong `.env`.
      `APP_DEBUG=true` làm trang lỗi Laravel in ra stack trace **kèm toàn bộ biến môi
      trường**, trong đó có `DB_PASSWORD` và `OPENAI_API_KEY`. Kiểm tra:
      `grep APP_DEBUG /var/www/stories/backend/.env`
- [ ] **`APP_ENV=production`**. Ngoài việc tắt màn debug, đây còn là công tắc khiến
      `AdminUserSeeder` từ chối tạo admin bằng mật khẩu mặc định.
- [ ] **Đã đổi mật khẩu admin.** Mặc định dev là `admin@stories.test` / `password`
      (`DEV_FALLBACK_PASSWORD` trong `database/seeders/AdminUserSeeder.php`). Trên
      production phải đặt `ADMIN_EMAIL` + `ADMIN_PASSWORD` trong `.env`. Đổi mật khẩu
      cho tài khoản đã tồn tại:
      ```bash
      cd /var/www/stories/backend
      sudo -u www-data php artisan tinker --execute="\
        \App\Models\User::where('email','admin@tunastory.com')\
          ->update(['password' => 'MAT_KHAU_MOI_RAT_MANH']);"
      ```
      (Model `User` khai báo cast `'password' => 'hashed'` nên Laravel tự hash, **không**
      truyền `bcrypt()` vào nữa kẻo bị hash hai lần.)
      Sau đó xác nhận đăng nhập được rồi mới yên tâm.
- [ ] **Không dùng user MySQL `root`.** `grep DB_USERNAME .env` phải ra `stories`,
      không phải `root`. Và user đó chỉ có quyền trên đúng database `stories`.
- [ ] **`.env` quyền 600, owner `www-data`.**
      ```bash
      stat -c '%a %U:%G %n' /var/www/stories/backend/.env   # kỳ vọng: 600 www-data:www-data
      ```
- [ ] **`.env` không truy cập được qua web.**
      ```bash
      curl -s -o /dev/null -w '%{http_code}\n' https://tunastory.com/.env   # kỳ vọng 403 hoặc 404
      curl -s -o /dev/null -w '%{http_code}\n' https://tunastory.com/.git/config
      ```
- [ ] **HTTPS đã bật và HTTP tự chuyển hướng sang HTTPS.**
      `curl -I http://tunastory.com/up` phải trả `301`. Bắt buộc vì iOS ATS.
- [ ] **Gia hạn chứng chỉ tự động chạy.** `sudo certbot renew --dry-run` phải sạch.
- [ ] **`SESSION_SECURE_COOKIE=true`** để cookie phiên admin không bao giờ đi qua HTTP.
- [ ] **Rate limit API đang chạy.** `curl -sI https://.../api/home | grep -i ratelimit`
      phải có header. Ngưỡng ở `config/api.php` (`API_RATE_LIMIT`,
      `API_RATE_LIMIT_READ`).
      **Nếu đặt Cloudflare hoặc load balancer trước VPS:** rate limit tính theo
      `$request->ip()`; khi có proxy đứng trước mà chưa cấu hình trusted proxy, **mọi
      request đều mang IP của proxy** → cả thế giới dùng chung một hạn mức và bị chặn
      oan. Phải khai báo trusted proxies trước khi bật proxy.
- [ ] **Tường lửa bật, chỉ mở 22/80/443.** `sudo ufw status`
- [ ] **MySQL chỉ nghe 127.0.0.1.** `sudo ss -lntp | grep 3306`
- [ ] **SSH khoá cứng:** đăng nhập bằng khoá, tắt mật khẩu và tắt login root.
      Trong `/etc/ssh/sshd_config`: `PasswordAuthentication no`, `PermitRootLogin no`,
      rồi `sudo systemctl restart ssh`. **Kiểm tra mở được phiên SSH mới TRƯỚC KHI
      thoát phiên hiện tại.**
- [ ] **`OPENAI_API_KEY` chỉ nằm trong `.env` trên máy chủ**, không có trong git.
      ```bash
      # Chỉ được ra chuỗi placeholder `sk-...` trong tài liệu, KHÔNG được ra key thật
      # (key thật dài ~50 ký tự trở lên sau tiền tố sk-).
      git -C /var/www/stories grep -nIE 'sk-[A-Za-z0-9_-]{16,}' $(git -C /var/www/stories rev-list --all) 2>/dev/null | head
      # Và chắc chắn chưa từng commit file .env nào:
      git -C /var/www/stories log --all --pretty=format: --name-only | sort -u | grep -E '(^|/)\.env($|\.)' | grep -v example
      ```
- [ ] **Đã có backup và đã thử khôi phục ít nhất một lần** (mục 11).

---

## 11. Backup

Hai thứ **không** nằm trong git và mất là không lấy lại được:

1. **Database** — toàn bộ truyện, chương, thể loại admin nhập tay.
2. **`storage/app/public/`** — ảnh bìa upload và **file MP3 giọng đọc AI**. Sinh lại
   audio tốn tiền OpenAI thật, nên đây là dữ liệu đắt. Cả hai thư mục
   `storage/app/public/stories` và `storage/app/public/audio` đều bị `.gitignore` loại
   trừ một cách có chủ đích.

```bash
sudo mkdir -p /var/backups/stories
sudo tee /usr/local/bin/stories-backup >/dev/null <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
STAMP="$(date +%F-%H%M)"
DEST=/var/backups/stories

mysqldump --single-transaction --quick --user=stories --password="$DB_PASSWORD" stories \
  | gzip > "$DEST/db-$STAMP.sql.gz"

tar -czf "$DEST/media-$STAMP.tar.gz" \
  -C /var/www/stories/backend/storage/app public

# Giữ 14 ngày gần nhất
find "$DEST" -name '*.gz' -mtime +14 -delete
EOF
sudo chmod 700 /usr/local/bin/stories-backup
```

Đặt `DB_PASSWORD` trong `/etc/stories-deploy.env` hoặc dùng file `~/.my.cnf` quyền 600
để không lộ mật khẩu trong danh sách tiến trình. Lên lịch:

```bash
sudo crontab -e
# 30 3 * * * . /etc/stories-deploy.env && /usr/local/bin/stories-backup
```

**Chép bản backup ra khỏi VPS** (rsync/S3/Backblaze). Backup nằm cùng máy với dữ liệu
gốc thì không phải là backup.

---

## 12. Giới hạn đã biết của bản hiện tại

Ghi lại để không ai ngộ nhận hệ thống có gì mà thực ra không có.

### 12.1 Nội dung chương là công khai với mọi request

API `/api` **không có xác thực**. Endpoint
`GET /api/stories/{story}/chapters/{number}` trả **đầy đủ nội dung** của bất kỳ chương
nào cho bất kỳ ai gọi:

```bash
curl -s https://tunastory.com/api/stories/1/chapters/3
```

Cơ chế "chương 1 miễn phí, chương 2+ khoá 30 xu" **hoàn toàn do client quản lý**: số xu
lưu trong AsyncStorage trên máy người dùng, việc mở khoá chỉ là logic trong app. Đây là
hệ quả trực tiếp của **mô hình guest — không có tài khoản người dùng** (đúng theo
SPEC.md), không phải lỗi cấu hình.

Nghĩa là:

- Bất kỳ ai biết URL API đều đọc được toàn bộ truyện mà không tốn xu.
- Bất kỳ ai gỡ app ra đọc code đều bỏ qua được cơ chế khoá.
- Rate limit ở mục 10 **làm chậm** việc cào dữ liệu chứ **không ngăn** được.

**Muốn khoá thật thì phải có tài khoản người dùng ở server**: đăng nhập, lưu số xu và
danh sách chương đã mở khoá trong DB, và endpoint chương chỉ trả nội dung khi người
dùng đã mở khoá chương đó. Đây là thay đổi nghiệp vụ lớn, nằm ngoài phạm vi deploy.

### 12.2 Sinh audio TTS phụ thuộc hoàn toàn vào queue worker

Việc sinh audio đã được tách khỏi request web (`App\Jobs\GenerateChapterAudio`), nên
không còn nguy cơ 502/504. Đổi lại, hệ thống có một điểm phụ thuộc mới:

- **Worker chết là tính năng chết im lặng.** Chương kẹt ở `audio_status = 'queued'`,
  admin không nhận được thông báo nào. `Restart=always` trong unit xử lý được trường
  hợp worker crash, nhưng không xử lý được trường hợp ai đó quên
  `systemctl enable` sau khi cài lại máy.
- **Chỉ có một worker, xử lý tuần tự.** Sinh audio cho 20 chương thì chương cuối phải
  chờ 19 chương trước xong. Muốn nhanh hơn thì chạy nhiều instance của unit (dùng
  systemd template `stories-queue@.service`) — nhưng nhớ hạn mức API của OpenAI.
- **Job thất bại nằm lại bảng `failed_jobs`.** Nên xem định kỳ:
  `php artisan queue:failed`, chạy lại bằng `php artisan queue:retry all`.
- **Không có hàng đợi riêng cho việc nặng.** Mọi job dùng chung queue `default`. Nếu
  sau này thêm loại job cần phản hồi nhanh, phải tách queue riêng, vì một job TTS đang
  chạy có thể chiếm worker tới 900 giây.

### 12.3 Chưa có gì khác

- **Chưa có CDN** cho ảnh bìa và MP3. nginx phục vụ trực tiếp với cache 1 năm (URL đã
  có `?v=<mtime>` nên an toàn), nhưng lưu lượng audio sẽ ăn hết băng thông VPS khi
  lượng người dùng tăng. Cân nhắc S3/R2 + CDN sau này.
- **Chưa có giám sát / cảnh báo**. Tối thiểu nên dựng uptime check ngoài trỏ vào
  `https://tunastory.com/up`.
- **Chưa có tổng hợp log**. Log nằm rải ở `storage/logs/laravel.log`,
  `/var/log/nginx/stories.*.log` và `journalctl -u stories-queue`.
- **Deploy có downtime ngắn** (chế độ bảo trì trong lúc `composer install` và
  `migrate`). Chấp nhận được ở giai đoạn này; muốn zero-downtime thì cần cơ chế thư
  mục release + symlink kiểu Envoyer/Deployer.

---

## 13. Xử lý sự cố nhanh

| Triệu chứng | Nguyên nhân thường gặp | Cách kiểm tra |
|---|---|---|
| 502 Bad Gateway ở mọi trang | php-fpm chết hoặc sai socket | `sudo systemctl status php8.2-fpm`; `ls -l /run/php/php8.2-fpm.sock` |
| Bấm "Tạo giọng đọc AI" xong kẹt mãi ở *Đang chờ* | queue worker không chạy | `sudo systemctl status stories-queue`; `sudo journalctl -u stories-queue -n 50` |
| Audio báo *Thất bại* | Thiếu/sai `OPENAI_API_KEY`, hết hạn mức, hoặc thiếu ffmpeg | `sudo tail -50 .../storage/logs/laravel.log`; `php artisan queue:failed` |
| Trang trắng / 500 mà không có chi tiết | `APP_DEBUG=false` (đúng như mong muốn) | `sudo tail -100 /var/www/stories/backend/storage/logs/laravel.log` |
| Upload MP3 quay về trang cũ, không báo lỗi | `upload_max_filesize`/`post_max_size` quá nhỏ | Mục 5 |
| 413 Request Entity Too Large | `client_max_body_size` của nginx quá nhỏ | Đã đặt 120M trong `deploy/nginx.conf` |
| App mobile hiện ảnh trắng, không phát audio | `APP_URL` sai (còn localhost/http) | `grep APP_URL .env`; sửa rồi `php artisan config:cache` |
| Đổi `.env` mà không có tác dụng | Config đang bị cache | `sudo -u www-data php artisan config:clear && sudo -u www-data php artisan config:cache` |
| 429 Too Many Requests khi test | Rate limit đang chạy đúng | Chờ 1 phút, hoặc chỉnh `API_RATE_LIMIT` |
| iOS build không gọi được API, Android thì được | API còn HTTP — ATS chặn | `curl -I https://...` phải 200 |
| `git pull` báo dubious ownership | Repo thuộc user khác | `sudo git config --global --add safe.directory /var/www/stories` |

Log cần xem theo thứ tự:

```bash
sudo tail -100 /var/www/stories/backend/storage/logs/laravel.log
sudo tail -100 /var/log/nginx/stories.error.log
sudo journalctl -u php8.2-fpm -n 100 --no-pager
sudo journalctl -u stories-queue -n 100 --no-pager
```


---

## 14. App mobile — EAS Build, TestFlight, Play Internal Testing

Chạy mọi lệnh trong thư mục `mobile/`. Yêu cầu **Node.js >= 20** (`node -v`).
Không có file `app.json` — nguồn cấu hình duy nhất là `mobile/app.config.js`.

### 14.1 Kiểm tra cấu hình trước khi build

```bash
cd mobile
npm ci
npx tsc --noEmit                    # phải exit 0
npx expo config --type public       # xác nhận 4 giá trị dưới đây
```

Bốn giá trị bắt buộc phải đúng, nếu không EAS sẽ không build được:

| Khoá | Giá trị |
|---|---|
| `name` | `Stories` |
| `slug` | `stories` |
| `ios.bundleIdentifier` | `com.chutuan.stories` |
| `android.package` | `com.chutuan.stories` |

### 14.2 Tạo project trên Expo — BẮT BUỘC làm trước lần build đầu

`extra.eas.projectId` trong `app.config.js` đang **rỗng** (đọc từ biến `EAS_PROJECT_ID`).
Vì đây là dynamic config, `eas init` **không tự ghi được** vào file — nó chỉ in ID ra
màn hình, bạn phải tự đặt:

```bash
npm i -g eas-cli
eas login
eas init                            # in ra projectId dạng UUID
```

Rồi đặt ID đó vào `mobile/.env` (không commit):

```dotenv
EAS_PROJECT_ID=<UUID-vua-nhan>
```

Kiểm tra lại: `npx expo config --type public | grep -A2 eas` phải thấy `projectId` khác rỗng.

### 14.3 Điền giá trị thật vào `mobile/eas.json`

Profile `production` hiện còn **giá trị mẫu**. Sửa mục `build.production.env`:

```json
"EXPO_PUBLIC_API_URL": "https://tunastory.com/api",
"EXPO_PUBLIC_ADMOB_ANDROID_APP_ID": "ca-app-pub-XXXX~YYYY",
"EXPO_PUBLIC_ADMOB_IOS_APP_ID": "ca-app-pub-XXXX~ZZZZ",
"EXPO_PUBLIC_ADMOB_BANNER_ID": "ca-app-pub-XXXX/AAAA",
"EXPO_PUBLIC_ADMOB_REWARDED_ID": "ca-app-pub-XXXX/BBBB"
```

> **`EXPO_PUBLIC_API_URL` bắt buộc `https://`.** App Transport Security của iOS chặn
> `http://`; bản TestFlight sẽ không gọi được API và không có cách vá phía JS.
>
> **AdMob để trống = KHÔNG có doanh thu.** `mobile/src/lib/ads.tsx` và
> `mobile/app.config.js` fallback về unit ID TEST của Google
> (`ca-app-pub-3940256099942544/...`) khi biến rỗng — quảng cáo vẫn hiện, vẫn bấm được,
> nhưng không sinh tiền. Dùng ID thật rồi thì **không được tự bấm quảng cáo của chính
> mình**, AdMob khoá tài khoản vì việc đó.

`eas.json` có comment `//` — hợp lệ, EAS CLI parse bằng JSON5. Đừng chạy qua công cụ
JSON nghiêm ngặt nào rồi ghi đè lại file.

### 14.4 Build

```bash
eas build --platform ios     --profile production
eas build --platform android --profile production
```

`cli.appVersionSource = "remote"` + `autoIncrement: true` (profile `production`) nghĩa là
EAS giữ và tự tăng `ios.buildNumber` / `android.versionCode` trên server. **Không khai
hai giá trị đó trong `app.config.js`** — chỉ tăng `version` (`1.0.0`) khi phát hành bản
mới cho người dùng.

Muốn bản APK cài tay để test nội bộ trước: `eas build -p android --profile preview`
(nhớ sửa `EXPO_PUBLIC_API_URL` của profile `preview` khỏi `api.tunastory.com`).

### 14.5 Nộp store

```bash
eas submit --platform ios     --profile production   # -> TestFlight
eas submit --platform android --profile production   # -> Play Internal Testing (track: internal)
```

Chuẩn bị trước:

- **iOS**: app đã tạo trong App Store Connect. Điền `submit.production.ios`
  (`appleId`, `ascAppId`, `appleTeamId`) trong `eas.json`, hoặc để trống và trả lời
  các câu hỏi EAS CLI hỏi lúc submit.
- **Android**: file service account JSON của Google Play, đặt tại
  `mobile/google-play-service-account.json` và khai
  `"serviceAccountKeyPath": "./google-play-service-account.json"`.
  **File này KHÔNG được commit** — `mobile/.gitignore` đã chặn sẵn.

### 14.6 Sau khi cài bản build lên máy thật

- Mở app, kiểm tra trang chủ có truyện (nếu trắng: sai `EXPO_PUBLIC_API_URL`, hoặc
  backend còn `APP_URL=http://localhost` nên ảnh bìa trỏ sai — xem mục 4.3).
- Vào chương có audio, bấm phát: chứng minh nginx phục vụ được `/storage/audio/...`
  qua HTTPS kèm Range request.
- Bấm quảng cáo có thưởng một lần để chắc SDK AdMob đã khởi tạo (chỉ làm với ID TEST).
