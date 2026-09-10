# Trả lời Apple — Guideline 2.1 Information Needed

Bản nháp cho lần từ chối "Information Needed" của build **1.0.0 (2)**.

- Phần **A** là văn bản tiếng Anh dán thẳng vào App Store Connect (cả ô *Reply* lẫn ô
  *App Review Information → Notes*, Apple yêu cầu cả hai).
- Phần **B** là việc bạn phải tự làm, mình không làm thay được.

Kiểm chứng trước khi viết: build đang bị xét dựng từ commit `8e00e4f`, tại đó
`package.json` **chưa có** `expo-iap` — nên mọi câu trả lời dưới đây nói về một app
**không có tài khoản và không có mua trong ứng dụng**. Gói đăng ký 9,99 $ làm sau đó
chưa từng được build hay nộp; **đừng nhắc tới nó** trong thư này.

---

## A. Văn bản gửi Apple (tiếng Anh)

> ### 1. Screen recording
>
> A screen recording captured on an iPhone running the latest iOS is attached / available
> at: **[dán link vào đây]**
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
>   money. The first chapter of every story is free to read, and some stories offer more
>   than one free chapter. Later chapters are unlocked with an in-app virtual item called
>   "coins", which users **earn for free only** by: (a) a daily check-in, (b) reaching
>   daily reading-time milestones, and (c) watching a Google AdMob rewarded video to
>   completion. Unlocking one chapter costs 10 coins; one completed rewarded video grants
>   30 coins. Coins cannot be purchased, cannot be transferred, and have no monetary
>   value.
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
>    every story is free and opens immediately. A banner ad appears at the top of the
>    reading screen. The "Aa" button in the header opens reading settings (font size, line
>    spacing, background theme, brightness).
> 4. **Listen to audio** — narrated audio is available for a subset of our catalogue while
>    we continue producing it; the "Listen" control is disabled on stories that do not
>    have it yet. *The Janitor Owns the Company*, *The Sixty-Dollar Suit* and *Undrafted*
>    all have audio for every chapter. Open one of those, then tap the headphone button in
>    the reading screen header, or the "Listen" row on the story detail screen. Audio is
>    streamed as an MP3 file from our own server.
> 5. **Unlock a later chapter** — open a story whose chapter 2 is locked (for example
>    *The Janitor Owns the Company*) and tap chapter 2. A screen explains it costs 10
>    coins. If the balance is too low, tap "Watch ad · +30 coins", watch the rewarded video
>    to the end, and coins are credited. Then tap "Unlock" and the chapter opens.
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
> | Google AdMob | Banner advertising in the reading screen and rewarded video advertising used to grant coins | Whatever the AdMob SDK collects for ad serving and measurement, as described in Google's documentation |
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
3. Chạm vào **The Janitor Owns the Company** → màn chi tiết: tóm tắt, thể loại, danh sách
   chương. **Dùng đúng truyện này** cho cả bước đọc, nghe và mở khoá — nó có audio đủ 5
   chương và chương 2 đang khoá, nên quay được trọn vẹn mọi thứ Apple hỏi trong một mạch.
4. Chạm **Read now** → đọc chương 1 (miễn phí). Cuộn vài đoạn. **Cho thấy banner quảng
   cáo ở đầu màn**.
5. Chạm nút **Aa** → đổi cỡ chữ và nền đọc → đóng lại.
6. Chạm nút **tai nghe** → màn nghe → bấm phát, để chạy vài giây cho thấy audio thật.
7. Quay lại danh sách chương → chạm **chương 2** → màn khoá hiện "10 coins".
8. Chạm **Watch ad · +30 coins** → xem hết quảng cáo rewarded → cho thấy xu được cộng.
9. Chạm **Unlock** → chương 2 mở ra, đọc được.
10. Mở tab **Rewards** cho thấy ba cách kiếm xu, rồi tab **Library**.

Không cần quay đăng ký/đăng nhập/xoá tài khoản — app không có. Thư ở phần A đã nói rõ
điều đó để người review không đi tìm.

Nộp video: tải lên Google Drive hoặc YouTube (để **unlisted**), rồi dán link vào ô Reply.
Nhớ đặt quyền xem công khai bằng link, người review không đăng nhập tài khoản của bạn được.

### B2. Trước khi nộp lại — hai vấn đề nằm trong chính bản binary

Mình soát mã nguồn của đúng bản `1.0.0 (2)` và tìm ra hai chỗ mà người review nhiều khả
năng sẽ vấp phải:

**a) Bản đang bị xét chạy ID quảng cáo TEST của Google.**
Profile `production` trong `eas.json` tại commit đó không đặt biến `EXPO_PUBLIC_ADMOB_*`
nào, nên `src/lib/ads.tsx` rơi về ID test (`ca-app-pub-3940256099942544/...`). Người
review sẽ thấy banner dán nhãn **"Test Ad"** và video rewarded thử nghiệm. Ngoài chuyện
trông như bản chưa xong, nộp ID test lên bản phát hành còn trái chính sách AdMob và app
không thu được đồng nào.

**b) App khai `NSUserTrackingUsageDescription` nhưng không bao giờ hiện hộp thoại ATT.**
Chuỗi mô tả có trong Info.plist, nhưng không dòng mã nào gọi `requestTrackingAuthorization`
— dự án cũng không cài `expo-tracking-transparency`. Nếu bảng App Privacy khai
*Used for Tracking = Yes* thì đây là mâu thuẫn Apple hay đánh trượt. Apple đã cảnh báo
đúng điểm này ở lần nộp trước.

**c) Chỉ 3 trên 13 truyện có audio.**
Hiện chỉ *The Janitor Owns the Company*, *The Sixty-Dollar Suit* và *Undrafted* có file
MP3; 10 truyện còn lại nút "Listen" bị tắt. Mô tả trên App Store đã cẩn thận ghi
*"where audio is available"* nên không phải khai sai, nhưng người review bấm trúng một
truyện không có audio vẫn có thể coi là tính năng chưa hoàn thiện. Chạy
`php artisan chapters:audio --queue` trên máy chủ để phủ kín trước khi nộp lại — cái này
**không cần build lại**, vì nội dung lấy từ máy chủ.

Hai vấn đề (a) và (b) đều **phải build lại** mới sửa được; (c) thì không.

### B3. Cần kiểm trong App Store Connect

Những thứ này nằm ngoài kho mã, mình không nhìn được:

- **App Review Information → Notes**: dán toàn bộ phần A vào đây, Apple yêu cầu rõ.
- **Sign-In Required**: phải **bỏ tick** — app không có tài khoản.
- **App Privacy**: đối chiếu lại với thực tế ở mục B2b.
- **Ảnh chụp màn hình**: Apple nhắc riêng điều 2.3.3 — phải là ảnh app đang dùng, không
  phải màn splash hay ảnh tiêu đề.
- **Mô tả / từ khoá / promotional text**: không được hứa tính năng bản này không có.
  Đặc biệt **không nhắc gói đăng ký**, vì bản bị xét không có.
