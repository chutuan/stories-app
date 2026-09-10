# Văn bản gửi Apple — CẢ HAI ô đều giới hạn 4.000 ký tự

App Store Connect chặn cứng 4.000 ký tự ở **cả** ô *App Review Information → Notes*
**lẫn** ô *Reply* khi trả lời thư từ chối. Vì vậy phần A trong APPLE_REVIEW_REPLY.md
(8.788 ký tự) KHÔNG dán thẳng được vào đâu cả — nó là bản đầy đủ để tra cứu, còn hai
bản dưới đây mới là bản dán.

Cả hai đều trả lời đủ sáu mục Apple hỏi và đã điền sẵn link video.

## Ô Reply — dán bản này (3.998 ký tự)

```text
Answers to all six items below, plus a screen recording made on a real iPhone.

1. SCREEN RECORDING
Video: https://drive.google.com/file/d/1Rb5-UFqJYZWWDgwKTFWrmV44Vaw9PVY7/view
- Account registration, login, deletion: NOT APPLICABLE. Stories has no accounts of any kind and is fully usable from first launch. Coin balance, unlocked chapters, saved stories and reading settings live only in local device storage, never sent to us. Nothing is created, so nothing can be deleted.
- User-generated content: NOT APPLICABLE. Users cannot post, comment, upload, message or share. No profiles or social features, so reporting/blocking does not apply.
- Paid content: NO in-app purchases, NO subscriptions. Nothing is bought with money. Two stories — WHOSE SON ARE YOU and THE INVENTORY — are free in full, every chapter, narrated. Elsewhere chapter 1 is free and later chapters cost 30 "coins", a virtual item earned FREE ONLY. Coins cannot be bought or transferred and have no monetary value.
- Tracking: NOT APPLICABLE. No App Tracking Transparency request, no IDFA access, no NSUserTrackingUsageDescription in the binary. Ads are non-personalised. Our App Privacy answers say "used for tracking: No", matching the binary.

2. PURPOSE AND AUDIENCE
A free short-fiction reading app: serialised English drama about people who are underestimated or betrayed. Stories run 3-10 short chapters, a few minutes each; some are narrated. Audience: adult readers of commercial fiction, 18-45. No explicit sex, graphic violence or strong profanity. No user interaction. Not for children. Business model: advertising only.

3. HOW TO ACCESS EVERY FEATURE
No credentials needed — there is no account system. Just launch the app.
1) Home opens on a shelf of stories; tap one for its synopsis and chapter list.
2) READ A WHOLE STORY FREE: open WHOSE SON ARE YOU (3 chapters) or THE INVENTORY (5 chapters), or use the "Free" tab on Home. Every chapter opens at once, no coins.
3) AUDIO: both stories above are narrated in full. Tap the headphone button in the reading screen header. Audio streams as MP3 from our server.
4) READING SETTINGS: the "Aa" button opens font size, spacing, brightness and page themes.
5) LOCKED CHAPTERS: open another story, tap chapter 2 for the unlock screen (30 coins). Three free ways to earn coins:
   a) Daily check-in: +15 coins, Rewards tab.
   b) Reading milestones: +15 after 10 minutes, then +25, +40, +60. Check-in plus the 10-minute milestone gives 30 coins — one chapter unlocked WITHOUT any ad.
   c) Rewarded video: +90 coins, watched to the end.
   PLEASE NOTE: ads may not display during review. Our AdMob account still lists this app as "review required", which Google only completes once an app is live on the App Store, so fill is currently zero. The app handles that correctly, showing "Ads are not available right now". Paths (a), (b) and the two free stories work regardless, so every feature stays reachable.

4. EXTERNAL SERVICES
Two only, at runtime:
- api.tunastory.com, our own VPS: read-only REST API serving story text, covers and MP3s. GET only. No user data or device identifier is ever sent.
- Google AdMob: banner and rewarded ads, non-personalised.
No analytics, attribution, crash-reporting or social SDKs of any kind. No over-the-air code updates.
Content is produced on our server before publication, never in the app: OpenAI gpt-4o-mini-tts for narration, gpt-image-1 for covers. All story text is original fiction written for this app with AI assistance and reviewed by us; narration and covers derive from our own text. No licensed or third-party works, no fan fiction, no user submissions, no scraped content.

5. REGIONAL DIFFERENCES
None. Identical everywhere: no geographic gating, no region-specific content, pricing or feature flags. English only.

6. REGULATED INDUSTRY / THIRD-PARTY MATERIAL
Not a financial, medical, gambling, health, dating or government service; no regulated advice. No third-party protected material, so no licences apply.
```

## Ô App Review Information → Notes — dán bản này (3.983 ký tự)

```text
NO ACCOUNT, NO PURCHASES, NO TRACKING
1. SCREEN RECORDING
Video: https://drive.google.com/file/d/1Rb5-UFqJYZWWDgwKTFWrmV44Vaw9PVY7/view
- Account registration, login, deletion: NOT APPLICABLE. Stories has no accounts of any kind and is fully usable from first launch. Coin balance, unlocked chapters, saved stories and reading settings live only in local device storage, never sent to us. Nothing is created, so nothing can be deleted.
- User-generated content: NOT APPLICABLE. Users cannot post, comment, upload, message or share. No profiles or social features, so reporting/blocking does not apply.
- Paid content: NO in-app purchases, NO subscriptions. Nothing is bought with money. Two stories — WHOSE SON ARE YOU and THE INVENTORY — are completely free, every chapter, with narrated audio. Elsewhere chapter 1 is free and later chapters cost 30 "coins", a virtual item earned FREE ONLY. Coins cannot be bought or transferred and have no monetary value.
- Tracking: NOT APPLICABLE. No App Tracking Transparency request, no IDFA access, no NSUserTrackingUsageDescription in the binary. Ads are non-personalised. Our App Privacy answers say "used for tracking: No", matching the binary.

2. PURPOSE AND AUDIENCE
A free short-fiction reading app: serialised English drama about people who are underestimated or betrayed. Stories run 3-10 short chapters, a few minutes each; some are narrated. Audience: adult readers of commercial fiction, 18-45. No explicit sex, graphic violence or strong profanity. No user interaction. Not for children. Business model: advertising only.

3. HOW TO ACCESS EVERY FEATURE
No credentials needed — there is no account system. Just launch the app.
1) Home opens on a shelf of stories; tap one for its synopsis and chapter list.
2) READ A WHOLE STORY FREE: open WHOSE SON ARE YOU (3 chapters) or THE INVENTORY (5 chapters), or use the "Free" tab on Home. Every chapter opens at once, no coins.
3) AUDIO: both stories above are narrated in full. Tap the headphone button in the reading screen header. Audio streams as MP3 from our server.
4) READING SETTINGS: the "Aa" button opens font size, spacing, brightness and page themes.
5) LOCKED CHAPTERS: open another story, tap chapter 2 for the unlock screen (30 coins). Three free ways to earn coins:
   a) Daily check-in: +15 coins, Rewards tab.
   b) Reading milestones: +15 after 10 minutes, then +25, +40, +60. Check-in plus the 10-minute milestone gives 30 coins — one chapter unlocked WITHOUT any ad.
   c) Rewarded video: +90 coins, watched to the end.
   PLEASE NOTE: ads may not display during review. Our AdMob account still lists this app as "review required", which Google only completes once an app is live on the App Store, so fill is currently zero. The app handles that correctly, showing "Ads are not available right now". Paths (a), (b) and the two free stories work regardless, so every feature stays reachable.

4. EXTERNAL SERVICES
Two only, at runtime:
- api.tunastory.com, our own VPS: read-only REST API serving story text, covers and MP3s. GET only. No user data or device identifier is ever sent.
- Google AdMob: banner and rewarded ads, non-personalised.
No analytics, attribution, crash-reporting or social SDKs of any kind. No over-the-air code updates.
Content is produced on our server before publication, never in the app: OpenAI gpt-4o-mini-tts for narration, gpt-image-1 for covers. All story text is original fiction written for this app with AI assistance and reviewed by us; narration and covers derive from our own text and are ours. No licensed or third-party works, no fan fiction, no user submissions, no scraped content.

5. REGIONAL DIFFERENCES
None. Identical everywhere: no geographic gating, no region-specific content, pricing or feature flags. English only.

6. REGULATED INDUSTRY / THIRD-PARTY MATERIAL
Not a financial, medical, gambling, health, dating or government service; no regulated advice. No third-party protected material, so no licences apply.
```
