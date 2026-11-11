/**
 * Google Analytics cho app (Firebase Analytics, module native modules/stories-analytics).
 *
 * Mục đích chính: cho Google Ads đếm được LƯỢT CÀI app từ quảng cáo. Firebase tự ghi
 * `first_open` (lần mở đầu tiên = một lượt cài) và `session_start`, Google Ads nhập
 * `first_open` làm chuyển đổi. Các sự kiện dưới đây cho biết người cài có đọc thật không.
 *
 * Không gửi gì nhận dạng người dùng: không user ID, không IDFA (pod không kèm AdSupport),
 * tín hiệu cá nhân hoá quảng cáo tắt sẵn (app.config.js). Chỉ có app instance ID của Firebase.
 *
 * Web, Expo Go hoặc build thiếu module thì mọi hàm thành no-op.
 *
 * Sự kiện:
 * - `screen_view`          màn đang xem (theo route, không kèm id truyện)
 * - `read_chapter`         mở được một chương để đọc {story_id, chapter}
 * - `rewarded_ad_watched`  xem hết quảng cáo có thưởng {placement: reader|rewards|audio}
 */
import { NativeModule, requireOptionalNativeModule } from 'expo';

type Params = Record<string, string | number>;

declare class StoriesAnalyticsModule extends NativeModule {
  logEvent(name: string, params?: Params | null): void;
  logScreenView(screenName: string): void;
}

const Native = requireOptionalNativeModule<StoriesAnalyticsModule>('StoriesAnalytics');

/** Luật tên của Google Analytics: chữ/số/gạch dưới, bắt đầu bằng chữ, tối đa 40 ký tự. */
const NAME_RE = /^[a-zA-Z][a-zA-Z0-9_]{0,39}$/;

export function logEvent(name: string, params?: Params): void {
  if (!Native || !NAME_RE.test(name)) return;
  try {
    Native.logEvent(name, params ?? null);
  } catch {
    // Đo lường không bao giờ được làm hỏng thao tác của người dùng.
  }
}

export function logScreenView(screenName: string): void {
  if (!Native || !screenName) return;
  try {
    Native.logScreenView(screenName.slice(0, 100));
  } catch {
    // như trên
  }
}
