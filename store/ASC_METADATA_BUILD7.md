# Metadata cho build 7 — dán vào App Store Connect

Soạn 28/09/2026 sau đợt soát toàn diện (15 agent). Mọi độ dài đã kiểm bằng máy.

## Thứ tự làm

1. Upload **build 7** (archive trong Organizer). **Bỏ chọn "Manage Version and Build Number"**,
   nếu không Xcode tự đổi số — lần trước nó biến build 5 thành 6.
2. Khi build 7 xử lý xong, **cài từ TestFlight lên iPhone thật, mở ra, và vào tab Library xem có
   mục "About Stories" với 3 link** — đây là thay đổi duy nhất của build 7, phải tận mắt thấy.
3. Dán các ô dưới đây. Chọn **build 7** cho bản nộp (KHÔNG chọn 5 hay 6).
4. Dán thư trả lời, nộp.
5. **Trong lúc Apple duyệt: KHÔNG nạp truyện mới, KHÔNG đổi truyện nổi bật / số chương free.**
   Ghi chú và thư đang mô tả đúng dữ liệu hiện tại; pipeline nạp truyện có thể ghi đè nó.

## Keywords (98/100)

```text
revenge,drama,billionaire,ceo,romance,secret identity,second chance,family,mystery,suspense,serial
```

Đã bỏ `webnovel` (tên một app lớn — Guideline 2.3.7 cấm dùng tên app khác), `werewolf` (không có truyện
nào), và các từ trùng với tên app (`story`, `stories`, `book`, `read` — Apple đã tự đánh chỉ mục tên app).
Mọi từ còn lại đều có truyện thật: revenge 15, drama 10, secret identity 7, ceo 7, suspense 7,
second chance 9, family 4, mystery 4, romance 4, billionaire 2.

## Promotional text (161/170)

```text
Short serialised stories you can finish in one sitting: revenge, secret identities, second chances and family drama. Start free with 90 coins, no account needed.
```

## Description (1381/4000)

Bản cũ quảng cáo "the janitor who owns the tower", "the broke husband", "useless son-in-law" — **toàn là
bộ truyện mẫu đã xoá từ 10/09**. Reviewer đọc xong mà không tìm thấy trong app là lỗi Guideline 2.3.1.
Bản cũ cũng ghi email `support@tunastory.com` — tên miền không có bản ghi MX nên thư gửi vào bị trả về.

```text
Short serialised stories you can finish in one sitting: people who are underestimated, betrayed or written off, and what happens when the truth finally comes out.

WHAT YOU GET

• Original serials across Revenge, Family Drama, Secret Identity, Second Chance, CEO, Romance, Suspense and Mystery
• A reader built for long sessions — adjust font size, line spacing and brightness, and choose between four page themes including a night theme
• Narrated audio on selected stories: listen instead of read
• Two complete stories are free from the first chapter to the last
• No account, no sign-up, no email — open the app and start reading

HOW COINS WORK

Every new install starts with 90 coins, enough to unlock three chapters straight away. The first chapter of every story is free. Later chapters cost 30 coins each and stay unlocked for good.

Coins are earned inside the app and are never sold for money. Check in each day, reach a reading-time milestone, or watch an optional rewarded video. If no video is available, you still receive the coins.

PRIVACY

Stories has no accounts and does not track you across other apps or websites. Ads are non-personalised. Your coins, unlocked chapters, saved stories and reading settings stay on your device.

Privacy Policy, Terms of Service and a contact form are linked at the bottom of the Library and Rewards tabs, and at tunastory.com.
```

## App Privacy — sửa lại toàn bộ

Bảng cũ chỉ khai 2 loại và ghi "Linked: No". Manifest của Google Mobile Ads **nằm ngay trong binary**
khai 7 loại, trong đó 4 loại Linked = Yes. Khai như sau:

| Loại dữ liệu (trên App Store Connect) | Linked to user | Used for tracking | Purposes |
|---|---|---|---|
| Identifiers → **Device ID** | **Yes** | **No** | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |
| Usage Data → **Advertising Data** | **Yes** | No | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |
| Usage Data → **Product Interaction** | **Yes** | No | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |
| Location → **Coarse Location** | **Yes** | No | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |
| Diagnostics → **Crash Data** | No | No | Analytics |
| Diagnostics → **Performance Data** | No | No | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |
| Diagnostics → **Other Diagnostic Data** | No | No | Third-Party Advertising, Developer's Advertising or Marketing, Analytics |

**"Used for tracking" = No ở MỌI dòng, kể cả Device ID.** Manifest của Google ghi Device ID là
tracking = true, nhưng đó là trường hợp app xin quyền ATT. App này không gọi
`requestTrackingAuthorization`, nên iOS không cấp IDFA (trả toàn số 0), và mọi yêu cầu quảng cáo đều
`requestNonPersonalizedAdsOnly: true`. Khai tracking = Yes thì Apple BẮT BUỘC app hiện hộp thoại ATT —
đúng lỗi đã làm build 2 bị chặn.

"Linked to user = Yes" thì an toàn: nó KHÔNG kéo theo yêu cầu ATT, chỉ nói dữ liệu gắn với một định
danh thiết bị. Khai đúng như Google khai.

Ngoài ra server của mình đếm lượt đọc (bảng `story_views`) bằng dấu băm xoay vòng theo ngày — thuộc
Usage Data → Product Interaction, mục đích Analytics, không linked. Dòng Product Interaction ở trên đã
bao trùm nó (App Store Connect gộp theo loại dữ liệu, không theo người thu).

## App Review Information → Notes (1809/4000)

```text
Stories is a free reading app for short serialised English fiction. No accounts, no sign-in, no in-app purchases, no subscriptions. Revenue comes only from non-personalised Google AdMob ads.

HOW TO TEST — no credentials needed, just launch the app
1. The featured story on the Home screen, THE INVENTORY, is free in full, as is WHOSE SON ARE YOU. Every chapter opens without coins.
2. To test unlocking: open THE SIXTY-DOLLAR SUIT, read chapter 1 (free), tap Next. Chapter 2 is locked. A new install has 90 coins; tap "Unlock · 30 coins" and the chapter opens, balance 60.
3. Rewarded video: on a locked chapter, tap "Watch ad · +90 coins". If AdMob has an ad, it plays and grants +90 coins. If no ad is available, the app grants the coins anyway and shows "No ad was available — we added +90 coins anyway." A user never gets stuck because of ad fill.
4. Other free ways to earn coins are in the Rewards tab: daily check-in (15 to 30 coins depending on the day) and reading-time milestones.
5. Audio: open a chapter of a narrated story and tap the headphones button.
6. Privacy Policy, Terms of Service and Contact are at the bottom of the Library tab and the Rewards tab. They open in an in-app browser.

COINS
Coins are a virtual reward earned only inside the app. They cannot be purchased, transferred or exchanged for anything of value.

PRIVACY AND TRACKING
The app does not request App Tracking Transparency permission, so no advertising identifier is available to it, and every ad request is non-personalised. The app itself collects no personal data; coins, unlocked chapters, saved stories and reading settings are stored only on the device.

CONTENT
Original fiction written for this app. No explicit sexual content, no graphic violence. No user-generated content, no messaging, no social features.
```

## Ô Reply cho thư từ chối (1939/4000)

```text
Thank you for the crash logs — they identified the problem precisely. It is fixed, and build 7 is the build submitted for review.

WHAT WAS WRONG
Both logs show EXC_BREAKPOINT (SIGTRAP) on the main thread in __UIApplicationEvaluateRuntimeIssueForNoSceneLifecycleAdoption. The app still used the older app-delegate life cycle and had not adopted the UIScene life cycle. Build 4 was the first build we linked against the iOS 27 SDK, and iOS 27 stops apps that have not adopted scenes at launch.

WHAT WE CHANGED
The app now adopts the UIScene life cycle: Info.plist declares UIApplicationSceneManifest, and the window is created by a UIWindowSceneDelegate when the scene connects instead of in application(_:didFinishLaunchingWithOptions:).

While preparing this build we also added links to our Privacy Policy, Terms of Service and a contact form inside the app, at the bottom of the Library and Rewards tabs.

HOW WE VERIFIED IT
We tested on the iOS 27.0 Simulator using the same two models as your review, iPhone 17 Pro Max and iPad Air 11-inch (M3). As a control, we removed only the scene manifest from the build: on both devices it terminated at launch with the same assertion as your logs ("UIScene life cycle is required for apps built with this SDK"). The build as submitted launches normally on both. On the iPhone we also opened a locked chapter, unlocked it with the included coins, watched a rewarded video to the end and received +90 coins, and played a narrated chapter.

WHAT TO TEST
- A new install starts with 90 coins. Open THE SIXTY-DOLLAR SUIT, read chapter 1 for free, tap Next, then tap "Unlock · 30 coins". The chapter opens and the balance falls to 60. No ad is needed.
- If an ad cannot be shown, the app grants the coins anyway and says so.
- The featured story on the Home screen, THE INVENTORY, is free in full, as is WHOSE SON ARE YOU.

There are no in-app purchases. Coins are earned free and cannot be bought.
```

## Chưa sửa, cần bạn biết

- **Tên app `Stories: Billionaire Novels`**: chỉ 2/20 truyện có thẻ Billionaire (7 có CEO). Chưa chắc bị
  trả về, nhưng là chỗ reviewer có thể hỏi "tên có khớp nội dung không". Đổi tên là quyết định thương
  hiệu nên tôi không tự đổi.
- **Ảnh chụp App Store làm từ 10/09**: hiện truyện đã xoá, ô "Advertisement" giả, 0 xu, mức điểm danh cũ.
  Guideline 2.3.3 yêu cầu ảnh phản ánh app thật. Bạn chọn chưa chụp lại — đây là rủi ro còn lại lớn nhất.
- **Video demo gửi Apple quay ngày 10/09**, trước khi có 90 xu khởi điểm và bản sửa UIScene.
