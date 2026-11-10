# Metadata cho phiên bản 1.0.5 (build 13)

**Đã nộp duyệt 10/11/2026 ~22:31 (giờ máy)** — "Waiting for Review", tự phát hành khi duyệt xong, phát hành ngay cho mọi
người, giữ rating cũ (y như 1.0.4). Promotional text chép lại của 1.0.4 (bản mới để trống). 4 ô đã kiểm lại sau khi tải lại
trang (độ dài + câu then chốt). Notes giới hạn 4.000 ký tự: bản dán là 3.971 — đã rút gọn so với bản nháp, văn bản dưới đây
là bản ĐÃ DÁN.

Soạn 10/11/2026. Build 13 archive tại máy (Xcode 27B52, SDK iphoneos27.2, cùng toolchain với 1.0.4; `ios/` không prebuild
lại, chỉ đổi số phiên bản) và upload bằng `xcodebuild -exportArchive` (destination upload) lúc 22:27; số build giữ 13.
Commit app: f67fb1a (kèm b6ed98e chỉ đụng Android).

Thay đổi trong app (Tuấn yêu cầu 10/11):
- nút tai nghe luôn bật; nghe một chương = xem một quảng cáo có thưởng, mở đúng chương đó 24 giờ;
  hết quảng cáo -> "Audio is currently unavailable. Please try again later." (không cấp bù);
  chương chưa có MP3 -> cùng thông báo, không chiếu quảng cáo;
- ghi lượt bấm nghe / lượt mở về server (reader_id ngẫu nhiên, lưu dạng băm) cho trang admin/audio;
- thanh % đã đọc dưới tên truyện (thẻ truyện, Ranking, History, trang truyện);
- truyện đọc hết vẫn ẩn ở trang chủ/Ranking/thể loại, cả ở lời mời bật thông báo; Search vẫn hiện.

**Rủi ro duyệt đã biết (Tuấn chấp nhận):** máy duyệt của Apple thường không có quảng cáo (no-fill) -> reviewer bấm nghe sẽ
thấy "Audio is currently unavailable". Notes nói thẳng điều này và nhấn mạnh chữ luôn đọc được. Nếu bị trượt 2.1 vì audio
thì phương án là cho nghe khi no-fill (như cấp bù xu) — hỏi Tuấn trước.

**App Privacy:** lượt bấm nghe thuộc Usage Data -> Product Interaction (đã khai, mục đích có Analytics), cùng loại với phiếu
chấm chương; không thêm SDK mới -> không phải sửa nhãn.

Trong lúc duyệt: giữ free_chapters/tên #16 The Inventory, #11 The Sixty-Dollar Suit, giữ audio đủ chương ở hai truyện đó,
giữ cấp bù 2 lần/ngày (Notes dựa vào các điều này).

## What's New in This Version

```text
• Listen to any narrated chapter: watch one short video and that chapter's audio is yours for 24 hours.
• The headphones button is on every chapter. Tapping it on a chapter that isn't narrated yet tells us which chapters to narrate next.
• A progress bar under each story shows how much of it you've read.
• Finished stories stay off the home screen and Ranking, and you can still find them in Search to read again.
```

## Description — 2 chỗ sửa

Dòng audio trong WHAT YOU GET:

```text
• Narrated audio on selected stories: watch one short video to listen to a chapter for 24 hours
```

Câu cuối đoạn PRIVACY (thay câu về chấm chương của 1.0.4):

```text
If you rate a chapter or tap the headphones button, the app sends the chapter with a random identifier that is not linked to you.
```

## Notes — đổi so với 1.0.4

Thay đoạn WHAT CHANGED:

```text
WHAT CHANGED IN 1.0.5
- Audio: an optional rewarded video unlocks one chapter's narration for 24 hours. Coins are not used for audio.
- The headphones button is on every chapter. On chapters not narrated yet it only counts the request; no ad is shown.
- Reading progress bars on story cards, Ranking, History and the story page.
```

Thay bước audio của HOW TO TEST:

```text
6. Audio: every chapter of THE INVENTORY and THE SIXTY-DOLLAR SUIT is narrated. In a chapter, tap the headphones button (top right), then "Watch ad". When the video ends the chapter plays, and it stays unlocked for 24 hours. If no ad can be shown (common on review devices), the player says "Audio is currently unavailable. Please try again later." Audio is optional; every chapter can still be read as text.
```

Câu dữ liệu gửi server (đoạn PRIVACY AND TRACKING):

```text
The only things sent to our server are: if the user turns on the daily notification, a notification token and the device time zone (turning it off deletes them); a chapter rating, if given; and which chapter the user opened in the audio player. Ratings and audio requests carry a random app-generated identifier stored only as a keyed hash.
```
