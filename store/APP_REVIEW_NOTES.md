# Ô App Review Information → Notes (giới hạn 4.000 ký tự)

Ô Notes trong App Store Connect chặn cứng ở 4.000 ký tự. Phần A trong
APPLE_REVIEW_REPLY.md dài 7.880 ký tự — đó là văn bản đầy đủ để dán vào ô
**Reply** (không giới hạn) khi trả lời thư từ chối. Bản dưới đây là bản rút gọn
cho ô **Notes**, giữ nguyên cả 6 mục Apple hỏi.

Nhớ thay [PASTE LINK] bằng link video demo trước khi dán.

```text
NO ACCOUNT, NO PURCHASES, NO TRACKING

1. SCREEN RECORDING
Attached / at: [PASTE LINK]
- Account registration, login, deletion: NOT APPLICABLE. Stories has no accounts of any kind — no sign-up, no sign-in, no profile. It is fully usable from first launch. Coin balance, unlocked chapters, saved stories and reading settings live only in local device storage (AsyncStorage) and are never sent to us. Nothing can be created, so nothing can be deleted.
- User-generated content: NOT APPLICABLE. Users cannot post, comment, upload, message or share. No profiles, no social features, so reporting/blocking is not required.
- Paid content: there are NO in-app purchases and NO subscriptions. Nothing can be bought with money. Chapter 1 of every story is free. Later chapters cost 30 "coins", an in-app virtual item earned FREE ONLY by (a) daily check-in, (b) reading-time milestones, (c) watching a Google AdMob rewarded video to completion (90 coins, so one ad unlocks three chapters). Coins cannot be purchased or transferred and have no monetary value.
- Tracking: NOT APPLICABLE. The app never requests App Tracking Transparency, never reads the IDFA, and contains no NSUserTrackingUsageDescription. Ads are requested non-personalised. Our App Privacy answers say "used for tracking: No", which matches the binary.

2. PURPOSE AND AUDIENCE
Stories is a free short-fiction reading app: serialised English drama about people who are underestimated or betrayed. Each story is 3-10 short chapters sized for a few minutes on a phone; some carry narrated audio. Audience: adult readers of popular commercial fiction, roughly 18-45. No explicit sex, graphic violence or strong profanity. No user interaction. Not aimed at children. Business model: advertising only.

3. HOW TO ACCESS EVERY FEATURE
No credentials needed — there is no account system. Just launch the app.
1) Home opens on a shelf of stories.
2) Tap any story for its synopsis, genres and chapter list.
3) Tap "Read now": chapter 1 is free and opens immediately. A banner ad shows at the top. The "Aa" button opens reading settings.
4) Audio: narration exists for 4 of our 10 stories while we produce the rest; the "Listen" control is disabled on the others. THE SIXTY-DOLLAR SUIT has audio for all 10 chapters — open it and tap the headphone button. Audio streams as MP3 from our own server.
5) Unlock a chapter: in THE SIXTY-DOLLAR SUIT tap chapter 2. It costs 30 coins. A new install has 0 coins, so tap "Watch ad - +90 coins" and watch the rewarded video TO THE END (closing early grants nothing, by Google's design). Then tap "Unlock" and the chapter opens.
6) Rewards tab shows the three ways to earn coins. Library tab shows saved stories.

4. EXTERNAL SERVICES
At runtime the app contacts only two:
- api.tunastory.com, our own VPS: read-only REST API serving story text, covers and MP3s. GET only. No user data or device identifier is ever sent.
- Google AdMob: banner and rewarded ads, requested non-personalised.
No analytics, attribution, crash-reporting or social SDKs — no Firebase, Facebook, Sentry, AppsFlyer, Adjust or Segment. No over-the-air code updates.
Content production happens on our server before publication, never in the app: OpenAI gpt-4o-mini-tts for narration, gpt-image-1 for cover art. All story text is original fiction created by us for this app with the assistance of AI writing tools and reviewed by us. Narration and cover art are generated from our own text and owned by us. No licensed or third-party works, no fan fiction, no user submissions, no scraped content.

5. REGIONAL DIFFERENCES
None. Identical everywhere: no geographic gating, no region-specific content, pricing or feature flags. English only, worldwide. Only AdMob varies the ads it serves by region, handled entirely by Google.

6. REGULATED INDUSTRY / THIRD-PARTY MATERIAL
Not a financial, medical, gambling, health, dating or government service; no regulated advice. No third-party protected material, so no licences are required.```
