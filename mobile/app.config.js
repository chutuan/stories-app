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

module.exports = {
  expo: {
    name: 'Stories',
    slug: 'stories',
    // Phiên bản hiển thị cho người dùng. Tăng thủ công khi phát hành bản mới.
    // ios.buildNumber / android.versionCode KHÔNG khai ở đây: EAS tự quản lý
    // và tự tăng (cli.appVersionSource = "remote" + autoIncrement trong eas.json).
    version: '1.0.0',
    orientation: 'portrait',
    icon: './assets/images/icon.png',
    scheme: 'stories',
    userInterfaceStyle: 'light',
    ios: {
      bundleIdentifier: 'com.chutuan.stories',
      supportsTablet: false,
      infoPlist: {
        // Bắt buộc khi dùng AdMob trên iOS 14+: App Tracking Transparency.
        // Thiếu chuỗi này App Store sẽ từ chối build có SDK quảng cáo.
        NSUserTrackingUsageDescription:
          'This identifier will be used to deliver personalized ads and to measure how well they perform.',
      },
    },
    android: {
      package: 'com.chutuan.stories',
      adaptiveIcon: {
        backgroundColor: '#F2703A',
        foregroundImage: './assets/images/android-icon-foreground.png',
        backgroundImage: './assets/images/android-icon-background.png',
        monochromeImage: './assets/images/android-icon-monochrome.png',
      },
      predictiveBackGestureEnabled: false,
    },
    web: {
      output: 'static',
      favicon: './assets/images/favicon.png',
    },
    plugins: [
      'expo-router',
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
