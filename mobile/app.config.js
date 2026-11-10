// @ts-check
/**
 * Cấu hình động của Expo (thay cho app.json).
 *
 * Dùng file JS thay vì JSON để đọc được biến môi trường lúc build:
 * - EAS Build nạp biến từ `eas.json` (mục `env` của từng profile) trước khi
 *   đánh giá file này, nên mỗi profile ra một cấu hình khác nhau.
 * - Khi chạy local (`npx expo start`), biến lấy từ shell hoặc file `.env`.
 *
 * LƯU Ý: file app.json đã bị XOÁ. Đây là nguồn cấu hình DUY NHẤT.
 */

// --- App ID TEST của Google (dùng khi chưa có biến môi trường) ---
// Đây là App ID mẫu công khai của AdMob, KHÔNG sinh doanh thu.
const ADMOB_ANDROID_APP_ID_TEST = 'ca-app-pub-3940256099942544~3347511713';
const ADMOB_IOS_APP_ID_TEST = 'ca-app-pub-3940256099942544~1458002511';

// API mặc định cho môi trường dev (backend Laravel chạy `php artisan serve --port=8001`).
const DEFAULT_API_URL = 'http://localhost:8001/api';

// Kotlin cho Android. Phải >= bản Kotlin mà play-services-ads (react-native-google-mobile-ads)
// dùng để biên dịch, hiện là 2.3.0. Dùng cho cả expo-build-properties lẫn plugin ghim KGP.
const KOTLIN_VERSION = '2.3.0';

// google-services.json của Firebase project "TunaStory" (tunastory-ed1ef), cần cho thông
// báo đẩy Android (Expo push đi qua FCM). File này KHÔNG commit:
// - EAS Build: lấy từ biến môi trường kiểu file GOOGLE_SERVICES_JSON (`eas env`,
//   môi trường production + preview), EAS gán biến này thành đường dẫn file tạm.
// - Build local: đặt file `google-services.json` cạnh app.config.js.
// Thiếu cả hai thì app vẫn build được, chỉ là Android không nhận được thông báo.
const fs = require('fs');
const path = require('path');
const GOOGLE_SERVICES_FILE =
  process.env.GOOGLE_SERVICES_JSON ??
  (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : undefined);

module.exports = {
  expo: {
    name: 'Stories',
    slug: 'stories',
    // Phiên bản hiển thị cho người dùng. Tăng thủ công khi phát hành bản mới.
    // ios.buildNumber / android.versionCode KHÔNG khai ở đây: EAS tự quản lý
    // và tự tăng (cli.appVersionSource = "remote" + autoIncrement trong eas.json).
    version: '1.0.5',
    orientation: 'portrait',
    icon: './assets/images/icon.png',
    scheme: 'stories',
    userInterfaceStyle: 'light',
    ios: {
      bundleIdentifier: 'com.chutuan.stories',
      // Team ký app. KHÔNG phải bí mật — nó nằm sẵn trong mọi bản app đã ký.
      // Phải khai ở đây vì `expo prebuild --clean` sinh lại project mà KHÔNG giữ
      // DEVELOPMENT_TEAM đặt tay trong Xcode, và archive lúc đó hỏng với
      // "Signing for Stories requires a development team" (đã dính 27/09/2026).
      // EAS tự quản lý chứng chỉ nên dòng này không ảnh hưởng build trên EAS.
      appleTeamId: '22W729ZRX2',
      // Bản iPad native (từ 01/11/2026): bố cục co theo bề ngang cửa sổ, xem src/lib/responsive.ts.
      // requireFullScreen để mặc định false -> Expo tự khai đủ 4 hướng xoay cho iPad (Apple
      // bắt buộc khi hỗ trợ Split View, ITMS-90474); iPhone vẫn khoá dọc theo `orientation`.
      // KHÔNG đổi lại false: bản đã phát hành có iPad thì bản cập nhật không được bỏ iPad.
      supportsTablet: true,
      infoPlist: {
        // Tuân thủ xuất khẩu mã hoá: app chỉ dùng HTTPS/TLS tiêu chuẩn, thuộc diện
        // miễn trừ. Khai false để App Store Connect không hỏi lại mỗi lần nộp build.
        ITSAppUsesNonExemptEncryption: false,
        // CỐ Ý KHÔNG khai NSUserTrackingUsageDescription. App không gọi
        // requestTrackingAuthorization, không đọc IDFA, và quảng cáo chạy
        // requestNonPersonalizedAdsOnly. Bản 1.0.0 (2) từng khai chuỗi này mà
        // không bao giờ hiện hộp ATT, Apple chặn nộp và bắt hoặc khai App Privacy
        // "used for tracking = Yes" hoặc nộp binary mới. Thêm lại chuỗi này là
        // quay về đúng chỗ bị chặn — chỉ thêm nếu THẬT SỰ gọi ATT.
      },
    },
    android: {
      package: 'com.chutuan.stories',
      googleServicesFile: GOOGLE_SERVICES_FILE,
      adaptiveIcon: {
        backgroundColor: '#F2703A',
        foregroundImage: './assets/images/android-icon-foreground.png',
        backgroundImage: './assets/images/android-icon-background.png',
        monochromeImage: './assets/images/android-icon-monochrome.png',
      },
      predictiveBackGestureEnabled: false,
      // Gỡ các quyền app không dùng nhưng template/plugin tự thêm vào manifest. Google Play
      // soi kỹ quyền đặc biệt, và người dùng thấy danh sách quyền ở trang cài đặt.
      // - WRITE_SETTINGS: expo-brightness thêm cho setSystemBrightnessAsync; app chỉ dùng
      //   setBrightnessAsync (độ sáng cửa sổ app), không cần quyền.
      // - SYSTEM_ALERT_WINDOW: chỉ dev menu cần.
      // - Bộ nhớ ngoài: app không đọc/ghi file của người dùng.
      blockedPermissions: [
        'android.permission.WRITE_SETTINGS',
        'android.permission.SYSTEM_ALERT_WINDOW',
        'android.permission.READ_EXTERNAL_STORAGE',
        'android.permission.WRITE_EXTERNAL_STORAGE',
      ],
    },
    web: {
      output: 'static',
      favicon: './assets/images/favicon.png',
    },
    plugins: [
      'expo-router',
      [
        // Nâng Kotlin lên 2.3.0. Lý do: react-native-google-mobile-ads 16.4 kéo về
        // play-services-ads 25.4.0, thư viện này biên dịch bằng Kotlin metadata 2.3.0
        // trong khi Expo SDK 57 mặc định compile bằng 2.1.0 -> task
        // :react-native-google-mobile-ads:compileReleaseKotlin fail với
        // "Module was compiled with an incompatible version of Kotlin".
        'expo-build-properties',
        {
          android: {
            kotlinVersion: KOTLIN_VERSION,
          },
          ios: {
            // Adopt UIScene lifecycle. Bản 1.0.0 (4) bị Apple đánh trượt 2.1(a) vì
            // crash ngay lúc mở app trên iOS/iPadOS 27: UIKit trap tại
            // __UIApplicationEvaluateRuntimeIssueForNoSceneLifecycleAdoption
            // (EXC_BREAKPOINT, main thread) — app đóng bằng Xcode 27 mà không adopt
            // scene lifecycle thì iOS 27 KHÔNG cho chạy, không phải cảnh báo nữa.
            //
            // Cờ này bắt expo-build-properties sửa AppDelegate thành
            // `ExpoAppDelegate, ExpoReactNativeFactoryProvider`, bỏ đoạn tự tạo
            // UIWindow, và chèn UIApplicationSceneManifest trỏ tới
            // EXExpoAppSceneDelegate. ĐÒI expo >= 57.0.23 (bản đầu tiên ship
            // ExpoAppSceneDelegate) — thấp hơn thì prebuild throw, nên đừng hạ
            // "expo" trong package.json xuống dưới mức đó.
            //
            // Plugin chỉ vá được AppDelegate MẶC ĐỊNH của template SDK 57. Nếu sau
            // này eject và sửa tay AppDelegate.swift, plugin từ chối ghi đè và phải
            // tự adopt scene. Lên SDK 58 thì template tự có, cờ này thành no-op và
            // prebuild sẽ nhắc gỡ.
            enableSceneSupport: true,
          },
        },
      ],
      // kotlinVersion ở trên KHÔNG đổi trình biên dịch Kotlin thật; plugin này mới đổi.
      // Xem giải thích trong plugins/with-kotlin-gradle-plugin.js.
      ['./plugins/with-kotlin-gradle-plugin', { version: KOTLIN_VERSION }],
      [
        'expo-splash-screen',
        {
          backgroundColor: '#FDF8F4',
          image: './assets/images/splash-icon.png',
          imageWidth: 180,
        },
      ],
      [
        'react-native-google-mobile-ads',
        {
          // Plugin CHỈ đọc camelCase; cặp snake_case cũ đã bỏ vì thừa.
          androidAppId:
            process.env.EXPO_PUBLIC_ADMOB_ANDROID_APP_ID || ADMOB_ANDROID_APP_ID_TEST,
          iosAppId: process.env.EXPO_PUBLIC_ADMOB_IOS_APP_ID || ADMOB_IOS_APP_ID_TEST,
        },
      ],
      [
        'expo-audio',
        {
          // App CHỈ phát audio, không ghi âm. Mặc định plugin thêm quyền
          // android.permission.RECORD_AUDIO -> Google Play bắt giải trình và
          // người dùng thấy app xin quyền micro vô cớ. Tắt đi.
          recordAudioAndroid: false,
          // Cùng lý do, phía iOS: mặc định plugin chèn
          // NSMicrophoneUsageDescription vào Info.plist. Khai quyền không dùng
          // là đúng loại lỗi Apple đánh trượt.
          microphonePermission: false,
          // Màn nghe đặt shouldPlayInBackground: false và dừng phát khi rời màn,
          // nên app KHÔNG phát nền. Để mặc định (true) thì plugin thêm
          // UIBackgroundModes: ['audio'] — khai một capability không dùng, rủi ro
          // Guideline 2.5.4. Bật lại ĐỒNG THỜI với shouldPlayInBackground nếu sau
          // này làm phát nền thật.
          enableBackgroundPlayback: false,
        },
      ],
      // Thông báo "truyện mới hôm nay" (src/store/notifications.tsx). Plugin thêm
      // entitlement aps-environment; KHÔNG bật enableBackgroundRemoteNotifications —
      // thông báo chỉ hiện banner, không cần đánh thức app chạy nền (Guideline 2.5.4).
      // ios/ bị gitignore và không prebuild lại: entitlement đã thêm tay vào
      // ios/Stories/Stories.entitlements — prebuild lần sau plugin này sinh lại đúng thế.
      [
        'expo-notifications',
        {
          // Android vẽ icon thông báo chỉ theo kênh alpha (trắng trên nền trong suốt);
          // để mặc định nó lấy icon app -> hiện thành ô vuông trắng. Đây là quyển sách
          // cắt từ android-icon-monochrome.png, tô màu cam của app.
          icon: './assets/images/notification-icon.png',
          color: '#F2703A',
          // Trùng id kênh tạo trong src/store/notifications.tsx ("New stories").
          defaultChannel: 'default',
        },
      ],
    ],
    extra: {
      // URL gốc của REST API. iOS chặn HTTP thường (App Transport Security)
      // nên bản production BẮT BUỘC dùng https://
      apiUrl: process.env.EXPO_PUBLIC_API_URL || DEFAULT_API_URL,
      eas: {
        // Project trên Expo, tài khoản `tunachu` (tạo bằng `eas init`).
        // Vì đây là dynamic config nên EAS CLI không tự ghi được — phải dán tay.
        // Biến EAS_PROJECT_ID cho phép ghi đè khi cần trỏ sang project khác.
        projectId: process.env.EAS_PROJECT_ID || '01451d8d-4073-4cf4-8e05-2d53c0e761f3',
      },
    },
    experiments: {
      typedRoutes: true,
      reactCompiler: true,
    },
  },
};
