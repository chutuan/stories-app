# Stories — App đọc truyện

Monorepo gồm 2 phần:

- **`backend/`** — Laravel 12 + MySQL: admin web quản lý truyện + REST API cho mobile.
- **`mobile/`** — Expo (React Native + TypeScript, expo-router): app đọc truyện, hệ thống xu, quảng cáo AdMob.

Đặc tả kỹ thuật đầy đủ (data model, API contract): xem [`SPEC.md`](SPEC.md).

---

## Backend (Laravel + MySQL)

Yêu cầu: PHP 8.2+, Composer, MySQL đang chạy.

```bash
cd backend
# .env đã cấu hình sẵn: DB stories @ 127.0.0.1, user root (không mật khẩu)
# Nếu MySQL của bạn khác, sửa DB_* trong backend/.env

php artisan migrate:fresh --seed   # tạo bảng + dữ liệu mẫu
php artisan storage:link           # để hiển thị ảnh đã upload
php artisan serve --port=8000      # chạy tại http://localhost:8000
```

- **Admin:** http://localhost:8000/admin — đăng nhập `admin@stories.test` / `password`.
  Quản lý Thể loại / Truyện (upload thumbnail, chọn nhiều thể loại, đánh dấu nổi bật) / Chương.
- **API:** tiền tố `/api` (không cần auth). Các endpoint: `home`, `categories`, `stories`,
  `stories/{id}`, `stories/{id}/chapters/{number}`.

## Mobile (Expo)

Yêu cầu: **Node ≥ 18** (máy này có sẵn Node 20 qua nvm — Node 14 mặc định KHÔNG chạy được).

```bash
cd mobile
nvm use 20            # hoặc: export PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH"
npx expo start        # rồi bấm i (iOS) / a (Android) / w (web)
```

### Cấu hình URL API
`mobile/app.json` → `expo.extra.apiUrl` (mặc định `http://localhost:8000/api`).

| Chạy trên | apiUrl |
|-----------|--------|
| Web / iOS Simulator | `http://localhost:8000/api` |
| Android Emulator | `http://10.0.2.2:8000/api` |
| Thiết bị thật | `http://<IP-LAN-máy-bạn>:8000/api` (chạy `php artisan serve --host=0.0.0.0`) |

### Quảng cáo (AdMob)
- Dùng `react-native-google-mobile-ads` với **Google TEST unit IDs** (thay ID thật khi phát hành).
- Quảng cáo thật **chỉ hiển thị trong bản dev build** (không chạy trong Expo Go). Trong Expo Go,
  banner hiện placeholder và rewarded trả thưởng ngay để test luồng xu:
  ```bash
  npx expo run:android   # hoặc: npx expo run:ios  (cần Android SDK / Xcode + CocoaPods)
  ```
- App IDs test khai báo trong `app.json` (plugin `react-native-google-mobile-ads`).

### Hệ thống xu (guest, lưu local)
- Không cần đăng nhập. Xu + chương đã mở khóa + truyện đã lưu → AsyncStorage (mất khi gỡ app).
- **Chương 1 miễn phí**, chương 2+ khóa. Mở 1 chương = **30 xu**. Xem HẾT 1 rewarded =
  **+90 xu** (đúng 3 chương), tối đa 4 lượt/ngày = 360 xu. Đóng quảng cáo sớm thì
  không được xu nào.
- Chỉnh trong `mobile/src/store/wallet.tsx`: `COIN_PER_CHAPTER`, `COIN_PER_REWARD`, `STARTER_COINS`.

### Giọng đọc AI (nghe sách nói)

App **không** đọc bằng TTS của điện thoại — server generate sẵn MP3, app tải link về phát.

1. Điền key vào `backend/.env` (không commit key):
   ```
   OPENAI_API_KEY=sk-...
   ```
2. Sinh audio:
   ```bash
   php artisan chapters:audio --story=1 --chapter=1   # thử 1 chương
   php artisan chapters:audio --limit=5               # giới hạn chi phí
   php artisan chapters:audio                         # tất cả chương chưa có
   ```
   Hoặc trong admin: sửa chương → nút **🎙️ Tạo giọng đọc AI**.

Giọng và sắc thái tự chọn theo thể loại truyện (linh dị giọng thì thầm, ngôn tình giọng nữ ấm áp,
kiếm hiệp giọng nam trầm hào sảng...). Chi tiết ở [SPEC.md](SPEC.md) mục 9.


---

## Ghi chú
- Nội dung chương do API trả về cho mọi request; việc khóa/mở do client (guest) tự quản lý.
  Muốn chặn thật ở server cần thêm tài khoản người dùng + entitlement (ngoài phạm vi bản này).
