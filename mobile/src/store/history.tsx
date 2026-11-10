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
 *
 * Song song là SỔ TIẾN ĐỘ (`stories:progress`, storyId -> chương xa nhất từng mở) cho MỌI
 * truyện, không cắt ở HISTORY_LIMIT: nó quyết định truyện nào "đã đọc" (không lên dải nổi
 * bật) và "đã đọc hết" (không hiện trong danh sách nữa). Nếu chỉ dựa vào lịch sử 100 dòng,
 * người đọc nhiều sẽ thấy truyện đọc xong từ lâu quay lại. Từ 1.0.4; lần đầu mở bản mới thì
 * sổ được dựng từ lịch sử sẵn có.
 */

export const HISTORY_LIMIT = 100;
const KEY_HISTORY = 'stories:history';
const KEY_PROGRESS = 'stories:progress';
/**
 * Trần sổ tiến độ (~15 byte/truyện). Quá thì bỏ truyện có id NHỎ nhất (truyện lên kệ sớm nhất):
 * khoá số của object JS luôn xếp theo số tăng dần, không theo thứ tự ghi. Kho hiện ~500 truyện.
 */
const PROGRESS_LIMIT = 5000;

/** storyId -> chương xa nhất từng mở. */
export type Progress = Record<number, number>;

export interface HistoryEntry {
  storyId: number;
  title: string;
  thumbnailUrl: string | null;
  /** chương đọc gần nhất (số thứ tự) */
  chapter: number;
  chapterTitle: string | null;
  /** tổng số chương lúc đọc; null nếu chưa biết */
  chaptersCount: number | null;
  /** chương xa nhất từng mở — quay lại đọc chương cũ không làm tụt số này */
  maxChapter: number;
  /** thời điểm đọc gần nhất, ms */
  readAt: number;
}

export type HistoryInput = Omit<HistoryEntry, 'readAt' | 'maxChapter'>;

/**
 * Đã đọc hết truyện = đã mở tới chương cuối. `lastChapter`: số chương cuối HIỆN TẠI nếu
 * nơi gọi biết (truyện đang ra thì tăng dần — có chương mới là truyện "chưa hết" trở lại);
 * không có thì dùng tổng số chương lưu lúc đọc.
 */
export function isFinished(entry: HistoryEntry, lastChapter?: number | null): boolean {
  const last = lastChapter ?? entry.chaptersCount;
  return last != null && last > 0 && entry.maxChapter >= last;
}

interface HistoryContextValue {
  /** mới nhất trước */
  entries: HistoryEntry[];
  /** sổ tiến độ mọi truyện từng mở — xem đầu file */
  progress: Progress;
  ready: boolean;
  /** Ghi một lượt đọc. Gọi lặp lại cùng truyện + chương thì chỉ cập nhật, không thêm dòng. */
  record: (input: HistoryInput) => void;
  remove: (storyId: number) => void;
  clear: () => void;
}

const HistoryContext = createContext<HistoryContextValue | null>(null);

function parseProgress(raw: string | null): Progress {
  if (!raw) return {};
  try {
    const data: unknown = JSON.parse(raw);
    if (!data || typeof data !== 'object' || Array.isArray(data)) return {};
    const out: Progress = {};
    for (const [id, max] of Object.entries(data as Record<string, unknown>)) {
      if (typeof max === 'number' && Number.isFinite(max) && /^\d+$/.test(id)) out[Number(id)] = max;
    }
    return out;
  } catch {
    return {};
  }
}

/** Ghi chương xa nhất của một truyện; trả về CHÍNH object cũ nếu không có gì đổi. */
function bumpProgress(progress: Progress, storyId: number, chapter: number): Progress {
  const prev = progress[storyId];
  if (prev != null && prev >= chapter) return progress;
  const next: Progress = { ...progress, [storyId]: Math.max(prev ?? 0, chapter) };
  const ids = Object.keys(next);
  for (const old of ids.slice(0, Math.max(0, ids.length - PROGRESS_LIMIT))) delete next[Number(old)];
  return next;
}

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
        // Dòng lưu từ bản cũ chưa có trường này: chương đang đọc là thứ duy nhất biết được.
        maxChapter: typeof e.maxChapter === 'number' ? Math.max(e.maxChapter, e.chapter) : e.chapter,
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

interface HistoryState {
  entries: HistoryEntry[];
  progress: Progress;
}

function applyOp(state: HistoryState, op: HistoryOp): HistoryState {
  if (op.type === 'clear') return { entries: [], progress: {} };
  if (op.type === 'remove') {
    let progress = state.progress;
    if (progress[op.storyId] != null) {
      progress = { ...progress };
      delete progress[op.storyId];
    }
    return { entries: state.entries.filter((e) => e.storyId !== op.storyId), progress };
  }
  return {
    entries: applyRecord(state.entries, op.input),
    progress: bumpProgress(state.progress, op.input.storyId, op.input.chapter),
  };
}

function applyRecord(list: HistoryEntry[], input: HistoryInput): HistoryEntry[] {
  const prev = list.find((e) => e.storyId === input.storyId);
  const entry: HistoryEntry = {
    ...input,
    // Giữ thông tin cũ nếu lượt ghi mới chưa có (vd. bìa tải sau nội dung chương).
    thumbnailUrl: input.thumbnailUrl ?? prev?.thumbnailUrl ?? null,
    chaptersCount: input.chaptersCount ?? prev?.chaptersCount ?? null,
    chapterTitle: input.chapterTitle ?? (prev?.chapter === input.chapter ? prev.chapterTitle : null),
    maxChapter: Math.max(prev?.maxChapter ?? 0, input.chapter),
    readAt: Date.now(),
  };
  return [entry, ...list.filter((e) => e.storyId !== input.storyId)].slice(0, HISTORY_LIMIT);
}

export function HistoryProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<HistoryState>({ entries: [], progress: {} });
  const [ready, setReady] = useState(false);
  // Bản mới nhất cho các callback: hai lượt ghi sát nhau không được đè mất nhau.
  const stateRef = useRef<HistoryState>({ entries: [], progress: {} });
  // Thao tác tới trước khi đọc xong AsyncStorage (ghi, xoá, xoá hết) thì để dành, chạy lại
  // đúng thứ tự sau khi đọc xong. Ghi thẳng lúc đó sẽ lưu đè danh sách rỗng lên dữ liệu cũ.
  const pendingRef = useRef<HistoryOp[]>([]);
  // Bật cùng lúc xả hàng đợi, KHÔNG qua useEffect: effect của màn con chạy trước effect
  // của provider, nên chờ effect thì lượt ghi rơi vào khoảng hở sẽ kẹt mãi trong hàng đợi.
  const readyRef = useRef(false);

  const commit = useCallback((next: HistoryState) => {
    const prev = stateRef.current;
    stateRef.current = next;
    setState(next);
    if (next.entries !== prev.entries) {
      AsyncStorage.setItem(KEY_HISTORY, JSON.stringify(next.entries)).catch(() => {});
    }
    if (next.progress !== prev.progress) {
      AsyncStorage.setItem(KEY_PROGRESS, JSON.stringify(next.progress)).catch(() => {});
    }
  }, []);

  useEffect(() => {
    let alive = true;
    Promise.all([
      // Đọc lỗi thì coi như chưa có lịch sử, nhưng vẫn giữ các thao tác đang chờ.
      AsyncStorage.getItem(KEY_HISTORY).catch(() => null),
      AsyncStorage.getItem(KEY_PROGRESS).catch(() => null),
    ]).then(([rawHistory, rawProgress]) => {
      if (!alive) return;
      const entries = parse(rawHistory);
      // Dựng/bổ sung sổ tiến độ từ lịch sử (lần đầu chạy bản có sổ, hoặc sổ bị hỏng).
      let progress = parseProgress(rawProgress);
      const loadedProgress = progress;
      for (const e of entries) progress = bumpProgress(progress, e.storyId, e.maxChapter);
      const pending = pendingRef.current;
      pendingRef.current = [];
      const loaded: HistoryState = { entries, progress: loadedProgress };
      const next = pending.reduce(applyOp, { entries, progress });
      readyRef.current = true;
      // So với bản ĐÃ LƯU để commit biết khoá nào cần ghi lại.
      stateRef.current = loaded;
      commit(next);
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
      commit(applyOp(stateRef.current, op));
    },
    [commit],
  );

  const record = useCallback((input: HistoryInput) => run({ type: 'record', input }), [run]);
  const remove = useCallback((storyId: number) => run({ type: 'remove', storyId }), [run]);
  const clear = useCallback(() => run({ type: 'clear' }), [run]);

  const value = useMemo(
    () => ({ entries: state.entries, progress: state.progress, ready, record, remove, clear }),
    [state, ready, record, remove, clear],
  );

  return <HistoryContext.Provider value={value}>{children}</HistoryContext.Provider>;
}

export function useHistory(): HistoryContextValue {
  const ctx = useContext(HistoryContext);
  if (!ctx) throw new Error('useHistory must be used within a HistoryProvider');
  return ctx;
}

/**
 * Phần trăm đã đọc của một truyện: chương xa nhất từng mở / số chương cuối, làm tròn.
 * null = chưa mở chương nào (hoặc chưa biết số chương). Đã mở thì tối thiểu 1% — truyện
 * 300 chương mới mở chương 1 vẫn phải thấy là đã bắt đầu, không hiện "0%".
 */
export function readPercent(maxChapter: number | null | undefined, lastChapter: number | null | undefined): number | null {
  if (maxChapter == null || maxChapter <= 0 || lastChapter == null || lastChapter <= 0) return null;
  if (maxChapter >= lastChapter) return 100;
  return Math.min(99, Math.max(1, Math.round((maxChapter / lastChapter) * 100)));
}

/**
 * % đã đọc cho nhãn góc phải bìa (Tuấn yêu cầu 10/11/2026). Đọc từ SỔ TIẾN ĐỘ nên đúng cả
 * với truyện đã rơi khỏi 100 dòng lịch sử. `lastChapter`: số chương cuối hiện tại của truyện.
 */
export function useReadPercent() {
  const { progress } = useHistory();
  return useCallback(
    (storyId: number, lastChapter: number | null | undefined) => readPercent(progress[storyId], lastChapter),
    [progress],
  );
}

/**
 * Lọc khỏi danh sách những truyện người dùng đã đọc hết — không mời đọc lại thứ người ta
 * vừa đọc xong. Áp cho trang chủ, Ranking, New/Free, thể loại và lời mời bật thông báo.
 * KHÔNG áp cho Search (Tuấn quyết 10/11/2026: người dùng tìm đích danh có thể muốn đọc lại —
 * thẻ vẫn có huy hiệu ✓100%), History và Library. Truyện đang ra có thêm chương thì tự hiện lại.
 */
export function useHideFinished() {
  const { progress } = useHistory();
  return useCallback(
    <T extends { id: number; chapters_count: number; latest_chapter_number: number | null }>(
      stories: T[],
    ): T[] =>
      stories.filter((s) => {
        const max = progress[s.id];
        const last = s.latest_chapter_number ?? s.chapters_count;
        return max == null || !(last > 0 && max >= last);
      }),
    [progress],
  );
}

/** Người dùng đã mở truyện này chưa (dù chỉ một chương). Dùng cho dải nổi bật. */
export function useHasRead() {
  const { progress } = useHistory();
  return useCallback((storyId: number) => progress[storyId] != null, [progress]);
}
