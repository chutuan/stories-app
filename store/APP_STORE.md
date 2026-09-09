# Nộp Stories lên App Store — runbook

Cập nhật: 9/9/2026 · Bundle ID `com.chutuan.stories` · Version `1.0.0`

---

## 0. Ba thứ CHẶN, chỉ bạn làm được

| # | Việc | Vì sao chặn |
|---|------|-------------|
| 1 | **Đăng ký Apple Developer Program** (99 USD/năm) | Không có thì không tạo được app record, không build ký được, không nộp được. Kể cả chỉ TestFlight nội bộ cũng cần. |
| 2 | **`eas login` + `eas init`** | `extra.eas.projectId` trong `app.config.js` đang **rỗng**. EAS Build sẽ dừng ngay ở bước đầu. |
| 3 | **AdMob ID thật** | Hiện dùng ID TEST của Google → app chạy nhưng **không ra doanh thu**. |

Không có tài khoản của bạn thì mình không đăng nhập hộ được — đó là thông tin đăng nhập.

---

## 1. Chuẩn bị tài khoản

```bash
cd mobile
nvm use                    # đọc .nvmrc -> Node 20 (Node 14 mặc định sẽ hỏng)
npx eas login              # tài khoản Expo
npx eas init               # tạo project, in ra projectId
```

> `eas-cli` đã được cài thành devDependency (pin 23.2.0) nên `npx eas` dùng bản trong
> `node_modules`, không phụ thuộc cache npx. Nếu vẫn gặp `ERR_MODULE_NOT_FOUND` từ
> `~/.npm/_npx/...` thì cache npx hỏng — xoá thư mục đó rồi chạy lại.

Dán `projectId` vừa nhận vào `mobile/app.config.js`:

```js
extra: {
  eas: { projectId: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' },
}
```

> Vì đây là **dynamic config** (`app.config.js` chứ không phải `app.json`), `eas init` **không tự ghi được** — bắt buộc dán tay.

---

## 2. Điền AdMob ID thật

Lấy trong AdMob Console → sửa `mobile/eas.json`, mục `build.production.env`:

```json
"EXPO_PUBLIC_ADMOB_IOS_APP_ID":   "ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX",
"EXPO_PUBLIC_ADMOB_ANDROID_APP_ID":"ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX",
"EXPO_PUBLIC_ADMOB_BANNER_ID":     "ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX",
"EXPO_PUBLIC_ADMOB_REWARDED_ID":   "ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX"
```

Để trống thì code tự fallback về ID test — app vẫn chạy, chỉ là không có doanh thu.

---

## 3. Build và nộp

```bash
cd mobile
nvm use                                      # BẮT BUỘC: .nvmrc -> Node 20; Node 14 mặc định sẽ làm eas sập

npx eas build --platform ios --profile production
# EAS hỏi Apple ID -> đăng nhập, nó tự tạo cert + provisioning profile

npx eas submit --platform ios --latest    # đẩy lên App Store Connect / TestFlight
```

`autoIncrement: true` đã bật trong profile `production`, nên `buildNumber` tự tăng mỗi lần build —
không bị Apple từ chối vì trùng số build.

---

## 4. Nội dung khai trong App Store Connect

### Thông tin cơ bản

| Trường | Giá trị |
|---|---|
| Name (30 ký tự) | `Stories: Billionaire Novels` |
| Subtitle (30 ký tự) | `Secret CEO & romance reads` |
| Primary category | Books |
| Secondary category | Entertainment |
| Support URL | `https://tunastory.com` |
| Marketing URL | `https://tunastory.com` |
| Privacy Policy URL | `https://tunastory.com/privacy` |

Ba URL trên đã sống, trả HTTP 200 (kiểm tra ngày 9/9/2026).

### Promotional text (170 ký tự)

```
New chapters every week. Meet the janitor who owns the tower, the broke husband
with a hidden empire, and the beggar who walks into the board meeting.
```

### Description

```
They laughed at his old car. They stepped over his mop bucket. They had no idea
who he really was.

Stories is a library of serialised fiction built around one irresistible moment:
the reveal. A janitor, a delivery boy, a "useless" son-in-law, a husband with no
money — until the day the mask comes off and the whole room goes quiet.

WHAT YOU GET

• Hand-picked serials across Billionaire, CEO, Secret Identity, Romance,
  Revenge, Family Drama, Rags to Riches and Second Chance
• A reader built for long sessions — adjust font size, line spacing and
  brightness, and pick between four page themes including night mode
• Listen instead of read, with narrated chapters where audio is available
• Earn coins by checking in daily and watching rewarded videos, then spend
  them to unlock the next chapter
• No account, no sign-up, no email required — open the app and start reading

HOW COINS WORK

Coins are earned inside the app, never bought with money. Check in each day,
watch a rewarded video, or hit a reading milestone. Spend them to unlock
chapters. Every story has free chapters to start with.

Questions or requests: support@tunastory.com
```

### Keywords (100 ký tự, phân tách bằng dấu phẩy, không khoảng trắng)

```
billionaire,ceo,romance,novel,webnovel,story,fiction,revenge,werewolf,drama,book,read,serial
```

---

## 5. App Privacy — khai đúng theo hành vi thật của app

App **tự nó không thu thập gì**: không tài khoản, không đăng nhập, chỉ gửi request GET,
không có SDK analytics hay crash-reporting. Xu, chương đã mở, truyện đã lưu và tuỳ chọn đọc
đều nằm trong AsyncStorage trên máy.

Thứ duy nhất thu thập dữ liệu là **Google AdMob**. Khai như sau:

| Data type | Collected | Purpose | Linked to identity | Used for tracking |
|---|---|---|---|---|
| Identifiers → Device ID | Yes | Third-Party Advertising | No | **Yes** |
| Usage Data → Advertising Data | Yes | Third-Party Advertising | No | **Yes** |

Vì có "Used for tracking" nên **bắt buộc** hiện App Tracking Transparency —
`NSUserTrackingUsageDescription` đã khai sẵn trong `app.config.js`.

> ⚠️ Trước khi bấm nộp, đối chiếu lại với trang khai báo quyền riêng tư hiện hành của AdMob
> (Google có cập nhật danh mục theo thời gian). Bảng trên phản ánh đúng những gì app này làm,
> nhưng danh mục chính xác do Google công bố mới là căn cứ cuối cùng.

### Age rating
Truyện là hư cấu người lớn (tình cảm, trả thù, xung đột gia đình) nhưng không có nội dung
tường minh. Trả lời bảng câu hỏi theo **đúng kho truyện bạn thực sự phát hành** — vì nội dung
do bạn tự thêm qua trang admin, mức rating sẽ đổi nếu bạn đăng truyện nặng đô hơn.

---

## 6. Ảnh chụp màn hình

`store/screenshots/ios-6.9/` — 6 ảnh **1320×2868** (kích thước 6.9" App Store yêu cầu):

| File | Màn hình |
|---|---|
| `1-discover.png` | Trang chủ: hero truyện nổi bật + hàng truyện |
| `2-detail.png` | Chi tiết truyện: đánh giá, thông tin, danh sách chương |
| `3-reader.png` | Màn đọc |
| `4-rewards.png` | Điểm danh và nhiệm vụ nhận xu |
| `5-audio.png` | Trình phát sách nói |
| `6-search.png` | Tìm kiếm theo thể loại |

> ⚠️ **Đây là ảnh NHÁP, chụp qua Expo Go** nên góc trên phải còn nút bánh răng của Expo Go.
> Nút đó **không tồn tại trong bản build thật**. Sau khi có bản TestFlight, chụp lại từ đó rồi
> thay thế — Apple không chấp nhận ảnh có lẫn giao diện công cụ phát triển.

Apple hiện chỉ bắt buộc bộ 6.9"; các kích thước nhỏ hơn sẽ được tự thu nhỏ.

---

## 7. Trước khi nộp — kiểm lại

- [ ] `projectId` đã điền, `eas build` chạy được
- [ ] AdMob ID thật đã thay ID test
- [ ] `EXPO_PUBLIC_API_URL` = `https://api.tunastory.com/api` (HTTPS — iOS chặn HTTP thường)
- [ ] Ảnh chụp đã thay bằng bản từ TestFlight (không còn nút dev)
- [ ] `support@tunastory.com` là hòm thư đọc được thật
- [ ] Đã đổi mật khẩu admin trên server
- [ ] Đã tự cài bản TestFlight lên máy thật và đọc thử trọn một chương
