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

Câu "tested on a physical device" trong thư là KHẲNG ĐỊNH. Nếu chưa làm bước 2 thì xoá câu
đó đi, đừng gửi một điều chưa làm.

## Ô Reply

```text
Thank you for the crash logs — they identified the problem precisely, and it is fixed in build 5.

WHAT WAS WRONG
Both logs show EXC_BREAKPOINT (SIGTRAP) on the main thread in __UIApplicationEvaluateRuntimeIssueForNoSceneLifecycleAdoption. The app still used the older app-delegate life cycle and had not adopted the UIScene life cycle. Build 4 was the first build we linked against the iOS 27 SDK, and iOS 27 stops apps that have not adopted scenes at launch. Build 3 was linked against the iOS 26 SDK, which is why it did not crash.

WHAT WE CHANGED (build 5)
The app now adopts the UIScene life cycle. Info.plist declares UIApplicationSceneManifest, and the window is created by a UIWindowSceneDelegate when the scene connects instead of in application(_:didFinishLaunchingWithOptions:). Deep links and universal links are delivered through the scene delegate.

HOW WE VERIFIED IT
We confirmed that iOS 27 no longer raises the scene-adoption runtime issue for build 5, and we tested build 5 on a physical device running iOS 27: the app opens normally, and a locked chapter can be unlocked with the included coins.

WHAT TO TEST
Build 5 also contains the fixes from our previous reply, which you were unable to see because build 4 did not open:
- A new install starts with 90 coins. Open any story, read chapter 1 for free, tap Next, then tap "Unlock · 30 coins" — the chapter opens and the balance falls to 60. No ad is needed.
- If an ad cannot be shown, the app grants the coins anyway and says so.
- The featured story on the home screen, THE INVENTORY, is free in full, as is WHOSE SON ARE YOU.

There are no in-app purchases. Coins are earned free and cannot be bought.
```
