import { NativeModules, Platform, StyleSheet, Text, View } from 'react-native';

import { FontSize, FontWeight, Palette, Radius, Spacing } from '@/constants/theme';

/**
 * Wrapper cho react-native-google-mobile-ads với fallback an toàn.
 *
 * Trong Expo Go / web / khi chưa build native, native module không tồn tại.
 * Khi đó BannerAd hiển thị placeholder và showRewarded() trả thưởng ngay
 * để luồng xu vẫn test được. Khi có native build, dùng banner/rewarded thật
 * bằng unit ID lấy từ biến môi trường (fallback về Google TEST unit IDs).
 */

// Google test unit IDs — dùng khi chưa cấu hình biến môi trường.
const BANNER_TEST_ID = 'ca-app-pub-3940256099942544/6300978111';
const REWARDED_TEST_ID = 'ca-app-pub-3940256099942544/5224354917';

/**
 * Unit ID thật lấy từ biến môi trường EXPO_PUBLIC_* (Expo inline giá trị này
 * vào bundle lúc build, nên phải viết nguyên `process.env.EXPO_PUBLIC_X`,
 * không được destructure hay dựng chuỗi tên biến động).
 *
 * QUAN TRỌNG: trong AdMob, iOS và Android là HAI APP RIÊNG, mỗi app có bộ ad
 * unit riêng. Unit ID của app iOS KHÔNG phục vụ quảng cáo cho bản Android và
 * ngược lại — Google sẽ không trả quảng cáo. Vì vậy mỗi vị trí quảng cáo cần
 * hai biến, tách theo nền tảng.
 *
 * Thứ tự ưu tiên: biến theo nền tảng -> biến chung (nếu chỉ phát hành 1 nền
 * tảng) -> ID TEST của Google.
 */
const BANNER_UNIT_ID =
  Platform.select({
    ios: process.env.EXPO_PUBLIC_ADMOB_BANNER_ID_IOS,
    android: process.env.EXPO_PUBLIC_ADMOB_BANNER_ID_ANDROID,
  }) ||
  process.env.EXPO_PUBLIC_ADMOB_BANNER_ID ||
  BANNER_TEST_ID;

const REWARDED_UNIT_ID =
  Platform.select({
    ios: process.env.EXPO_PUBLIC_ADMOB_REWARDED_ID_IOS,
    android: process.env.EXPO_PUBLIC_ADMOB_REWARDED_ID_ANDROID,
  }) ||
  process.env.EXPO_PUBLIC_ADMOB_REWARDED_ID ||
  REWARDED_TEST_ID;

/** Tuỳ chọn gắn vào MỌI yêu cầu quảng cáo — xem NON_PERSONALIZED. */
type AdRequestOptions = { requestNonPersonalizedAdsOnly: boolean };

type AdsModule = {
  default: () => {
    initialize: () => Promise<unknown>;
    setRequestConfiguration?: (config: Record<string, unknown>) => Promise<unknown>;
  };
  BannerAd: React.ComponentType<{ unitId: string; size: string; requestOptions?: AdRequestOptions }>;
  BannerAdSize: Record<string, string>;
  RewardedAd: {
    createForAdRequest: (unitId: string, options?: AdRequestOptions) => RewardedAdInstance;
  };
  RewardedAdEventType: { LOADED: string; EARNED_REWARD: string };
  /** Sự kiện chung của mọi loại quảng cáo. Khai optional vì bản cũ của thư viện có thể thiếu. */
  AdEventType?: { ERROR: string; OPENED: string; CLOSED: string };
};

type RewardedAdInstance = {
  addAdEventListener: (type: string, listener: (payload?: unknown) => void) => () => void;
  load: () => void;
  show: () => void;
};

let adsModule: AdsModule | null = null;
let nativeAvailable = false;

// QUAN TRỌNG: chỉ coi là "có ads thật" khi native module ĐÃ được link.
// Package JS luôn nằm trong node_modules nên require() không throw trong Expo Go;
// vì vậy phải kiểm tra NativeModules trước, nếu không banner/rewarded thật sẽ crash.
const nativeModulePresent = Object.keys(NativeModules).some((k) =>
  k.startsWith('RNGoogleMobileAds'),
);

if (nativeModulePresent) {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const mod = require('react-native-google-mobile-ads') as AdsModule;
    if (mod && typeof mod.default === 'function' && mod.BannerAd) {
      adsModule = mod;
      // initialize; nếu thất bại vẫn không crash
      // Đặt cấu hình TRƯỚC initialize để lần lấy quảng cáo đầu tiên cũng đúng chế độ.
      const ads = mod.default();
      ads
        .setRequestConfiguration?.({
          maxAdContentRating: 'T',
          tagForChildDirectedTreatment: false,
          tagForUnderAgeOfConsent: false,
        })
        ?.catch(() => {});
      ads.initialize().catch(() => {});
      nativeAvailable = true;
    }
  } catch {
    adsModule = null;
    nativeAvailable = false;
  }
}

/**
 * App KHÔNG theo dõi người dùng qua các app/website khác, nên MỌI yêu cầu quảng cáo
 * đều ở chế độ không cá nhân hoá.
 *
 * Vì sao chọn hướng này: muốn quảng cáo cá nhân hoá trên iOS thì bắt buộc phải hiện
 * hộp thoại App Tracking Transparency (Guideline 5.1.2(i)). Bản trước khai
 * NSUserTrackingUsageDescription trong Info.plist mà KHÔNG hề gọi hộp thoại — hồ sơ
 * nói có theo dõi còn app thì không xin phép, đúng thứ Apple đánh trượt. Bỏ hẳn
 * tracking là cách gọn nhất: không cần ATT, không cần CMP, và bảng App Privacy khai
 * "Used for Tracking = No" cho mọi mục.
 *
 * Đánh đổi: doanh thu mỗi lượt hiển thị thấp hơn quảng cáo cá nhân hoá.
 */
const NON_PERSONALIZED: AdRequestOptions = { requestNonPersonalizedAdsOnly: true };

export const adsAvailable = nativeAvailable;

/** Banner ở đầu màn đọc. Placeholder khi không có native module. */
export function BannerAd() {
  if (adsModule && nativeAvailable) {
    const RealBanner = adsModule.BannerAd;
    const size = adsModule.BannerAdSize.ANCHORED_ADAPTIVE_BANNER ?? adsModule.BannerAdSize.BANNER;
    return (
      <View style={styles.bannerReal}>
        <RealBanner unitId={BANNER_UNIT_ID} size={size} requestOptions={NON_PERSONALIZED} />
      </View>
    );
  }
  return (
    <View style={styles.placeholder}>
      <Text style={styles.placeholderText}>Advertisement</Text>
    </View>
  );
}

export interface RewardedResult {
  rewarded: boolean;
  /**
   * true = không có quảng cáo để chiếu (bản build thiếu native module).
   * Khác hẳn `rewarded: false` do người dùng đóng sớm, nên nơi gọi phải báo khác
   * nhau — đổ lỗi "bạn chưa xem hết" khi thực ra app không chiếu được là sai.
   */
  unavailable?: boolean;
}

/** Chờ AdMob trả quảng cáo về. Không có hàng thì 'error' thường tới sớm hơn mốc này. */
const AD_LOAD_TIMEOUT_MS = 30_000;
/** Chặn cuối cho trường hợp quảng cáo đã mở nhưng không bao giờ báo đóng. */
const AD_WATCH_TIMEOUT_MS = 300_000;

/**
 * Hiện rewarded ad. Resolve { rewarded: true } khi nhận thưởng.
 * Fallback (không native): resolve ngay { rewarded: true }.
 *
 * Promise này BẮT BUỘC phải kết thúc ở mọi nhánh. Nơi gọi đều theo khuôn
 * `setBusy(true) ... finally setBusy(false)`, nên một lần không kết thúc là nút bấm
 * kẹt vĩnh viễn ở trạng thái đang quay, không cách nào thoát ngoài đổi màn hình.
 */
export function showRewarded(): Promise<RewardedResult> {
  if (!adsModule || !nativeAvailable) {
    // KHÔNG có native module (Expo Go, web, hoặc build thiếu). Chỉ bản dev mới được
    // cấp thưởng giả để thử luồng xu; bản phát hành phải TỪ CHỐI.
    //
    // Đây là đường DUY NHẤT có thể nhận xu mà không xem quảng cáo: nhánh thật bên
    // dưới chỉ đặt earned = true khi AdMob bắn EARNED_REWARD, tức người dùng đã xem
    // đủ lâu để được tính thưởng. Đóng sớm thì 'closed' trả về earned = false.
    return Promise.resolve(__DEV__ ? { rewarded: true } : { rewarded: false, unavailable: true });
  }

  return new Promise<RewardedResult>((resolve) => {
    const mod = adsModule as AdsModule;
    // Bản thư viện cũ có thể chưa xuất AdEventType -> dùng đúng chuỗi mà nó phát ra.
    const events = mod.AdEventType ?? { ERROR: 'error', OPENED: 'opened', CLOSED: 'closed' };

    let earned = false;
    let settled = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    const unsubs: (() => void)[] = [];

    const finish = (result: RewardedResult) => {
      if (settled) return;
      settled = true;
      if (timer) clearTimeout(timer);
      // Gỡ listener ở MỌI lối ra. Bản trước chỉ gỡ trong nhánh 'closed' nên khi
      // quảng cáo lỗi thì listener treo lại mãi.
      for (const off of unsubs) {
        try {
          off();
        } catch {
          // đã gỡ rồi — bỏ qua
        }
      }
      resolve(result);
    };

    /** Đặt lại mốc chờ; hết giờ thì trả về đúng trạng thái thưởng đang có. */
    const arm = (ms: number) => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => finish({ rewarded: earned }), ms);
    };

    try {
      const rewarded = mod.RewardedAd.createForAdRequest(REWARDED_UNIT_ID, NON_PERSONALIZED);

      const on = (type: string, listener: (payload?: unknown) => void) => {
        const off = rewarded.addAdEventListener(type, listener);
        if (typeof off === 'function') unsubs.push(off);
      };

      on(mod.RewardedAdEventType.LOADED, () => {
        // Đã có hàng -> chắc chắn sẽ hiện, nới ngay mốc chờ. Nếu vẫn để mốc 30s của
        // lúc tải mà mạng chậm làm tải mất 29s, mốc đó sẽ hết hạn ngay giữa lúc
        // quảng cáo vừa mở -> trả về "không nhận thưởng" trong khi người dùng đang
        // ngồi xem, và lát nữa 'closed' tới thì Promise đã chốt, xu mất trắng.
        arm(AD_WATCH_TIMEOUT_MS);
        try {
          rewarded.show();
        } catch {
          finish({ rewarded: false });
        }
      });

      on(mod.RewardedAdEventType.EARNED_REWARD, () => {
        earned = true;
      });

      // Quảng cáo đã hiện: người dùng đang ngồi xem, nới mốc chờ ra.
      on(events.OPENED, () => arm(AD_WATCH_TIMEOUT_MS));

      on(events.CLOSED, () => finish({ rewarded: earned }));

      // CHỖ BẢN CŨ THIẾU: hết hàng để trả (no fill), rớt mạng, sai unit ID... AdMob
      // chỉ bắn 'error' và KHÔNG bao giờ bắn 'closed'. Không nghe sự kiện này thì
      // Promise không bao giờ kết thúc.
      on(events.ERROR, () => finish({ rewarded: false }));

      arm(AD_LOAD_TIMEOUT_MS);
      rewarded.load();
    } catch {
      finish({ rewarded: false });
    }
  });
}

const styles = StyleSheet.create({
  bannerReal: {
    alignItems: 'center',
    // banner thật: nền trắng, tách khỏi nội dung bằng viền mảnh dưới
    backgroundColor: Palette.bgDeep,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  placeholder: {
    height: 50,
    alignItems: 'center',
    justifyContent: 'center',
    // nền sáng: ô quảng cáo là khối kem nhạt, viền mảnh, chữ mờ
    backgroundColor: Palette.surfaceAlt,
    borderRadius: Radius.sm,
    marginHorizontal: Spacing.sm,
    marginVertical: Spacing.xs,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  placeholderText: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    letterSpacing: 1.2,
  },
});
