# Vá node_modules

**Hiện không có bản vá nào.** Thư mục giữ lại để chỗ đặt sẵn khi cần.

Các bản vá ở đây **không tự áp dụng** — chưa cài `patch-package`, vì thêm
dependency ngay trước lúc nộp App Store là rủi ro không cần thiết. Nếu sau này
thêm patch, áp tay sau khi `npm install`:

```bash
cd mobile && git apply patches/*.patch
```

## Đã gỡ: expo-modules-jsi — `abs()` nhập nhằng khi build bằng Xcode tại máy

Bản vá `expo-modules-jsi+57.0.3.patch` đổi `abs(milliseconds)` thành
`milliseconds.magnitude` trong `JavaScriptCodable+Date.swift`, vì Xcode 26.3
biên dịch `ExpoModulesJSI` với `-cxx-interoperability-mode=default` khiến lời
gọi `abs` không chọn được nạp chồng nào và cả archive dừng lại.

Thượng nguồn đã sửa đúng như vậy từ `expo-modules-jsi@57.1.0` (kéo về cùng đợt
nâng `expo@57.0.24`), nên bản vá thành thừa và `git apply` sẽ fail vì không còn
khớp ngữ cảnh. Xem `git log` nếu cần nội dung cũ.
