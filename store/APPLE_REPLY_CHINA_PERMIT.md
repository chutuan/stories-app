# Trả lời Apple — Guideline 2.1, giấy phép xuất bản ở Trung Quốc đại lục

Submission ID: 9c5c5bb4-2b71-44aa-8bca-148e3dbd258a · Review date: 06/10/2026 · Bản 1.0 (7)
Thiết bị Apple dùng: iPad Air 11-inch (M3)

## Vì sao bị hỏi

Luật Trung Quốc buộc app có nội dung sách/tạp chí phải có Giấy phép dịch vụ xuất bản qua mạng
(网络出版服务许可证) do Cục Báo chí Xuất bản Quốc gia (NPPA) cấp, và tên trên giấy phép phải trùng
tên developer. Một developer cá nhân ở ngoài Trung Quốc gần như không thể có giấy phép này.

Lỗi gốc nằm ở thư storefront ngày 14/09 (`APPLE_REPLY_STOREFRONTS.md`): bản A khuyên mở mọi quốc
gia và viết "We are not aware of any territory where the app would be restricted" — sai với Trung
Quốc. Cách xử lý chuẩn là **bỏ China mainland khỏi Availability** rồi trả lời Apple.

## Tin tốt từ log server

Reviewer (IP 17.84.123.163, UA `Stories/7`) mở app lúc 07:59 UTC ngày 06/10, vào Home, mở truyện
#37 và đọc chương 1–3, mọi request trả 200. Tức build 7 **chạy được trên iPad iOS 27 thật** của Apple:
lỗi crash UIScene đã hết. Apple chỉ dừng vì giấy phép, không vì chức năng.

## Làm theo thứ tự

1. App Store Connect → app Stories → **Pricing and Availability** → mục **App Availability** →
   **Edit** (hoặc *Manage*) → **bỏ chọn China mainland** → Save. Kiểm lại danh sách còn lại đúng ý.
2. Mở tin nhắn từ chối (App Review), dán **thư bên dưới**, gửi.
3. Về trang phiên bản 1.0 → vẫn là **build 7** → **Add for Review / Resubmit to App Review**.
4. Trong lúc duyệt: **tắt routine Cowork nạp truyện**. Lần trước nó vẫn nạp truyện #37 lúc 11:18 UTC
   ngày 28/09, sau khi đã nộp, và reviewer mở đúng truyện đó đầu tiên.

## Thư dán vào App Store Connect (tiếng Anh)

```text
Thank you for the review.

We do not hold an Internet Publishing License (网络出版服务许可证) or any other publication permit for China mainland, and we do not intend to distribute Stories there. We have removed China mainland from the app's availability under Pricing and Availability, so the app is no longer offered on the China mainland storefront. This also corrects our earlier answer about storefronts, where we said we were not aware of any territory-specific restriction.

Build 1.0 (7) is unchanged and ready for review. The review notes still apply: no sign-in is needed, the featured story on the Home screen, THE INVENTORY, is free in full, and THE SIXTY-DOLLAR SUIT (Search tab, type "sixty") shows how chapters are unlocked with the 90 coins every new install receives.
```
