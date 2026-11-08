import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { AppState } from 'react-native';

import { COIN_PER_REWARD, useWallet } from '@/store/wallet';

/**
 * KHO PHẦN THƯỞNG HẰNG NGÀY (trang "Phần thưởng").
 *
 * Lưu cục bộ bằng AsyncStorage, KHÔNG đụng tới ví: mọi khoản xu đều cộng qua
 * `useWallet().addCoins` để nguồn xu duy nhất vẫn là `store/wallet.tsx`.
 *
 * Hai nguồn xu:
 *  1. Thời gian đọc trong ngày — 4 mốc 10/30/60/90 phút, reset khi sang NGÀY mới.
 *  2. Xem quảng cáo — KHÔNG giới hạn lượt, mỗi lượt +COIN_PER_REWARD. Bộ đếm trong ngày
 *     chỉ để hiện "hôm nay đã xem mấy lượt", không chặn gì.
 *
 * Điểm danh hằng ngày đã BỎ từ 1.0.4 (Tuấn quyết 08/11/2026). Bản lưu cũ còn các
 * trường `week`/`checkedDays` — parseState lờ đi, không cần tăng STATE_VERSION.
 *
 * Mọi phép so ngày đều theo GIỜ ĐỊA PHƯƠNG của máy (không dùng UTC), vì mốc
 * "làm mới lúc 00:00" là 00:00 của người dùng.
 */

/* ------------------------------------------------------------------ */
/* HẰNG SỐ NHIỆM VỤ                                                    */
/* ------------------------------------------------------------------ */

export interface ReadingMilestone {
  /** số phút đọc cần đạt */
  minutes: number;
  /** xu thưởng khi nhận mốc này */
  coins: number;
}

/** 4 mốc thời gian đọc trong ngày. */
export const READING_MILESTONES: readonly ReadingMilestone[] = [
  { minutes: 10, coins: 15 },
  { minutes: 30, coins: 25 },
  { minutes: 60, coins: 40 },
  { minutes: 90, coins: 60 },
] as const;

/**
 * Xu cho MỖI lượt xem quảng cáo — không giới hạn số lượt trong ngày (từ 1.0.4).
 *
 * Bản ≤ 1.0.3: 4 lượt/ngày × 90 xu. Tuấn đổi 08/11/2026 thành không giới hạn nhưng mỗi
 * lượt 60 xu (= 2 chương, COIN_PER_CHAPTER = 30). Nút bấm luôn hứa đúng con số này.
 */
export const AD_REWARD = COIN_PER_REWARD;

/** Trần thời gian đọc ghi nhận mỗi ngày (giây) — chặn số liệu vô lý. */
const MAX_READING_SECONDS = 12 * 60 * 60;

/** Nhịp cộng thời gian đọc (ms). */
const TICK_MS = 15_000;
/** Khoảng cách giữa 2 nhịp lớn hơn mức này coi như máy ngủ -> bỏ qua. */
const MAX_TICK_GAP_MS = 90_000;

const KEY_REWARDS = 'stories:rewards';
const STATE_VERSION = 1;

/* ------------------------------------------------------------------ */
/* HÀM NGÀY THÁNG (giờ địa phương)                                     */
/* ------------------------------------------------------------------ */

function pad2(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

/** Khoá ngày dạng 'YYYY-MM-DD' theo giờ địa phương. */
export function dayKey(date: Date = new Date()): string {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

/* ------------------------------------------------------------------ */
/* TRẠNG THÁI                                                          */
/* ------------------------------------------------------------------ */

interface RewardsState {
  /** ngày của số liệu trong ngày ('YYYY-MM-DD') */
  day: string;
  /** số giây đã đọc trong ngày */
  readingSeconds: number;
  /** các mốc phút đã nhận thưởng trong ngày */
  readingClaimed: number[];
  /** số lượt quảng cáo đã xem trong ngày */
  adsWatched: number;
  /** tổng xu đã nhận từ quảng cáo trong ngày */
  adsCoins: number;
  /**
   * số lần đã cấp bù trong ngày vì không chiếu được quảng cáo (xem recordAdFallback).
   * Đếm RIÊNG với adsWatched: cấp bù không phải là một lượt xem quảng cáo.
   */
  fallbackGrants: number;
}

function emptyState(now: Date): RewardsState {
  return {
    day: dayKey(now),
    readingSeconds: 0,
    readingClaimed: [],
    adsWatched: 0,
    adsCoins: 0,
    fallbackGrants: 0,
  };
}

/**
 * Đưa trạng thái về đúng ngày hiện tại.
 * Trả về CHÍNH object cũ nếu không có gì phải reset (giữ nguyên tham chiếu để
 * React bỏ qua render thừa và không ghi lại AsyncStorage vô ích).
 */
function rollOver(state: RewardsState, day: string): RewardsState {
  if (state.day === day) return state;
  // Sang ngày mới: thời gian đọc + bộ đếm quảng cáo về 0.
  return {
    ...state,
    day,
    readingSeconds: 0,
    readingClaimed: [],
    adsWatched: 0,
    adsCoins: 0,
    fallbackGrants: 0,
  };
}

/** Đọc lại state đã lưu; dữ liệu hỏng/khác phiên bản thì bỏ qua. */
function parseState(raw: string | null, now: Date): RewardsState | null {
  if (!raw) return null;
  try {
    const data = JSON.parse(raw) as Partial<RewardsState> & { version?: number };
    if (!data || typeof data !== 'object' || data.version !== STATE_VERSION) return null;
    const base = emptyState(now);
    return {
      day: typeof data.day === 'string' ? data.day : base.day,
      readingSeconds:
        typeof data.readingSeconds === 'number' && Number.isFinite(data.readingSeconds)
          ? Math.max(0, Math.min(MAX_READING_SECONDS, data.readingSeconds))
          : 0,
      readingClaimed: Array.isArray(data.readingClaimed)
        ? data.readingClaimed.filter((n): n is number => typeof n === 'number')
        : [],
      adsWatched:
        typeof data.adsWatched === 'number' && Number.isFinite(data.adsWatched)
          ? Math.max(0, Math.floor(data.adsWatched))
          : 0,
      adsCoins:
        typeof data.adsCoins === 'number' && Number.isFinite(data.adsCoins)
          ? Math.max(0, data.adsCoins)
          : 0,
      // Bản lưu từ build cũ không có trường này -> 0, không cần tăng STATE_VERSION.
      fallbackGrants:
        typeof data.fallbackGrants === 'number' && Number.isFinite(data.fallbackGrants)
          ? Math.max(0, Math.floor(data.fallbackGrants))
          : 0,
    };
  } catch {
    return null;
  }
}

/* ------------------------------------------------------------------ */
/* CONTEXT                                                             */
/* ------------------------------------------------------------------ */

export interface RewardsContextValue {
  /** đã đọc xong AsyncStorage chưa (chưa xong thì khoá nút để tránh cộng nhầm) */
  ready: boolean;
  /** khoá ngày hôm nay */
  today: string;

  /** số phút đã đọc trong ngày */
  readingMinutes: number;
  /** cộng thêm thời gian đọc (dùng khi màn đọc muốn tự báo) */
  addReadingMinutes: (minutes: number) => void;
  /** các mốc phút đã nhận thưởng */
  readingClaimed: number[];
  /** mốc đã đạt nhưng CHƯA nhận (null nếu không có) */
  claimableReading: ReadingMilestone | null;
  /** mốc gần nhất chưa đạt (null nếu đã xong hết) */
  nextReading: ReadingMilestone | null;
  /** tổng xu đã nhận từ mốc thời gian đọc hôm nay */
  readingCoins: number;
  /** Nhận thưởng 1 mốc. Trả về số xu nhận được, 0 nếu chưa đạt/đã nhận. */
  claimReading: (minutes: number) => number;

  /** số lượt quảng cáo đã xem hôm nay */
  adsWatched: number;
  /** tổng xu đã nhận từ quảng cáo hôm nay */
  adsCoins: number;
  /** Ghi nhận 1 lượt xem quảng cáo hợp lệ (không giới hạn); trả về số xu vừa cộng. */
  recordAdWatch: () => number;
  /** số lần đã cấp bù hôm nay vì không có quảng cáo */
  fallbackGrants: number;
  /**
   * Cấp bù `coins` xu vì không chiếu được quảng cáo, nếu hôm nay chưa quá
   * `dailyLimit` lần. Trả về số xu đã cộng (0 = hết lượt hoặc chưa sẵn sàng).
   * Nơi gọi tự quyết định CÓ NÊN cấp không (đang thiếu xu, cấu hình đang bật).
   */
  recordAdFallback: (coins: number, dailyLimit: number) => number;
}

const RewardsContext = createContext<RewardsContextValue | null>(null);

export function RewardsProvider({ children }: { children: React.ReactNode }) {
  const { addCoins } = useWallet();
  const [state, setState] = useState<RewardsState>(() => emptyState(new Date()));
  const [ready, setReady] = useState(false);
  /** đổi mỗi khi qua ngày mới để tính lại `today` */
  const [dayStamp, setDayStamp] = useState(() => dayKey(new Date()));

  // Bản mới nhất của state cho các callback (không đọc ref trong thân render).
  const stateRef = useRef(state);
  useEffect(() => {
    stateRef.current = state;
  }, [state]);

  const readyRef = useRef(ready);
  useEffect(() => {
    readyRef.current = ready;
  }, [ready]);

  // --- Nạp từ AsyncStorage ---
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const raw = await AsyncStorage.getItem(KEY_REWARDS);
        if (!mounted) return;
        const now = new Date();
        const parsed = parseState(raw, now);
        if (parsed) {
          setState(rollOver(parsed, dayKey(now)));
        }
      } catch {
        // hỏng thì dùng mặc định
      } finally {
        if (mounted) setReady(true);
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  // --- Ghi lại mỗi khi state đổi ---
  useEffect(() => {
    if (!ready) return;
    AsyncStorage.setItem(
      KEY_REWARDS,
      JSON.stringify({ version: STATE_VERSION, ...state }),
    ).catch(() => {});
  }, [state, ready]);

  /** Cộng thời gian đọc theo giây, tự reset nếu đã sang ngày mới. */
  const addReadingSeconds = useCallback((seconds: number) => {
    if (seconds <= 0) return;
    const now = new Date();
    const day = dayKey(now);
    setState((prev) => {
      const rolled = rollOver(prev, day);
      const nextSeconds = Math.min(MAX_READING_SECONDS, rolled.readingSeconds + seconds);
      if (nextSeconds === rolled.readingSeconds) return rolled;
      return { ...rolled, readingSeconds: nextSeconds };
    });
    setDayStamp(day);
  }, []);

  // --- Đồng hồ đếm thời gian đọc ---
  // Cộng theo MỐC THỜI GIAN THẬT (không đếm số nhịp) để timer bị bóp vẫn đúng,
  // và chỉ cộng khi ứng dụng đang ở tiền cảnh.
  useEffect(() => {
    if (!ready) return;
    let last = Date.now();

    const timer = setInterval(() => {
      const now = Date.now();
      const elapsed = now - last;
      last = now;
      if (AppState.currentState !== 'active') return;
      if (elapsed <= 0 || elapsed > MAX_TICK_GAP_MS) return;
      addReadingSeconds(Math.round(elapsed / 1000));
    }, TICK_MS);

    // Quay lại tiền cảnh: bỏ qua quãng thời gian nằm nền.
    const sub = AppState.addEventListener('change', () => {
      last = Date.now();
    });

    return () => {
      clearInterval(timer);
      sub.remove();
    };
  }, [ready, addReadingSeconds]);

  const addReadingMinutes = useCallback(
    (minutes: number) => addReadingSeconds(Math.round(minutes * 60)),
    [addReadingSeconds],
  );

  const claimReading = useCallback(
    (minutes: number): number => {
      if (!readyRef.current) return 0;
      const milestone = READING_MILESTONES.find((m) => m.minutes === minutes);
      if (!milestone) return 0;
      const now = new Date();
      const day = dayKey(now);
      const current = rollOver(stateRef.current, day);
      const reached = Math.floor(current.readingSeconds / 60) >= milestone.minutes;
      if (!reached || current.readingClaimed.includes(milestone.minutes)) {
        setState(current);
        setDayStamp(day);
        return 0;
      }
      setState({ ...current, readingClaimed: [...current.readingClaimed, milestone.minutes] });
      setDayStamp(day);
      addCoins(milestone.coins);
      return milestone.coins;
    },
    [addCoins],
  );

  const recordAdWatch = useCallback((): number => {
    if (!readyRef.current) return 0;
    const now = new Date();
    const day = dayKey(now);
    const current = rollOver(stateRef.current, day);
    const coins = AD_REWARD;
    const next = {
      ...current,
      adsWatched: current.adsWatched + 1,
      adsCoins: current.adsCoins + coins,
    };
    // Ghi luôn vào ref thay vì chờ effect đồng bộ sau lần render kế: lượt sau tới
    // trước khi effect chạy thì vẫn đọc đúng số lượt đã đếm.
    stateRef.current = next;
    setState(next);
    setDayStamp(day);
    addCoins(coins);
    return coins;
  }, [addCoins]);

  const recordAdFallback = useCallback(
    (coins: number, dailyLimit: number): number => {
      if (!readyRef.current || coins <= 0) return 0;
      const now = new Date();
      const day = dayKey(now);
      const current = rollOver(stateRef.current, day);
      if (current.fallbackGrants >= dailyLimit) {
        setState(current);
        setDayStamp(day);
        return 0;
      }
      const next = { ...current, fallbackGrants: current.fallbackGrants + 1 };
      // Ghi luôn vào ref: hai lần bấm sát nhau không được cùng đọc số cũ rồi cùng
      // vượt qua hạn mức trước khi effect kịp đồng bộ.
      stateRef.current = next;
      setState(next);
      setDayStamp(day);
      addCoins(coins);
      return coins;
    },
    [addCoins],
  );

  // --- Giá trị dẫn xuất ---
  const readingMinutes = useMemo(() => Math.floor(state.readingSeconds / 60), [state.readingSeconds]);

  const today = useMemo(() => {
    // `dayStamp` chỉ để ép tính lại khi qua ngày; giá trị thật vẫn lấy từ đồng hồ.
    void dayStamp;
    return dayKey(new Date());
  }, [dayStamp]);

  const claimableReading = useMemo(
    () =>
      READING_MILESTONES.find(
        (m) => readingMinutes >= m.minutes && !state.readingClaimed.includes(m.minutes),
      ) ?? null,
    [readingMinutes, state.readingClaimed],
  );

  const nextReading = useMemo(
    () => READING_MILESTONES.find((m) => readingMinutes < m.minutes) ?? null,
    [readingMinutes],
  );

  const readingCoins = useMemo(
    () =>
      READING_MILESTONES.filter((m) => state.readingClaimed.includes(m.minutes)).reduce(
        (sum, m) => sum + m.coins,
        0,
      ),
    [state.readingClaimed],
  );

  const value = useMemo<RewardsContextValue>(
    () => ({
      ready,
      today,
      readingMinutes,
      addReadingMinutes,
      readingClaimed: state.readingClaimed,
      claimableReading,
      nextReading,
      readingCoins,
      claimReading,
      adsWatched: state.adsWatched,
      adsCoins: state.adsCoins,
      recordAdWatch,
      fallbackGrants: state.fallbackGrants,
      recordAdFallback,
    }),
    [
      ready,
      today,
      readingMinutes,
      addReadingMinutes,
      state.readingClaimed,
      state.adsWatched,
      state.adsCoins,
      claimableReading,
      nextReading,
      readingCoins,
      claimReading,
      recordAdWatch,
      state.fallbackGrants,
      recordAdFallback,
    ],
  );

  return <RewardsContext.Provider value={value}>{children}</RewardsContext.Provider>;
}

export function useRewards(): RewardsContextValue {
  const ctx = useContext(RewardsContext);
  if (!ctx) {
    throw new Error('useRewards must be used within a RewardsProvider');
  }
  return ctx;
}
