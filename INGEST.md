# API đăng truyện cho AI

Tài liệu này dành cho **một AI khác**: nó viết xong truyện rồi tự gọi API để đăng lên
Stories, kèm đặt hàng ảnh bìa và giọng đọc. Đưa nguyên file này cho nó là đủ.

- **Base URL**: `https://api.tunastory.com/api/ingest`
- **Xác thực**: mọi request kèm header `Authorization: Bearer <INGEST_TOKEN>`
  (hoặc `X-Ingest-Token: <INGEST_TOKEN>`). Thiếu/sai token → `401`.
  Máy chủ chưa cấu hình `INGEST_TOKEN` → `503` (mặc định đóng).
- Mọi request/response đều JSON. Nhớ gửi `Accept: application/json`.

> **Không gọi được API?** Nếu môi trường của bạn chặn egress (proxy trả `403` ngay ở
> bước `CONNECT`), **đừng tìm cách đi vòng**. Hãy dùng [lối xuất file](#lối-2--xuất-file-json-khi-bị-chặn-mạng)
> ở cuối tài liệu: bạn chỉ ghi ra một file JSON, việc đăng chạy ở nơi khác.

## Nguyên tắc phải nhớ

1. **Gọi lại được an toàn.** `slug` là khoá bất biến của truyện, `number` là khoá của
   chương. Đăng lại cùng khoá = **cập nhật**, không tạo bản sao. Timeout thì cứ gọi lại.
2. **Việc nặng chạy nền.** Vẽ bìa và đọc audio mất hàng chục giây tới vài phút, nên API
   chỉ **xếp hàng** rồi trả `202` ngay. Hỏi tiến trình bằng bước 5.
3. **Sửa nội dung chương thì audio cũ bị xoá** tự động, vì bản đọc cũ không còn khớp chữ.
   Sửa xong nhớ đặt lại audio.
4. **Truyện viết bằng tiếng Anh.** Giọng đọc và ảnh bìa đều dựng prompt tiếng Anh từ
   chính nội dung truyện.
5. **Luôn nộp truyện ĐỦ CHƯƠNG.** Không có khái niệm truyện đang viết dở ở đây. Việc
   nhả chương dần cho người đọc là do LỊCH ĐĂNG lo (xem bước 2), không phải do bạn
   giữ lại chương.
6. **Một truyện một ảnh bìa, một chương một audio.** Hệ thống tự chặn trùng ở nhiều
   lớp, nên cứ gọi lại thoải mái; nhưng đừng chủ ý gửi `force` nếu không thực sự cần
   làm lại, vì mỗi lần làm lại là một lần tốn tiền.

---

## Bước 1 — Lấy danh sách thể loại

```
GET /categories
```

```json
{ "data": [ { "id": 5, "name": "Revenge", "slug": "revenge" } ] }
```

Chỉ được dùng `slug` có trong danh sách này. Mặc định có: `billionaire`, `ceo`,
`family-drama`, `rags-to-riches`, `revenge`, `romance`, `second-chance`, `secret-identity`.

### Tạo thể loại mới (chỉ khi cần)

```
POST /categories
```

| Trường | Bắt buộc | Ghi chú |
|---|---|---|
| `name` | ✅ | tên hiển thị, phải chưa ai dùng |
| `slug` | — | bỏ trống thì tự suy từ `name`; đây là khoá bất biến |

```bash
curl -X POST https://api.tunastory.com/api/ingest/categories \
  -H "Authorization: Bearer $INGEST_TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name": "Sports Romance"}'
```

`201` khi tạo mới, `200` khi slug đã có (coi như đổi tên). Gửi `name` trùng một
thể loại khác mà `slug` lại khác thì trả `422` — tên là duy nhất.

**Đừng lạm dụng.** Thể loại quyết định giọng đọc và tông ảnh bìa; đẻ thêm slug lạ
sẽ rơi vào hồ sơ mặc định. Chỉ tạo khi thật sự không có cái nào phù hợp.

## Bước 2 — Tạo truyện

```
POST /stories
```

| Trường | Bắt buộc | Ghi chú |
|---|---|---|
| `title` | ✅ | tối đa 255 ký tự |
| `slug` | — | bỏ trống thì tự suy từ `title`. **Gửi lên nếu muốn tự kiểm soát khoá bất biến.** |
| `author` | — | tên bút danh |
| `description` | — | tóm tắt, tối đa 5000 ký tự |
| `status` | — | `ongoing` (mặc định) hoặc `completed` |
| `free_chapters` | — | số chương đầu miễn phí, mặc định `1` |
| `is_featured` | — | `true` để lên hero trang chủ |
| `categories` | — | mảng slug, tối đa 5 |
| `publish_every_hours` | — | **nhịp nhả chương**, tính bằng giờ. `24` = mỗi ngày một chương. Bỏ trống = đăng hết ngay. |
| `publish_start_at` | — | mốc của chương ĐẦU TIÊN (ISO 8601). Bỏ trống = ngay bây giờ. |

### Lịch đăng hoạt động thế nào

Bạn nộp cả 10 chương một lần, người đọc thấy dần:

```
publish_every_hours: 24, publish_start_at: bỏ trống

  ch1  ngay bây giờ     -> đọc được
  ch2  +24h             -> ẩn hoàn toàn
  ch3  +48h             -> ẩn hoàn toàn
```

- Mỗi chương tính từ **chương liền trước** cộng `publish_every_hours`, nên thêm
  chương lẻ về sau vẫn nối đúng vào đuôi lịch.
- Chương chưa tới giờ bị giấu **triệt để** khỏi API công khai: không có trong mục
  lục, không tính vào `chapters_count`, đọc thẳng URL thì trả `404`, và nút "chương
  sau" của chương trước cũng không trỏ tới.
- `status` mặc định là `completed` vì truyện đã đủ chương; nhưng **người đọc vẫn
  thấy "Ongoing"** chừng nào còn chương đang chờ, và tự chuyển sang "Completed" khi
  chương cuối tới giờ. Không cần bạn gọi lại để cập nhật.
- Muốn một chương ra vào giờ riêng thì khai `published_at` cho chính chương đó ở
  bước 3; giờ đã đặt sẽ **không bị dời** khi bạn nạp lại nội dung để sửa chữ.

```bash
curl -X POST https://api.tunastory.com/api/ingest/stories \
  -H "Authorization: Bearer $INGEST_TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{
    "title": "The Gardener Who Bought the Bank",
    "author": "Marcus Vale",
    "description": "He trimmed the hedges of the men who foreclosed on his mother.",
    "status": "ongoing",
    "free_chapters": 1,
    "categories": ["revenge", "secret-identity"]
  }'
```

`201` khi tạo mới, `200` khi cập nhật. Lấy `story.id` để dùng cho các bước sau.

## Bước 3 — Nạp từng chương

```
POST /stories/{story}/chapters
```

| Trường | Bắt buộc | Ghi chú |
|---|---|---|
| `number` | ✅ | 1, 2, 3… — cũng là khoá bất biến của chương |
| `title` | ✅ | |
| `content` | ✅ | văn xuôi tiếng Anh, ngăn đoạn bằng dòng trống, tối đa 200 000 ký tự |
| `published_at` | — | ghi đè giờ đăng của riêng chương này (ISO 8601). Bỏ trống = tính từ chương trước. |

Gọi lặp cho mỗi chương. Nạp lại cùng `number` là ghi đè.

## Bước 4 — Đặt hàng ảnh bìa và giọng đọc

```
POST /stories/{story}/cover                      # ảnh bìa
POST /stories/{story}/audio                      # đọc MỌI chương chưa có audio
POST /stories/{story}/chapters/{number}/audio    # đọc đúng một chương
```

Thêm `{"force": true}` để làm lại cái đã có. Tất cả trả `202` kèm `queued`.

Ảnh bìa được dựng qua hai bước: hệ thống **đọc chính truyện** (tiêu đề, tóm tắt,
đoạn mở đầu chương 1) để viết chỉ đạo chụp ảnh, rồi mới vẽ — nên bìa bám đúng bối
cảnh truyện chứ không phải ảnh minh hoạ chung chung. Không cần gửi prompt.

## Bước 5 — Theo dõi tới khi xong

```
GET /stories/{story}/status
```

```json
{
  "story": {
    "id": 11, "cover_status": "done",
    "thumbnail_url": "https://…/stories/….jpg?v=1788…",
    "publish_every_hours": 24,
    "published_chapters_count": 1
  },
  "chapters": [
    { "number": 1, "audio_status": "done", "has_audio": true,
      "published_at": null, "is_published": true },
    { "number": 2, "audio_status": "done", "has_audio": true,
      "published_at": "2026-09-11T13:00:00.000000Z", "is_published": false }
  ],
  "pending": false
}
```

Hỏi lại mỗi 15–30 giây cho tới khi `pending` là `false`. Trạng thái đi theo
`queued → processing → done | failed`; khi `failed` thì đọc `cover_error` /
`audio_error` để biết lý do.

---

## Trình tự gọn cho một truyện mới

```
GET  /categories
POST /categories              (chỉ khi thiếu thể loại phù hợp)
POST /stories                                  -> lấy story.id
POST /stories/{id}/chapters   (lặp mỗi chương)
POST /stories/{id}/cover
POST /stories/{id}/audio
GET  /stories/{id}/status     (lặp tới khi pending = false)
```

## Mã lỗi

| Mã | Ý nghĩa |
|---|---|
| `401` | thiếu hoặc sai token |
| `404` | không có truyện với id đó |
| `422` | dữ liệu sai — đọc `errors` để biết trường nào |
| `429` | gọi quá dày, chờ rồi thử lại |
| `503` | máy chủ chưa bật ingest (`INGEST_TOKEN` trống) |

## Bật API trên máy chủ

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"   # sinh token
# rồi thêm vào backend/.env:  INGEST_TOKEN=<token vừa sinh>
php artisan config:cache
```

Việc nền chỉ chạy khi có worker hàng đợi (`stories-queue.service` trên production,
hoặc `php artisan queue:work --timeout=900` khi chạy tay). Không có worker thì mọi thứ
sẽ mãi nằm ở `queued`.


---

# Lối 2 — Xuất file JSON (khi bị chặn mạng)

Có môi trường chạy AI chặn egress ở tầng tổ chức: mọi kết nối ra `api.tunastory.com`
bị proxy từ chối ngay ở bước `CONNECT`. Đó là chính sách bảo mật của tổ chức —
**không được đi vòng**, và cũng không cần, vì việc đăng truyện không nhất thiết phải
do chính AI thực hiện.

Cách làm: AI chỉ **ghi ra một file JSON**. Việc nạp chạy ở nơi vốn đã có quyền truy
cập (máy của chủ dự án, hoặc chính máy chủ).

## Định dạng file

Giống hệt thân request của API, chỉ gộp lại thành một gói:

```json
{
  "story": {
    "title": "The Gardener Who Bought the Bank",
    "author": "Marcus Vale",
    "description": "He trimmed the hedges of the men who foreclosed on his mother.",
    "status": "ongoing",
    "free_chapters": 1,
    "categories": ["revenge", "secret-identity"]
  },
  "chapters": [
    { "number": 1, "title": "Hedges and Ledgers", "content": "..." },
    { "number": 2, "title": "The Quiet Buyer",   "content": "..." }
  ],
  "generate": { "cover": true, "audio": true }
}
```

- Luật kiểm tra từng trường **y hệt** phần API ở trên (cùng một bộ mã).
- `generate` bỏ trống thì mặc định đặt cả bìa lẫn audio.
- `publish_every_hours` và `publish_start_at` đặt trong `story`, dùng y như phần API.
- Cần thể loại chưa tồn tại thì khai ở `new_categories` **ngay tại gốc gói** — đây là
  bản đối ứng của `POST /categories`, và nó chạy trước khi truyện được kiểm tra:

  ```json
  {
    "new_categories": [ { "name": "Sports Romance", "slug": "sports-romance" } ],
    "story": { "title": "...", "categories": ["sports-romance"] },
    "chapters": [ ... ]
  }
  ```

  `--dry` hiểu được các slug sắp tạo này nên gói hợp lệ vẫn báo hợp lệ, và vẫn
  không ghi gì vào cơ sở dữ liệu.
- Nạp nhiều truyện một lần: để một **mảng** các gói như trên ở gốc file.
- Kiểu rút gọn cũng chấp nhận: các trường của truyện nằm thẳng ở gốc, cạnh `chapters`.
- Danh sách `categories` hợp lệ: `billionaire`, `ceo`, `family-drama`, `rags-to-riches`,
  `revenge`, `romance`, `second-chance`, `secret-identity`.

## Nạp file

```bash
php artisan stories:import truyen.json --dry   # chỉ kiểm tra, KHÔNG ghi gì
php artisan stories:import truyen.json         # nạp và xếp hàng bìa + audio
php artisan stories:import truyen.json --force # vẽ lại bìa / đọc lại audio đã có
php artisan stories:import truyen.json --no-jobs  # chỉ nạp chữ
```

Luôn chạy `--dry` trước: nó kiểm tra **toàn bộ** các chương rồi mới quyết định, nên
file hỏng sẽ bị chặn sạch chứ không để lại một truyện cụt trong cơ sở dữ liệu.

Vẫn giữ nguyên tính bất biến: nạp lại cùng `slug` là cập nhật, không nhân bản.

## Nếu muốn dùng đường API thay vì file

Việc cần làm là **xin mở allow-list**, không phải né proxy. Gửi cho người quản trị
môi trường đúng thông tin sau:

| | |
|---|---|
| Host | `api.tunastory.com` |
| Cổng | `443` (HTTPS) |
| Chiều | chỉ đi ra (outbound) |
| Dùng để | đăng truyện qua REST API của chính dự án |
