/**
 * Bản WEB của lib/iap.
 *
 * Metro phân tích `require` TĨNH, nên chỉ cần nhánh web chạm tới `expo-iap` là cả
 * bundle web hỏng (đúng vết xe của react-native-google-mobile-ads, xem ads.web.tsx).
 * File này KHÔNG import expo-iap: trên web không có chợ ứng dụng nào để mua.
 */

export const SUBSCRIPTION_SKU = 'com.chutuan.stories.premium.monthly';
export const FALLBACK_PRICE_LABEL = '$9.99';
export const iapAvailable = false;

export interface PurchaseResult {
  ok: boolean;
  message?: string;
  cancelled?: boolean;
}

export function connectStore(): Promise<boolean> {
  return Promise.resolve(false);
}

export function fetchPriceLabel(): Promise<string | null> {
  return Promise.resolve(null);
}

export function isSubscriptionActive(): Promise<boolean> {
  return Promise.resolve(false);
}

export function purchaseSubscription(): Promise<PurchaseResult> {
  return Promise.resolve({
    ok: false,
    message: 'Subscriptions are only available in the iOS and Android apps.',
  });
}

export function restoreSubscription(): Promise<boolean> {
  return Promise.resolve(false);
}
