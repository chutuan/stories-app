# Metadata cho phiên bản 1.0.6 (build 14)

**Đã nộp duyệt 11/11/2026 ~16:05 (giờ máy)** → "Waiting for Review"; tự phát hành ngay khi duyệt, giữ rating cũ (y như
1.0.5). 4 ô đã kiểm lại sau khi tải lại trang. Notes 3.986/4.000 ký tự. Văn bản dưới đây là bản ĐÃ DÁN.

Soạn 11/11/2026 (Tuấn: "1.0.5 đã duyệt. E thêm firebase analytics để tối ưu đi", rồi chọn "Nộp luôn" sau khi thử trên
simulator). Build 14 archive tại máy (`Archives/2026-11-11/Stories 1.0.6 (14).xcarchive`) và upload bằng
`xcodebuild -exportArchive` (destination upload); số build giữ 14. Có native mới (Firebase) nên đã prebuild lại `ios/`
và soát đủ 4 bẫy (build 14, ID AdMob thật, `.xcode.env.local`, precompiled modules tắt). Chưa chạy trên máy thật —
chỉ simulator iOS 27 (bản Release): `first_open`, `screen_view` (home/search/rewards/saved/reader), `read_chapter` đều
lên Firebase, GA Realtime thấy 1 người dùng.

Thay đổi trong app — chỉ có đo lường, không đổi tính năng:
- Google Analytics for Firebase (pod `FirebaseAnalytics/Core` 12.19, KHÔNG có `GoogleAppMeasurementIdentitySupport` →
  không đọc IDFA), module native `mobile/modules/stories-analytics`, project Firebase `tunastory-ed1ef`.
- Sự kiện: `first_open`/`session_start` (tự động), `screen_view` theo route, `read_chapter {story_id, chapter}`,
  `rewarded_ad_watched {placement}`. Không user ID.
- `GOOGLE_ANALYTICS_DEFAULT_ALLOW_AD_PERSONALIZATION_SIGNALS=false`; đếm màn hình tự động tắt
  (`FirebaseAutomaticScreenReportingEnabled=false`, không thì mỗi màn bị ghi thêm một lần tên "RNSScreen").
- GA property 563695317 đã liên kết Google Ads 114-550-6599 (Personalized Advertising: TẮT).

**App Privacy: KHÔNG phải sửa.** Nhãn Tuấn đăng 01/11 đã rộng hơn những gì Firebase Analytics thu (Google: app instance
ID, tương tác trong app, vị trí ước lượng từ IP đã che bớt). Nhãn đang hiện (kiểm 11/11):

| Data type | Purpose | Linked |
|---|---|---|
| Location → Coarse Location | Analytics, Third-Party Advertising, Developer's Advertising | Yes |
| Identifiers → Device ID | Third-Party Advertising, Analytics, App Functionality, Developer's Advertising | Yes |
| Usage Data → Product Interaction | Analytics, Developer's Advertising, Third-Party Advertising | Yes |
| Usage Data → Advertising Data | Third-Party Advertising, Analytics, Developer's Advertising | Yes |
| Diagnostics → Crash / Performance / Other Diagnostic Data | Analytics (+ quảng cáo ở 2 loại sau) | No |

Không có mục "Data Used to Track You" — giữ nguyên như vậy (không ATT, không IDFA, Personalized Advertising tắt).

## Promotional text (161/170) — chép lại y 1.0.5 (bản mới để trống)

```text
Short serialised stories you can finish in one sitting: revenge, secret identities, second chances and family drama. Start free with 90 coins, no account needed.
```

## What's New in This Version

```text
• We now use Google Analytics for Firebase to see, without knowing who you are, which stories and screens readers use most, so we can bring you more of the stories you enjoy. It is not used to personalise ads.
• Nothing else changes: same stories, same free first chapters, same coins.
```

## Description — 1 chỗ sửa

Đoạn PRIVACY: chèn sau câu "Ads are non-personalised.":

```text
The app uses Google Analytics for Firebase to count anonymous usage, such as which screens and chapters are opened; it is not used to personalise ads.
```

## Notes — đổi so với 1.0.5 (trần 4.000 ký tự)

Thay đoạn WHAT CHANGED:

```text
WHAT CHANGED IN 1.0.6
- Added Google Analytics for Firebase (no IDFA/AdSupport) to count app opens, screens, chapters opened and completed rewarded videos. No user ID; ad personalisation signals off; still no App Tracking Transparency request. Already covered by our App Privacy answers.
- No feature changes.
```

Đoạn PRIVACY AND TRACKING, câu thứ hai:

```text
Data collected by the Google Mobile Ads SDK and Google Analytics for Firebase is declared in App Privacy, and none of it is used for tracking.
```
