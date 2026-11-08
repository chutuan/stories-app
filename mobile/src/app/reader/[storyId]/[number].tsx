import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import type { Href } from 'expo-router';
import { Fragment, memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  PixelRatio,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import type { TextStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ChapterReaction } from '@/components/chapter-reaction';
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
import { BannerAd, InlineAd, showRewarded } from '@/lib/ads';
import { API_URL, ApiError, getStory, type Chapter } from '@/lib/api';
import { formatCoins, plural } from '@/lib/format';
import { SHEET_MAX_WIDTH, useResponsive } from '@/lib/responsive';
import {
  ReaderPrefsProvider,
  useReaderPrefs,
  type ReaderTheme,
} from '@/store/reader-prefs';
import { useAppConfig } from '@/store/config';
import { useHistory } from '@/store/history';
import { AD_REWARD, useRewards } from '@/store/rewards';
import { COIN_PER_CHAPTER, useWallet } from '@/store/wallet';

/**
 * Thông tin truyện — chỉ gọi API 1 lần cho mỗi truyện trong 1 phiên. Dùng cho chỉ báo
 * "Chương x/y" ở thanh dưới và cho bìa trong lịch sử đọc. Không có cũng không sao.
 */
const storyMetaCache = new Map<string, { chaptersCount: number; thumbnailUrl: string | null }>();

/** Ô trong thân chương phải ra khỏi màn hình thêm chừng này (pt) thì banner trên mới gắn lại. */
const MID_AD_SLACK = 80;

/**
 * Chỗ đặt ô quảng cáo 300×250 TRONG THÂN chương: chỉ số đoạn mà ô nằm ngay sau.
 *
 *  - dưới 600 từ: không ô nào (chỉ còn ô cuối chương) — quảng cáo sau vài câu mở đầu là cắt
 *    ngang mạch đọc, AdMob/AdSense đều phạt kiểu chèn đó;
 *  - 600–1.199 từ: một ô quanh 40% chương (như bản ≤ 1.0.3);
 *  - từ 1.200 từ (≈85% số chương, trung vị ~1.800): HAI ô quanh 1/3 và 2/3 — Tuấn thêm
 *    ô thứ hai từ 1.0.4.
 *
 * Hai ô luôn cách nhau ít nhất MIN_WORDS_BETWEEN_ADS từ (đoạn rất dài không đẩy hai ô sát
 * nhau), và không bao giờ đặt sau đoạn cuối: chỗ đó đã có ô cuối chương.
 */
const MIN_WORDS_BETWEEN_ADS = 300;

function inContentAdSlots(paragraphs: string[]): number[] {
  const counts = paragraphs.map((p) => p.split(/\s+/).filter(Boolean).length);
  const total = counts.reduce((a, b) => a + b, 0);
  const marks = total >= 1200 ? [1 / 3, 2 / 3] : total >= 600 ? [0.4] : [];
  const slots: number[] = [];
  let acc = 0;
  let lastAt = 0;
  for (let i = 0; i < counts.length - 1 && slots.length < marks.length; i++) {
    acc += counts[i];
    if (acc >= total * marks[slots.length] && (slots.length === 0 || acc - lastAt >= MIN_WORDS_BETWEEN_ADS)) {
      slots.push(i);
      lastAt = acc;
    }
  }
  return slots;
}

/**
 * Bề rộng cột chữ trên màn rộng tính theo CỠ CHỮ (em), không cố định theo pt: chừng
 * 75 ký tự mỗi dòng ở mọi cỡ — dòng dài hơn thì mắt khó dò về đầu dòng sau. Chữ to
 * thì cột rộng ra theo, như Apple Books. iPhone hẹp hơn mức này nên không đổi gì.
 */
const READER_MEASURE_EM = 32;

function readerColumnWidth(fontSize: number): number {
  return Math.round(fontSize * READER_MEASURE_EM) + Spacing.xl * 2;
}

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
    throw new ApiError(res.status, `/stories/${storyId}/chapters/${number}`);
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
  const { width } = useResponsive();
  const router = useRouter();
  const { coins, spendCoins, isUnlocked, unlock } = useWallet();
  // Sổ cái quảng cáo dùng CHUNG với tab Phần thưởng (bộ đếm "hôm nay đã xem mấy lượt").
  const {
    recordAdWatch,
    recordAdFallback,
    ready: rewardsReady,
  } = useRewards();
  const { ad_fallback: adFallback } = useAppConfig();
  const { record: recordHistory, remove: removeHistory } = useHistory();
  // Số dư lúc quảng cáo ĐÓNG mới là số dư đúng để tính phần thiếu, không phải số
  // dư lúc bấm nút — nên đọc qua ref sau `await`, không đọc biến trong closure.
  const coinsRef = useRef(coins);
  useEffect(() => {
    coinsRef.current = coins;
  }, [coins]);
  const {
    fontSize,
    lineHeight,
    colors: rt,
    ready: prefsReady,
  } = useReaderPrefs();
  const columnWidth = readerColumnWidth(fontSize);
  /** lề trái/phải để thanh Previous/Next thẳng hàng với cột chữ */
  const columnInset = Math.max(Spacing.screen, Math.round((width - columnWidth) / 2));

  const chapterNumber = Number(number);

  /** Chương đã tải kèm số chương của nó — đổi chương là tự về trạng thái đang tải. */
  const [loaded, setLoaded] = useState<ChapterWithAudio | null>(null);
  /**
   * Chương tải lỗi; so `number` với chapterNumber để biết lỗi có còn hiệu lực không.
   * `gone`: API trả 404 — chương/truyện đã bị gỡ, thử lại cũng vô ích.
   */
  const [failure, setFailure] = useState<{ number: number; gone: boolean } | null>(null);
  /** Tăng lên mỗi lần bấm "Thử lại" để chạy lại effect tải chương. */
  const [retryCount, setRetryCount] = useState(0);
  /** Thông báo xu, gắn số chương để không "dính" sang chương kế tiếp. */
  const [message, setMessage] = useState<{ number: number; text: string; ok: boolean } | null>(
    null,
  );
  const [watchingAd, setWatchingAd] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [storyMeta, setStoryMeta] = useState(() => storyMetaCache.get(String(storyId)) ?? null);
  const totalChapters = storyMeta?.chaptersCount ?? null;

  const scrollRef = useRef<ScrollView>(null);

  /**
   * Banner trên cùng được gỡ khi một ô quảng cáo trong thân chương lọt vào màn hình: hai quảng cáo
   * trong cùng một khung nhìn là kiểu AdMob không khuyến khích. Gắn số chương như `message`
   * để sang chương mới thì banner tự hiện lại. Gỡ hẳn (unmount), KHÔNG ẩn bằng opacity —
   * banner bị ẩn vẫn tính lượt hiển thị, đó mới là vi phạm chính sách.
   */
  const [midAdOnScreen, setMidAdOnScreen] = useState<number | null>(null);
  const topBannerHidden = midAdOnScreen === chapterNumber;
  /** Chiều cao banner lúc còn hiện. Giữ đúng khoảng này khi gỡ và khi gắn lại (banner mới
   *  tải chậm), để header không nhảy lên xuống dưới ngón tay — lỗi bấm nhầm đã từng gặp. */
  const [bannerHeight, setBannerHeight] = useState(0);
  const scrollY = useRef(0);
  const viewportH = useRef(0);
  /**
   * Vị trí các ô trong thân chương (theo thứ tự ô) trong nội dung cuộn; `chapter` để không
   * dùng nhầm số đo của chương trước.
   */
  const midAdBoxes = useRef<{ chapter: number; boxes: Record<number, { y: number; h: number }> }>({
    chapter: -1,
    boxes: {},
  });

  const syncTopBanner = useCallback(() => {
    const { chapter: measured, boxes } = midAdBoxes.current;
    let onScreen = false;
    if (measured === chapterNumber && viewportH.current > 0) {
      // Đã gỡ thì phải cuộn ô ra xa thêm một đoạn mới gắn lại: không có độ trễ này,
      // cuộn lắc quanh mép sẽ gỡ/gắn liên tục, mỗi lần gắn là một lượt xin quảng cáo mới.
      const slack = topBannerHidden ? MID_AD_SLACK : 0;
      onScreen = Object.values(boxes).some(
        (box) =>
          box.h > 0 &&
          box.y - slack < scrollY.current + viewportH.current &&
          box.y + box.h + slack > scrollY.current,
      );
    }
    setMidAdOnScreen(onScreen ? chapterNumber : null);
  }, [chapterNumber, topBannerHidden]);

  useEffect(() => {
    let alive = true;
    fetchChapter(storyId, chapterNumber)
      .then((data) => {
        if (alive) setLoaded(data);
      })
      .catch((e) => {
        if (alive) {
          setFailure({ number: chapterNumber, gone: e instanceof ApiError && e.status === 404 });
        }
      });
    return () => {
      alive = false;
    };
  }, [storyId, chapterNumber, retryCount]);

  const chapter = loaded && loaded.number === chapterNumber ? loaded : null;
  const failed = failure?.number === chapterNumber;
  const gone = failed && failure.gone;
  // Chờ cả tùy chọn đọc để nội dung không nhảy cỡ chữ ngay sau khi hiện.
  const loading = (!chapter && !failed) || !prefsReady;

  const retry = useCallback(() => {
    setFailure(null);
    setRetryCount((n) => n + 1);
  }, []);

  // Mở từ deep link thì màn đọc là route duy nhất: back() không có chỗ để lùi về.
  const goBack = useCallback(() => {
    if (router.canGoBack()) router.back();
    else router.replace('/');
  }, [router]);

  // Tổng số chương (không chặn màn hình, lỗi thì bỏ qua)
  useEffect(() => {
    const key = String(storyId);
    // Đã có trong cache thì state đã được khởi tạo sẵn, không cần gọi lại API — trừ khi
    // chương vừa trả 404: hỏi lại xem cả truyện còn không (truyện có thể vừa bị gỡ).
    if (storyMetaCache.has(key) && !gone) return;
    let alive = true;
    getStory(key)
      .then((story) => {
        const meta = { chaptersCount: story.chapters_count, thumbnailUrl: story.thumbnail_url };
        storyMetaCache.set(key, meta);
        if (alive) setStoryMeta(meta);
      })
      .catch((e) => {
        // Truyện đã bị gỡ khỏi server: bỏ khỏi lịch sử để History không dẫn vào màn lỗi.
        if (e instanceof ApiError && e.status === 404) {
          storyMetaCache.delete(key);
          if (alive) setStoryMeta(null);
          removeHistory(Number(key));
        }
      });
    return () => {
      alive = false;
    };
  }, [storyId, gone, removeHistory]);

  // Đổi chương thì đưa nội dung về đầu trang
  useEffect(() => {
    if (chapter) scrollRef.current?.scrollTo({ y: 0, animated: false });
  }, [chapter]);

  const unlockedLocal = isUnlocked(storyId, chapterNumber);
  const canRead = chapter ? chapter.is_free || unlockedLocal : false;

  // Lịch sử đọc: chỉ ghi khi chương ĐỌC ĐƯỢC đã hiện ra (không ghi màn khoá). Chạy lại
  // khi bìa/số chương tải xong để điền vào dòng lịch sử; ghi lặp cùng chương chỉ cập nhật.
  useEffect(() => {
    if (!chapter || !canRead) return;
    recordHistory({
      storyId: chapter.story.id,
      title: chapter.story.title,
      thumbnailUrl: storyMeta?.thumbnailUrl ?? null,
      chapter: chapter.number,
      chapterTitle: chapter.title,
      chaptersCount: storyMeta?.chaptersCount ?? null,
    });
  }, [chapter, canRead, storyMeta, recordHistory]);

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
    if (watchingAd) return;
    setMessage(null);
    setWatchingAd(true);
    try {
      const { rewarded, unavailable } = await showRewarded();

      // KHÔNG CHIẾU ĐƯỢC QUẢNG CÁO (no-fill, chặn quảng cáo, lỗi mạng tới Google).
      //
      // Bản 1.0(3) bị Apple từ chối theo Guideline 2.1(a) vì người mới cài không có
      // xu và AdMob không trả quảng cáo -> không mở nổi chương nào. Bản 1.0(4)–(7)
      // sửa bằng cách cấp +90 mỗi lần không có quảng cáo, nhưng thế thì ai chặn
      // quảng cáo cũng bấm liên tục lấy xu mà không xem gì.
      //
      // Giờ chỉ cấp bù khi người đọc THỰC SỰ KẸT: đang đứng ở chương khoá và số dư
      // không đủ mở nó. Cấp vừa đủ một chương, tối đa `daily_limit` lần/ngày, bật/tắt
      // từ trang admin. Áp dụng như nhau cho mọi người — không có "chế độ duyệt".
      // Cấp bù không tính là một lượt xem quảng cáo.
      if (unavailable) {
        const shortfall = Math.max(0, COIN_PER_CHAPTER - coinsRef.current);
        if (shortfall === 0) {
          notify(
            `No ad is available right now. You already have enough coins to unlock this chapter.`,
            false,
          );
          return;
        }
        const granted = adFallback.enabled
          ? recordAdFallback(shortfall, adFallback.daily_limit)
          : 0;
        if (granted > 0) {
          notify(
            `No ad was available — we added +${formatCoins(granted)} so you can unlock this chapter.`,
            true,
          );
        } else {
          notify(
            'No ad is available right now. Keep reading to earn free coins on the Rewards tab, or try again later.',
            false,
          );
        }
        return;
      }
      if (!rewarded) {
        notify('You need to watch the whole ad to earn coins.', false);
        return;
      }
      // Xu thưởng chỉ cộng qua recordAdWatch — nơi duy nhất đếm lượt cho tab Phần thưởng.
      // Không giới hạn lượt (từ 1.0.4).
      const got = recordAdWatch();
      if (got > 0) notify(`You got +${formatCoins(got)}!`, true);
    } catch {
      notify('Could not load the ad, please try again later.', false);
    } finally {
      setWatchingAd(false);
    }
  }, [watchingAd, recordAdWatch, recordAdFallback, adFallback, notify]);

  const goTo = useCallback(
    (target: number | null) => {
      if (target == null) return;
      // `dir` để app/_layout.tsx chọn chiều trượt: lùi chương thì vào từ trái.
      const dir = chapter && target < chapter.number ? 'prev' : 'next';
      router.replace(`/reader/${storyId}/${target}?dir=${dir}`);
    },
    [router, storyId, chapter],
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

  // Ô quảng cáo trong thân chương — xem inContentAdSlots.
  const adSlots = useMemo(() => inContentAdSlots(paragraphs), [paragraphs]);

  const storyTitle = chapter?.story.title ?? null;
  const missingCoins = Math.max(0, COIN_PER_CHAPTER - coins);
  const notEnough = missingCoins > 0;

  const activeMessage = message && message.number === chapterNumber ? message : null;

  const banner = activeMessage
    ? { text: activeMessage.text, ok: activeMessage.ok }
    : notEnough
      ? {
          // PHẢI nêu cả đường miễn phí, không chỉ quảng cáo: khi AdMob không có hàng,
          // chỉ nhắc "Watch an ad" là dẫn người dùng vào đúng cánh cửa đang đóng. Đó là
          // thứ Apple gặp ở bản 1.0(3).
          text: `You need ${plural(
            missingCoins,
            'more coin',
            'more coins',
          )}. Watch an ad for +${formatCoins(AD_REWARD)}, or earn coins free by reading.`,
          ok: false,
        }
      : null;

  // Kiểu chữ nội dung dựng từ tùy chọn người dùng (cỡ chữ x hệ số giãn dòng).
  // Giữ nguyên tham chiếu giữa các lần render để ChapterBody (memo) khỏi vẽ lại.
  const bodyTextStyle = useMemo<TextStyle>(
    () => ({ fontSize, lineHeight: Math.round(fontSize * lineHeight), color: rt.text }),
    [fontSize, lineHeight, rt.text],
  );

  const onMidAdFrame = useCallback(
    (slot: number, { y, height }: { y: number; height: number }) => {
      // Ô không có hàng thì báo cao 0 — banner trên được ở lại.
      if (midAdBoxes.current.chapter !== chapterNumber) {
        midAdBoxes.current = { chapter: chapterNumber, boxes: {} };
      }
      midAdBoxes.current.boxes[slot] = { y, h: height };
      syncTopBanner();
    },
    [chapterNumber, syncTopBanner],
  );

  return (
    <View style={styles.screen}>
      <Stack.Screen options={{ headerShown: false }} />

      {/* Banner AdMob — BẮT BUỘC nằm trên cùng màn hình */}
      <View style={[styles.adSlot, { paddingTop: insets.top }]}>
        <View
          style={{ minHeight: bannerHeight }}
          onLayout={
            topBannerHidden ? undefined : (e) => setBannerHeight(e.nativeEvent.layout.height)
          }
        >
          {topBannerHidden ? null : <BannerAd />}
        </View>
      </View>

      {/* Header: quay lại · tên truyện · ví xu · Aa · nghe */}
      <View style={styles.header}>
        <Pressable
          onPress={goBack}
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
        <ReaderSkeleton rt={rt} columnWidth={columnWidth} />
      ) : !chapter ? (
        <View style={styles.center}>
          {gone && storyMeta ? (
            // Truyện còn, chỉ chương này mất (bị gỡ hoặc đánh số lại): mở trang truyện để chọn chương.
            <EmptyState
              icon="document-text-outline"
              title="This chapter is no longer available"
              description="Open the story to see the chapters you can read."
              actionLabel="Open story"
              onAction={() => router.replace(`/story/${storyId}`)}
            />
          ) : gone ? (
            <EmptyState
              icon="document-text-outline"
              title="This story is no longer available"
              description="It may have been removed from Stories."
              actionLabel="Go back"
              onAction={goBack}
            />
          ) : (
            <EmptyState
              danger
              icon="cloud-offline-outline"
              title="Couldn't load this chapter"
              description="Check your connection and try again."
              actionLabel="Retry"
              onAction={retry}
            />
          )}
        </View>
      ) : canRead ? (
        <ScrollView
          ref={scrollRef}
          style={[styles.flex, { backgroundColor: rt.bg }]}
          contentContainerStyle={[styles.readerContent, { maxWidth: columnWidth }]}
          showsVerticalScrollIndicator={false}
          scrollEventThrottle={32}
          onLayout={(e) => {
            viewportH.current = e.nativeEvent.layout.height;
            syncTopBanner();
          }}
          onScroll={(e) => {
            scrollY.current = e.nativeEvent.contentOffset.y;
            viewportH.current = e.nativeEvent.layoutMeasurement.height;
            syncTopBanner();
          }}
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
          <ChapterBody
            paragraphs={paragraphs}
            textStyle={bodyTextStyle}
            adSlots={adSlots}
            chapterNumber={chapterNumber}
            labelColor={rt.faint}
            onMidAdFrame={onMidAdFrame}
          />

          {/* Kết chương */}
          <View style={styles.endMark}>
            <View style={[styles.endLine, { backgroundColor: rt.border }]} />
            <Text style={[styles.endText, { color: rt.faint }]}>
              End of Chapter {chapter.number}
            </Text>
            <View style={[styles.endLine, { backgroundColor: rt.border }]} />
          </View>

          {/* Năm khuôn mặt chấm chương — giữ nguyên chiều cao sau khi bấm (ô quảng cáo ngay dưới). */}
          <ChapterReaction
            key={`react-${chapter.story.id}-${chapter.number}`}
            storyId={chapter.story.id}
            chapterNumber={chapter.number}
            totals={chapter.reaction}
            rt={rt}
          />

          <InlineAd key={`end-${chapterNumber}`} labelColor={rt.faint} />
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
              disabled={watchingAd || !rewardsReady}
              accessibilityRole="button"
              accessibilityState={{ disabled: watchingAd || !rewardsReady }}
              accessibilityLabel={`Watch an ad to get ${formatCoins(AD_REWARD)}`}
              style={({ pressed }) => [
                styles.adBtn,
                (watchingAd || !rewardsReady) && styles.adBtnBusy,
                pressed && !watchingAd && styles.pressed,
              ]}
            >
              {watchingAd ? (
                <ActivityIndicator color={Palette.coinDeep} size="small" />
              ) : (
                <>
                  <Ionicons name="play-circle" size={18} color={Palette.coinDeep} />
                  <Text style={styles.adBtnText}>{`Watch ad · +${formatCoins(AD_REWARD)}`}</Text>
                </>
              )}
            </Pressable>

            {/* Lối sang tab Phần thưởng. Màn khoá trước đây là ngõ cụt điều hướng:
                xu kiếm được bằng mốc thời gian đọc phải BẤM TAY để
                nhận ở một tab khác, mà từ đây không có đường nào tới đó. Người dùng
                ngồi đủ 10 phút cũng không biết mình đã đủ điều kiện. */}
            <Pressable
              onPress={() => router.push('/rewards' as Href)}
              accessibilityRole="button"
              accessibilityLabel="Open rewards to earn coins for free"
              style={({ pressed }) => [styles.freeCoinsBtn, pressed && styles.pressed]}
            >
              <Ionicons name="gift-outline" size={16} color={Palette.accentDeep} />
              <Text style={styles.freeCoinsBtnText}>Earn coins for free</Text>
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
        <View
          style={[
            styles.bottomBar,
            // màn rộng: nút Previous/Next thẳng cột chữ thay vì kéo dài hết bề ngang
            { paddingHorizontal: columnInset, paddingBottom: Math.max(insets.bottom, Spacing.md) },
          ]}>
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

/**
 * Thân chương — tách riêng và memo hoá vì màn đọc render lại khá thường: bộ đếm phút
 * đọc của Rewards cập nhật context mỗi 15 giây, banner gỡ/gắn khi ô trong thân chương vào
 * khung nhìn, số xu đổi... Chương dài cả trăm đoạn mà dựng lại đúng lúc đang cuộn là
 * rớt khung hình, thấy rõ trên iPad (cột chữ rộng, nhiều dòng mỗi đoạn).
 */
const ChapterBody = memo(function ChapterBody({
  paragraphs,
  textStyle,
  adSlots,
  chapterNumber,
  labelColor,
  onMidAdFrame,
}: {
  paragraphs: string[];
  textStyle: TextStyle;
  /** chỉ số đoạn có ô quảng cáo ngay sau — xem inContentAdSlots */
  adSlots: number[];
  chapterNumber: number;
  labelColor: string;
  onMidAdFrame: (slot: number, frame: { y: number; height: number }) => void;
}) {
  return (
    <>
      {paragraphs.map((text, i) => {
        const slot = adSlots.indexOf(i);
        return (
          <Fragment key={i}>
            <Text style={[styles.paragraph, textStyle]} selectable>
              {text}
            </Text>
            {/* key theo số chương: sang chương mới thì ô được dựng lại và xin quảng cáo mới */}
            {slot >= 0 ? (
              <InlineAd
                key={`mid-${chapterNumber}-${slot}`}
                labelColor={labelColor}
                onFrame={(frame) => onMidAdFrame(slot, frame)}
              />
            ) : null}
          </Fragment>
        );
      })}
    </>
  );
});

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

function ReaderSkeleton({ rt, columnWidth }: { rt: ReaderTheme; columnWidth: number }) {
  // cùng lề với cột chữ `readerContent` (rộng tối đa columnWidth, nằm giữa)
  const { width } = useResponsive();
  const side = Math.max(0, (width - columnWidth) / 2) + Spacing.xl;
  return (
    <View style={[styles.skeletonWrap, { backgroundColor: rt.bg, paddingHorizontal: side }]}>
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
    // Màn rộng: cột chữ ở giữa; maxWidth đặt theo cỡ chữ (readerColumnWidth).
    width: '100%',
    alignSelf: 'center',
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
    // Nửa pixel đệm dưới — KHÔNG bỏ. lineHeight là số nguyên nên chiều cao chữ luôn
    // khớp lưới pixel, và Yoga làm tròn khung bằng float: ở y lớn (giữa chương dài)
    // khung hụt ~0.0002pt. Lúc vẽ, TextKit thấy dòng cuối "không lọt" khung nên dồn
    // nó lên dòng trên rồi cắt mất ở mép phải -> dòng cuối trống mà vẫn giữ chỗ.
    // Chiều cao lẻ nửa pixel buộc Yoga làm tròn LÊN, khung luôn dư ít nhất nửa pixel.
    paddingBottom: 0.5 / PixelRatio.get(),
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
    width: '100%',
    maxWidth: SHEET_MAX_WIDTH,
    alignSelf: 'center',
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
  // Nút phụ, cố tình nhạt hơn hai nút chính ở trên: đây là lối thoát, không phải
  // hành động được mời gọi.
  freeCoinsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.xs,
    paddingVertical: Spacing.sm,
    paddingHorizontal: Spacing.md,
    borderRadius: Radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    backgroundColor: Palette.surface,
    marginTop: Spacing.sm,
  },
  freeCoinsBtnText: {
    color: Palette.accentDeep,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
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
