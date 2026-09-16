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
/**
 * Xu tặng khi cài mới. 90 = đúng 3 chương (COIN_PER_CHAPTER = 30).
 *
 * TRƯỚC ĐÂY LÀ 0, và đó là lý do Apple từ chối bản 1.0(3) theo Guideline 2.1(a):
 * "unable to use the core feature, unlock chapters". Người vừa cài có 0 xu, nên
 * đường DUY NHẤT dùng thử được chức năng mở chương là xem quảng cáo — tức là đặt
 * toàn bộ chức năng lõi vào tay một mạng quảng cáo bên thứ ba. Máy của Apple nằm
 * trong trung tâm dữ liệu, app lại chỉ xin quảng cáo KHÔNG cá nhân hoá, và tài
 * khoản AdMob thì mới tinh — không có hàng để trả là chuyện bình thường. Khi đó
 * showRewarded() trả `unavailable`, số dư vẫn 0, và không còn đường nào.
 *
 * Đường không-quảng-cáo có tồn tại (điểm danh + mốc đọc 10 phút) nhưng nằm ở một
 * tab khác, đòi ngồi đủ 10 phút, và THỨ HAI/THỨ BA cộng lại chỉ ra 25 xu — không
 * mở nổi chương nào. Không phiên duyệt nào đi qua được đường đó.
 */
export const STARTER_COINS = 90;

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
  // Khởi tạo 0 chứ KHÔNG phải STARTER_COINS: người dùng cũ sẽ thấy số dư ma trong
  // lúc AsyncStorage còn đang đọc. Xu chào mừng được cấp trong effect bên dưới,
  // chỉ khi xác định được đây là lần cài đầu.
  const [coins, setCoins] = useState<number>(0);
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
        } else {
          // Chưa từng có khoá ví trên máy -> đúng là lần cài đầu. Cấp xu chào mừng
          // và GHI NGAY, để mỗi máy chỉ nhận đúng một lần.
          //
          // Phải làm ở đây chứ không chỉ đổi giá trị khởi tạo của useState: người
          // dùng CŨ sẽ thấy 90 xu loé lên trong lúc AsyncStorage còn đang đọc, và
          // trong cửa sổ vài mili-giây đó họ bấm Unlock được bằng số dư ma.
          setCoins(STARTER_COINS);
          AsyncStorage.setItem(KEY_COINS, String(STARTER_COINS)).catch(() => {});
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
      // Trừ vào ref NGAY, không chờ render. Ref chỉ được đồng bộ lại sau khi render
      // xong, nên hai lần bấm sát nhau đều đọc ra cùng một số dư cũ và tiêu hai lần
      // trên một túi tiền. Comment cũ ở đây khẳng định điều đó không xảy ra được —
      // nó khẳng định sai.
      coinsRef.current -= n;
      setCoins((prev) => {
        const next = Math.max(0, prev - n);
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
