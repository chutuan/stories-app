# Trả lời Apple — Guideline 2.1 Information Needed

Thư trả lời lần từ chối "Information Needed" của build **1.0.0 (2)**, gửi kèm build
mới **1.0.0 (3)** — bản đã sửa cả hai vấn đề nằm trong binary (xem phần B2).

- Phần **A** là văn bản tiếng Anh dán thẳng vào App Store Connect (cả ô *Reply* lẫn ô
  *App Review Information → Notes*, Apple yêu cầu cả hai).
- Phần **B** là việc bạn phải tự làm, mình không làm thay được.

Kiểm chứng trước khi viết: bản nộp lại **1.0.0 (3)** dựng từ commit `5169c84`, tại đó
`package.json` **không có** `expo-iap` và `app.config.js` **không khai**
`NSUserTrackingUsageDescription` — nên mọi câu trả lời dưới đây nói về một app **không
có tài khoản, không có mua trong ứng dụng, và không theo dõi người dùng**. Gói đăng ký
9,99 $ đã gỡ khỏi bản này; **đừng nhắc tới nó** trong thư.

---

## A. Văn bản gửi Apple (tiếng Anh)

> ### 1. Screen recording
>
> A screen recording captured on an iPhone running the latest iOS is attached / available
> at: **https://drive.google.com/file/d/1Rb5-UFqJYZWWDgwKTFWrmV44Vaw9PVY7/view**
>
> Please note the following about the flows Apple asked us to include:
>
> - **Account registration, login, account deletion** — *not applicable*. Stories has no
>   accounts of any kind. There is no sign-up, no sign-in, and no user profile. The app is
>   fully usable from first launch with no credentials. Coin balance, unlocked chapters,
>   saved stories and reading preferences are stored only in local device storage
>   (`AsyncStorage`) and are never transmitted to us. Because no account can be created,
>   there is nothing to delete, so the account-deletion requirement does not apply.
> - **User-generated content** — *not applicable*. Users cannot post, comment, upload,
>   message, or share anything. There are no profiles and no social features, so content
>   reporting and blocking mechanisms are not required.
> - **Accessing paid content or features** — the recording shows this. Stories contains
>   **no in-app purchases and no subscriptions**; nothing in the app can be bought with
>   money. **Two stories are completely free, every chapter, with narrated audio:
>   *Whose Son Are You* (3 chapters) and *The Inventory* (5 chapters).** They are also
>   listed under the "Free" tab on the home screen. For every other story chapter 1 is
>   free and later chapters are unlocked with an in-app virtual item called "coins",
>   which users **earn for free only** by: (a) a daily check-in worth 15 coins,
>   (b) reading-time milestones worth 15 coins after ten minutes and more thereafter, and
>   (c) watching a Google AdMob rewarded video to completion, worth 90 coins. Unlocking a
>   chapter costs 30 coins — so the daily check-in plus the ten-minute reading milestone
>   already unlocks one chapter **without watching any advertisement**. Coins cannot be
>   purchased, cannot be transferred, and have no monetary value.
> - **Tracking** — *not applicable*. The app does not request App Tracking Transparency
>   permission, does not read the IDFA, and contains no `NSUserTrackingUsageDescription`
>   string. Advertising is requested in non-personalised mode
>   (`requestNonPersonalizedAdsOnly`). Our App Privacy answers declare *Used for tracking:
>   No* for every data type, which matches the binary.
>
> ### 2. Purpose of the app and target audience
>
> **Purpose.** Stories is a free short-fiction reading app. It offers serialised
> English-language drama in the "hidden wealth / secret identity" genre — stories about
> people who are underestimated, dismissed, or taken for poor, and what happens when the
> truth comes out. Each story is 4–10 short chapters, sized to be read in a few minutes
> on a phone. Selected stories also carry a narrated audio track for every chapter, so the
> same story works while commuting, walking or doing chores; we are producing narration for
> the rest of the catalogue.
>
> **Problem it solves.** Readers who want a complete, satisfying story in one short
> sitting are usually forced into either full-length novels or ad-heavy websites. Stories
> gives them a self-contained chapter in a few minutes, with a narrated version available
> for selected stories, and without requiring an account or a payment.
>
> **Target audience.** General adult readers of popular commercial fiction, roughly
> 18–45, who read on a phone in short sessions. Content is written to a mainstream
> standard: no explicit sexual content, no graphic violence, no profanity beyond mild
> language. There is no user interaction and no content that targets children.
>
> **Business model.** Advertising only. The app is free, contains banner and rewarded
> advertising from Google AdMob, and sells nothing.
>
> ### 3. How to set up and access the main features
>
> **No credentials are required.** There is no demo account because the app has no
> account system. Launch the app and everything is immediately accessible.
>
> Suggested path through the main features:
>
> 1. **Home** — the app opens on a shelf of stories with a featured story at the top and
>    rows for recently updated and newest stories.
> 2. **Story detail** — tap any story to see its synopsis, genres and chapter list.
> 3. **Read a free chapter** — tap "Read now", or tap chapter 1 in the list. Chapter 1 of
>    every story is free and opens immediately. The reading screen carries a banner ad slot
>    at the top; it may be empty during your review for the reason explained in step 5. The
>    "Aa" button in the header opens reading settings (font size, line spacing, background
>    theme, brightness).
> 4. **Listen to audio** — narrated audio is available for a subset of our catalogue while
>    we continue producing it; the "Listen" control is disabled on stories that do not
>    have it yet. *The Sixty-Dollar Suit*, *Undrafted*, *The Inventory* and *Whose Son Are You*
>    all have audio for every chapter. Open one of those, then tap the headphone button in
>    the reading screen header, or the "Listen" row on the story detail screen. Audio is
>    streamed as an MP3 file from our own server.
> 5. **Unlock a later chapter** — open a story whose chapter 2 is locked (for example
>    *The Sixty-Dollar Suit*) and tap chapter 2. A screen explains it costs 30 coins.
>
>    **Please note that advertising may not display during your review.** Our AdMob
>    account still lists this app with the approval status "review required", and the
>    store-listing field is still empty because the app is not yet on the App Store.
>    Google completes that approval only after an app is published, so ad fill is
>    currently zero and the app correctly reports "Ads are not available right now"
>    instead of failing silently. This affects only the rewarded-video route to coins.
>    Every feature remains reachable without it: the two completely free stories above,
>    the daily check-in, and the reading-time milestones. We did not want to submit a
>    recording of a feature that Google has not yet enabled for us, so the attached video
>    demonstrates the unlock screen and then reads a full free story instead.
> 6. **Rewards tab** — shows the daily check-in, reading-time milestones and the daily ad
>    tasks, which are the three ways coins are earned.
> 7. **Library tab** — stories the user has saved with the bookmark button.
>
> ### 4. External services, tools and platforms
>
> **Services the app itself contacts at runtime — there are only two:**
>
> | Service | Purpose | Data sent |
> |---|---|---|
> | `api.tunastory.com` — our own server (a VPS we operate) | Read-only REST API that serves story text, cover images and narration MP3 files | None. The app only performs GET requests. No user data, account data or device identifier is sent. |
> | Google AdMob | Banner advertising in the reading screen and rewarded video advertising used to grant coins | Requested in **non-personalised** mode; the app never asks for tracking permission and never reads the IDFA |
>
> The app contains **no analytics, attribution, crash-reporting or social SDKs** — no
> Firebase, no Facebook SDK, no Sentry, no AppsFlyer, no Adjust, no Segment. It does not
> use over-the-air code updates.
>
> **AI services used to produce the content, on our server, before publication.** The app
> never contacts these services; they run in our own backend as part of our editorial
> pipeline, and the resulting files are stored on our server and served as static content:
>
> | Service | Used for |
> |---|---|
> | OpenAI `gpt-4o-mini-tts` | Generating the narrated audio track for each chapter from our own story text |
> | OpenAI `gpt-image-1` and `gpt-4o-mini` | Generating the cover artwork for each story |
>
> **Content ownership.** All story text in the app is original fiction created by us
> specifically for this app, with the assistance of AI writing tools, and reviewed by us
> before publication. Narration audio and cover artwork are likewise generated from our
> own text and owned by us. The app contains **no licensed or third-party copyrighted
> works**, no fan fiction, no user submissions, and no scraped or reproduced content.
>
> ### 5. Regional differences
>
> The app functions **identically in all regions**. There is no geographic gating, no
> region-specific content, no region-specific pricing (the app is free everywhere and
> sells nothing), and no region-specific feature flags. The interface and all content are
> in English only, worldwide. The only variation is that Google AdMob serves advertising
> appropriate to the viewer's region, which is handled entirely by Google.
>
> ### 6. Regulated industry / third-party material
>
> Stories does not operate in a regulated industry. It is not a financial, medical,
> gambling, health, dating, or government-related service, and it provides no regulated
> advice. It contains no third-party protected material — see "Content ownership" in
> section 4 — so no licences or authorisations are required.

---

## B. Việc bạn phải tự làm

### B1. Quay video trên máy thật — mình không làm thay được

Apple bắt buộc quay trên **thiết bị vật lý**, chạy iOS mới nhất. Mình chỉ điều khiển
được simulator, mà video simulator sẽ bị từ chối.

Cách quay: cài bản TestFlight lên iPhone → Cài đặt → Trung tâm điều khiển → thêm
**Ghi màn hình** → mở Trung tâm điều khiển, bấm nút ghi → quay xong lưu vào Ảnh.

Trình tự cảnh phải có, theo đúng thứ tự (khoảng 2–3 phút):

1. Màn hình chính của iPhone, **chạm vào icon Stories** để mở — Apple yêu cầu video phải
   bắt đầu từ lúc khởi chạy app.
2. Đợi màn hình chủ hiện danh sách truyện, cuộn xuống cho thấy các hàng truyện.
3. Chạm vào **The Sixty-Dollar Suit** → màn chi tiết: tóm tắt, thể loại, danh sách chương.
   **Dùng đúng truyện này** cho cả bước đọc, nghe và mở khoá — nó có audio đủ 10 chương và
   chương 2 đang khoá, nên quay được trọn vẹn mọi thứ Apple hỏi trong một mạch.
   (Kịch bản trước dùng *The Janitor Owns the Company*; truyện đó đã bị gỡ khỏi kho ngày
   10/09/2026 cùng 9 truyện demo khác — mỗi "chương" chỉ khoảng 200 từ, quá mỏng để chạy
   Google AdSense trên bản web.)
4. Chạm **Read now** → đọc chương 1 (miễn phí). Cuộn vài đoạn.
5. Chạm nút **Aa** → đổi cỡ chữ và nền đọc → đóng lại.
6. Chạm nút **tai nghe** → màn nghe → bấm phát, để chạy vài giây cho thấy audio thật.
7. Quay lại danh sách chương → chạm **chương 2** → màn khoá hiện "Unlock · 30 coins".
   Dừng ở đây vài giây cho thấy rõ giá và số dư — **ĐỪNG bấm "Watch ad"** (lý do ở dưới).
8. Quay lại màn chủ → tab **Free** → mở **Whose Son Are You** → đọc hết chương 1, chạm
   **Next** sang chương 2, rồi chương 3. Đây là phần quan trọng nhất của cả video: nó chứng
   minh người dùng đọc trọn một truyện mà không tốn xu và không xem quảng cáo nào.
9. Chạm nút **tai nghe** trong chính truyện đó → phát vài giây (truyện này có audio đủ 3
   chương).
10. Mở tab **Rewards**: cho thấy **Check in +15**, các mốc đọc **+15/+25/+40/+60**, và ô
    **Watch ad · +90 coins**. Chỉ CHỈ vào chúng, không bấm.
11. Mở tab **Library**, kết thúc.

**VÌ SAO KHÔNG QUAY CẢNH XEM QUẢNG CÁO.** Bảng điều khiển AdMob đang để app iOS ở trạng
thái *"Yêu cầu xem xét"*, và mục *"Thông tin chi tiết về cửa hàng ứng dụng"* còn trống vì
app chưa lên App Store. Google chỉ duyệt sau khi app được phát hành, nên hiện tỉ lệ lấp
quảng cáo bằng **0** — bấm "Watch ad" trên máy thật chỉ hiện *"Ads are not available right
now"*. Quay đúng cảnh đó vào video nộp cho Apple là tự tay đưa bằng chứng rằng tính năng
kiếm xu không chạy. Đây là vòng luẩn quẩn không gỡ được trước khi nộp: chưa phát hành thì
AdMob chưa duyệt.

Bù lại, ngày 10/09/2026 đã mở **hai truyện đọc miễn phí trọn vẹn** — *Whose Son Are You*
(3 chương) và *The Inventory* (5 chương), cả hai có audio đủ — nên mọi tính năng Apple hỏi
đều quay được mà không phụ thuộc quảng cáo. Ghi chú gửi người review
(`store/APP_REVIEW_NOTES.md`) đã nói thẳng chuyện AdMob chưa duyệt, nên không có gì mâu
thuẫn giữa video và lời khai.

Không cần quay đăng ký/đăng nhập/xoá tài khoản — app không có. Thư ở phần A đã nói rõ
điều đó để người review không đi tìm.

Nộp video: tải lên Google Drive hoặc YouTube (để **unlisted**), rồi dán link vào ô Reply.
Nhớ đặt quyền xem công khai bằng link, người review không đăng nhập tài khoản của bạn được.

### B2. Hai vấn đề trong binary — đã sửa ở bản 1.0.0 (3)

Bản `1.0.0 (2)` bị hai lỗi nằm trong chính file binary, không sửa được bằng cách trả lời
Apple. Cả hai đã xử lý xong, đây là để bạn đối chiếu nếu Apple hỏi lại:

**a) Bản (2) chạy ID quảng cáo TEST của Google — đã sửa.**
Profile `production` trong `eas.json` khi đó không đặt biến `EXPO_PUBLIC_ADMOB_*` nào,
nên `src/lib/ads.tsx` rơi về ID test (`ca-app-pub-3940256099942544/...`): người review
thấy banner dán nhãn **"Test Ad"**. Bản (3) đã nhúng ID thật của đơn vị quảng cáo iOS
(app `…~9913447750`, banner `…/3899537894`, rewarded `…/3713659468`), xác nhận bằng
`eas config --platform ios --profile production`.

**b) Bản (2) khai `NSUserTrackingUsageDescription` mà không bao giờ hiện hộp ATT — đã gỡ.**
Chuỗi mô tả có trong Info.plist nhưng không dòng mã nào gọi `requestTrackingAuthorization`
(dự án không cài `expo-tracking-transparency`). Chính điểm này khiến Apple chặn nộp với
thông báo *"update your App Privacy response … or upload a new build"*. Bản (3) đã bỏ hẳn
chuỗi đó khỏi `app.config.js`, và quảng cáo chạy `requestNonPersonalizedAdsOnly`. Nhờ vậy
bảng App Privacy giữ nguyên **Used for tracking: No** — đúng với binary.

**c) Chỉ 3 trên 13 truyện có audio — chưa làm, không chặn nộp.**
Hiện chỉ *The Sixty-Dollar Suit*, *Undrafted*, *The Inventory* và *Whose Son Are You* có
file MP3 — 4 trên 10 truyện; 6 truyện còn lại nút "Listen" bị tắt. Mô tả trên App Store đã ghi
*"where audio is available"* nên không khai sai, nhưng người review bấm trúng truyện
không có audio vẫn có thể coi là tính năng dở dang. Chạy
`php artisan chapters:audio --queue` trên máy chủ để phủ kín — **không cần build lại**,
vì nội dung lấy từ máy chủ.

**Cạm bẫy đã tránh khi dựng bản (3):** thư mục `mobile/ios/` cũ còn sót lại trên máy vẫn
mang Info.plist có `NSUserTrackingUsageDescription` và ID AdMob test. Vì `.easignore`
**đè lên** `.gitignore`, thư mục đó sẽ được tải lên EAS, EAS bỏ qua bước prebuild, và bản
(3) sẽ ra y hệt bản (2) mà **không báo lỗi gì**. Đã xoá thư mục và thêm `mobile/ios/`,
`mobile/android/` vào `.easignore`. Nếu sau này bạn chạy `expo prebuild` để mở Xcode,
nhớ xoá lại thư mục đó trước khi build EAS lần kế.

### B3. Cần kiểm trong App Store Connect

Những thứ này nằm ngoài kho mã, mình không nhìn được:

- **Chọn đúng build `1.0.0 (3)`** ở mục Build trước khi bấm Submit — bản (2) vẫn còn đó
  và chọn nhầm là quay lại đúng chỗ bị từ chối.
- **App Review Information → Notes**: dán toàn bộ phần A vào đây, Apple yêu cầu rõ.
- **Sign-In Required**: phải **bỏ tick** — app không có tài khoản.
- **App Privacy**: cả *Device ID* và *Advertising Data* đều để **Used for tracking: No**.
  Bản (3) đã gỡ ATT nên khai như vậy là khớp binary; nếu để *Yes*, Apple lại chặn nộp
  với đúng thông báo lần trước.
- **Ảnh chụp màn hình**: Apple nhắc riêng điều 2.3.3 — phải là ảnh app đang dùng, không
  phải màn splash hay ảnh tiêu đề. Chụp lại từ bản (3) để quảng cáo hiện là ad thật,
  không phải "Test Ad".
- **Mô tả / từ khoá / promotional text**: không được hứa tính năng bản này không có.
  Đặc biệt **không nhắc gói đăng ký**, vì bản nộp không có.
