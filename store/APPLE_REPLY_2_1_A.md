# Trả lời Apple — Guideline 2.1(a), nút mở khoá không phản hồi

Submission ID: 9c5c5bb4-2b71-44aa-8bca-148e3dbd258a · Review date: 16/09/2026 · Bản 1.0 (3)
Thiết bị họ dùng: iPad Air 11-inch (M3), iPadOS 27.0

## Phải làm gì trước khi gửi

1. Tăng build number rồi build lại bằng EAS (bản sửa nằm ở commit `962cd0b`).
2. Nộp binary mới.
3. Dán bản dưới vào ô Reply.

Đừng gửi lời giải thích mà không kèm binary mới — lỗi này nằm trong mã, không sửa được từ server.

## Ô Reply (1.848 ký tự)

```text
Thank you for the detailed report — you found a real bug and we have fixed it.

WHAT WAS WRONG
The two buttons were not frozen; they ran correctly and showed an error. The defect was in our coin economy. A newly installed app started with a balance of 0 coins, and unlocking a chapter costs 30. The only way to reach 30 coins quickly was the rewarded ad. When our ad network returned no fill — which is common for a new AdMob account serving non-personalised ads — the balance stayed at 0 and no chapter could be unlocked. From the reviewer's seat the app simply went nowhere, which matches your description exactly. We reproduced it on an iPad Air 11-inch (M3) simulator running a clean install.

WHAT WE CHANGED (build 4)
1. A new install now starts with 90 coins — enough to unlock three chapters immediately. The core unlock feature can be used in full without ever watching an ad.
2. If an ad cannot be shown, we now grant the coins anyway and say so plainly: "No ad was available — we added +90 anyway." We still withhold the reward when the user closes an ad early, which remains the honest distinction.
3. Two weekdays previously had a free-coin path that summed to only 25 coins, below the 30 needed. Daily check-in on those days is now 15, so every day of the week has a working path to a chapter without ads.
4. The locked screen now always mentions the free ways to earn coins, and has a new "Earn coins for free" button linking to the Rewards tab. Previously it only pointed at the ad.

HOW TO VERIFY
Install the new build on a clean device and open any story. Chapter 1 is free. Tap Next to reach chapter 2, which is locked. The balance in the header shows 90. Tap "Unlock · 30 coins" — the chapter opens immediately and the balance becomes 60. No ad is required at any point.

Two stories, WHOSE SON ARE YOU and THE INVENTORY, also remain free in full, every chapter.

There are no in-app purchases in this app. Coins are earned free and cannot be bought.
```
