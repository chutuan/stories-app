import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import type { Href } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import type { TextStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/empty-state';
import { ReaderSettingsSheet } from '@/components/reader-settings-sheet';
import { Skeleton, SkeletonText } from '@/components/skeleton';
import {
  FontSize,
  FontWeight,
  Fonts,
  Gradients,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
} from '@/constants/theme';
import { BannerAd, showRewarded } from '@/lib/ads';
import { API_URL, getStory, type Chapter } from '@/lib/api';
import { formatCoins, plural } from '@/lib/format';
import {
  ReaderPrefsProvider,
  useReaderPrefs,
  type ReaderTheme,
} from '@/store/reader-prefs';
import { COIN_PER_CHAPTER, COIN_PER_REWARD, useWallet } from '@/store/wallet';

/**
 * Tổng số chương theo truyện — chỉ gọi API 1 lần cho mỗi truyện trong 1 phiên,
 * dùng cho chỉ báo "Chương x/y" ở thanh dưới. Không có cũng không sao.
 */
const totalChaptersCache = new Map<string, number>();

/**
 * Chương kèm `audio_url` (backend đã trả field này; `null` khi chưa có MP3).
 * `src/lib/api.ts` do agent khác giữ nên ở đây tự khai báo + tự fetch,
 * tránh hai agent sửa cùng một file.
 */
interface ChapterWithAudio extends Chapter {
  audio_url: string | null;
}

async function fetchChapter(
  storyId: string | number,
  number: string | number,
): Promise<ChapterWithAudio> {
  const res = await fetch(`${API_URL}/stories/${storyId}/chapters/${number}`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    throw new Error(`API ${res.status}: /stories/${storyId}/chapters/${number}`);
  }
  return (await res.json()) as ChapterWithAudio;
}

/** Tách nội dung chương thành từng đoạn để giãn dòng đều, dễ đọc. */
function toParagraphs(content: string): string[] {
  const parts = content
    .replace(/\r\n/g, '\n')
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0);
  return parts.length > 0 ? parts : [content];
}

/**
 * Màu nhấn đọc được trên MỌI nền đọc: nền sáng dùng sắc độ đậm,
 * nền Đêm dùng sắc độ nhạt (đảo lại quy tắc của giao diện app).
 */
function tint(rt: ReaderTheme, kind: 'accent' | 'coin' | 'free'): string {
  if (kind === 'coin') return rt.dark ? Palette.coinSoft : Palette.coinDeep;
  if (kind === 'free') return rt.dark ? Palette.freeSoft : Palette.freeDeep;
  return rt.dark ? Palette.accentSoft : Palette.accentDeep;
}

/* ------------------------------------------------------------------ */
/* Route — Provider tùy chọn đọc chỉ bọc trong màn này                 */
/* ------------------------------------------------------------------ */

export default function ReaderRoute() {
  return (
    <ReaderPrefsProvider>
      <ReaderScreen />
    </ReaderPrefsProvider>
  );
}

function ReaderScreen() {
  const { storyId, number } = useLocalSearchParams<{ storyId: string; number: string }>();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { coins, addCoins, spendCoins, isUnlocked, unlock } = useWallet();
  const {
    fontSize,
    lineHeight,
    colors: rt,
    ready: prefsReady,
  } = useReaderPrefs();

  const chapterNumber = Number(number);

  /** Chương đã tải kèm số chương của nó — đổi chương là tự về trạng thái đang tải. */
  const [loaded, setLoaded] = useState<ChapterWithAudio | null>(null);
  /** Số chương tải lỗi; so với chapterNumber để biết lỗi có còn hiệu lực không. */
  const [failedNumber, setFailedNumber] = useState<number | null>(null);
  /** Tăng lên mỗi lần bấm "Thử lại" để chạy lại effect tải chương. */
  const [retryCount, setRetryCount] = useState(0);
  /** Thông báo xu, gắn số chương để không "dính" sang chương kế tiếp. */
  const [message, setMessage] = useState<{ number: number; text: string; ok: boolean } | null>(
    null,
  );
  const [watchingAd, setWatchingAd] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [totalChapters, setTotalChapters] = useState<number | null>(
    () => totalChaptersCache.get(String(storyId)) ?? null,
  );

  const scrollRef = useRef<ScrollView>(null);

  useEffect(() => {
    let alive = true;
    fetchChapter(storyId, chapterNumber)
      .then((data) => {
        if (alive) setLoaded(data);
      })
      .catch(() => {
        if (alive) setFailedNumber(chapterNumber);
      });
    return () => {
      alive = false;
    };
  }, [storyId, chapterNumber, retryCount]);

  const chapter = loaded && loaded.number === chapterNumber ? loaded : null;
  const failed = failedNumber === chapterNumber;
  // Chờ cả tùy chọn đọc để nội dung không nhảy cỡ chữ ngay sau khi hiện.
  const loading = (!chapter && !failed) || !prefsReady;

  const retry = useCallback(() => {
    setFailedNumber(null);
    setRetryCount((n) => n + 1);
  }, []);

  // Tổng số chương (không chặn màn hình, lỗi thì bỏ qua)
  useEffect(() => {
    const key = String(storyId);
    // Đã có trong cache thì state đã được khởi tạo sẵn, không cần gọi lại API.
    if (totalChaptersCache.has(key)) return;
    let alive = true;
    getStory(key)
      .then((story) => {
        totalChaptersCache.set(key, story.chapters_count);
        if (alive) setTotalChapters(story.chapters_count);
      })
      .catch(() => {});
    return () => {
      alive = false;
    };
  }, [storyId]);

  // Đổi chương thì đưa nội dung về đầu trang
  useEffect(() => {
    if (chapter) scrollRef.current?.scrollTo({ y: 0, animated: false });
  }, [chapter]);

  const unlockedLocal = isUnlocked(storyId, chapterNumber);
  const canRead = chapter ? chapter.is_free || unlockedLocal : false;

  const notify = useCallback(
    (text: string, ok: boolean) => {
      setMessage({ number: chapterNumber, text, ok });
    },
    [chapterNumber],
  );

  const handleUnlock = useCallback(() => {
    setMessage(null);
    // Trừ xu trước; spendCoins tự từ chối nếu số dư không đủ nên không bao giờ âm.
    if (spendCoins(COIN_PER_CHAPTER)) {
      unlock(storyId, chapterNumber);
    } else {
      notify('Not enough coins. Watch an ad to get more coins.', false);
    }
  }, [spendCoins, unlock, notify, storyId, chapterNumber]);

  const handleWatchAd = useCallback(async () => {
    setMessage(null);
    setWatchingAd(true);
    try {
      const result = await showRewarded();
      if (result.rewarded) {
        addCoins(COIN_PER_REWARD);
        notify(`You got +${formatCoins(COIN_PER_REWARD)}!`, true);
      } else {
        notify('You did not finish the ad. Please try again.', false);
      }
    } finally {
      setWatchingAd(false);
    }
  }, [addCoins, notify]);

  const goTo = useCallback(
    (target: number | null) => {
      if (target == null) return;
      router.replace(`/reader/${storyId}/${target}`);
    },
    [router, storyId],
  );

  const hasAudio = chapter?.audio_url != null;

  const openAudio = useCallback(() => {
    // Màn nghe do route riêng đảm nhiệm; ép kiểu vì bảng route sinh tự động
    // chỉ được cập nhật sau khi file /audio/... tồn tại trên đĩa.
    router.push(`/audio/${storyId}/${chapterNumber}` as Href);
  }, [router, storyId, chapterNumber]);

  const paragraphs = useMemo(
    () => (chapter && canRead ? toParagraphs(chapter.content) : []),
    [chapter, canRead],
  );

  const storyTitle = chapter?.story.title ?? null;
  const missingCoins = Math.max(0, COIN_PER_CHAPTER - coins);
  const notEnough = missingCoins > 0;

  const activeMessage = message && message.number === chapterNumber ? message : null;

  const banner = activeMessage
    ? { text: activeMessage.text, ok: activeMessage.ok }
    : notEnough
      ? {
          text: `You need ${plural(missingCoins, 'more coin', 'more coins')}. Watch an ad to get +${formatCoins(
            COIN_PER_REWARD,
          )}.`,
          ok: false,
        }
      : null;

  // Kiểu chữ nội dung dựng từ tùy chọn người dùng (cỡ chữ x hệ số giãn dòng).
  const bodyTextStyle: TextStyle = {
    fontSize,
    lineHeight: Math.round(fontSize * lineHeight),
    color: rt.text,
  };

  return (
    <View style={styles.screen}>
      <Stack.Screen options={{ headerShown: false }} />

      {/* Banner AdMob — BẮT BUỘC nằm trên cùng màn hình */}
      <View style={[styles.adSlot, { paddingTop: insets.top }]}>
        <BannerAd />
      </View>

      {/* Header: quay lại · tên truyện · ví xu · Aa · nghe */}
      <View style={styles.header}>
        <Pressable
          onPress={() => router.back()}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Back"
          style={({ pressed }) => [styles.iconBtn, pressed && styles.pressed]}
        >
          <Ionicons name="chevron-back" size={22} color={Palette.text} />
        </Pressable>

        <View style={styles.headerCenter}>
          {storyTitle ? (
            <Text style={styles.headerTitle} numberOfLines={1}>
              {storyTitle}
            </Text>
          ) : (
            <Skeleton width={120} height={13} radius={Radius.xs} />
          )}
        </View>

        <View style={styles.coinPill}>
          <Ionicons name="logo-usd" size={11} color={Palette.coinDeep} />
          <Text style={styles.coinText} numberOfLines={1}>
            {coins}
          </Text>
        </View>

        <Pressable
          onPress={() => setSettingsOpen(true)}
          hitSlop={6}
          accessibilityRole="button"
          accessibilityLabel="Reading settings"
          style={({ pressed }) => [styles.iconBtn, pressed && styles.pressed]}
        >
          <Text style={styles.aaLabel}>Aa</Text>
        </Pressable>

        <Pressable
          onPress={openAudio}
          disabled={!hasAudio}
          hitSlop={6}
          accessibilityRole="button"
          accessibilityLabel={hasAudio ? 'Listen to this chapter' : 'No audio for this chapter yet'}
          accessibilityState={{ disabled: !hasAudio }}
          style={({ pressed }) => [
            styles.iconBtn,
            hasAudio ? styles.iconBtnOn : styles.iconBtnOff,
            pressed && hasAudio && styles.pressed,
          ]}
        >
          <Ionicons
            name="headset"
            size={18}
            color={hasAudio ? Palette.onAccent : Palette.faint}
          />
        </Pressable>
      </View>

      {loading ? (
        <ReaderSkeleton rt={rt} />
      ) : !chapter ? (
        <View style={styles.center}>
          <EmptyState
            danger
            icon="cloud-offline-outline"
            title="Couldn't load this chapter"
            description="Check your connection and try again."
            actionLabel="Retry"
            onAction={retry}
          />
        </View>
      ) : canRead ? (
        <ScrollView
          ref={scrollRef}
          style={[styles.flex, { backgroundColor: rt.bg }]}
          contentContainerStyle={styles.readerContent}
          showsVerticalScrollIndicator={false}
        >
          {/* Đầu chương */}
          <View style={styles.chapterHead}>
            <View style={styles.chapterMeta}>
              <PageTag rt={rt} icon="reader-outline" label={`Chapter ${chapter.number}`} kind="accent" />
              {chapter.is_free ? (
                <PageTag rt={rt} icon="gift-outline" label="Free" kind="free" />
              ) : unlockedLocal ? (
                <PageTag rt={rt} icon="lock-open-outline" label="Unlocked" kind="coin" />
              ) : null}
              {hasAudio ? (
                <PageTag rt={rt} icon="headset-outline" label="Audio available" kind="accent" />
              ) : null}
            </View>

            <Text
              style={[
                styles.chapterTitle,
                { color: rt.text, fontSize: fontSize + 5, lineHeight: Math.round((fontSize + 5) * 1.35) },
              ]}
            >
              {chapter.title}
            </Text>

            <LinearGradient
              colors={Gradients.accent}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.titleRule}
            />
          </View>

          {/* Nội dung */}
          {paragraphs.map((text, i) => (
            <Text key={i} style={[styles.paragraph, bodyTextStyle]} selectable>
              {text}
            </Text>
          ))}

          {/* Kết chương */}
          <View style={styles.endMark}>
            <View style={[styles.endLine, { backgroundColor: rt.border }]} />
            <Text style={[styles.endText, { color: rt.faint }]}>
              End of Chapter {chapter.number}
            </Text>
            <View style={[styles.endLine, { backgroundColor: rt.border }]} />
          </View>
        </ScrollView>
      ) : (
        /* ---------------- MÀN KHÓA ---------------- */
        <ScrollView
          style={styles.flex}
          contentContainerStyle={[
            styles.lockScroll,
            { paddingBottom: insets.bottom + Spacing.xxl },
          ]}
          showsVerticalScrollIndicator={false}
        >
          <LinearGradient
            colors={Gradients.hero}
            start={{ x: 0.5, y: 0 }}
            end={{ x: 0.5, y: 1 }}
            style={styles.lockCard}
          >
            <LinearGradient
              colors={Gradients.accent}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.lockCircle}
            >
              <Ionicons name="lock-closed" size={34} color={Palette.onAccent} />
            </LinearGradient>

            <View style={styles.lockChip}>
              <Text style={styles.lockChipText}>Chapter {chapter.number}</Text>
            </View>

            <Text style={styles.lockTitle} numberOfLines={3}>
              {chapter.title}
            </Text>
            <Text style={styles.lockSubtitle}>
              This chapter is locked. Unlock it once for {formatCoins(COIN_PER_CHAPTER)} and read it
              forever.
            </Text>

            {banner ? (
              <View style={[styles.banner, banner.ok ? styles.bannerOk : styles.bannerWarn]}>
                <Ionicons
                  name={banner.ok ? 'checkmark-circle' : 'alert-circle'}
                  size={16}
                  color={banner.ok ? Palette.freeDeep : Palette.coinDeep}
                />
                <Text
                  style={[
                    styles.bannerText,
                    { color: banner.ok ? Palette.freeDeep : Palette.coinDeep },
                  ]}
                >
                  {banner.text}
                </Text>
              </View>
            ) : null}

            <Pressable
              onPress={handleUnlock}
              accessibilityRole="button"
              accessibilityLabel={`Unlock chapter for ${formatCoins(COIN_PER_CHAPTER)}`}
              style={({ pressed }) => [
                styles.primaryBtn,
                notEnough && styles.primaryBtnDim,
                pressed && styles.pressed,
              ]}
            >
              <LinearGradient
                colors={Gradients.accent}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={StyleSheet.absoluteFill}
              />
              <Ionicons name="lock-open" size={18} color={Palette.onAccent} />
              <Text style={styles.primaryBtnText}>Unlock · {formatCoins(COIN_PER_CHAPTER)}</Text>
            </Pressable>

            <Pressable
              onPress={handleWatchAd}
              disabled={watchingAd}
              accessibilityRole="button"
              accessibilityLabel={`Watch an ad to get ${formatCoins(COIN_PER_REWARD)}`}
              style={({ pressed }) => [
                styles.adBtn,
                watchingAd && styles.adBtnBusy,
                pressed && !watchingAd && styles.pressed,
              ]}
            >
              {watchingAd ? (
                <ActivityIndicator color={Palette.coinDeep} size="small" />
              ) : (
                <>
                  <Ionicons name="play-circle" size={18} color={Palette.coinDeep} />
                  <Text style={styles.adBtnText}>Watch ad · +{formatCoins(COIN_PER_REWARD)}</Text>
                </>
              )}
            </Pressable>

            <View style={styles.walletHint}>
              <Ionicons name="logo-usd" size={13} color={Palette.coinDeep} />
              <Text style={styles.walletHintText}>
                Balance: <Text style={styles.walletHintValue}>{formatCoins(coins)}</Text>
              </Text>
            </View>
          </LinearGradient>
        </ScrollView>
      )}

      {/* Thanh điều hướng chương — hiện cả ở MÀN KHÓA để không bị cụt đường
          (chương khóa vẫn nhảy được sang chương trước/sau). */}
      {chapter && !loading ? (
        <View style={[styles.bottomBar, { paddingBottom: Math.max(insets.bottom, Spacing.md) }]}>
          <NavButton
            label="Previous"
            direction="prev"
            disabled={chapter.prev == null}
            onPress={() => goTo(chapter.prev)}
          />

          <View style={styles.progress}>
            <Text style={styles.progressLabel}>CHAPTER</Text>
            <Text style={styles.progressValue}>
              {totalChapters != null
                ? `${chapter.number}/${totalChapters}`
                : String(chapter.number)}
            </Text>
          </View>

          <NavButton
            label="Next"
            direction="next"
            disabled={chapter.next == null}
            onPress={() => goTo(chapter.next)}
          />
        </View>
      ) : null}

      {/* Bảng cài đặt đọc */}
      <ReaderSettingsSheet visible={settingsOpen} onClose={() => setSettingsOpen(false)} />
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Pill nhỏ ở đầu chương — màu bám theo NỀN ĐỌC, không dùng Chip của   */
/* app (Chip luôn tính màu theo nền sáng, sẽ chìm trên nền Đêm).       */
/* ------------------------------------------------------------------ */

function PageTag({
  rt,
  icon,
  label,
  kind,
}: {
  rt: ReaderTheme;
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  kind: 'accent' | 'coin' | 'free';
}) {
  const fg = tint(rt, kind);
  return (
    <View style={[styles.pageTag, { backgroundColor: rt.surface, borderColor: rt.border }]}>
      <Ionicons name={icon} size={11} color={fg} />
      <Text style={[styles.pageTagText, { color: fg }]}>{label}</Text>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Nút chuyển chương                                                   */
/* ------------------------------------------------------------------ */

interface NavButtonProps {
  label: string;
  direction: 'prev' | 'next';
  disabled: boolean;
  onPress: () => void;
}

function NavButton({ label, direction, disabled, onPress }: NavButtonProps) {
  const isNext = direction === 'next';
  const color = disabled ? Palette.faint : isNext ? Palette.onAccent : Palette.text;

  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [
        styles.navBtn,
        disabled && styles.navBtnDisabled,
        pressed && !disabled && styles.pressed,
      ]}
    >
      {isNext && !disabled ? (
        <LinearGradient
          colors={Gradients.accent}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      ) : null}
      {!isNext ? <Ionicons name="chevron-back" size={16} color={color} /> : null}
      <Text style={[styles.navText, { color }]} numberOfLines={1}>
        {label}
      </Text>
      {isNext ? <Ionicons name="chevron-forward" size={16} color={color} /> : null}
    </Pressable>
  );
}

/* ------------------------------------------------------------------ */
/* Khung xương khi đang tải                                            */
/* ------------------------------------------------------------------ */

function ReaderSkeleton({ rt }: { rt: ReaderTheme }) {
  return (
    <View style={[styles.skeletonWrap, { backgroundColor: rt.bg }]}>
      <View style={styles.skeletonMeta}>
        <Skeleton width={82} height={20} radius={Radius.pill} />
        <Skeleton width={68} height={20} radius={Radius.pill} />
      </View>
      <Skeleton width="85%" height={20} radius={Radius.xs} />
      <Skeleton width={44} height={3} radius={Radius.pill} style={styles.skeletonRule} />
      <SkeletonText lines={5} lineHeight={13} gap={Spacing.md} lastLineWidth="72%" />
      <SkeletonText
        lines={4}
        lineHeight={13}
        gap={Spacing.md}
        lastLineWidth="55%"
        style={styles.skeletonBlock}
      />
      <SkeletonText
        lines={4}
        lineHeight={13}
        gap={Spacing.md}
        lastLineWidth="80%"
        style={styles.skeletonBlock}
      />
    </View>
  );
}

/* ------------------------------------------------------------------ */

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  flex: {
    flex: 1,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  // --- Quảng cáo trên cùng ---
  adSlot: {
    backgroundColor: Palette.bgDeep,
  },

  // --- Header (luôn là "khung app" nền sáng vì nằm ngay dưới banner quảng cáo) ---
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs + 2,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm,
    backgroundColor: Palette.bgDeep,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  iconBtn: {
    width: 36,
    height: 36,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  iconBtnOn: {
    backgroundColor: Palette.accentDeep,
    borderColor: 'transparent',
  },
  iconBtnOff: {
    opacity: 0.45,
  },
  aaLabel: {
    color: Palette.accentDeep,
    fontSize: 14,
    fontWeight: FontWeight.black,
  },
  headerCenter: {
    flex: 1,
    justifyContent: 'center',
  },
  headerTitle: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small,
  },
  coinPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    paddingHorizontal: Spacing.sm,
    height: 26,
    borderRadius: Radius.pill,
    backgroundColor: Palette.coinDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.coinBorder,
  },
  coinText: {
    color: Palette.coinDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },

  // --- Trạng thái rỗng / lỗi ---
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },

  // --- Nội dung đọc ---
  readerContent: {
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.xl,
    paddingBottom: Spacing.xxl,
  },
  chapterHead: {
    marginBottom: Spacing.xl,
  },
  chapterMeta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: Spacing.sm,
    marginBottom: Spacing.md,
  },
  pageTag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    paddingHorizontal: Spacing.sm,
    paddingVertical: 3,
    borderRadius: Radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
  },
  pageTagText: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
  },
  chapterTitle: {
    fontWeight: FontWeight.black,
  },
  titleRule: {
    width: 44,
    height: 3,
    borderRadius: Radius.pill,
    marginTop: Spacing.md,
  },
  paragraph: {
    fontFamily: Fonts.serif,
    fontWeight: FontWeight.regular,
    marginBottom: Spacing.lg,
  },
  endMark: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    marginTop: Spacing.sm,
  },
  endLine: {
    flex: 1,
    height: StyleSheet.hairlineWidth,
  },
  endText: {
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    letterSpacing: 0.4,
  },

  // --- Thanh dưới ---
  bottomBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.md,
    backgroundColor: Palette.bgDeep,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Palette.border,
  },
  navBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.xs,
    height: 46,
    paddingHorizontal: Spacing.sm,
    borderRadius: Radius.md,
    overflow: 'hidden',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  navBtnDisabled: {
    backgroundColor: Palette.surface,
    borderColor: 'transparent',
    opacity: 0.55,
  },
  navText: {
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
  },
  progress: {
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 58,
    paddingHorizontal: Spacing.sm,
  },
  progressLabel: {
    color: Palette.faint,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.bold,
    letterSpacing: 0.8,
  },
  progressValue: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.black,
  },

  // --- Màn khóa ---
  lockScroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.xxl,
  },
  lockCard: {
    alignItems: 'center',
    gap: Spacing.md,
    padding: Spacing.xxl,
    borderRadius: Radius.xl,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.borderStrong,
    ...Shadow.card,
  },
  lockCircle: {
    width: 84,
    height: 84,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: Spacing.xs,
  },
  lockChip: {
    paddingHorizontal: Spacing.md,
    paddingVertical: 4,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accentDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.accentBorder,
  },
  lockChipText: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },
  lockTitle: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h2,
    textAlign: 'center',
  },
  lockSubtitle: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.body,
    textAlign: 'center',
  },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    alignSelf: 'stretch',
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm + 2,
    borderRadius: Radius.md,
    borderWidth: StyleSheet.hairlineWidth,
  },
  bannerWarn: {
    backgroundColor: Palette.coinDim,
    borderColor: Palette.coinBorder,
  },
  bannerOk: {
    backgroundColor: Palette.freeDim,
    borderColor: Palette.freeBorder,
  },
  bannerText: {
    flex: 1,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption + 2,
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
    marginTop: Spacing.xs,
  },
  primaryBtnDim: {
    opacity: 0.55,
  },
  primaryBtnText: {
    color: Palette.onAccent,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  adBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    alignSelf: 'stretch',
    height: 52,
    borderRadius: Radius.md,
    backgroundColor: Palette.coinDim,
    borderWidth: 1.5,
    borderColor: Palette.coinBorder,
  },
  adBtnBusy: {
    opacity: 0.7,
  },
  adBtnText: {
    color: Palette.coinDeep,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  walletHint: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    marginTop: Spacing.xs,
  },
  walletHintText: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
  },
  walletHintValue: {
    color: Palette.coinDeep,
    fontWeight: FontWeight.bold,
  },

  // --- Skeleton ---
  skeletonWrap: {
    flex: 1,
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.xl,
    gap: Spacing.md,
  },
  skeletonMeta: {
    flexDirection: 'row',
    gap: Spacing.sm,
  },
  skeletonRule: {
    marginBottom: Spacing.sm,
  },
  skeletonBlock: {
    marginTop: Spacing.lg,
  },
});
