# Vá node_modules

Các bản vá ở đây **chưa tự áp dụng** — chưa cài `patch-package`, vì thêm
dependency ngay trước lúc nộp App Store là rủi ro không cần thiết. Sau khi
`npm install`, áp tay:

```bash
cd mobile && git apply patches/*.patch
```

## expo-modules-jsi — `abs()` nhập nhằng khi build bằng Xcode tại máy

**Chỉ cần khi build tại máy.** EAS Build dùng Xcode khác nên không dính; bỏ qua
bản vá này thì `eas build` vẫn chạy bình thường.

Xcode 26.3 (Swift 6.2.4) biên dịch `ExpoModulesJSI` với
`-cxx-interoperability-mode=default`. Cờ đó kéo các nạp chồng `abs` của C++ vào
tầm nhìn, nên `abs(milliseconds)` trong `JavaScriptCodable+Date.swift` không còn
chọn được nạp chồng nào:

```
error: type of expression is ambiguous without a type annotation
  guard milliseconds.isFinite, abs(milliseconds) <= maxJavaScriptDateMilliseconds else {
```

Toàn bộ archive dừng ở đây. `Double.magnitude` là thuộc tính sẵn có của kiểu,
không qua phép phân giải nạp chồng nào, nên tránh hẳn xung đột mà giữ nguyên
ngữ nghĩa (`abs` của một `Double` chính là `magnitude` của nó).

Gỡ bản vá khi Expo phát hành bản đã sửa ở thượng nguồn.
