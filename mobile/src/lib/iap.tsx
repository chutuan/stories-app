import { requireOptionalNativeModule } from 'expo-modules-core';
import { Platform } from 'react-native';

/**
 * Bọc expo-iap cho gói đăng ký hằng tháng, có đường lui an toàn.
 *
 * Giống lib/ads.tsx: trong Expo Go / trên web / khi chưa build native thì native
 * module KHÔNG tồn tại. Khác một điểm rất quan trọng với quảng cáo — ở đây đường
 * lui KHÔNG cấp quyền: mất native module trong bản phát hành mà vẫn "tặng" gói
 * đăng ký thì ai cũng đọc miễn phí. Chỉ bản dev (`__DEV__`) mới được cấp giả lập
 * để còn thử luồng giao diện.
 */

/**
 * Mã sản phẩm khai trong App Store Connect / Google Play.
 * Expo nội tuyến `process.env.EXPO_PUBLIC_*` vào bundle lúc build nên phải viết
 * nguyên biểu thức, không destructure và không ghép tên biến động.
 *
 * iOS và Android là HAI sản phẩm riêng; mặc định dùng chung một mã cho gọn vì
 * cả hai chợ đều chấp nhận định danh ngược tên miền.
 */
export const SUBSCRIPTION_SKU =
  Platform.select({
    ios: process.env.EXPO_PUBLIC_IAP_SUBSCRIPTION_ID_IOS,
    android: process.env.EXPO_PUBLIC_IAP_SUBSCRIPTION_ID_ANDROID,
  }) ||
  process.env.EXPO_PUBLIC_IAP_SUBSCRIPTION_ID ||
  'com.chutuan.stories.premium.monthly';

/** Giá hiển thị khi chưa hỏi được chợ (chưa build native, hoặc mạng lỗi). */
export const FALLBACK_PRICE_LABEL = '$9.99';

type StorePurchase = { productId?: string; [key: string]: unknown };

type StoreProduct = {
  id?: string;
  productId?: string;
  displayPrice?: string;
  price?: number;
  currency?: string;
};

type IapModule = {
  initConnection: () => Promise<unknown>;
  endConnection: () => Promise<unknown>;
  fetchProducts: (req: { skus: string[]; type?: 'in-app' | 'subs' | 'all' }) => Promise<unknown>;
  requestPurchase: (args: {
    request: { apple?: { sku: string }; google?: { skus: string[] } };
    type: 'subs' | 'in-app';
  }) => Promise<unknown>;
  getActiveSubscriptions: (ids?: string[]) => Promise<{ isActive?: boolean; productId?: string }[]>;
  finishTransaction: (args: { purchase: StorePurchase; isConsumable?: boolean }) => Promise<unknown>;
  purchaseUpdatedListener: (cb: (p: StorePurchase) => void) => { remove: () => void };
  purchaseErrorListener: (cb: (e: { message?: string; code?: string }) => void) => {
    remove: () => void;
  };
};

// Module native của expo-iap tên là "ExpoIap". requireOptionalNativeModule trả null
// khi thiếu, khác requireNativeModule (ném lỗi) mà chính expo-iap gọi bên trong.
const nativeModulePresent = requireOptionalNativeModule('ExpoIap') != null;

let iapModule: IapModule | null = null;

if (nativeModulePresent) {
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const mod = require('expo-iap') as IapModule;
    if (mod && typeof mod.requestPurchase === 'function') {
      iapModule = mod;
    }
  } catch {
    iapModule = null;
  }
}

/** true khi mua hàng THẬT dùng được (đã build native và chợ sẵn sàng). */
export const iapAvailable = iapModule != null;

let connected = false;

/** Mở kết nối tới chợ; gọi nhiều lần cũng chỉ nối một lần. */
export async function connectStore(): Promise<boolean> {
  if (!iapModule) return false;
  if (connected) return true;
  try {
    await iapModule.initConnection();
    connected = true;
  } catch {
    connected = false;
  }
  return connected;
}

/** Giá bản địa hoá của gói (vd "179.000 ₫"). null nếu chưa hỏi được chợ. */
export async function fetchPriceLabel(): Promise<string | null> {
  if (!iapModule || !(await connectStore())) return null;
  try {
    const result = (await iapModule.fetchProducts({
      skus: [SUBSCRIPTION_SKU],
      type: 'subs',
    })) as StoreProduct[] | null;

    const product = Array.isArray(result)
      ? (result.find((p) => (p.id ?? p.productId) === SUBSCRIPTION_SKU) ?? result[0])
      : null;

    return product?.displayPrice ?? null;
  } catch {
    return null;
  }
}

/** Hỏi chợ xem gói còn hiệu lực không. */
export async function isSubscriptionActive(): Promise<boolean> {
  if (!iapModule || !(await connectStore())) return false;
  try {
    const active = await iapModule.getActiveSubscriptions([SUBSCRIPTION_SKU]);
    return Array.isArray(active) && active.some((s) => s?.isActive !== false);
  } catch {
    return false;
  }
}

export interface PurchaseResult {
  ok: boolean;
  /** Lý do hiển thị cho người dùng khi ok = false; rỗng khi người dùng tự huỷ. */
  message?: string;
  /** true khi người dùng bấm huỷ — không phải lỗi, đừng hiện báo đỏ. */
  cancelled?: boolean;
}

/** Chờ tối đa 3 phút cho một phiên mua (người dùng còn phải xác thực Face ID / mật khẩu). */
const PURCHASE_TIMEOUT_MS = 180_000;

/**
 * Mở luồng mua gói đăng ký.
 *
 * expo-iap trả kết quả qua SỰ KIỆN chứ không qua giá trị trả về của requestPurchase,
 * nên phải gắn listener trước rồi mới gọi, và luôn tự kết thúc bằng timeout để nút
 * bấm không kẹt ở trạng thái đang quay mãi.
 */
export function purchaseSubscription(): Promise<PurchaseResult> {
  if (!iapModule) {
    return Promise.resolve({
      ok: false,
      message: 'In-app purchases are not available in this build.',
    });
  }

  const mod = iapModule;

  return new Promise<PurchaseResult>((resolve) => {
    let settled = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let updated: { remove: () => void } | null = null;
    let errored: { remove: () => void } | null = null;

    const finish = (result: PurchaseResult) => {
      if (settled) return;
      settled = true;
      if (timer) clearTimeout(timer);
      updated?.remove();
      errored?.remove();
      resolve(result);
    };

    try {
      updated = mod.purchaseUpdatedListener((purchase) => {
        // Gói đăng ký KHÔNG phải hàng tiêu hao -> isConsumable = false.
        // Không kết thúc giao dịch thì iOS phát lại nó ở mỗi lần mở app.
        mod.finishTransaction({ purchase, isConsumable: false }).catch(() => {});
        finish({ ok: true });
      });

      errored = mod.purchaseErrorListener((error) => {
        const code = String(error?.code ?? '');
        const cancelled = /cancel/i.test(code) || /cancel/i.test(String(error?.message ?? ''));
        finish({
          ok: false,
          cancelled,
          message: cancelled ? undefined : (error?.message ?? 'The purchase could not be completed.'),
        });
      });

      timer = setTimeout(
        () => finish({ ok: false, message: 'The store did not respond. Please try again.' }),
        PURCHASE_TIMEOUT_MS,
      );

      connectStore()
        .then(() =>
          mod.requestPurchase({
            request: {
              apple: { sku: SUBSCRIPTION_SKU },
              google: { skus: [SUBSCRIPTION_SKU] },
            },
            type: 'subs',
          }),
        )
        .catch((e: unknown) =>
          finish({
            ok: false,
            message: e instanceof Error ? e.message : 'The purchase could not be started.',
          }),
        );
    } catch (e) {
      finish({
        ok: false,
        message: e instanceof Error ? e.message : 'The purchase could not be started.',
      });
    }
  });
}

/** Khôi phục gói đã mua (Apple bắt buộc phải có nút này trên màn bán hàng). */
export async function restoreSubscription(): Promise<boolean> {
  return isSubscriptionActive();
}
