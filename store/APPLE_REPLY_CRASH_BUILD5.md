# Trả lời Apple — Guideline 2.1(a), app crash lúc mở (bản 1.0 (4))

Submission ID: 9c5c5bb4-2b71-44aa-8bca-148e3dbd258a · Review date: 20/09/2026
Thiết bị Apple dùng: iPad Air 11-inch (M3) iPadOS 27.0 và iPhone 17 Pro Max iOS 27.0

## PHẢI LÀM TRƯỚC KHI GỬI — đừng bỏ qua

1. Upload build **1.0.0 (5)** lên App Store Connect.
2. Khi build 5 xử lý xong, **cài nó từ TestFlight lên một iPhone hoặc iPad THẬT chạy iOS 27
   và mở ra.** Apple yêu cầu đúng điều này ("Test the app on a device"), và simulator đã
   chứng minh là KHÔNG bắt được lỗi này — bản 4 chạy bình thường trên simulator nhưng crash
   trên máy thật. Chỉ gửi thư khi đã tận mắt thấy app mở được trên máy thật.
3. Chọn build 5 cho bản nộp, dán bản dưới vào ô Reply.

ĐÃ SỬA 28/09/2026: bản trước viết "tested on a physical device running iOS 27" và "confirmed
that iOS 27 no longer raises…" — cả hai SAI. Simulator dùng để kiểm chạy iOS 26.3 (máy chỉ cài
runtime đó; SDK 27.0 là SDK để build, không phải hệ điều hành simulator), và máy thật của Tuấn
chạy iOS 26.7. Thư giờ chỉ nói đúng những gì đã làm, KHÔNG nhắc số phiên bản iOS nào chưa thử.
Nếu sau này thử được trên iOS 27 thì mới thêm câu đó vào.

## Ô Reply

```text
Thank you for the crash logs — they identified the problem precisely, and it is fixed in build 5.

WHAT WAS WRONG
Both logs show EXC_BREAKPOINT (SIGTRAP) on the main thread in __UIApplicationEvaluateRuntimeIssueForNoSceneLifecycleAdoption. The app still used the older app-delegate life cycle and had not adopted the UIScene life cycle. Build 4 was the first build we linked against the iOS 27 SDK, and iOS 27 stops apps that have not adopted scenes at launch. Build 3 was linked against the iOS 26 SDK, which is why it did not crash.

WHAT WE CHANGED (build 5)
The app now adopts the UIScene life cycle. Info.plist declares UIApplicationSceneManifest, and the window is created by a UIWindowSceneDelegate when the scene connects instead of in application(_:didFinishLaunchingWithOptions:). Deep links and universal links are delivered through the scene delegate.

HOW WE VERIFIED IT
Build 4 caused UIKit to raise the scene-adoption runtime issue ("UIScene lifecycle will soon be required") at launch; build 5 no longer raises it, which confirms the scene life cycle is now adopted. We also installed build 5 from TestFlight on an iPhone 17 Pro Max and confirmed that it opens normally and that a locked chapter can be unlocked with the included coins.

WHAT TO TEST
Build 5 also contains the fixes from our previous reply, which you were unable to see because build 4 did not open:
- A new install starts with 90 coins. Open any story, read chapter 1 for free, tap Next, then tap "Unlock · 30 coins" — the chapter opens and the balance falls to 60. No ad is needed.
- If an ad cannot be shown, the app grants the coins anyway and says so.
- The featured story on the home screen, THE INVENTORY, is free in full, as is WHOSE SON ARE YOU.

There are no in-app purchases. Coins are earned free and cannot be bought.
```
