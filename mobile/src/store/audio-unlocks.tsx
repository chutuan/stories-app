import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState } from 'react-native';

/**
 * AUDIO MỞ BẰNG QUẢNG CÁO (Tuấn yêu cầu 10/11/2026).
 *
 * Nghe audio của BẤT KỲ chương nào (miễn phí hay trả xu) đều phải xem một quảng cáo có thưởng.
 * Mỗi lượt xem hết mở audio của ĐÚNG MỘT chương trong AUDIO_UNLOCK_MS (24 giờ); hết hạn thì
 * xem lại. Tách hẳn khỏi khoá xu của chữ (store/wallet): mở chữ bằng xu không mở audio, và
 * ngược lại.
 *
 * Chỉ lưu trong máy, như mọi thứ khác của app (không có tài khoản). Server chỉ nhận số liệu
 * để đếm (recordAudioRequest), không kiểm tra quyền nghe.
 *
 * `now` là MỐC so hạn, chỉ cập nhật lúc đọc xong bộ nhớ, lúc app quay lại từ nền và lúc mở
 * khoá — không gọi Date.now() mỗi lần render. Nhờ vậy chương đang phát không bị cắt ngang
 * đúng giây thứ 24 giờ; lần mở app / quay lại app sau đó mới đòi xem quảng cáo lại.
 */

export const AUDIO_UNLOCK_MS = 24 * 60 * 60 * 1000;

const KEY_AUDIO_UNLOCKS = 'stories:audio-unlocks';

/** "storyId:number" -> hết hạn lúc (ms). */
type Unlocks = Record<string, number>;

function unlockKey(storyId: number | string, number: number | string): string {
  return `${storyId}:${number}`;
}

/** Bỏ mục đã hết hạn; trả CHÍNH object cũ nếu không có gì để bỏ. */
function prune(unlocks: Unlocks, now: number): Unlocks {
  const expired = Object.keys(unlocks).filter((k) => unlocks[k] <= now);
  if (expired.length === 0) return unlocks;
  const next = { ...unlocks };
  for (const k of expired) delete next[k];
  return next;
}

function parse(raw: string | null): Unlocks {
  if (!raw) return {};
  try {
    const data: unknown = JSON.parse(raw);
    if (!data || typeof data !== 'object' || Array.isArray(data)) return {};
    const out: Unlocks = {};
    for (const [k, v] of Object.entries(data as Record<string, unknown>)) {
      if (typeof v === 'number' && Number.isFinite(v) && /^\d+:\d+$/.test(k)) out[k] = v;
    }
    return out;
  } catch {
    return {};
  }
}

interface AudioUnlocksValue {
  /** Audio của chương này đang mở (đã xem quảng cáo trong 24 giờ qua). */
  isAudioUnlocked: (storyId: number | string, number: number | string) => boolean;
  /** Gọi SAU KHI người dùng xem hết quảng cáo có thưởng. */
  unlockAudio: (storyId: number | string, number: number | string) => void;
}

const AudioUnlocksContext = createContext<AudioUnlocksValue | null>(null);

interface State {
  unlocks: Unlocks;
  now: number;
}

export function AudioUnlocksProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<State>({ unlocks: {}, now: 0 });
  const stateRef = useRef(state);

  const commit = useCallback((next: State, persist: boolean) => {
    stateRef.current = next;
    setState(next);
    if (persist) AsyncStorage.setItem(KEY_AUDIO_UNLOCKS, JSON.stringify(next.unlocks)).catch(() => {});
  }, []);

  useEffect(() => {
    let alive = true;
    AsyncStorage.getItem(KEY_AUDIO_UNLOCKS)
      .catch(() => null)
      .then((raw) => {
        if (!alive) return;
        const now = Date.now();
        // Gộp với mục vừa mở khoá trong lúc còn đang đọc bộ nhớ (hiếm, nhưng không được mất).
        const merged = { ...parse(raw), ...stateRef.current.unlocks };
        const unlocks = prune(merged, now);
        commit({ unlocks, now }, unlocks !== merged || Object.keys(stateRef.current.unlocks).length > 0);
      });

    const sub = AppState.addEventListener('change', (s) => {
      if (s !== 'active') return;
      const now = Date.now();
      const unlocks = prune(stateRef.current.unlocks, now);
      commit({ unlocks, now }, unlocks !== stateRef.current.unlocks);
    });

    return () => {
      alive = false;
      sub.remove();
    };
  }, [commit]);

  const isAudioUnlocked = useCallback(
    (storyId: number | string, number: number | string) =>
      (state.unlocks[unlockKey(storyId, number)] ?? 0) > state.now,
    [state],
  );

  const unlockAudio = useCallback(
    (storyId: number | string, number: number | string) => {
      const now = Date.now();
      const unlocks = { ...prune(stateRef.current.unlocks, now), [unlockKey(storyId, number)]: now + AUDIO_UNLOCK_MS };
      commit({ unlocks, now }, true);
    },
    [commit],
  );

  const value = useMemo(() => ({ isAudioUnlocked, unlockAudio }), [isAudioUnlocked, unlockAudio]);

  return <AudioUnlocksContext.Provider value={value}>{children}</AudioUnlocksContext.Provider>;
}

export function useAudioUnlocks(): AudioUnlocksValue {
  const ctx = useContext(AudioUnlocksContext);
  if (!ctx) throw new Error('useAudioUnlocks must be used within an AudioUnlocksProvider');
  return ctx;
}
