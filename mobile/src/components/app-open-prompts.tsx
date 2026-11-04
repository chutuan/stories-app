import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { LinearGradient } from 'expo-linear-gradient';
import { usePathname, useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
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
import { getHome, type StoryCard } from '@/lib/api';
import { useResponsive } from '@/lib/responsive';
import { isFinished, type HistoryEntry, useHistory } from '@/store/history';
import { openedFromNotificationRecently, useNotifications } from '@/store/notifications';

/**
 * Hai lời mời hiện ở dạng bảng trượt từ đáy, KHÔNG BAO GIỜ cùng lúc:
 *
 * 1. "Đọc tiếp" — mỗi lần mở app (mở lạnh, hoặc quay lại sau ≥ 30 phút ở nền) nếu có
 *    truyện đọc dở: truyện đọc gần nhất mà chưa mở tới chương cuối. Chỉ hiện khi đang
 *    đứng ở một tab — mở app bằng thông báo hay deep link thì người ta đang đi chỗ khác.
 * 2. "Bật thông báo truyện mới" — tối đa 3 lần, cách nhau ít nhất 3 ngày (đếm trong
 *    store/notifications.tsx), ở một trong hai lúc:
 *    - vừa rời màn đọc/nghe (họ vừa đọc thử, biết app có gì);
 *    - mở app mà không có truyện đọc dở để mời "đọc tiếp" — trừ lần mở app ĐẦU TIÊN:
 *      iOS chỉ cho xin quyền một lần, hỏi khi người ta chưa biết app là gì thì gần như
 *      chắc bị từ chối.
 *    Đã từ chối hộp xin quyền của iOS thì lời mời đổi thành "Open Settings".
 *    Bảng có bìa 3 truyện mới nhất cho người ta thấy mình sẽ nhận được gì.
 */

const TAB_PATHS = new Set(['/', '/saved', '/search', '/rewards']);
const READING_PATH = /^\/(reader|audio)\//;
/** Ở nền lâu chừng này thì quay lại tính là "mở app lần nữa". */
const RESUME_AFTER_MS = 30 * 60 * 1000;
/** Chờ màn hình dựng xong / hoạt ảnh chuyển màn chạy hết rồi mới trượt bảng lên. */
const SETTLE_MS = 900;
/** Số lần mở app lạnh — lần đầu tiên không mời bật thông báo lúc mở app. */
const KEY_LAUNCHES = 'stories:launches';

type Sheet =
  | { kind: 'continue'; entry: HistoryEntry }
  | { kind: 'notify'; denied: boolean }
  | null;

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

  const launchesRef = useRef(0);
  useEffect(() => {
    AsyncStorage.getItem(KEY_LAUNCHES)
      .catch(() => null)
      .then((raw) => {
        const n = (Number(raw) || 0) + 1;
        launchesRef.current = n;
        AsyncStorage.setItem(KEY_LAUNCHES, String(n)).catch(() => {});
      });
  }, []);

  /** Hiện lời mời bật thông báo nếu đã tới lượt (chỉ đọc ref nên không đổi giữa các lần dựng). */
  const askNotify = useCallback(() => {
    const n = notifyRef.current;
    if (!n.askDue()) return;
    n.markAsked();
    setSheet({ kind: 'notify', denied: n.permission === 'denied' });
  }, []);

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

  // 1. Mở app: đọc tiếp nếu có truyện đọc dở, không thì (từ lần mở thứ hai) mời bật thông báo.
  useEffect(() => {
    if (!historyReady) return;
    const timer = setTimeout(() => {
      if (sheetRef.current || !TAB_PATHS.has(pathRef.current)) return;
      if (openedFromNotificationRecently()) return;
      const entry = entriesRef.current.find((e) => !isFinished(e));
      if (entry) {
        setSheet({ kind: 'continue', entry });
        return;
      }
      if (launchesRef.current >= 2 || openCount > 0) askNotify();
    }, SETTLE_MS);
    return () => clearTimeout(timer);
  }, [historyReady, openCount, askNotify]);

  // 2. Bật thông báo — khi vừa rời màn đọc/nghe.
  const prevPath = useRef(pathname);
  useEffect(() => {
    const leftReading = READING_PATH.test(prevPath.current) && !READING_PATH.test(pathname);
    prevPath.current = pathname;
    if (!leftReading) return;
    const timer = setTimeout(() => {
      if (sheetRef.current || READING_PATH.test(pathRef.current)) return;
      askNotify();
    }, SETTLE_MS);
    return () => clearTimeout(timer);
  }, [pathname, askNotify]);

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
        <NotifySheet denied={sheet.denied} onTurnOn={turnOn} onClose={close} />
      ) : null}
    </Modal>
  );
}

function SheetFrame({ children }: { children: React.ReactNode }) {
  const insets = useSafeAreaInsets();
  const { sheetInset } = useResponsive();
  return (
    <View
      style={[
        styles.sheet,
        // màn rộng: bảng nổi giữa đáy màn, không trải hết bề ngang
        { left: sheetInset, right: sheetInset, paddingBottom: Math.max(insets.bottom, Spacing.lg) },
      ]}>
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

function NotifySheet({
  denied,
  onTurnOn,
  onClose,
}: {
  /** đã từ chối hộp xin quyền của iOS: chỉ bật lại được trong Cài đặt */
  denied: boolean;
  onTurnOn: () => void;
  onClose: () => void;
}) {
  // Bìa 3 truyện mới nhất: cho thấy cụ thể mỗi tối sẽ nhận được gì. null = đang tải.
  const [covers, setCovers] = useState<StoryCard[] | null>(null);
  useEffect(() => {
    let alive = true;
    getHome()
      .then((home) => {
        if (alive) setCovers(home.newest.filter((s) => s.thumbnail_url).slice(0, 3));
      })
      .catch(() => {
        if (alive) setCovers([]);
      });
    return () => {
      alive = false;
    };
  }, []);

  return (
    <SheetFrame>
      {/* Khung cao cố định: bìa tải xong hay hỏng (về chuông) thì bảng cũng không nhảy. */}
      <View style={styles.art}>
        {covers && covers.length === 3 ? (
          <CoverFan stories={covers} />
        ) : covers ? (
          <View style={styles.bell}>
            <Ionicons name="notifications" size={30} color={Palette.accentDeep} />
          </View>
        ) : null}
      </View>
      <Text style={[styles.eyebrow, styles.centered]}>A NEW STORY EVERY DAY</Text>
      <Text style={styles.title}>Never miss a new story</Text>
      <Text style={styles.body}>
        {denied
          ? 'Notifications for Stories are turned off in Settings. Turn them on to get one new story each evening — never more than one a day.'
          : 'Each evening, we\u2019ll send you one notification when a new story lands on the shelf — never more than one a day. Turn it off anytime in Library.'}
      </Text>
      <PrimaryButton
        icon={denied ? 'settings-outline' : 'notifications-outline'}
        label={denied ? 'Open Settings' : 'Turn on notifications'}
        onPress={onTurnOn}
      />
      <SecondaryButton label="Not now" onPress={onClose} />
    </SheetFrame>
  );
}

/** Ba bìa xoè ra như một tay bài, bìa giữa nổi lên trên và đeo chuông. */
function CoverFan({ stories }: { stories: StoryCard[] }) {
  return (
    <View style={styles.fan} accessible={false} importantForAccessibility="no-hide-descendants">
      {stories.map((story, i) => {
        const middle = i === 1;
        return (
          <View
            key={story.id}
            style={[
              middle ? styles.fanMiddle : styles.fanSide,
              i === 0 && styles.fanLeft,
              i === 2 && styles.fanRight,
            ]}>
            <StoryCover
              thumbnailUrl={story.thumbnail_url}
              title={story.title}
              seed={story.id}
              radius={Radius.sm}
              showRibbon={false}
              showChapters={false}
            />
            {middle ? (
              <View style={styles.fanBadge}>
                <Ionicons name="notifications" size={15} color={Palette.onAccent} />
              </View>
            ) : null}
          </View>
        );
      })}
    </View>
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
/** Bìa xoè trong lời mời bật thông báo (bìa 3:4). */
const FAN_MIDDLE = 84;
const FAN_SIDE = 70;
const FAN_OVERLAP = 16;
const ART_HEIGHT = Math.round(FAN_MIDDLE / Layout.coverAspect) + 8;

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
  centered: {
    textAlign: 'center',
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
  art: {
    height: ART_HEIGHT,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: Spacing.xs,
  },
  bell: {
    width: 64,
    height: 64,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDim,
  },
  fan: {
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  fanSide: {
    width: FAN_SIDE,
    marginBottom: Spacing.xs,
    opacity: 0.92,
  },
  fanLeft: {
    marginRight: -FAN_OVERLAP,
    transform: [{ rotate: '-8deg' }],
  },
  fanRight: {
    marginLeft: -FAN_OVERLAP,
    transform: [{ rotate: '8deg' }],
  },
  fanMiddle: {
    width: FAN_MIDDLE,
    zIndex: 1,
    ...Shadow.sheet,
  },
  fanBadge: {
    position: 'absolute',
    top: -8,
    right: -8,
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accent,
    borderWidth: 2,
    borderColor: Palette.surfaceHigh,
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
