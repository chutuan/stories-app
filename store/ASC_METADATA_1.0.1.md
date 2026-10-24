# Metadata cho phiên bản 1.0.1 (build 9 trên ASC)

Soạn 24/10/2026 sau đợt soát 9 agent. Mọi độ dài đã kiểm bằng máy. Bản 1.0 (build 7) phát hành
20/10/2026. Khác biệt duy nhất người dùng thấy: không chiếu được quảng cáo thì chỉ cấp bù ở màn chương
khoá khi đang thiếu xu (vừa đủ 30), tối đa N lần/ngày theo /admin/settings; tab Rewards không cấp nữa.

## Checklist trước khi bấm nộp

1. Backend đã deploy (bảng `settings`, `/api/config`). Kiểm:
   `curl https://api.tunastory.com/api/config` → `{"ad_fallback":{"enabled":true,"daily_limit":2}}`.
2. **/admin/settings: bật cấp bù, 2 lần/ngày** — Notes ghi "currently 2". Giữ nguyên từ lúc nộp tới khi
   phát hành, và đừng siết lại ngay sau khi được duyệt (Guideline 2.3.1).
3. ~~Upload~~ — xong 24/10 13:58. Ô "Manage Version and Build Number" vẫn tick nên Xcode tự đổi
   build 8 thành **9** khi upload (cùng bản đã soát, chỉ khác số). Lần build sau phải ≥ 10.
4. ASC → **+ Version** → `1.0.1` → chọn **build 9** (`1.0.1 (9)`).
5. **Marketing URL = `https://tunastory.com`** (trang phiên bản 1.0.1). AdMob tìm app-ads.txt theo trường
   này; bản 1.0 để trống nên AdMob chưa xác minh được. Không dùng `www.`.
6. Dán Description, What's New, Notes bên dưới. Promotional Text và Keywords giữ nguyên (vẫn đúng).
7. Ảnh chụp ASC tự chép từ bản 1.0: không ảnh nào được có thông báo "we added +90 anyway".
8. App Privacy: không đổi (build 8 không thêm SDK hay loại dữ liệu nào; `/api/config` không gửi gì
   về server ngoài một GET).
9. **Tắt routine Cowork nạp truyện** trong lúc duyệt. Không đổi `free_chapters` của THE INVENTORY (#16,
   đang 999) và THE SIXTY-DOLLAR SUIT (#11, đang 1).
10. Sau khi phát hành: AdMob → app-ads.txt → kiểm tra lại (chờ ≥ 24 giờ sau khi trang App Store hiện
    "Developer Website").

## Promotional text — giữ nguyên (161/170)

```text
Short serialised stories you can finish in one sitting: revenge, secret identities, second chances and family drama. Start free with 90 coins, no account needed.
```

## Keywords — giữ nguyên (98/100)

```text
revenge,drama,billionaire,ceo,romance,secret identity,second chance,family,mystery,suspense,serial
```

## What's New in This Version (357/4000)

```text
• Watching a rewarded video to the end still gives +90 coins, up to four times a day.
• When no video can be shown, the app now helps only if you are stuck on a locked chapter without enough coins: it adds just enough to unlock that chapter, a limited number of times a day, instead of 90 coins every time.
• Small fixes to how daily ad rewards are counted.
```

## Description (1541/4000)

Chỉ khác bản 1.0 ở đoạn thứ hai của HOW COINS WORK. Dùng "may" và "a limited number of times a day"
có chủ ý: Description bị khoá theo phiên bản, nên câu phải đúng với mọi giá trị chỉnh từ admin.

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

Stories has no accounts and does not track you across other apps or websites. Ads are non-personalised. Your coins, unlocked chapters, saved stories and reading settings stay on your device.

Privacy Policy, Terms of Service and a contact page are linked at the bottom of the Library and Rewards tabs, and at tunastory.com.
```

## App Review Information → Notes (3238/4000)

```text
Stories is a free reading app for short serialised English fiction. No accounts, no sign-in, no in-app purchases, no subscriptions. Revenue comes only from non-personalised Google AdMob ads.

WHAT CHANGED IN 1.0.1
One rule: what happens when a rewarded video cannot be shown. Version 1.0 added +90 coins anyway, on any screen. Version 1.0.1 only helps a reader who is actually stuck: on a locked chapter with fewer than 30 coins, it adds exactly the missing amount. This is the only user-facing change.

HOW TO TEST — no credentials needed, just launch the app
1. The featured story on the Home screen, THE INVENTORY, is free in full: every chapter opens without coins.
2. To test unlocking: open the Search tab, type "sixty" and open THE SIXTY-DOLLAR SUIT. Read chapter 1 (free), tap Next. Chapter 2 is locked. A new install has 90 coins; tap "Unlock · 30 coins" and the chapter opens, balance 60. No ad is needed.
3. Rewarded video: on a locked chapter, tap "Watch ad · +90 coins". If AdMob serves an ad and you watch it to the end, you get +90 coins (up to 4 videos a day). Ads may not fill on review devices. When no ad can be shown:
- with 30 coins or more, nothing is added and the app says "No ad is available right now. You already have enough coins to unlock this chapter."
- with fewer than 30 coins, the app adds exactly what is missing and says, for example, "No ad was available — we added +30 coins so you can unlock this chapter." This top-up works up to twice a day per device; after that the app points to the free check-in and reading rewards.
To see the top-up from a new install: unlock chapters 2, 3 and 4 (balance 90, 60, 30, 0), tap Next to chapter 5, then tap "Watch ad · +90 coins".
4. The "Watch ad" button in the Rewards tab pays only for a video that was actually watched. If no ad is available it says "No ad is available right now. Please try again later." Unlocking chapters never depends on it.
5. Other free ways to earn coins are in the Rewards tab: daily check-in (15 to 30 coins depending on the day) and reading-time milestones.
6. Audio: every chapter of THE SIXTY-DOLLAR SUIT is narrated. Tap the headphones button at the top right of the chapter.
7. Privacy Policy, Terms of Service and Contact are at the bottom of the Library tab and the Rewards tab. They open in an in-app browser.

The daily number of no-ad top-ups is a server setting that applies to every user alike (currently 2). It is not changed while a version is in review.

COINS
Coins are a virtual reward earned only inside the app. They cannot be purchased, transferred or exchanged for anything of value.

PRIVACY AND TRACKING
The app does not request App Tracking Transparency permission, so no advertising identifier is available to it, and every ad request is non-personalised. The only data collection in the app is by the Google Mobile Ads SDK, as declared in App Privacy, and none of it is used for tracking. The app has no accounts and sends nothing personal to our servers: coins, unlocked chapters, saved stories and reading settings are stored only on the device.

CONTENT
Original fiction written for this app. No explicit sexual content, no graphic violence. No user-generated content, no messaging, no social features.
```
