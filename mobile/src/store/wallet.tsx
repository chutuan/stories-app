import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

// ---- Kinh tế xu (SPEC §8) ----
/**
 * Xu của LƯỢT xem quảng cáo ĐẦU TIÊN trong ngày. Các lượt sau trả theo bậc
 * `AD_TASKS` trong `store/rewards` (30/30/40/50) — hằng số này chỉ là mức nền,
 * mọi khoản thưởng thật đều do `recordAdWatch()` quyết định.
 */
export const COIN_PER_REWARD = 90;
/** Giá mở 1 chương. Bằng đúng 1 lượt quảng cáo -> xem 1 quảng cáo mở 1 chương. */
export const COIN_PER_CHAPTER = 30;
export const STARTER_COINS = 0;

const KEY_COINS = 'stories:coins';
const KEY_UNLOCKED = 'stories:unlocked';
const KEY_SAVED = 'stories:saved';

function unlockKey(storyId: number | string, number: number | string): string {
  return `${storyId}:${number}`;
}

export interface WalletContextValue {
  coins: number;
  addCoins: (n: number) => void;
  /** Trừ xu; trả về false nếu không đủ. */
  spendCoins: (n: number) => boolean;
  isUnlocked: (storyId: number | string, number: number | string) => boolean;
  unlock: (storyId: number | string, number: number | string) => void;
  // Đã lưu
  savedIds: number[];
  isSaved: (id: number | string) => boolean;
  toggleSaved: (id: number | string) => void;
  ready: boolean;
}

const WalletContext = createContext<WalletContextValue | null>(null);

export function WalletProvider({ children }: { children: React.ReactNode }) {
  const [coins, setCoins] = useState<number>(STARTER_COINS);
  const [unlocked, setUnlocked] = useState<string[]>([]);
  const [savedIds, setSavedIds] = useState<number[]>([]);
  const [ready, setReady] = useState(false);

  // Giữ số xu mới nhất cho closure của spendCoins (cập nhật sau khi render xong).
  const coinsRef = useRef(coins);
  useEffect(() => {
    coinsRef.current = coins;
  }, [coins]);

  // Load từ AsyncStorage khi mount
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const [c, u, s] = await Promise.all([
          AsyncStorage.getItem(KEY_COINS),
          AsyncStorage.getItem(KEY_UNLOCKED),
          AsyncStorage.getItem(KEY_SAVED),
        ]);
        if (!mounted) return;
        if (c != null) {
          const parsed = parseInt(c, 10);
          if (!Number.isNaN(parsed)) setCoins(parsed);
        }
        if (u != null) {
          const arr = JSON.parse(u);
          if (Array.isArray(arr)) setUnlocked(arr);
        }
        if (s != null) {
          const arr = JSON.parse(s);
          if (Array.isArray(arr)) setSavedIds(arr);
        }
      } catch {
        // bỏ qua, dùng mặc định
      } finally {
        if (mounted) setReady(true);
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  const persistCoins = useCallback((value: number) => {
    AsyncStorage.setItem(KEY_COINS, String(value)).catch(() => {});
  }, []);
  const persistUnlocked = useCallback((value: string[]) => {
    AsyncStorage.setItem(KEY_UNLOCKED, JSON.stringify(value)).catch(() => {});
  }, []);
  const persistSaved = useCallback((value: number[]) => {
    AsyncStorage.setItem(KEY_SAVED, JSON.stringify(value)).catch(() => {});
  }, []);

  const addCoins = useCallback(
    (n: number) => {
      setCoins((prev) => {
        const next = prev + n;
        persistCoins(next);
        return next;
      });
    },
    [persistCoins],
  );

  const spendCoins = useCallback(
    (n: number): boolean => {
      if (coinsRef.current < n) return false;
      setCoins((prev) => {
        const next = prev - n;
        persistCoins(next);
        return next;
      });
      return true;
    },
    [persistCoins],
  );

  const isUnlocked = useCallback(
    (storyId: number | string, number: number | string) => unlocked.includes(unlockKey(storyId, number)),
    [unlocked],
  );

  const unlock = useCallback(
    (storyId: number | string, number: number | string) => {
      const key = unlockKey(storyId, number);
      setUnlocked((prev) => {
        if (prev.includes(key)) return prev;
        const next = [...prev, key];
        persistUnlocked(next);
        return next;
      });
    },
    [persistUnlocked],
  );

  const isSaved = useCallback(
    (id: number | string) => savedIds.includes(Number(id)),
    [savedIds],
  );

  const toggleSaved = useCallback(
    (id: number | string) => {
      const numId = Number(id);
      setSavedIds((prev) => {
        const next = prev.includes(numId) ? prev.filter((x) => x !== numId) : [...prev, numId];
        persistSaved(next);
        return next;
      });
    },
    [persistSaved],
  );

  const value = useMemo<WalletContextValue>(
    () => ({
      coins,
      addCoins,
      spendCoins,
      isUnlocked,
      unlock,
      savedIds,
      isSaved,
      toggleSaved,
      ready,
    }),
    [coins, addCoins, spendCoins, isUnlocked, unlock, savedIds, isSaved, toggleSaved, ready],
  );

  return <WalletContext.Provider value={value}>{children}</WalletContext.Provider>;
}

export function useWallet(): WalletContextValue {
  const ctx = useContext(WalletContext);
  if (!ctx) {
    throw new Error('useWallet must be used within a WalletProvider');
  }
  return ctx;
}
