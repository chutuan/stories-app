# Metadata cho phiên bản 1.0.4 (BẢN NHÁP, chưa điền lên App Store Connect)

Soạn 08/11/2026. Thay đổi trong app (Tuấn yêu cầu 08/11):
- bỏ điểm danh hằng ngày;
- xem quảng cáo không giới hạn, mỗi lượt +60 xu (trước: +90 × 4 lượt/ngày);
- truyện Featured bỏ qua truyện người dùng đã mở;
- truyện đã đọc hết không hiện ở trang chủ, Ranking, thể loại;
- năm khuôn mặt chấm điểm dưới mỗi chương (như web);
- chương dài (≥ 1.200 từ) có hai ô quảng cáo trong thân chương thay vì một.

**Phải sửa cả Description** (Description gắn theo từng phiên bản): đoạn HOW COINS WORK của 1.0.2 vẫn nói
"Check in each day … 90 coins, up to four times a day" — sai với 1.0.4.

**App Privacy:** phiếu chấm chương thuộc Usage Data → Product Interaction; dòng đó đã khai (mục đích có
Analytics), nên không cần thêm loại dữ liệu. Không thêm SDK mới.

## What's New in This Version

```text
• Rate every chapter with one tap: five faces at the end of each chapter.
• Watch as many videos as you like: +60 coins each, no daily limit.
• The featured story now picks something you haven't started yet.
• Stories you've finished no longer show up on the home screen, Ranking and genre pages.
• Daily check-in has been retired; reading-time milestones still give free coins every day.
```

## Description — thay đoạn HOW COINS WORK

```text
HOW COINS WORK

Every new install starts with 90 coins, enough to unlock three chapters straight away. The first chapter of every story is free. Later chapters cost 30 coins each and stay unlocked on your device.

Coins are earned inside the app and are never sold for money. Reach a reading-time milestone, or watch an optional rewarded video for 60 coins, as often as you like. Videos depend on ad availability: if none is available while you are short of coins on a locked chapter, the app may add just enough to unlock it, a limited number of times a day.
```

Đoạn PRIVACY trong Description: thêm sau câu về thông báo:

```text
If you rate a chapter, the app sends the score with a random identifier that is not linked to you.
```

## Notes — đổi so với 1.0.2/1.0.3

```text
WHAT CHANGED IN 1.0.4
- Rewarded videos: +60 coins each, no daily limit (was +90, up to 4 a day).
- Daily check-in removed. Reading-time milestones in the Rewards tab remain as the free way to earn coins.
- Each chapter ends with an optional five-face rating ("How was this chapter?"). It is a number only, no text, and only the total ("4.5/5 from 20 readers") is shown.
- The featured story skips stories the user has already opened; finished stories are hidden from browse lists.
```

Bước 3 và 5 của HOW TO TEST:

```text
3. Rewarded video: on a locked chapter, tap "Watch ad · +60 coins". If AdMob serves an ad and you watch it to the end, you get +60 coins; there is no daily limit. Ads may not fill on review devices. When no ad can be shown and you have fewer than 30 coins, the app adds exactly what is missing to unlock that chapter, up to twice a day per device. With 30 coins or more it adds nothing and says you already have enough.
5. Another free way to earn coins is in the Rewards tab: reading-time milestones (10, 30, 60 and 90 minutes a day).
```

Câu dữ liệu gửi server (đoạn PRIVACY AND TRACKING):

```text
The only things sent to our server are, if the user turns on the daily notification, a notification token and the device time zone (turning it off deletes them), and, if the user rates a chapter, the score with a random app-generated identifier that is stored only as a keyed hash.
```

Câu CONTENT:

```text
No user-generated content, no messaging, no social features. Chapter ratings are a number from 1 to 5 with no text, shown only as an average.
```
