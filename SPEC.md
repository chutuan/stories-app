# Stories — App đọc truyện (Spec dùng chung)

Nguồn sự thật cho cả `backend/` (Laravel) và `mobile/` (Expo React Native).
Mọi tên endpoint, tên field JSON phải khớp CHÍNH XÁC giữa 2 bên.

## 1. Tổng quan
- Backend: Laravel + MySQL. Hai vai trò: (a) **Admin web** (Blade) để CRUD thể loại/truyện/chương + upload ảnh; (b) **REST API** công khai cho mobile đọc.
- Mobile: Expo + TypeScript + expo-router. Chế độ **guest** (không đăng nhập). Xu và danh sách chương đã mở khóa lưu **local** bằng AsyncStorage.
- Kiếm tiền: **Banner** (trên cùng màn đọc) + **Rewarded** (AdMob).
- Kinh tế xu: 1 rewarded xem hết = **+30 xu**. Mở 1 chương = **10 xu**.
- Khóa chương: **Chương 1 miễn phí**, **chương 2 trở đi khóa** (theo `story.free_chapters`, mặc định 1).

## 2. Data model (MySQL)

### categories
- id (PK)
- name (string, unique)
- slug (string, unique)
- timestamps

### stories
- id (PK)
- title (string)
- slug (string, unique)
- author (string, nullable)
- description (text, nullable)
- thumbnail (string, nullable) — đường dẫn file trong storage (vd `stories/abc.jpg`)
- status (enum: 'ongoing' | 'completed', default 'ongoing') — hiển thị "Đang ra" / "Hoàn thành"
- free_chapters (unsigned int, default 1) — số chương đầu miễn phí
- views (unsigned bigint, default 0)
- is_featured (boolean, default false) — dùng cho hero banner trang chủ
- timestamps

### chapters
- id (PK)
- story_id (FK -> stories, cascade delete)
- number (unsigned int) — thứ tự chương, bắt đầu từ 1. unique theo (story_id, number)
- title (string)
- content (longtext)
- audio_path (string, nullable) — đường dẫn file MP3 trong storage disk `public` (vd `audio/abc.mp3`).
  App KHÔNG đọc TTS trên máy: nghe chương = phát file MP3 do server cung cấp qua `audio_url`.
- audio_status (string(20), nullable) — tiến trình sinh giọng đọc AI:
  `null` (chưa từng chạy) | `queued` | `processing` | `done` | `failed`. Chỉ dùng cho admin, KHÔNG lộ ra API.
- audio_error (text, nullable) — thông báo lỗi của lần chạy gần nhất (chỉ có nghĩa khi `audio_status = failed`).
- timestamps

### category_story (pivot, many-to-many)
- category_id (FK)
- story_id (FK)
Một truyện có NHIỀU thể loại (khớp app mẫu: "Tiên Hiệp, Huyền Huyễn, Trọng Sinh").

### users (mặc định Laravel) — chỉ dùng cho đăng nhập admin.

## 3. Quy tắc khóa chương (client tự xử lý)
- `is_free = (chapter.number <= story.free_chapters)`.
- API TRẢ nội dung chương cho mọi request (không chặn server-side vì là guest/không tài khoản).
  Mobile tự gate hiển thị: chương free → đọc ngay; chương khóa & chưa unlock local → hiện màn khóa.
- (Ghi chú: muốn chặn thật cần tài khoản + entitlement server-side — ngoài phạm vi MVP.)

## 4. REST API — prefix `/api`, trả JSON. Không auth.

Quy ước ảnh: mọi field `*_url` là URL tuyệt đối (dùng `asset('storage/...')` / `Storage::url`),
kèm query `?v=<mtime>` để chống cache khi ảnh đổi mà tên file giữ nguyên.
Ảnh null -> trả `null` (mobile tự hiện placeholder).

### GET /api/home
Trả dữ liệu cho màn Trang chủ trong 1 call.
```json
{
  "featured": Story | null,          // truyện is_featured mới nhất (hero banner)
  "updated": [StoryCard],            // 10 truyện cập nhật gần nhất (row "Cập nhật")
  "newest":  [StoryCard]             // 10 truyện mới tạo gần nhất (row "Mới nhất")
}
```

### GET /api/categories
Sắp xếp theo `name`. Mỗi thể loại kèm ảnh bìa đại diện + số truyện.
```json
[
  {
    "id": 1,
    "name": "Tiên Hiệp",
    "slug": "tien-hiep",
    "cover_url": "http://.../storage/stories/x.jpg?v=1785774675" | null,
    "stories_count": 4
  }, ...
]
```
- `cover_url`: thumbnail của truyện **mới nhất** (created_at desc) thuộc thể loại đó **có ảnh bìa**;
  `null` nếu thể loại chưa có truyện nào có ảnh.
- `stories_count` (int): số truyện thuộc thể loại.
- LƯU Ý: danh sách `categories[]` **nhúng** trong StoryCard/Story vẫn giữ shape gọn
  `{id, name, slug}` (không có `cover_url`/`stories_count`).

### GET /api/stories
Query params:
- `?search=` — khớp `title` hoặc `author` (LIKE).
- `?category=<slug>`
- `?sort=updated|newest|views` (default `updated`)
  - `updated` — updated_at giảm dần (mặc định)
  - `newest` — created_at giảm dần
  - `views` — **lượt xem giảm dần** (tab "Xếp hạng"); tie-break bằng updated_at desc
- `?free=1` — **chỉ truyện đọc miễn phí toàn bộ**: `free_chapters >= tổng số chương`
  và truyện phải có ít nhất 1 chương (tab "Miễn phí"). Nhận `1|true|on|yes`.
- `?page=`

Các tham số kết hợp được với nhau (vd `?free=1&sort=views&category=tien-hiep`).
Trả phân trang Laravel (20 truyện/trang):
```json
{
  "data": [StoryCard],
  "current_page": 1, "last_page": 5, "per_page": 20, "total": 93
}
```

### GET /api/stories/{id}
Story đầy đủ + danh sách chương (KHÔNG kèm content).
```json
{
  ...Story,
  "chapters": [
    { "id": 12, "number": 1, "title": "Chương 1: ...", "is_free": true, "has_audio": false }, ...
  ]
}
```
- `has_audio` (bool): chương đã có file MP3 trên server hay chưa (dùng để hiện icon 🔊 trong danh sách chương).
- Danh sách chương KHÔNG kèm `content`.

### GET /api/stories/{id}/chapters/{number}
Nội dung 1 chương.
```json
{
  "id": 12, "story_id": 3, "number": 2, "title": "Chương 2: ...",
  "content": "….", "is_free": false,
  "audio_url": "http://.../storage/audio/abc.mp3?v=1788937203" | null,
  "prev": 1, "next": 3,          // number chương trước/sau, null nếu không có
  "story": { "id": 3, "title": "…", "free_chapters": 1 }
}
```
- `audio_url` (string|null): link MP3 tuyệt đối (Content-Type `audio/mpeg`) + `?v=<mtime>` chống cache.
  `null` khi chương chưa có audio hoặc file đã bị xóa khỏi disk.
  Mobile phát bằng `expo-audio` — **KHÔNG dùng TTS trên máy**.
- Gọi API này cũng tăng `views` của truyện (+1) mà không đụng `updated_at`.

## 5. JSON shapes

### StoryCard (dùng trong list/row)
```json
{
  "id": 3,
  "title": "Cực Phẩm Thần Y",
  "slug": "cuc-pham-than-y",
  "author": "Đường Gia Tam Thiếu",
  "thumbnail_url": "http://.../storage/stories/x.jpg" | null,
  "status": "ongoing",
  "status_label": "Đang ra",
  "categories": [ { "id":1, "name":"Đô Thị", "slug":"do-thi" } ],
  "views": 481530,
  "chapters_count": 375,
  "latest_chapter_number": 375,
  "updated_at": "2026-07-19T08:00:00Z"
}
```

### Story (chi tiết) = StoryCard + `description`, `free_chapters`, `is_featured`
(`views` đã có sẵn trong StoryCard). Khi có `chapters[]` thì mỗi phần tử là
`{ id, number, title, is_free, has_audio }`.

## 6. Admin web (Laravel Blade)
- Đăng nhập admin tại `/admin/login` (session auth, 1 user seed: email `admin@stories.test`, password `password`).
- `/admin` dashboard: đếm truyện/chương/thể loại.
- CRUD `/admin/categories`, `/admin/stories` (form upload thumbnail, chọn nhiều category, set is_featured/status/free_chapters), `/admin/stories/{story}/chapters` (CRUD chương của 1 truyện).
- Form chương có ô **upload audio MP3** (`name="audio"`, form `enctype=multipart/form-data`):
  - File lưu vào `storage/app/public/audio`, đường dẫn ghi vào `chapters.audio_path`.
  - Đã có audio -> hiện `<audio controls>` nghe thử + nút **Xóa audio**
    (`DELETE /admin/stories/{story}/chapters/{chapter}/audio`, route name `admin.stories.chapters.audio.destroy`).
  - Upload file mới sẽ **thay** file cũ (xóa file cũ trên disk). Xóa chương / xóa truyện cũng dọn file audio.
  - Validate: `mimes:mp3,mpga,m4a,aac,wav,ogg`, tối đa 100MB — nhưng vẫn bị chặn bởi
    `upload_max_filesize`/`post_max_size` của PHP (mặc định 2M/8M), form có hiển thị giá trị hiện tại.
  - Danh sách chương có cột **Audio** (🔊 Có / —) kèm badge tiến trình sinh giọng đọc AI
    (Đang chờ / Đang tạo / Lỗi — xem mục 9).
  - Nút **🎙️ Tạo giọng đọc AI** chỉ **đưa vào hàng đợi** rồi trả về ngay
    (`POST /admin/stories/{story}/chapters/{chapter}/audio/generate`, route name
    `admin.stories.chapters.audio.generate`); form sửa chương hiện trạng thái + lỗi nếu có.
- UI: Bootstrap 5 qua CDN (không cần build asset/npm).
- Upload ảnh lưu vào `storage/app/public/stories`, chạy `php artisan storage:link`.

## 7. Seed data

Nội dung là truyện **tiếng Anh**, theo mô-típ **"hidden billionaire"**: nhân vật chính bị coi thường
vì trông nghèo (lao công, shipper, chồng "vô dụng", con rể nghèo, khách đi xe cũ...) rồi lộ ra là
chủ tịch / người thừa kế cực giàu. Cao trào là khoảnh khắc lộ thân phận và bộ mặt của những kẻ
từng khinh thường.

### 8 thể loại (name | slug)
| Name | Slug |
|---|---|
| Billionaire | `billionaire` |
| CEO | `ceo` |
| Secret Identity | `secret-identity` |
| Romance | `romance` |
| Revenge | `revenge` |
| Family Drama | `family-drama` |
| Rags to Riches | `rags-to-riches` |
| Second Chance | `second-chance` |

### 10 truyện mẫu
| # | Title | Slug | Author | Status | Featured | free_chapters | Thể loại |
|---|---|---|---|---|---|---|---|
| 1 | The Janitor Owns the Company | `the-janitor-owns-the-company` | Marcus Vale | ongoing | ✅ | 1 | secret-identity, ceo |
| 2 | My Broke Husband Is a Billionaire | `my-broke-husband-is-a-billionaire` | Elena Hart | ongoing | ✅ | 2 | romance, billionaire |
| 3 | The Beggar at the Board Meeting | `the-beggar-at-the-board-meeting` | Daniel Cross | ongoing | — | 1 | ceo, secret-identity |
| 4 | Return of the Hidden Heir | `return-of-the-hidden-heir` | Victor Lang | completed | — | 1 | revenge, billionaire |
| 5 | She Laughed at His Old Car | `she-laughed-at-his-old-car` | Sophie Bennett | ongoing | — | 3 | romance, revenge |
| 6 | Son-in-Law of the Silver Empire | `son-in-law-of-the-silver-empire` | Adrian Wolfe | ongoing | — | 1 | family-drama, billionaire |
| 7 | The Delivery Boy Who Bought the Mall | `the-delivery-boy-who-bought-the-mall` | Ryan Cole | completed | — | 2 | rags-to-riches, secret-identity |
| 8 | Ten Years Poor, One Day King | `ten-years-poor-one-day-king` | Nathan Reed | ongoing | — | 1 | rags-to-riches, second-chance |
| 9 | My Landlord Is a Secret CEO | `my-landlord-is-a-secret-ceo` | Grace Miller | ongoing | — | 2 | romance, ceo |
| 10 | The Pauper's Revenge Empire | `the-paupers-revenge-empire` | Lucas Grant | completed | — | 1 | revenge, second-chance |

- Mỗi truyện 4–6 chương, `content` là văn xuôi **tiếng Anh** (vài đoạn/chương). 2 truyện `is_featured` (#1, #2).
- Mỗi truyện gán 2 thể loại theo bảng trên (tên + slug phải khớp CHÍNH XÁC — giọng đọc AI khoá theo slug này).
- Tab "Miễn phí" (`?free=1`) chỉ có dữ liệu khi tồn tại truyện thoả `free_chapters >= tổng số chương`;
  seeder cần đảm bảo có 1–2 truyện như vậy (cho truyện đó ít chương hơn `free_chapters` đã chốt).
- Seeder KHÔNG tạo file audio; audio sinh bằng `php artisan chapters:audio` hoặc upload thủ công qua admin.
- Không bắt buộc ảnh thật (thumbnail null -> placeholder).

## 8. Mobile — cấu trúc & hành vi

### Kinh tế xu (constants)
- `COIN_PER_REWARD = 30`
- `COIN_PER_CHAPTER = 10`
- `STARTER_COINS = 0`

### Local store (AsyncStorage) — module `store/wallet`
- `coins: number`
- `unlocked: string[]`  // key `"<storyId>:<number>"`
- API: `getCoins()`, `addCoins(n)`, `spendCoins(n): boolean`, `isUnlocked(storyId, number)`, `unlock(storyId, number)`, dùng React Context để re-render.
- `savedStories` (Đã lưu) cũng lưu local: `string[]` id.

### Màn hình (expo-router)
- Tabs: `Trang chủ` (index) · `Tìm kiếm` (search) · `Đã lưu` (saved).
- Stack ngoài tabs: `story/[id]` (chi tiết + danh sách chương có badge khóa) · `reader/[storyId]/[number]` (đọc).
- Home: hero featured + row "Cập nhật" (updated) + row "Mới nhất" (newest), card có badge số chương + ribbon status.
- Story detail: cover, title, author, status, categories, mô tả, nút Đã lưu, danh sách chương — chương free badge "Miễn phí" (xanh), chương khóa badge "🔒 10 xu" (vàng).
- Reader:
  - **Banner AdMob trên cùng**.
  - Header: nút back + ví xu (icon coin + số xu).
  - Nếu chương free hoặc đã unlock -> render content + nút chương trước/sau.
  - Nếu chương khóa & chưa unlock -> màn khóa với 2 nút:
    - "Mở khóa · 10 xu": nếu coins>=10 -> spend, unlock, đọc; nếu thiếu -> toast gợi ý xem quảng cáo.
    - "Xem quảng cáo · +30 xu": show rewarded; nhận thưởng -> addCoins(30).
  - **Nghe chương**: nếu `chapter.audio_url != null` -> phát MP3 từ server bằng `expo-audio`.
    `audio_url == null` -> ẩn/disable nút nghe. TUYỆT ĐỐI không dùng TTS trên máy (expo-speech đã gỡ).

### AdMob — module `lib/ads`
- Dùng `react-native-google-mobile-ads`.
- Wrap để chạy được cả trong Expo Go (không có native module) -> fallback placeholder không crash.
- Test unit IDs của Google (banner: `ca-app-pub-3940256099942544/6300978111`, rewarded: `ca-app-pub-3940256099942544/5224354917`). Ghi chú thay ID thật khi release.
- App config `app.json`: thêm plugin `react-native-google-mobile-ads` với App IDs test.

### API client — module `lib/api`
- Base URL từ `app.json > extra.apiUrl` hoặc constant. Mặc định `http://localhost:8000/api` (ghi chú: Android emulator dùng `10.0.2.2`, thiết bị thật dùng IP LAN).
- Hàm: `getHome()`, `getCategories()`, `getStories(params)`, `getStory(id)`, `getChapter(storyId, number)`.
- `getStories(params)` nhận `{ search?, category?, sort?: 'updated'|'newest'|'views', free?: 1, page? }`.

### Theme
- Dark tím/xanh: nền `#17132b`, surface `#231b3f`, accent tím `#534ab7`, xu vàng `#efb027`, free xanh `#1d9e75`, text `#e5e0f5` / muted `#9d95c4`.
- Ribbon status góc bìa; badge số chương đè bìa.

## 9. Giọng đọc AI (OpenAI TTS)

App **không** dùng TTS của điện thoại. Server generate sẵn MP3, app chỉ tải `audio_url` về phát
bằng `expo-audio`. Generate một lần -> nghe bao nhiêu lần cũng không tốn thêm tiền.

### Cấu hình
`backend/.env`:
```
OPENAI_API_KEY=sk-...            # tự điền, KHÔNG commit
OPENAI_TTS_MODEL=gpt-4o-mini-tts
```

### Sinh audio
```bash
php artisan chapters:audio                    # mọi chương chưa có audio (chạy ngay, đồng bộ)
php artisan chapters:audio --story=3          # riêng 1 truyện
php artisan chapters:audio --story=3 --chapter=1
php artisan chapters:audio --limit=5          # giới hạn để kiểm soát chi phí
php artisan chapters:audio --story=3 --force  # tạo lại đè lên bản cũ
php artisan chapters:audio --story=3 --queue  # ĐẨY VÀO HÀNG ĐỢI thay vì chạy ngay
```
Mặc định lệnh CLI chạy **đồng bộ** (tiện ở máy local vì thấy kết quả ngay); thêm `--queue` để
xếp hàng như admin.

Hoặc trong admin: sửa chương -> nút **🎙️ Tạo giọng đọc AI**.

### Hàng đợi (bắt buộc trên production)
Mỗi chương mất **hàng chục giây tới vài phút** (chương dài bị cắt nhiều đoạn, mỗi đoạn 1 request
tới OpenAI + nối bằng ffmpeg). Chạy thẳng trong request HTTP thì nginx/php-fpm sẽ **timeout 502/504**,
nên nút trong admin chỉ **dispatch job** rồi trả về ngay.

- Job: `App\Jobs\GenerateChapterAudio` (`ShouldQueue`, `SerializesModels`) —
  `$timeout = 900`, `$tries = 2`, `backoff() = 60` giây, `failed()` ghi log + đánh dấu chương `failed`.
- Connection: `QUEUE_CONNECTION=database` (bảng `jobs` / `failed_jobs` do migration mặc định của Laravel tạo).
- Worker (production nên chạy thường trú bằng systemd/supervisor):
  ```bash
  php artisan queue:work --queue=default --timeout=900 --tries=2
  ```
  `--timeout` của worker phải **>=** `$timeout` của job, nếu không worker sẽ giết job giữa chừng.
- Xem/chạy lại job hỏng: `php artisan queue:failed`, `php artisan queue:retry <id>`.

### Trạng thái `chapters.audio_status`
| Giá trị | Ý nghĩa | Ai ghi |
|---|---|---|
| `null` | chưa từng đưa vào hàng đợi | — |
| `queued` | đã dispatch, đang chờ worker | admin controller / lệnh `--queue` |
| `processing` | worker đang gọi OpenAI TTS | `ChapterAudioGenerator::generate()` |
| `done` | đã có file MP3 | `ChapterAudioGenerator::generate()` |
| `failed` | lỗi; nội dung lỗi nằm ở `audio_error` | service (lúc ném lỗi) + `Job::failed()` (khi hết lượt thử) |

Admin hiển thị trạng thái ở cột **Audio** của danh sách chương và ở form sửa chương
(**Đang chờ / Đang tạo / Xong / Lỗi** + thông báo lỗi). Xóa audio của chương sẽ xóa luôn trạng thái.
Chưa có `OPENAI_API_KEY` thì job kết thúc ở `failed` với `audio_error` báo thiếu key — đúng như thiết kế.

### Giọng theo thể loại
`App\Services\ChapterAudioGenerator` chọn giọng + sắc thái theo thể loại truyện, dùng tham số
`instructions` của `gpt-4o-mini-tts`.

> Nội dung truyện là **tiếng Anh**, nên `instructions` được viết **bằng tiếng Anh** và luôn mở đầu
> bằng "Narrate in English." để model đọc đúng ngôn ngữ.

| Thể loại | Slug | Giọng | Sắc thái |
|---|---|---|---|
| Revenge | `revenge` | ash | lạnh, sắc, dồn nén; cao trào dứt khoát |
| Romance | `romance` | coral | nữ ấm áp, dịu dàng, giàu cảm xúc |
| Secret Identity | `secret-identity` | ballad | trầm, bí ẩn, nhiều khoảng lặng trước lúc lộ thân phận |
| Family Drama | `family-drama` | sage | ấm, mộc mạc, giọng kể chuyện gia đình |
| Second Chance | `second-chance` | sage | hy vọng, nhẹ nhàng, vươn lên dần |
| Rags to Riches | `rags-to-riches` | ash | truyền cảm hứng, tăng dần khí thế |
| CEO | `ceo` | onyx | nam điềm tĩnh, uy quyền phòng họp, nhịp chắc |
| Billionaire | `billionaire` | onyx | nam tự tin, quyền lực ngầm, nhịp chắc |
| _(không khớp thể loại nào)_ | — | ash | fallback: ấm, truyền cảm, nhịp vừa phải |

Truyện nhiều thể loại: duyệt theo danh sách ưu tiên **cố định** `PROFILE_PRIORITY` —
`revenge > romance > secret-identity > family-drama > second-chance > rags-to-riches > ceo > billionaire`
(thể loại có chất giọng đặc trưng hơn thắng; `ceo`/`billionaire` mang tính bối cảnh nên xếp cuối).
Nhờ vậy cùng một truyện luôn ra cùng một giọng ở mọi lần chạy, không phụ thuộc thứ tự DB trả về.
Ví dụ: *She Laughed at His Old Car* (romance + revenge) -> **revenge/ash**;
*The Janitor Owns the Company* (secret-identity + ceo) -> **secret-identity/ballad**.

### Chi tiết kỹ thuật
- Nội dung dài bị cắt theo **ranh giới câu** (giới hạn 4096 ký tự/request của API), mặc định 3500
  ký tự/đoạn; các đoạn được nối lại bằng **ffmpeg** (có dự phòng nối nhị phân nếu máy không có ffmpeg).
- File lưu `storage/app/public/audio/{story_id}/{number}.mp3`, ghi vào `chapters.audio_path`.
- `audio_url` kèm `?v=<mtime>` chống cache giống ảnh bìa.
- `ChapterAudioGenerator::generate()` bọc toàn bộ phần việc: đánh dấu `processing` khi bắt đầu,
  `done` khi xong, `failed` + `audio_error` khi lỗi rồi ném tiếp lỗi ra ngoài (để worker retry).
