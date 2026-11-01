# Metadata cho phiên bản 1.0.2 (build 10)

Soạn 01/11/2026. 1.0.1 (9) đang trên App Store. 1.0.2 là bản đầu tiên chạy **native trên iPad**, nên ASC sẽ
đòi ảnh iPad 13". Thay đổi người dùng thấy: bố cục iPad, lịch sử đọc, modal đọc tiếp, thông báo truyện mới
(mặc định tắt), ẩn truyện đã đọc hết, truyện nổi bật theo lượt xem tuần, sửa mất dòng cuối, quảng cáo 300×250
giữa và cuối chương.

## Checklist trước khi bấm nộp

1. **Khoá APNs cho Expo — XONG 01/11**: key `3LMG5U44U3` (Sandbox & Production) tạo bằng `eas credentials`,
   App ID `com.chutuan.stories` đã bật Push Notifications. Còn thử một thông báo thật trên máy TestFlight.
2. **Archive + upload — XONG 01/11 21:03**: build 1.0.2 (10) "Validated" trên TestFlight, ký Apple Distribution,
   entitlements `aps-environment: production`, `get-task-allow: false`, iPhone + iPad, iOS 16.4+. Đã vào nhóm
   nội bộ "Team (Expo)". Đã gắn build 10 vào phiên bản 1.0.2; Promotional text, What's New, Description,
   Notes đã dán đúng bản dưới đây (kiểm SHA-256 sau khi tải lại trang, 01/11).
3. **Cài từ TestFlight lên iPhone VÀ iPad thật** trước khi nộp: mở app, đọc một chương dài (ô quảng cáo giữa
   + cuối chương), mở khoá 90→60, bật thông báo ở Library → Notifications, xoay iPad + Split View.
4. **Ảnh iPad 13"** (2064×2752) — bộ chụp từ bản Release 1.0.2 trên simulator iPad Pro 13" ở
   `store/screenshots/ipad-13/`: 1-discover, 2-detail, 3-reader, 4-library (tab History), 5-rewards,
   6-continue (modal đọc tiếp, tuỳ chọn). **Đã tải lên 01/11, đủ 6/10 đúng thứ tự.** Ảnh iPhone giữ nguyên.
5. **App Privacy — XONG 01/11 (đã Publish):** nhãn cũ (từ ~09/2026) chỉ có 2 dòng và khai Advertising Data
   "Used for tracking" = Yes, trái với binary (không `NSUserTrackingUsageDescription`, không gọi
   `requestTrackingAuthorization`, `NSPrivacyTracking` = false). Nay là 7 dòng theo manifest Google Mobile Ads
   trong build 10, **tracking = No ở mọi dòng**:
   - Linked: Coarse Location, Product Interaction, Advertising Data — 3P Advertising, Developer's Advertising,
     Analytics; Device ID — 3 mục đó + **App Functionality** (token thông báo).
   - Not linked: Crash Data — Analytics; Performance Data, Other Diagnostic Data — 3 mục kia.
6. Marketing URL vẫn là `https://tunastory.com`.
7. /admin/settings: cấp bù vẫn bật, 2 lần/ngày (Notes ghi "currently 2"). Kiểm:
   `curl https://api.tunastory.com/api/config` → `{"ad_fallback":{"enabled":true,"daily_limit":2}}`.
8. Trong lúc duyệt: không đổi `free_chapters` của The Inventory (#16, 999) và The Sixty-Dollar Suit (#11, 1),
   không đổi tên hai truyện đó. Kho vẫn đăng 10 truyện/ngày — các truyện này đã qua soát nội dung.

## What's New in This Version (732/4000)

```text
• Designed for iPad: wider layouts for the home shelves, story pages, the reader, Library and Rewards, and they adapt to Split View and Stage Manager windows.
• Reading history: Library now has a History tab. Tap a story to pick up where you left off, or long-press to remove it.
• When you come back to the app, it offers to continue the story you were reading.
• Optional daily new-story notification. It is off unless you turn it on, and you can switch it off anytime in Library.
• Stories you have finished no longer fill up the home screen. They return when a new chapter is added.
• The featured story is now one of the most-read stories of the past week.
• Fixed the reader sometimes cutting off the last line of a paragraph.
```

## Promotional text, Keywords — giữ nguyên như 1.0.1

## Description (1755/4000)

Chỉ khác 1.0.1 ở đoạn PRIVACY (thêm lịch sử đọc và thông báo tuỳ chọn).

```text
Short serialised stories you can finish in one sitting: people who are underestimated, betrayed or written off, and what happens when the truth finally comes out.

WHAT YOU GET

• Original serials across Revenge, Family Drama, Secret Identity, Second Chance, CEO, Romance, Suspense and Mystery
• A reader built for long sessions — adjust font size, line spacing and brightness, and choose between four page themes including a night theme
• Narrated audio on selected stories: listen instead of read
• Some stories are free from the first chapter to the last
• No account, no sign-up, no email — open the app and start reading

HOW COINS WORK

Every new install starts with 90 coins, enough to unlock three chapters straight away. The first chapter of every story is free. Later chapters cost 30 coins each and stay unlocked on your device.

Coins are earned inside the app and are never sold for money. Check in each day, reach a reading-time milestone, or watch an optional rewarded video for 90 coins, up to four times a day. Videos depend on ad availability: if none is available while you are short of coins on a locked chapter, the app may add just enough to unlock it, a limited number of times a day.

PRIVACY

Stories has no accounts and does not track you across other apps or websites. Ads are non-personalised. Your coins, unlocked chapters, saved stories, reading history and reading settings stay on your device. If you turn on the optional daily new-story notification, the app sends our server a notification token and your time zone so the notification arrives in your evening; turning it off deletes them.

Privacy Policy, Terms of Service and a contact page are linked at the bottom of the Library and Rewards tabs, and at tunastory.com.
```

## App Review Information → Notes (3164/4000)

Khác 1.0.1: không dựa vào truyện nổi bật nữa (giờ đổi theo lượt xem tuần) mà chỉ đường bằng Search; thêm iPad và
thông báo; câu về dữ liệu gửi server và câu về nội dung viết lại cho đúng.

```text
Stories is a free reading app for short serialised English fiction. No accounts, no sign-in, no in-app purchases, no subscriptions. Revenue comes only from non-personalised Google AdMob ads.

WHAT CHANGED IN 1.0.2
- The app now runs natively on iPad, with layouts that adapt to the window width, including Split View and Stage Manager.
- Library has a History tab (stored only on the device).
- An optional daily new-story notification. It is off by default.
- Reader fixes and an ad placement after the middle and at the end of long chapters.

HOW TO TEST — no credentials needed, just launch the app
1. Open the Search tab, type "inventory" and open THE INVENTORY. It is free in full: every chapter opens without coins.
2. To test unlocking: in Search, type "sixty-dollar" and open THE SIXTY-DOLLAR SUIT. Read chapter 1 (free), tap Next. Chapter 2 is locked. A new install has 90 coins; tap "Unlock · 30 coins" and the chapter opens, balance 60. No ad is needed.
3. Rewarded video: on a locked chapter, tap "Watch ad · +90 coins". If AdMob serves an ad and you watch it to the end, you get +90 coins (up to 4 videos a day). Ads may not fill on review devices. When no ad can be shown and you have fewer than 30 coins, the app adds exactly what is missing to unlock that chapter, up to twice a day per device. With 30 coins or more it adds nothing and says you already have enough.
4. Notifications: the first time you leave a chapter, the app asks once "Get one new story a day?". Nothing is requested unless you tap "Turn on notifications"; only then does the system permission dialog appear. "Not now" closes it. The setting can be switched on or off at any time in the Library tab under Notifications.
5. Other free ways to earn coins are in the Rewards tab: daily check-in and reading-time milestones.
6. Audio: every chapter of THE SIXTY-DOLLAR SUIT is narrated. Tap the headphones button at the top right of the chapter.
7. Privacy Policy, Terms of Service and Contact are at the bottom of the Library tab and the Rewards tab. They open in an in-app browser.

The daily number of no-ad top-ups is a server setting that applies to every user alike (currently 2). It is not changed while a version is in review.

COINS
Coins are a virtual reward earned only inside the app. They cannot be purchased, transferred or exchanged for anything of value.

PRIVACY AND TRACKING
The app does not request App Tracking Transparency permission, so no advertising identifier is available to it, and every ad request is non-personalised. Data collected by the Google Mobile Ads SDK is declared in App Privacy, and none of it is used for tracking. The app has no accounts. Coins, unlocked chapters, saved stories, reading history and settings are stored only on the device. The only thing sent to our server is, if the user turns on the daily notification, a notification token and the device time zone; turning it off deletes them.

CONTENT
Original fiction. No explicit sexual content. Some stories include realistic violence and mature themes such as betrayal and family conflict, reflected in the age rating. No user-generated content, no messaging, no social features.
```
