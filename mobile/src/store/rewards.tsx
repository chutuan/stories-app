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
 * Ba nhóm nhiệm vụ:
 *  1. Điểm danh — 1 lần/ngày, mốc theo thứ trong tuần, reset khi sang TUẦN mới.
 *  2. Thời gian đọc trong ngày — 4 mốc 10/30/60/90 phút, reset khi sang NGÀY mới.
 *  3. Nhiệm vụ quảng cáo — 4 lượt/ngày, cũng reset khi sang ngày mới.
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

/** Xu cho lượt xem quảng cáo thứ 1, 2, 3, 4 trong ngày. */
export const AD_TASKS: readonly number[] = [
  COIN_PER_REWARD,
  COIN_PER_REWARD,
  40,
  50,
] as const;

/** Số lượt quảng cáo tối đa mỗi ngày. */
export const AD_TASK_LIMIT = AD_TASKS.length;

/** Tổng xu có thể nhận từ quảng cáo trong 1 ngày. */
export const AD_MAX_COINS = AD_TASKS.reduce((sum, n) => sum + n, 0);

/** Xu điểm danh theo thứ: CN, T2, T3, T4, T5, T6, T7 (cuối tuần nhiều hơn). */
export const CHECKIN_COINS: readonly number[] = [30, 10, 10, 15, 15, 20, 30] as const;

/** Nhãn thứ trong tuần, thứ tự khớp `Date.getDay()` (0 = CN). */
export const WEEKDAY_LABELS: readonly string[] = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'] as const;

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

/** Khoá ngày CHỦ NHẬT mở đầu tuần chứa `date`. */
export function weekStartKey(date: Date = new Date()): string {
  const start = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay());
  return dayKey(start);
}

/** 7 khoá ngày của tuần chứa `date`, theo thứ tự CN -> T7. */
export function getWeekDays(date: Date = new Date()): string[] {
  const base = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay());
  return Array.from({ length: 7 }, (_, i) =>
    dayKey(new Date(base.getFullYear(), base.getMonth(), base.getDate() + i)),
  );
}

/* ------------------------------------------------------------------ */
/* TRẠNG THÁI                                                          */
/* ------------------------------------------------------------------ */

interface RewardsState {
  /** ngày của số liệu trong ngày ('YYYY-MM-DD') */
  day: string;
  /** chủ nhật mở đầu tuần của `checkedDays` */
  week: string;
  /** các ngày đã điểm danh trong tuần hiện tại */
  checkedDays: string[];
  /** số giây đã đọc trong ngày */
  readingSeconds: number;
  /** các mốc phút đã nhận thưởng trong ngày */
  readingClaimed: number[];
  /** số lượt quảng cáo đã xem trong ngày */
  adsWatched: number;
  /** tổng xu đã nhận từ quảng cáo trong ngày */
  adsCoins: number;
}

function emptyState(now: Date): RewardsState {
  return {
    day: dayKey(now),
    week: weekStartKey(now),
    checkedDays: [],
    readingSeconds: 0,
    readingClaimed: [],
    adsWatched: 0,
    adsCoins: 0,
  };
}

/**
 * Đưa trạng thái về đúng ngày/tuần hiện tại.
 * Trả về CHÍNH object cũ nếu không có gì phải reset (giữ nguyên tham chiếu để
 * React bỏ qua render thừa và không ghi lại AsyncStorage vô ích).
 */
function rollOver(state: RewardsState, day: string, week: string): RewardsState {
  let next = state;
  if (next.day !== day) {
    // Sang ngày mới: thời gian đọc + nhiệm vụ quảng cáo về 0.
    next = {
      ...next,
      day,
      readingSeconds: 0,
      readingClaimed: [],
      adsWatched: 0,
      adsCoins: 0,
    };
  }
  if (next.week !== week) {
    // Sang tuần mới: lịch điểm danh làm lại từ đầu.
    next = { ...next, week, checkedDays: [] };
  }
  return next;
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
      week: typeof data.week === 'string' ? data.week : base.week,
      checkedDays: Array.isArray(data.checkedDays)
        ? data.checkedDays.filter((d): d is string => typeof d === 'string')
        : [],
      readingSeconds:
        typeof data.readingSeconds === 'number' && Number.isFinite(data.readingSeconds)
          ? Math.max(0, Math.min(MAX_READING_SECONDS, data.readingSeconds))
          : 0,
      readingClaimed: Array.isArray(data.readingClaimed)
        ? data.readingClaimed.filter((n): n is number => typeof n === 'number')
        : [],
      adsWatched:
        typeof data.adsWatched === 'number' && Number.isFinite(data.adsWatched)
          ? Math.max(0, Math.min(AD_TASK_LIMIT, data.adsWatched))
          : 0,
      adsCoins:
        typeof data.adsCoins === 'number' && Number.isFinite(data.adsCoins)
          ? Math.max(0, data.adsCoins)
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
  /** 7 khoá ngày của tuần hiện tại, CN -> T7 */
  weekDays: string[];
  /** các ngày đã điểm danh trong tuần */
  checkedDays: string[];
  /** hôm nay đã điểm danh chưa */
  checkedToday: boolean;
  /** xu điểm danh của hôm nay */
  todayCheckInCoins: number;
  /** Điểm danh. Trả về số xu nhận được, 0 nếu đã điểm danh rồi. */
  checkIn: () => number;

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
  /** xu của lượt xem kế tiếp (0 nếu hết lượt) */
  nextAdCoins: number;
  /** Ghi nhận 1 lượt xem quảng cáo hợp lệ; trả về số xu vừa cộng. */
  recordAdWatch: () => number;
}

const RewardsContext = createContext<RewardsContextValue | null>(null);

export function RewardsProvider({ children }: { children: React.ReactNode }) {
  const { addCoins } = useWallet();
  const [state, setState] = useState<RewardsState>(() => emptyState(new Date()));
  const [ready, setReady] = useState(false);
  /** đổi mỗi khi qua ngày mới để tính lại `today` / `weekDays` */
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
          setState(rollOver(parsed, dayKey(now), weekStartKey(now)));
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
    const week = weekStartKey(now);
    setState((prev) => {
      const rolled = rollOver(prev, day, week);
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

  const checkIn = useCallback((): number => {
    if (!readyRef.current) return 0;
    const now = new Date();
    const day = dayKey(now);
    const week = weekStartKey(now);
    const current = rollOver(stateRef.current, day, week);
    if (current.checkedDays.includes(day)) {
      setState(current);
      setDayStamp(day);
      return 0;
    }
    const coins = CHECKIN_COINS[now.getDay()] ?? 10;
    setState({ ...current, checkedDays: [...current.checkedDays, day] });
    setDayStamp(day);
    addCoins(coins);
    return coins;
  }, [addCoins]);

  const claimReading = useCallback(
    (minutes: number): number => {
      if (!readyRef.current) return 0;
      const milestone = READING_MILESTONES.find((m) => m.minutes === minutes);
      if (!milestone) return 0;
      const now = new Date();
      const day = dayKey(now);
      const week = weekStartKey(now);
      const current = rollOver(stateRef.current, day, week);
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
    const week = weekStartKey(now);
    const current = rollOver(stateRef.current, day, week);
    if (current.adsWatched >= AD_TASK_LIMIT) {
      setState(current);
      setDayStamp(day);
      return 0;
    }
    const coins = AD_TASKS[current.adsWatched] ?? COIN_PER_REWARD;
    setState({
      ...current,
      adsWatched: current.adsWatched + 1,
      adsCoins: current.adsCoins + coins,
    });
    setDayStamp(day);
    addCoins(coins);
    return coins;
  }, [addCoins]);

  // --- Giá trị dẫn xuất ---
  const readingMinutes = useMemo(() => Math.floor(state.readingSeconds / 60), [state.readingSeconds]);

  const today = useMemo(() => {
    // `dayStamp` chỉ để ép tính lại khi qua ngày; giá trị thật vẫn lấy từ đồng hồ.
    void dayStamp;
    return dayKey(new Date());
  }, [dayStamp]);

  const weekDays = useMemo(() => {
    void dayStamp;
    return getWeekDays(new Date());
  }, [dayStamp]);

  const checkedDays = useMemo(
    () => state.checkedDays.filter((d) => weekDays.includes(d)),
    [state.checkedDays, weekDays],
  );

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
      weekDays,
      checkedDays,
      checkedToday: checkedDays.includes(today),
      todayCheckInCoins: CHECKIN_COINS[new Date().getDay()] ?? 10,
      checkIn,
      readingMinutes,
      addReadingMinutes,
      readingClaimed: state.readingClaimed,
      claimableReading,
      nextReading,
      readingCoins,
      claimReading,
      adsWatched: state.adsWatched,
      adsCoins: state.adsCoins,
      nextAdCoins: state.adsWatched < AD_TASK_LIMIT ? (AD_TASKS[state.adsWatched] ?? 0) : 0,
      recordAdWatch,
    }),
    [
      ready,
      today,
      weekDays,
      checkedDays,
      checkIn,
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
    ],
  );

  return <RewardsContext.Provider value={value}>{children}</RewardsContext.Provider>;
}

export function useRewards(): RewardsContextValue {
  const ctx = useContext(RewardsContext);
  if (!ctx) {
    throw new Error('useRewards phải nằm trong <RewardsProvider>');
  }
  return ctx;
}
