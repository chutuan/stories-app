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

## Nạp vào Stories (chưa làm)

Chờ Apple duyệt xong bản 1.0.1. Khi nạp: sao lưu DB trước, chỉ nạp truyện đã được duyệt trong
báo cáo trùng, nạp ở trạng thái ẩn, và lưu mã gốc (slug Blogbio) để nạp lại không tạo bản trùng.

## Kiểm "cùng một truyện viết lại" (máy không tự bắt được)

`dedupe_report.py` chỉ bắt bản lặp y hệt / sửa nhẹ (đã thử đối chứng: chép y hệt 1.0, sửa 5% câu 0.94,
đổi đuôi tên — bắt cả ba). Truyện được **sinh lại từ cùng dàn ý** (đổi câu chữ, đổi tên vài nhân vật)
thì không. Lần 24/10/2026: lọc cặp nghi theo tên gần giống + chung ≥3 tên riêng hiếm → 7 cặp → cho
người đọc toàn bộ nội dung từng cặp → 5 cặp là cùng một truyện, kết quả ở `dedupe_verdicts.json`.
`review.csv` là bảng duyệt cuối (GIỮ / BỎ / CẦN QUYẾT) — mở bằng Excel/Numbers.
