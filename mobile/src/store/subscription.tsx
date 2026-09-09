import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState } from 'react-native';

import {
  FALLBACK_PRICE_LABEL,
  fetchPriceLabel,
  iapAvailable,
  isSubscriptionActive,
  purchaseSubscription,
  restoreSubscription,
} from '@/lib/iap';

/**
 * GÓI ĐĂNG KÝ (Premium) — 9,99 $/tháng, tự động gia hạn.
 *
 * Người đăng ký được:
 *  - đọc MỌI chương, không cần xu;
 *  - nghe audio MỌI chương.
 * Người thường vẫn:
 *  - đọc chương miễn phí, và mở thêm chương bằng xu như cũ;
 *  - nghe audio của các chương MIỄN PHÍ (thường là chương 1).
 * Audio của chương trả phí thì xu KHÔNG mở được — chỗ đó bán bằng gói đăng ký.
 *
 * Nguồn sự thật là CHỢ ỨNG DỤNG (StoreKit / Play Billing), hỏi qua lib/iap.
 * AsyncStorage chỉ giữ bản chụp gần nhất để mở app không bị nháy về "chưa đăng ký"
 * trong lúc chờ chợ trả lời; mỗi lần chợ trả lời là ghi đè lại.
 */

const KEY_SUBSCRIBED = 'stories:subscribed';

/** Giá hiển thị khi chưa hỏi được chợ. */
export const SUBSCRIPTION_PRICE_FALLBACK = FALLBACK_PRICE_LABEL;

export interface SubscriptionContextValue {
  /** đã đọc xong bản chụp cục bộ chưa (chưa xong thì đừng vội chặn giao diện) */
  ready: boolean;
  /** đang có gói Premium còn hiệu lực */
  subscribed: boolean;
  /** giá bản địa hoá lấy từ chợ, đã có sẵn giá dự phòng */
  priceLabel: string;
  /** đang mở luồng mua hoặc khôi phục */
  busy: boolean;
  /** Mở luồng mua. Trả về thông báo để màn bán hàng hiển thị. */
  subscribe: () => Promise<{ ok: boolean; message?: string; cancelled?: boolean }>;
  /** Khôi phục gói đã mua trên tài khoản chợ. */
  restore: () => Promise<{ ok: boolean; message?: string }>;
}

const SubscriptionContext = createContext<SubscriptionContextValue | null>(null);

export function SubscriptionProvider({ children }: { children: React.ReactNode }) {
  const [subscribed, setSubscribed] = useState(false);
  const [ready, setReady] = useState(false);
  const [busy, setBusy] = useState(false);
  const [priceLabel, setPriceLabel] = useState<string>(FALLBACK_PRICE_LABEL);

  const busyRef = useRef(busy);
  useEffect(() => {
    busyRef.current = busy;
  }, [busy]);

  /** Ghi cả state lẫn bản chụp cục bộ. */
  const apply = useCallback((value: boolean) => {
    setSubscribed(value);
    AsyncStorage.setItem(KEY_SUBSCRIBED, value ? '1' : '0').catch(() => {});
  }, []);

  // --- Nạp bản chụp rồi hỏi lại chợ ---
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const cached = await AsyncStorage.getItem(KEY_SUBSCRIBED);
        if (alive && cached === '1') setSubscribed(true);
      } catch {
        // hỏng thì coi như chưa đăng ký
      } finally {
        if (alive) setReady(true);
      }

      if (!iapAvailable) return;

      const [active, price] = await Promise.all([isSubscriptionActive(), fetchPriceLabel()]);
      if (!alive) return;
      setSubscribed(active);
      AsyncStorage.setItem(KEY_SUBSCRIBED, active ? '1' : '0').catch(() => {});
      if (price) setPriceLabel(price);
    })();
    return () => {
      alive = false;
    };
  }, []);

  // --- Quay lại tiền cảnh thì hỏi lại chợ ---
  // Người dùng có thể huỷ gói trong Cài đặt rồi quay lại app; không hỏi lại thì
  // quyền Premium vẫn còn cho tới lần mở app sau.
  useEffect(() => {
    if (!iapAvailable) return;
    const sub = AppState.addEventListener('change', (state) => {
      if (state !== 'active' || busyRef.current) return;
      isSubscriptionActive()
        .then((active) => apply(active))
        .catch(() => {});
    });
    return () => sub.remove();
  }, [apply]);

  const subscribe = useCallback(async () => {
    if (busyRef.current) return { ok: false as const };
    setBusy(true);
    try {
      if (!iapAvailable) {
        // KHÔNG có native module. Chỉ bản dev mới được cấp giả lập để thử giao diện;
        // bản phát hành phải từ chối, nếu không mọi người đều thành Premium miễn phí.
        if (__DEV__) {
          apply(true);
          return { ok: true, message: 'Dev build: Premium granted locally (no real purchase).' };
        }
        return {
          ok: false,
          message: 'In-app purchases are not available in this build.',
        };
      }

      const result = await purchaseSubscription();
      if (result.ok) {
        apply(true);
        return { ok: true };
      }
      return { ok: false, message: result.message, cancelled: result.cancelled };
    } finally {
      setBusy(false);
    }
  }, [apply]);

  const restore = useCallback(async () => {
    if (busyRef.current) return { ok: false as const };
    setBusy(true);
    try {
      if (!iapAvailable) {
        return { ok: false, message: 'Nothing to restore in this build.' };
      }
      const active = await restoreSubscription();
      apply(active);
      return active
        ? { ok: true }
        : { ok: false, message: 'No active subscription found on this account.' };
    } finally {
      setBusy(false);
    }
  }, [apply]);

  const value = useMemo<SubscriptionContextValue>(
    () => ({ ready, subscribed, priceLabel, busy, subscribe, restore }),
    [ready, subscribed, priceLabel, busy, subscribe, restore],
  );

  return <SubscriptionContext.Provider value={value}>{children}</SubscriptionContext.Provider>;
}

export function useSubscription(): SubscriptionContextValue {
  const ctx = useContext(SubscriptionContext);
  if (!ctx) {
    throw new Error('useSubscription must be used within a SubscriptionProvider');
  }
  return ctx;
}
