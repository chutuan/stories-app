import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { usePathname, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { AppState, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { StoryCover } from '@/components/story-card';
import {
  FontSize,
  FontWeight,
  Gradients,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  Typography,
} from '@/constants/theme';
import { isFinished, type HistoryEntry, useHistory } from '@/store/history';
import { openedFromNotificationRecently, useNotifications } from '@/store/notifications';

/**
 * Hai lời mời hiện ở dạng bảng trượt từ đáy, KHÔNG BAO GIỜ cùng lúc:
 *
 * 1. "Đọc tiếp" — mỗi lần mở app (mở lạnh, hoặc quay lại sau ≥ 30 phút ở nền) nếu có
 *    truyện đọc dở: truyện đọc gần nhất mà chưa mở tới chương cuối. Chỉ hiện khi đang
 *    đứng ở một tab — mở app bằng thông báo hay deep link thì người ta đang đi chỗ khác.
 * 2. "Bật thông báo truyện mới" — hiện MỘT lần, lúc người dùng vừa rời màn đọc lần đầu
 *    (họ vừa đọc thử, biết app có gì). Không hỏi ngay lúc mở app: iOS chỉ cho xin quyền
 *    một lần, hỏi khi người ta chưa biết app là gì thì gần như chắc bị từ chối.
 */

const TAB_PATHS = new Set(['/', '/saved', '/search', '/rewards']);
const READING_PATH = /^\/(reader|audio)\//;
/** Ở nền lâu chừng này thì quay lại tính là "mở app lần nữa". */
const RESUME_AFTER_MS = 30 * 60 * 1000;
/** Chờ màn hình dựng xong / hoạt ảnh chuyển màn chạy hết rồi mới trượt bảng lên. */
const SETTLE_MS = 900;

type Sheet = { kind: 'continue'; entry: HistoryEntry } | { kind: 'notify' } | null;

export function AppOpenPrompts() {
  const router = useRouter();
  const pathname = usePathname();
  const { entries, ready: historyReady } = useHistory();
  const notify = useNotifications();
  const [sheet, setSheet] = useState<Sheet>(null);
  /** Tăng lên mỗi "lần mở app"; giá trị đầu là lần mở lạnh. */
  const [openCount, setOpenCount] = useState(0);

  // Bản mới nhất cho các hẹn giờ bên dưới (đọc lúc hẹn giờ chạy, không phải lúc đặt).
  const pathRef = useRef(pathname);
  const entriesRef = useRef(entries);
  const sheetRef = useRef<Sheet>(null);
  const notifyRef = useRef(notify);
  useEffect(() => {
    pathRef.current = pathname;
    entriesRef.current = entries;
    sheetRef.current = sheet;
    notifyRef.current = notify;
  });

  useEffect(() => {
    let backgroundAt = 0;
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'background') {
        backgroundAt = Date.now();
      } else if (state === 'active' && backgroundAt > 0) {
        if (Date.now() - backgroundAt >= RESUME_AFTER_MS) setOpenCount((n) => n + 1);
        backgroundAt = 0;
      }
    });
    return () => sub.remove();
  }, []);

  // 1. Đọc tiếp.
  useEffect(() => {
    if (!historyReady) return;
    const timer = setTimeout(() => {
      if (sheetRef.current || !TAB_PATHS.has(pathRef.current)) return;
      if (openedFromNotificationRecently()) return;
      const entry = entriesRef.current.find((e) => !isFinished(e));
      if (entry) setSheet({ kind: 'continue', entry });
    }, SETTLE_MS);
    return () => clearTimeout(timer);
  }, [historyReady, openCount]);

  // 2. Bật thông báo — khi vừa rời màn đọc/nghe.
  const prevPath = useRef(pathname);
  useEffect(() => {
    const leftReading = READING_PATH.test(prevPath.current) && !READING_PATH.test(pathname);
    prevPath.current = pathname;
    if (!leftReading) return;
    const timer = setTimeout(() => {
      const n = notifyRef.current;
      if (sheetRef.current || READING_PATH.test(pathRef.current)) return;
      if (!n.supported || !n.ready || n.enabled || n.prompted || n.permission !== 'undetermined') {
        return;
      }
      n.markPrompted();
      setSheet({ kind: 'notify' });
    }, SETTLE_MS);
    return () => clearTimeout(timer);
  }, [pathname]);

  const close = () => setSheet(null);

  const continueReading = (entry: HistoryEntry) => {
    setSheet(null);
    router.push(`/reader/${entry.storyId}/${entry.chapter}`);
  };

  const turnOn = () => {
    setSheet(null);
    notify.enable().catch(() => {});
  };

  return (
    <Modal
      visible={sheet !== null}
      transparent
      animationType="slide"
      statusBarTranslucent
      navigationBarTranslucent
      onRequestClose={close}
    >
      <Pressable
        style={styles.backdrop}
        onPress={close}
        accessibilityRole="button"
        accessibilityLabel="Close"
      />
      {sheet?.kind === 'continue' ? (
        <ContinueSheet
          entry={sheet.entry}
          onContinue={() => continueReading(sheet.entry)}
          onClose={close}
        />
      ) : sheet?.kind === 'notify' ? (
        <NotifySheet onTurnOn={turnOn} onClose={close} />
      ) : null}
    </Modal>
  );
}

function SheetFrame({ children }: { children: React.ReactNode }) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, Spacing.lg) }]}>
      <View style={styles.grabber} />
      {children}
    </View>
  );
}

function ContinueSheet({
  entry,
  onContinue,
  onClose,
}: {
  entry: HistoryEntry;
  onContinue: () => void;
  onClose: () => void;
}) {
  const total = entry.chaptersCount;
  const progress = total ? `Chapter ${entry.chapter} of ${total}` : `Chapter ${entry.chapter}`;
  return (
    <SheetFrame>
      <Text style={styles.eyebrow}>PICK UP WHERE YOU LEFT OFF</Text>
      <View style={styles.storyRow}>
        <StoryCover
          thumbnailUrl={entry.thumbnailUrl}
          title={entry.title}
          seed={entry.storyId}
          radius={Radius.sm}
          showRibbon={false}
          showChapters={false}
          style={styles.cover}
        />
        <View style={styles.storyBody}>
          <Text style={styles.storyTitle} numberOfLines={3}>
            {entry.title}
          </Text>
          <Text style={styles.meta} numberOfLines={1}>
            {progress}
          </Text>
          {entry.chapterTitle ? (
            <Text style={styles.chapterTitle} numberOfLines={1}>
              {entry.chapterTitle}
            </Text>
          ) : null}
          {total ? (
            <View style={styles.bar}>
              <View
                style={[styles.barFill, { width: `${Math.min(100, (entry.chapter / total) * 100)}%` }]}
              />
            </View>
          ) : null}
        </View>
      </View>
      <PrimaryButton
        icon="book"
        label={`Continue · Chapter ${entry.chapter}`}
        onPress={onContinue}
      />
      <SecondaryButton label="Not now" onPress={onClose} />
    </SheetFrame>
  );
}

function NotifySheet({ onTurnOn, onClose }: { onTurnOn: () => void; onClose: () => void }) {
  return (
    <SheetFrame>
      <View style={styles.bell}>
        <Ionicons name="notifications" size={26} color={Palette.accentDeep} />
      </View>
      <Text style={styles.title}>Get one new story a day?</Text>
      <Text style={styles.body}>
        When a new story lands on the shelf, we&apos;ll send you one notification in the evening —
        never more than one a day. You can turn it off anytime in Library.
      </Text>
      <PrimaryButton icon="notifications-outline" label="Turn on notifications" onPress={onTurnOn} />
      <SecondaryButton label="Not now" onPress={onClose} />
    </SheetFrame>
  );
}

function PrimaryButton({
  icon,
  label,
  onPress,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.primaryBtn, pressed && styles.pressed]}
    >
      <LinearGradient
        colors={Gradients.accent}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <Ionicons name={icon} size={18} color={Palette.onAccent} />
      <Text style={styles.primaryBtnText}>{label}</Text>
    </Pressable>
  );
}

function SecondaryButton({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.secondaryBtn, pressed && styles.pressed]}
    >
      <Text style={styles.secondaryBtnText}>{label}</Text>
    </Pressable>
  );
}

const COVER_WIDTH = 76;

const styles = StyleSheet.create({
  backdrop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: Palette.overlay,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
  sheet: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    gap: Spacing.md,
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.md,
    backgroundColor: Palette.surfaceHigh,
    borderTopLeftRadius: Radius.xxl,
    borderTopRightRadius: Radius.xxl,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Palette.border,
    ...Shadow.sheet,
  },
  grabber: {
    alignSelf: 'center',
    width: 40,
    height: 5,
    borderRadius: Radius.pill,
    backgroundColor: Palette.borderStrong,
    marginBottom: Spacing.xs,
  },
  eyebrow: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
    letterSpacing: 1.2,
  },
  storyRow: {
    flexDirection: 'row',
    gap: Spacing.md,
    marginBottom: Spacing.xs,
  },
  cover: {
    width: COVER_WIDTH,
  },
  storyBody: {
    flex: 1,
    gap: Spacing.xs,
    justifyContent: 'center',
  },
  storyTitle: {
    ...Typography.h2,
  },
  meta: {
    ...Typography.caption,
    fontWeight: FontWeight.semibold,
  },
  chapterTitle: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    lineHeight: LineHeight.caption,
  },
  bar: {
    height: 5,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accentDim,
    overflow: 'hidden',
    marginTop: Spacing.xxs,
  },
  barFill: {
    height: '100%',
    borderRadius: Radius.pill,
    backgroundColor: Palette.accent,
  },
  bell: {
    alignSelf: 'center',
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDim,
    marginTop: Spacing.xs,
  },
  title: {
    ...Typography.h1,
    textAlign: 'center',
  },
  body: {
    ...Typography.bodyMuted,
    textAlign: 'center',
    marginBottom: Spacing.xs,
  },
  primaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    alignSelf: 'stretch',
    height: 52,
    borderRadius: Radius.md,
    overflow: 'hidden',
  },
  primaryBtnText: {
    color: Palette.onAccent,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  secondaryBtn: {
    alignSelf: 'center',
    paddingVertical: Spacing.sm,
    paddingHorizontal: Spacing.lg,
  },
  secondaryBtnText: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
  },
});
