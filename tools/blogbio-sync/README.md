# blogbio-sync — kéo truyện từ site Blogbio về máy, rồi nạp vào Stories

Nguồn hiện tại: **storypulse6.cfx.bz** (site Blogbio của Tuấn). Dữ liệu lưu ở
`imports/storypulse6/` — thư mục này bị git bỏ qua (lớn, có ảnh).

## Chạy (mỗi lần đều như nhau — lần sau chỉ tải phần mới)

```bash
cd /Users/tuan/Projects/Stories
python3 tools/blogbio-sync/sync_public.py --site https://storypulse6.cfx.bz --dest imports/storypulse6
python3 tools/blogbio-sync/download_images.py imports/storypulse6/export.json imports/storypulse6
python3 tools/blogbio-sync/dedupe_report.py imports/storypulse6/export.json imports/storypulse6/stories_existing.json > imports/storypulse6/dedupe_report.json
```

1. `sync_public.py` đọc sitemap công khai, so `lastmod` với `sync_state.json` (lần đồng bộ
   cuối) và **chỉ tải bài mới hoặc đã sửa**. Bài biến khỏi sitemap → `removed_at`, file cũ giữ
   nguyên. Ghi `posts/<slug>.json`, `series.json` (truyện + thứ tự chương), `export.json`.
2. `download_images.py` tải ảnh bìa truyện vào `covers/<truyện>.<ext>` và mọi ảnh khác vào
   `images/<sha1>.<ext>` (trùng nội dung chỉ lưu một lần). `images_manifest.json` nhớ URL nào đã
   tải → lần sau chỉ tải ảnh mới.
3. `dedupe_report.py` kiểm trùng: tên truyện đã chuẩn hoá (bỏ `bbb`/`xxx`, PART n…), nội dung
   chương (vân tay + độ giống cụm 8 từ), chuỗi chương đứt/trùng, và so với truyện đang có trên
   Stories (`stories_existing.json`).

## Vì sao đọc phía công khai, không dùng API admin

- Khung trình duyệt của Claude không lưu được file tải về và chặn gửi dữ liệu vào máy, nên bản
  xuất qua API admin (có phiên đăng nhập) không ghi xuống đĩa được.
- Không lấy cookie đăng nhập ra dùng ở terminal (đó là thông tin đăng nhập của chủ site).
- Phía công khai có đủ thứ cần nạp: nội dung, ảnh, ngày đăng/sửa, tên truyện và mục lục chương
  (`series-nav`). Đã đối chiếu 24/10/2026: admin có 1.709 bài = **1.296 đã đăng** (đúng bằng số
  URL trong sitemap) + bản nháp. Bản nháp và "story bible" của series chỉ có trong admin.

## Khi phía công khai bị chặn (từ 31/10/2026)

Cả Blogbio (`*.cfx.bz`, `*.cafex.biz`) bật Cloudflare challenge với mọi client không phải trình
duyệt → `sync_public.py` nhận 403. Không lách (không giả User-Agent, không tự giải ô kiểm). Làm qua
**Chrome thật của Tuấn** (extension Claude in Chrome), nơi Tuấn đã đăng nhập admin:

1. Trong tab của site, chạy JS lấy danh sách `GET /admin/api/v1/posts?per_page=100&page=N` (tối đa
   100/trang), lọc `status == published`, rồi lấy chi tiết từng bài `GET /admin/api/v1/posts/{id}`
   (nội dung ở `description`, có `prev_chapter`/`next_chapter`, `series`).
2. Gói thành `{site, exported_at, posts: [...]}` và tải xuống (Blob + `<a download>`) vào ~/Downloads
   — hỏi Tuấn trước khi tải.
3. `from_admin_export.py <file> --dest imports/<site>` tạo đúng bộ file như `sync_public.py`, rồi chạy
   tiếp `download_images.py` / `dedupe_report.py` như thường (ảnh ở blog.igallery.blog không bị chặn).

Nguồn thứ hai: **mundodehistorias.cafex.biz** → `imports/mundodehistorias/`. Lần 31/10/2026: 8.616 bài
nhưng chỉ 681 đã đăng (~151 truyện); 7.935 bản nháp chưa kéo (Tuấn chọn chỉ lấy bài đã đăng).

Nguồn thứ ba: **darkchapter.cafex.biz** → `imports/darkchapter/`. Lần 01/11/2026: 5.806 bài = 4.240 đã
đăng (845 truyện, gần hết 5 chương, ~8,5 triệu từ, đăng 29/09–31/10) + 1.566 nháp (chưa kéo). Trang
`/admin` hiện "403 Forbidden" nhưng API admin vẫn trả dữ liệu với phiên đăng nhập. Lấy chi tiết 6 luồng
song song mất ~4 phút, file xuất 61 MB. `stories_existing.json` lấy thẳng từ DB server (chỉ đọc).
Site này đăng nhiều bản viết lại cùng một dàn ý dưới tên khác (một cốt truyện có tới 14 bản). Lọc
theo tên riêng hiếm bỏ sót những cụm dùng tên phổ biến; cách chạy được là **độ giống TF-IDF toàn văn**
(cosine trên 400 từ nặng nhất mỗi truyện, viết bằng Python thuần). Hiệu chỉnh trên 205 cặp agent đã đọc:
cặp cùng truyện ≥ 0,3 (174/177), cặp khác truyện < 0,2 (26/28). Nên ≥ 0,3 gộp thẳng, 0,2–0,3 cho agent
đọc. So cả với bản dump truyện ĐANG có trên server: 37 truyện darkchapter là bản viết lại của truyện
đã đăng. Kết quả 01/11: giữ 484 / 845 (bỏ 247 viết lại, 72 nhạy cảm, 37 trùng truyện đang có, 5 trùng
y hệt) → đưa vào **kho chờ đăng**, không nạp thẳng (xem dưới).

## Kho chờ đăng (từ 01/11/2026)

Lô lớn không nạp một lần nữa mà xếp vào bảng `story_queue`; `stories:release-queued` đăng 10 truyện
đầu hàng mỗi ngày lúc 13:00 UTC (lịch ở `routes/console.php`). Gói cần `source_key` (build_bundle.py
đã ghi id series Blogbio). Xếp hàng lại cùng file không nhân đôi: truyện đang chờ được cập nhật, truyện
đã đăng hay đã bỏ thì giữ nguyên. Xem / bỏ truyện trong hàng ở `/admin/queue`.

```bash
php artisan stories:queue-add bundle.json --source=darkchapter --covers=stories_covers --noindex --dry
php artisan stories:queue-add bundle.json --source=darkchapter --covers=stories_covers --noindex
php artisan stories:release-queued --dry     # 10 truyện sắp đăng
```

## Nạp vào Stories (đã làm 25/10/2026: 249 truyện, 1.245 chương)

```bash
python3 tools/blogbio-sync/build_bundle.py imports/storypulse6 --meta imports/storypulse6/meta.json --out imports/storypulse6/bundle.json
python3 tools/blogbio-sync/prepare_covers.py imports/storypulse6      # bìa 600x800 JPEG như bìa Stories
# trên server: sao lưu DB -> stories:import bundle.json --dry -> stories:import --no-jobs
#              -> chép bìa vào storage/app/public/stories/<slug>.jpg + gán thumbnail -> covers:derive
```

- `build_bundle.py` chỉ lấy truyện **GIỮ** trong `review.csv` (bỏ BỎ / CẦN QUYẾT / TẠM GIỮ), đổi HTML ->
  chữ thuần, bóc "PART N: …" thành tiêu đề chương, dọn rác (quảng cáo, khung Report, ```, #, **).
  status luôn `completed` (routine Cowork chỉ viết tiếp truyện `ongoing`), không đặt bìa/audio.
- `meta.json` (thể loại + tóm tắt + cờ nội dung nhạy cảm) do agent đọc phần mở đầu từng truyện tạo ra.
- `import_map.json`: id series Blogbio -> slug trên Stories. Nạp lại cùng slug = cập nhật, không tạo bản trùng.
- Truyện nạp vào lấy slug theo tên đã bỏ đuôi; KHÔNG ghi đè truyện cũ (đã kiểm 0 slug trùng trước khi nạp).

## Kiểm "cùng một truyện viết lại" (máy không tự bắt được)

`dedupe_report.py` chỉ bắt bản lặp y hệt / sửa nhẹ (đã thử đối chứng: chép y hệt 1.0, sửa 5% câu 0.94,
đổi đuôi tên — bắt cả ba). Truyện được **sinh lại từ cùng dàn ý** (đổi câu chữ, đổi tên vài nhân vật)
thì không. Lần 24/10/2026: lọc cặp nghi theo tên gần giống + chung ≥3 tên riêng hiếm → 7 cặp → cho
người đọc toàn bộ nội dung từng cặp → 5 cặp là cùng một truyện, kết quả ở `dedupe_verdicts.json`.
`review.csv` là bảng duyệt cuối (GIỮ / BỎ / CẦN QUYẾT) — mở bằng Excel/Numbers.
