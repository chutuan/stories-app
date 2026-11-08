import { MaterialCommunityIcons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { FontSize, FontWeight, Layout, LineHeight, Palette, Radius, Spacing } from '@/constants/theme';
import { reactToChapter, type ReactionTotals } from '@/lib/api';
import type { ReaderTheme } from '@/store/reader-prefs';

/**
 * NĂM KHUÔN MẶT CUỐI CHƯƠNG — cùng thang 1–5 và cùng bảng phiếu với web
 * (backend: Api\ChapterReactionController, Web\ReactionController).
 *
 * App không có tài khoản: lần bấm đầu tiên sinh một mã ngẫu nhiên, giữ trong máy
 * (`stories:reader-id`) và gửi kèm mỗi phiếu để bấm lại là ĐỔI phiếu chứ không thêm phiếu.
 * Server chỉ lưu bản băm của mã đó. Phiếu của chính máy này nhớ ở `stories:reactions`.
 *
 * KHÁC WEB một chỗ, có chủ đích: bấm xong năm khuôn mặt VẪN Ở NGUYÊN, chỉ tô mặt đã chọn.
 * Ngay dưới khối này là ô quảng cáo cuối chương — nếu khối co lại sau khi bấm (như web thay
 * bằng lời cảm ơn), ô quảng cáo trượt lên đúng chỗ ngón tay vừa chạm: bấm nhầm quảng cáo,
 * AdMob tính là click không hợp lệ. Mọi dòng chữ ở đây đều một dòng để chiều cao không đổi.
 *
 * Không có ô chữ nào: chỉ là một con số, không phải nội dung do người dùng tạo (Guideline 1.2).
 */

const KEY_READER_ID = 'stories:reader-id';
const KEY_VOTES = 'stories:reactions';
/** Giữ phiếu của chừng này chương gần nhất; quá thì bỏ phiếu cũ nhất (chỉ là để tô mặt đã chọn). */
const MAX_VOTES = 3000;

const FACES = [
  { score: 1, label: 'Hated it', icon: 'emoticon-angry-outline', on: 'emoticon-angry' },
  { score: 2, label: 'Not for me', icon: 'emoticon-sad-outline', on: 'emoticon-sad' },
  { score: 3, label: 'It was ok', icon: 'emoticon-neutral-outline', on: 'emoticon-neutral' },
  { score: 4, label: 'Liked it', icon: 'emoticon-happy-outline', on: 'emoticon-happy' },
  { score: 5, label: 'Loved it', icon: 'emoticon-excited-outline', on: 'emoticon-excited' },
] as const;

/* ------------------------------------------------------------------ */
/* Lưu trong máy                                                        */
/* ------------------------------------------------------------------ */

let votesPromise: Promise<Record<string, number>> | null = null;

function loadVotes(): Promise<Record<string, number>> {
  votesPromise ??= AsyncStorage.getItem(KEY_VOTES)
    .then((raw) => {
      const data: unknown = raw ? JSON.parse(raw) : {};
      return data && typeof data === 'object' ? (data as Record<string, number>) : {};
    })
    .catch(() => ({}));
  return votesPromise;
}

async function saveVote(key: string, score: number): Promise<void> {
  const votes = await loadVotes();
  // Xoá rồi gán lại để khoá vừa bấm nằm cuối — thứ tự chèn của object là thứ tự "mới nhất".
  delete votes[key];
  votes[key] = score;
  const keys = Object.keys(votes);
  for (const old of keys.slice(0, Math.max(0, keys.length - MAX_VOTES))) delete votes[old];
  await AsyncStorage.setItem(KEY_VOTES, JSON.stringify(votes)).catch(() => {});
}

let readerIdPromise: Promise<string> | null = null;

/** Mã ngẫu nhiên của máy này. Không cần ngẫu nhiên mật mã: chỉ để phân biệt hai máy. */
function getReaderId(): Promise<string> {
  readerIdPromise ??= AsyncStorage.getItem(KEY_READER_ID)
    .catch(() => null)
    .then((stored) => {
      if (stored && /^[A-Za-z0-9_-]{16,64}$/.test(stored)) return stored;
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let id = '';
      for (let i = 0; i < 32; i++) id += chars[Math.floor(Math.random() * chars.length)];
      AsyncStorage.setItem(KEY_READER_ID, id).catch(() => {});
      return id;
    });
  return readerIdPromise;
}

/* ------------------------------------------------------------------ */
/* Khối giao diện                                                       */
/* ------------------------------------------------------------------ */

/** Màu nhấn đọc được trên mọi nền đọc (nền Đêm dùng sắc độ nhạt). */
function accent(rt: ReaderTheme): string {
  return rt.dark ? Palette.accentSoft : Palette.accentDeep;
}

export function ChapterReaction({
  storyId,
  chapterNumber,
  totals: initialTotals,
  rt,
}: {
  storyId: number;
  chapterNumber: number;
  /** từ API chương; undefined khi server cũ chưa trả */
  totals?: ReactionTotals;
  rt: ReaderTheme;
}) {
  const key = `${storyId}:${chapterNumber}`;
  const [mine, setMine] = useState<number | null>(null);
  const [totals, setTotals] = useState<ReactionTotals | undefined>(initialTotals);
  const [failed, setFailed] = useState(false);
  const sending = useRef(false);

  useEffect(() => {
    let alive = true;
    loadVotes().then((votes) => {
      if (alive && typeof votes[key] === 'number') setMine(votes[key]);
    });
    return () => {
      alive = false;
    };
  }, [key]);

  const vote = useCallback(
    async (score: number) => {
      if (sending.current || score === mine) return;
      sending.current = true;
      const previous = mine;
      setMine(score);
      setFailed(false);
      try {
        const res = await reactToChapter(storyId, chapterNumber, score, await getReaderId());
        setTotals({ count: res.count, average: res.average });
        await saveVote(key, score);
      } catch {
        setMine(previous);
        setFailed(true);
      } finally {
        sending.current = false;
      }
    },
    [mine, storyId, chapterNumber, key],
  );

  const tint = accent(rt);
  const note = failed
    ? "Couldn't send your rating. Please try again."
    : totals?.average != null
      ? `${totals.average.toFixed(1)}/5 from ${totals.count} readers`
      : mine != null
        ? 'Tap another face to change your rating.'
        : totals?.count === 0
          ? 'Be the first to rate this chapter.'
          : 'One tap, no account needed.';

  return (
    <View
      style={[styles.card, { backgroundColor: rt.surface, borderColor: rt.border }]}
      accessibilityRole="radiogroup"
      accessibilityLabel="Rate this chapter">
      <Text style={[styles.title, { color: mine != null ? tint : rt.text }]} numberOfLines={1}>
        {mine != null ? 'Thanks for rating this chapter.' : 'How was this chapter?'}
      </Text>

      <View style={styles.faces}>
        {FACES.map((face) => {
          const selected = mine === face.score;
          return (
            <Pressable
              key={face.score}
              onPress={() => vote(face.score)}
              hitSlop={4}
              accessibilityRole="radio"
              accessibilityLabel={face.label}
              accessibilityState={{ selected }}
              style={({ pressed }) => [styles.face, pressed && styles.pressed]}>
              <View
                style={[
                  styles.faceIcon,
                  selected && { backgroundColor: rt.dark ? 'rgba(255,177,133,0.18)' : Palette.accentDim },
                ]}>
                <MaterialCommunityIcons
                  name={selected ? face.on : face.icon}
                  size={32}
                  color={selected ? tint : rt.muted}
                />
              </View>
              <Text
                style={[styles.faceLabel, { color: selected ? tint : rt.faint }, selected && styles.faceLabelOn]}
                numberOfLines={1}>
                {face.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <Text style={[styles.note, { color: failed ? Palette.danger : rt.faint }]} numberOfLines={1}>
        {note}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    marginTop: Spacing.xl,
    paddingVertical: Spacing.lg,
    paddingHorizontal: Spacing.md,
    borderRadius: Radius.card,
    borderWidth: StyleSheet.hairlineWidth,
    gap: Spacing.md,
  },
  title: {
    fontSize: FontSize.body,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.body,
    textAlign: 'center',
  },
  faces: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  face: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.xs,
  },
  faceIcon: {
    width: 48,
    height: 48,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  faceLabel: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
  },
  faceLabelOn: {
    fontWeight: FontWeight.bold,
  },
  note: {
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    textAlign: 'center',
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
});
