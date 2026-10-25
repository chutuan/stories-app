import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

/**
 * Lịch sử đọc: những truyện người dùng đã mở, chương gần nhất và lúc đọc.
 *
 * CHỈ lưu trên máy (AsyncStorage), không gửi về server — cùng chỗ với xu, chương đã mở
 * khoá và truyện đã lưu. Privacy Policy liệt kê mục này trong "What stays on your
 * device"; nếu có ngày đồng bộ lên server thì phải sửa Privacy Policy và App Privacy.
 *
 * Mỗi truyện MỘT dòng (đọc lại thì cập nhật chương và đẩy lên đầu), tối đa
 * HISTORY_LIMIT truyện — dài hơn nữa thì không ai cuộn tới, chỉ tốn chỗ lưu.
 */

export const HISTORY_LIMIT = 100;
const KEY_HISTORY = 'stories:history';

export interface HistoryEntry {
  storyId: number;
  title: string;
  thumbnailUrl: string | null;
  /** chương đọc gần nhất (số thứ tự) */
  chapter: number;
  chapterTitle: string | null;
  /** tổng số chương lúc đọc; null nếu chưa biết */
  chaptersCount: number | null;
  /** thời điểm đọc gần nhất, ms */
  readAt: number;
}

export type HistoryInput = Omit<HistoryEntry, 'readAt'>;

interface HistoryContextValue {
  /** mới nhất trước */
  entries: HistoryEntry[];
  ready: boolean;
  /** Ghi một lượt đọc. Gọi lặp lại cùng truyện + chương thì chỉ cập nhật, không thêm dòng. */
  record: (input: HistoryInput) => void;
  remove: (storyId: number) => void;
  clear: () => void;
}

const HistoryContext = createContext<HistoryContextValue | null>(null);

/** Đọc lại dữ liệu đã lưu; dòng hỏng thì bỏ, không làm hỏng cả danh sách. */
function parse(raw: string | null): HistoryEntry[] {
  if (!raw) return [];
  try {
    const data = JSON.parse(raw);
    if (!Array.isArray(data)) return [];
    return data
      .filter(
        (e): e is HistoryEntry =>
          !!e &&
          typeof e.storyId === 'number' &&
          typeof e.title === 'string' &&
          typeof e.chapter === 'number' &&
          typeof e.readAt === 'number',
      )
      .map((e) => ({
        storyId: e.storyId,
        title: e.title,
        thumbnailUrl: typeof e.thumbnailUrl === 'string' ? e.thumbnailUrl : null,
        chapter: e.chapter,
        chapterTitle: typeof e.chapterTitle === 'string' ? e.chapterTitle : null,
        chaptersCount: typeof e.chaptersCount === 'number' ? e.chaptersCount : null,
        readAt: e.readAt,
      }))
      .sort((a, b) => b.readAt - a.readAt)
      .slice(0, HISTORY_LIMIT);
  } catch {
    return [];
  }
}

/** Một thao tác lên danh sách; thao tác tới trước khi đọc xong AsyncStorage thì xếp hàng. */
type HistoryOp =
  | { type: 'record'; input: HistoryInput }
  | { type: 'remove'; storyId: number }
  | { type: 'clear' };

function applyOp(list: HistoryEntry[], op: HistoryOp): HistoryEntry[] {
  if (op.type === 'clear') return [];
  if (op.type === 'remove') return list.filter((e) => e.storyId !== op.storyId);
  const { input } = op;
  const prev = list.find((e) => e.storyId === input.storyId);
  const entry: HistoryEntry = {
    ...input,
    // Giữ thông tin cũ nếu lượt ghi mới chưa có (vd. bìa tải sau nội dung chương).
    thumbnailUrl: input.thumbnailUrl ?? prev?.thumbnailUrl ?? null,
    chaptersCount: input.chaptersCount ?? prev?.chaptersCount ?? null,
    chapterTitle: input.chapterTitle ?? (prev?.chapter === input.chapter ? prev.chapterTitle : null),
    readAt: Date.now(),
  };
  return [entry, ...list.filter((e) => e.storyId !== input.storyId)].slice(0, HISTORY_LIMIT);
}

export function HistoryProvider({ children }: { children: React.ReactNode }) {
  const [entries, setEntries] = useState<HistoryEntry[]>([]);
  const [ready, setReady] = useState(false);
  // Bản mới nhất cho các callback: hai lượt ghi sát nhau không được đè mất nhau.
  const entriesRef = useRef<HistoryEntry[]>([]);
  // Thao tác tới trước khi đọc xong AsyncStorage (ghi, xoá, xoá hết) thì để dành, chạy lại
  // đúng thứ tự sau khi đọc xong. Ghi thẳng lúc đó sẽ lưu đè danh sách rỗng lên dữ liệu cũ.
  const pendingRef = useRef<HistoryOp[]>([]);
  // Bật cùng lúc xả hàng đợi, KHÔNG qua useEffect: effect của màn con chạy trước effect
  // của provider, nên chờ effect thì lượt ghi rơi vào khoảng hở sẽ kẹt mãi trong hàng đợi.
  const readyRef = useRef(false);

  const commit = useCallback((next: HistoryEntry[]) => {
    entriesRef.current = next;
    setEntries(next);
    AsyncStorage.setItem(KEY_HISTORY, JSON.stringify(next)).catch(() => {});
  }, []);

  useEffect(() => {
    let alive = true;
    AsyncStorage.getItem(KEY_HISTORY)
      // Đọc lỗi thì coi như chưa có lịch sử, nhưng vẫn giữ các thao tác đang chờ.
      .catch(() => null)
      .then((raw) => {
        if (!alive) return;
        const pending = pendingRef.current;
        pendingRef.current = [];
        const list = pending.reduce(applyOp, parse(raw));
        readyRef.current = true;
        if (pending.length > 0) commit(list);
        else {
          entriesRef.current = list;
          setEntries(list);
        }
        setReady(true);
      });
    return () => {
      alive = false;
    };
  }, [commit]);

  const run = useCallback(
    (op: HistoryOp) => {
      if (!readyRef.current) {
        pendingRef.current.push(op);
        return;
      }
      commit(applyOp(entriesRef.current, op));
    },
    [commit],
  );

  const record = useCallback((input: HistoryInput) => run({ type: 'record', input }), [run]);
  const remove = useCallback((storyId: number) => run({ type: 'remove', storyId }), [run]);
  const clear = useCallback(() => run({ type: 'clear' }), [run]);

  const value = useMemo(
    () => ({ entries, ready, record, remove, clear }),
    [entries, ready, record, remove, clear],
  );

  return <HistoryContext.Provider value={value}>{children}</HistoryContext.Provider>;
}

export function useHistory(): HistoryContextValue {
  const ctx = useContext(HistoryContext);
  if (!ctx) throw new Error('useHistory must be used within a HistoryProvider');
  return ctx;
}
