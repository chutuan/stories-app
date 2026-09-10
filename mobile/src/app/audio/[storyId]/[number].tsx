/**
 * MÀN NGHE SÁCH NÓI — route `/audio/[storyId]/[number]`
 *
 * NGUỒN ÂM THANH: chỉ lấy link MP3 thật từ backend (`audio_url` của API chương)
 * rồi phát bằng expo-audio (useAudioPlayer / useAudioPlayerStatus — SDK 57).
 * KHÔNG dùng TTS trên máy (expo-speech đã bị gỡ khỏi dự án).
 *
 * Chương chưa có file MP3 -> `audio_url` = null: màn hình vẫn dựng đủ trình phát
 * nhưng mọi nút bị vô hiệu hoá và hiện thông báo "Chương này chưa có bản audio".
 * TUYỆT ĐỐI không giả lập tiến trình chạy khi không có file thật.
 */

import { Ionicons } from '@expo/vector-icons';
import Slider from '@react-native-community/slider';
import { useEventListener } from 'expo';
import { setAudioModeAsync, useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Stack, useIsFocused, useLocalSearchParams, useRouter } from 'expo-router';
import type { Href } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  FlatList,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/empty-state';
import { Skeleton } from '@/components/skeleton';
import {
  coverGradient,
  FontSize,
  FontWeight,
  Gradients,
  hashSeed,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  withAlpha,
} from '@/constants/theme';
import { API_URL } from '@/lib/api';
import { formatCoins } from '@/lib/format';
import { COIN_PER_CHAPTER, useWallet } from '@/store/wallet';

type IoniconName = keyof typeof Ionicons.glyphMap;

/* ------------------------------------------------------------------ */
/* KIỂU DỮ LIỆU + GỌI API                                              */
/* ------------------------------------------------------------------ */
/* `audio_url` / `has_audio` là field MỚI của backend, chưa có trong
   src/lib/api.ts. Chỉ agent "màn khám phá" được sửa file đó, nên ở đây
   khai báo kiểu và gọi fetch cục bộ để tránh tranh chấp file. */

interface AudioChapter {
  id: number;
  story_id: number;
  number: number;
  title: string;
  is_free: boolean;
  /** URL MP3 tuyệt đối, null khi chương chưa có bản audio */
  audio_url: string | null;
  prev: number | null;
  next: number | null;
  story: { id: number; title: string; free_chapters: number };
}

interface AudioChapterMeta {
  id: number;
  number: number;
  title: string;
  is_free: boolean;
  /** chương đã có file MP3 chưa */
  has_audio?: boolean;
}

interface AudioStory {
  id: number;
  title: string;
  author: string | null;
  thumbnail_url: string | null;
  chapters_count: number;
  chapters?: AudioChapterMeta[];
}

async function fetchJson<T>(path: string): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(`API ${res.status}: ${path}`);
  return (await res.json()) as T;
}

/* ------------------------------------------------------------------ */
/* HẰNG SỐ                                                             */
/* ------------------------------------------------------------------ */

/** Các mức tốc độ phát, áp thẳng vào player bằng setPlaybackRate. */
const SPEEDS = [1, 1.25, 1.5, 2] as const;
/** Nhãn hiển thị (dấu chấm thập phân) cho từng mức tốc độ. */
const SPEED_LABELS = ['1x', '1.25x', '1.5x', '2x'] as const;
/** Số giây tua mỗi lần bấm nút lùi/tiến. */
const SKIP_SECONDS = 15;
/** Chiều cao 1 dòng trong bảng mục lục (cố định để FlatList nhảy đúng vị trí). */
const SHEET_ROW_HEIGHT = 56;

/* ------------------------------------------------------------------ */
/* HÀM PHỤ                                                             */
/* ------------------------------------------------------------------ */

/** Giây -> "m:ss" (hoặc "h:mm:ss" khi dài hơn 1 tiếng). */
function formatTime(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';
  const total = Math.floor(seconds);
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  const ss = String(s).padStart(2, '0');
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${ss}`;
  return `${m}:${ss}`;
}

/**
 * ĐIỂM ĐÁNH GIÁ LÀ MÔ PHỎNG — backend CHƯA có API đánh giá.
 * Điểm được suy ra TẤT ĐỊNH từ id truyện (hashSeed) nên một truyện luôn hiện
 * đúng một điểm, không đổi mỗi lần render và không phải số ngẫu nhiên.
 * Khi backend có bảng đánh giá thật thì thay hàm này bằng dữ liệu thật.
 */
function simulatedRating(storyId: string | number): number {
  return 4 + (hashSeed(`danh-gia:${storyId}`) % 10) / 10; // 4,0 – 4,9
}

/** Bỏ tiền tố "Chương N:" trùng lặp trong tên chương (số chương đã hiện riêng). */
function chapterLabel(chapter: { number: number; title: string }): string {
  const raw = (chapter.title ?? '').trim();
  if (!raw) return `Chapter ${chapter.number}`;
  const prefix = new RegExp(`^(chương|chuong|chapter)\\s*0*${chapter.number}\\b\\s*[:.\\-–—]*\\s*`, 'i');
  const stripped = raw.replace(prefix, '').trim();
  return stripped.length > 0 ? stripped : raw;
}

/** Chữ cái đầu cho bìa dự phòng. */
function initialOf(title?: string | null): string {
  const ch = (title ?? '').trim().charAt(0);
  return ch ? ch.toUpperCase() : '?';
}

/* ------------------------------------------------------------------ */
/* MÀN HÌNH                                                            */
/* ------------------------------------------------------------------ */

interface ChapterRow {
  number: number;
  title: string;
  hasAudio: boolean;
  locked: boolean;
}

/** Chương kế tiếp — dữ liệu cần để quyết định làm gì khi nghe hết chương này. */
interface NextChapter {
  number: number;
  title: string;
  /** nghe được: chương miễn phí, hoặc người dùng có gói Premium */
  playable: boolean;
  /** đã có file MP3 chưa; `null` = chưa biết (mục lục chưa tải xong) */
  hasAudio: boolean | null;
}

export default function AudioPlayerScreen() {
  const { storyId, number, autoplay } = useLocalSearchParams<{
    storyId: string;
    number: string;
    /** '1' khi màn hình mở ra do TỰ CHUYỂN CHƯƠNG -> phát ngay sau khi nạp xong */
    autoplay?: string;
  }>();
  const chapterNumber = Number(number);
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { width, height } = useWindowDimensions();
  /**
   * Audio đi theo ĐÚNG quyền đọc: chương miễn phí thì ai cũng nghe, chương trả phí
   * phải mở bằng xu ở màn đọc.
   *
   * Gói Premium đã bị TẮT TẠM THỜI để bản nộp App Store không chứa mua trong ứng
   * dụng — bản Apple đang xét cũng không có. Bật lại bằng cách revert đúng một
   * commit, xem thông điệp commit "Tắt tạm gói đăng ký".
   */
  const { isUnlocked } = useWallet();
  /**
   * Modal của React Native phủ TOÀN BỘ cửa sổ app, không nằm trong ngăn xếp
   * điều hướng. Nếu màn này vẫn còn mount bên dưới một màn khác (deep link đẩy
   * route mới lên trên), modal sẽ nổi đè lên màn đang xem. Suy ra theo tiêu điểm
   * để mọi modal của màn này tự biến mất khi màn không còn được xem.
   */
  const focused = useIsFocused();
  const autoplayRequested = autoplay === '1';

  /** Chương đã tải (kèm số chương của nó để đổi chương là tự về trạng thái tải). */
  const [chapterData, setChapterData] = useState<AudioChapter | null>(null);
  /** Số chương tải lỗi — so với chapterNumber để biết lỗi còn hiệu lực không. */
  const [failedNumber, setFailedNumber] = useState<number | null>(null);
  const [retryCount, setRetryCount] = useState(0);
  const [story, setStory] = useState<AudioStory | null>(null);
  const [speedIndex, setSpeedIndex] = useState(0);
  const [tocOpen, setTocOpen] = useState(false);
  /** Vị trí đang kéo trên thanh tiến trình (null = không kéo). */
  const [seeking, setSeeking] = useState<number | null>(null);
  const [coverFailed, setCoverFailed] = useState(false);

  /* ---- Tải chương ---- */
  useEffect(() => {
    let alive = true;
    fetchJson<AudioChapter>(`/stories/${storyId}/chapters/${chapterNumber}`)
      .then((data) => {
        if (alive) setChapterData(data);
      })
      .catch(() => {
        if (alive) setFailedNumber(chapterNumber);
      });
    return () => {
      alive = false;
    };
  }, [storyId, chapterNumber, retryCount]);

  /* ---- Tải truyện (bìa, tác giả, mục lục) — lỗi thì bỏ qua ---- */
  useEffect(() => {
    let alive = true;
    fetchJson<AudioStory>(`/stories/${storyId}`)
      .then((data) => {
        if (alive) setStory(data);
      })
      .catch(() => {});
    return () => {
      alive = false;
    };
  }, [storyId, retryCount]);

  /* ---- Cấu hình phiên âm thanh: nghe được cả khi máy đang ở chế độ im lặng.
         KHÔNG bật shouldPlayInBackground vì rời màn là dừng phát. ---- */
  useEffect(() => {
    setAudioModeAsync({
      playsInSilentMode: true,
      shouldPlayInBackground: false,
      interruptionMode: 'duckOthers',
    }).catch(() => {});
  }, []);

  const chapter = chapterData && chapterData.number === chapterNumber ? chapterData : null;
  const failed = failedNumber === chapterNumber;
  const loading = !chapter && !failed;

  /* Chương trả phí chỉ dành cho thành viên; chương miễn phí thì ai cũng nghe. */
  const locked = chapter ? !chapter.is_free && !isUnlocked(storyId, chapterNumber) : false;
  const audioUrl = chapter && !locked ? chapter.audio_url : null;
  const hasAudio = audioUrl != null;

  /* ---- PLAYER (expo-audio) ----
     Đổi `source` là hook tự tạo player mới và giải phóng player cũ;
     rời màn hình thì hook tự release, không cần gọi remove(). */
  const player = useAudioPlayer(audioUrl ? { uri: audioUrl } : null, { updateInterval: 300 });
  const status = useAudioPlayerStatus(player);

  /**
   * STATUS CÓ THỂ CÒN LÀ CỦA PLAYER CŨ.
   * `useAudioPlayerStatus` dựng trên `useEvent`, mà `useEvent` chỉ nhận giá trị
   * khởi tạo ở lần render ĐẦU TIÊN. Đổi chương -> player mới được tạo nhưng state
   * vẫn giữ bản tin cuối của player cũ cho tới khi player mới bắn sự kiện đầu tiên;
   * bản tin đó còn mang `didJustFinish: true` và thời lượng của chương trước. Tin
   * vào nó thì tự chuyển chương sẽ nhảy dây chuyền qua sạch các chương còn lại.
   * `status.id` rỗng (nền tảng không điền) thì coi như hợp lệ, để không kẹt mãi.
   */
  const statusFresh = !status.id || status.id === player.id;

  const playing = statusFresh && status.playing;
  const duration = statusFresh && status.duration > 0 ? status.duration : 0;
  const currentTime = statusFresh ? Math.max(0, status.currentTime) : 0;
  const position = seeking ?? currentTime;
  const playbackError = statusFresh ? status.error : null;
  /**
   * Đang nạp/đệm file: nút phát hiện vòng xoay thay vì icon.
   * Có lỗi phát thì thôi quay (không để vòng xoay chạy mãi), lỗi đã có thẻ
   * thông báo riêng phía trên thanh tiến trình.
   */
  const busy =
    hasAudio &&
    !playbackError &&
    (!statusFresh || !status.isLoaded || (status.isBuffering && playing));

  /* ---- Dọn dẹp: dừng phát khi rời màn hoặc khi đổi chương ---- */
  useEffect(() => {
    return () => {
      try {
        player.pause();
      } catch {
        // player đã được giải phóng — bỏ qua
      }
    };
  }, [player]);

  /* ---- Áp tốc độ phát (áp lại mỗi khi đổi mức hoặc file vừa nạp xong) ---- */
  useEffect(() => {
    if (!hasAudio) return;
    try {
      player.setPlaybackRate(SPEEDS[speedIndex], 'high');
    } catch {
      // một số nền tảng không đổi được tốc độ — bỏ qua
    }
  }, [player, speedIndex, hasAudio, status.isLoaded]);

  /* ---- Hành động ---- */

  const retry = useCallback(() => {
    setFailedNumber(null);
    setRetryCount((n) => n + 1);
  }, []);

  const togglePlay = useCallback(() => {
    if (!hasAudio) return;
    if (playing) {
      player.pause();
      return;
    }
    // Đã nghe hết chương -> tua về đầu rồi phát lại.
    // (Cố tình KHÔNG tự tua về 0 lúc `didJustFinish`: thanh tiến trình cần
    //  đứng ở cuối để người dùng biết đã nghe hết, và để nút tua +15 giây
    //  không bị nhảy ngược về đầu.)
    if (duration > 0 && currentTime >= duration - 0.25) {
      player
        .seekTo(0)
        .then(() => player.play())
        .catch(() => {});
      return;
    }
    player.play();
  }, [hasAudio, playing, player, duration, currentTime]);

  const skip = useCallback(
    (delta: number) => {
      if (!hasAudio) return;
      const upper = duration > 0 ? Math.max(duration - 0.2, 0) : currentTime + Math.abs(delta);
      const target = Math.min(Math.max(currentTime + delta, 0), upper);
      player.seekTo(target).catch(() => {});
    },
    [hasAudio, player, duration, currentTime],
  );

  const handleSeekStart = useCallback((value: number) => {
    setSeeking(value);
  }, []);

  const handleSeekChange = useCallback(
    (value: number) => {
      // Chỉ nhận khi người dùng đang thực sự kéo, tránh nhiễu khi `value` tự cập nhật.
      if (seeking != null) setSeeking(value);
    },
    [seeking],
  );

  const handleSeekEnd = useCallback(
    (value: number) => {
      if (!hasAudio) {
        setSeeking(null);
        return;
      }
      // Giữ vị trí vừa thả cho tới khi player tua xong -> thanh không bị giật về.
      player
        .seekTo(Math.max(0, value))
        .catch(() => {})
        .finally(() => setSeeking(null));
    },
    [hasAudio, player],
  );

  const cycleSpeed = useCallback(() => {
    setSpeedIndex((i) => (i + 1) % SPEEDS.length);
  }, []);

  const goChapter = useCallback(
    (target: number | null, options?: { autoplay?: boolean }) => {
      if (target == null) return;
      setTocOpen(false);
      setSeeking(null);
      // `autoplay=1` để màn hình đích tự bấm phát; chuyển chương THỦ CÔNG không
      // gắn cờ này, giữ nguyên nếp cũ là người dùng tự bấm nút phát.
      // `dir` để app/_layout.tsx chọn chiều trượt (lùi chương thì vào từ trái) và
      // để không trượt lên từ đáy mỗi lần đổi chương — hoạt ảnh đó dành cho lúc MỞ
      // trình phát, dùng lại khi tự chuyển chương thì rất giật.
      const dir = chapter && target < chapter.number ? 'prev' : 'next';
      const query = `?dir=${dir}` + (options?.autoplay ? '&autoplay=1' : '');
      // typedRoutes đang bật nhưng route mới chỉ được sinh kiểu khi chạy expo start,
      // nên ép kiểu Href cho đường dẫn động này.
      router.replace(`/audio/${storyId}/${target}${query}` as Href);
    },
    [router, storyId, chapter],
  );

  const openReader = useCallback(() => {
    router.replace(`/reader/${storyId}/${chapterNumber}`);
  }, [router, storyId, chapterNumber]);

  /* ---- Dữ liệu hiển thị ---- */

  const storyTitle = story?.title ?? chapter?.story.title ?? null;
  const author = story?.author?.trim();
  const authorLabel = author && author.length > 0 ? author : 'Unknown author';
  const title = chapter ? chapterLabel(chapter) : null;
  const totalChapters = story?.chapters_count ?? null;
  const rating = useMemo(() => simulatedRating(storyId), [storyId]);
  const ratingText = rating.toFixed(1);

  const chapterRows = useMemo<ChapterRow[]>(() => {
    const list = story?.chapters ?? [];
    return list.map((c) => ({
      number: c.number,
      title: chapterLabel(c),
      hasAudio: c.has_audio === true,
      locked: !c.is_free && !isUnlocked(storyId, c.number),
    }));
  }, [story, isUnlocked, storyId]);

  /* ---- Chương kế tiếp ---- */
  const nextInfo = useMemo<NextChapter | null>(() => {
    const target = chapter?.next;
    if (!chapter || target == null) return null;
    const meta = story?.chapters?.find((c) => c.number === target) ?? null;
    // Mục lục chưa về thì suy ra "miễn phí" từ `free_chapters` của truyện.
    const isFree = meta ? meta.is_free : target <= chapter.story.free_chapters;
    return {
      number: target,
      title: meta ? chapterLabel(meta) : `Chapter ${target}`,
      playable: isFree || isUnlocked(storyId, target),
      hasAudio: meta ? meta.has_audio === true : null,
    };
  }, [chapter, story, isUnlocked, storyId]);

  /* ---- Tự phát khi màn hình được mở bằng cách tự chuyển chương ----
     Mỗi player (mỗi chương) chỉ tự bấm phát ĐÚNG MỘT LẦN: người dùng bấm tạm
     dừng xong thì không bị hệ thống bật lại. */
  const autoStartedRef = useRef<string | null>(null);
  useEffect(() => {
    if (!autoplayRequested || !hasAudio) return;
    if (!statusFresh || !status.isLoaded) return;
    if (autoStartedRef.current === player.id) return;
    autoStartedRef.current = player.id;
    try {
      player.play();
    } catch {
      // player vừa được giải phóng — bỏ qua
    }
  }, [autoplayRequested, hasAudio, statusFresh, status.isLoaded, player]);

  /* ---- Nghe hết chương thì làm gì ---- */
  const handleFinished = useCallback(() => {
    // Không còn được xem (đã sang màn khác) thì im lặng: không tự chuyển chương
    // và không bật modal đè lên màn người dùng đang đọc.
    if (!focused) return;
    // Hết truyện: không còn chương sau, cứ để thanh tiến trình đứng ở cuối.
    if (!nextInfo) return;
    if (!nextInfo.playable) {
      // Chương sau chưa mở khoá. Màn ĐỌC đã có sẵn giao diện trả xu / xem quảng cáo
      // để mở, nên đưa thẳng người nghe sang đó thay vì dựng thêm một hộp thoại nữa
      // trong màn nghe — mở khoá xong bấm nút tai nghe là nghe tiếp được.
      setTocOpen(false);
      router.replace(`/reader/${storyId}/${nextInfo.number}?dir=next` as Href);
      return;
    }
    // Chương sau chắc chắn CHƯA có MP3 -> ở lại, nhảy sang cũng không phát được.
    // `null` (chưa biết) thì vẫn nhảy: màn hình đích tự báo nếu thiếu audio.
    if (nextInfo.hasAudio === false) return;
    goChapter(nextInfo.number, { autoplay: true });
  }, [focused, nextInfo, goChapter, router, storyId]);

  /**
   * Bắt lúc nghe hết TRÊN LUỒNG SỰ KIỆN, không đọc từ state đã render.
   *
   * `useAudioPlayerStatus` chỉ là `useState` bọc quanh sự kiện, nên hai bản tin bắn
   * sát nhau sẽ gộp thành MỘT lần render mang giá trị của bản tin SAU. Trên iOS,
   * ngay sau bản tin `didJustFinish: true` thường còn một bản tin của bộ đếm thời
   * gian (didJustFinish: false); component không bao giờ render với `true` và cả
   * tính năng im lặng không chạy. Nghe thẳng sự kiện thì không bản tin nào bị nuốt.
   *
   * useEventListener tự giữ listener mới nhất trong ref nên truyền closure thẳng
   * vào đây không làm đăng ký lại sau mỗi lần render.
   */
  const finishRef = useRef<{ id: string; finished: boolean }>({ id: '', finished: false });
  useEventListener(player, 'playbackStatusUpdate', (s) => {
    // Bản tin của player CŨ (vừa đổi chương) còn mang didJustFinish của chương trước.
    if (s.id && s.id !== player.id) return;
    if (!s.didJustFinish) {
      finishRef.current = { id: player.id, finished: false };
      return;
    }
    if (finishRef.current.id === player.id && finishRef.current.finished) return;
    finishRef.current = { id: player.id, finished: true };
    handleFinished();
  });


  /* Loại thông báo cần hiện phía trên thanh tiến trình. */
  const notice: 'locked' | 'no-audio' | null = !chapter
    ? null
    : locked
      ? 'locked'
      : chapter.audio_url == null
        ? 'no-audio'
        : null;

  /* ---- Kích thước bìa + đĩa than ---- */
  const coverSize = Math.round(Math.max(Math.min(width * 0.56, height * 0.28, 232), 140));
  const discSize = Math.round(coverSize * 0.94);
  const discPeek = Math.round(coverSize * 0.3);

  /* ---- Đĩa than quay khi đang phát ---- */
  const [spin] = useState(() => new Animated.Value(0));
  useEffect(() => {
    if (!playing) return;
    spin.setValue(0);
    const loop = Animated.loop(
      Animated.timing(spin, {
        toValue: 1,
        duration: 9000,
        easing: Easing.linear,
        // react-native-web không hỗ trợ native driver
        useNativeDriver: Platform.OS !== 'web',
      }),
    );
    loop.start();
    return () => {
      loop.stop();
    };
  }, [playing, spin]);
  const rotate = useMemo(
    () => spin.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] }),
    [spin],
  );

  const coverUrl = story?.thumbnail_url ?? null;
  const fallbackGradient = coverGradient(storyTitle ?? storyId);

  /* ------------------------------ RENDER ------------------------------ */

  return (
    <View style={styles.screen}>
      <Stack.Screen options={{ headerShown: false, animation: 'slide_from_bottom' }} />

      {/* ---------- Header: thu gọn · tên truyện · tốc độ ---------- */}
      <View style={[styles.header, { paddingTop: insets.top + Spacing.sm }]}>
        <Pressable
          onPress={() => router.back()}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Collapse player"
          style={({ pressed }) => [styles.iconBtn, pressed && styles.pressed]}
        >
          <Ionicons name="chevron-down" size={22} color={Palette.text} />
        </Pressable>

        <View style={styles.headerCenter}>
          <Text style={styles.headerLabel}>NOW PLAYING</Text>
          {storyTitle ? (
            <Text style={styles.headerTitle} numberOfLines={1}>
              {storyTitle}
            </Text>
          ) : (
            <Skeleton width={132} height={13} radius={Radius.xs} />
          )}
        </View>

        <Pressable
          onPress={cycleSpeed}
          disabled={!hasAudio}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel={`Speed ${SPEED_LABELS[speedIndex]}, tap to change`}
          style={({ pressed }) => [
            styles.speedBtn,
            !hasAudio && styles.speedBtnOff,
            pressed && hasAudio && styles.pressed,
          ]}
        >
          <Ionicons
            name="speedometer-outline"
            size={13}
            color={hasAudio ? Palette.accentDeep : Palette.faint}
          />
          <Text style={[styles.speedText, !hasAudio && styles.speedTextOff]}>
            {SPEED_LABELS[speedIndex]}
          </Text>
        </Pressable>
      </View>

      {failed ? (
        /* ---------- Lỗi tải chương ---------- */
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
      ) : (
        <>
          {/* ---------- Bìa + đĩa than + thông tin ---------- */}
          <ScrollView
            style={styles.flex}
            contentContainerStyle={styles.body}
            showsVerticalScrollIndicator={false}
          >
            <View style={[styles.stage, { width: coverSize + discPeek, height: coverSize }]}>
              {/* Đĩa than thò ra bên phải, vẽ hoàn toàn bằng View bo tròn */}
              <Animated.View
                style={[
                  styles.disc,
                  {
                    width: discSize,
                    height: discSize,
                    borderRadius: discSize / 2,
                    top: (coverSize - discSize) / 2,
                    transform: [{ rotate }],
                  },
                ]}
              >
                {[0.1, 0.2, 0.3].map((f) => {
                  const inset = Math.round(discSize * f);
                  return (
                    <View
                      key={f}
                      style={[
                        styles.discRing,
                        {
                          top: inset,
                          left: inset,
                          right: inset,
                          bottom: inset,
                          borderRadius: (discSize - inset * 2) / 2,
                        },
                      ]}
                    />
                  );
                })}
                {/* vệt sáng để thấy được đĩa đang quay */}
                <View
                  style={[
                    styles.discStreak,
                    {
                      top: Math.round(discSize * 0.06),
                      left: discSize / 2 - 1,
                      height: Math.round(discSize * 0.2),
                    },
                  ]}
                />
                <View
                  style={[
                    styles.discLabel,
                    {
                      width: Math.round(discSize * 0.32),
                      height: Math.round(discSize * 0.32),
                      borderRadius: Math.round(discSize * 0.16),
                      top: Math.round(discSize * 0.34),
                      left: Math.round(discSize * 0.34),
                    },
                  ]}
                >
                  <View
                    style={[
                      styles.discHole,
                      {
                        width: Math.round(discSize * 0.07),
                        height: Math.round(discSize * 0.07),
                        borderRadius: Math.round(discSize * 0.035),
                      },
                    ]}
                  />
                </View>
              </Animated.View>

              {/* Bìa vuông bo góc */}
              <View style={[styles.coverBox, { width: coverSize, height: coverSize }]}>
                {loading ? (
                  <Skeleton width={coverSize} height={coverSize} radius={Radius.xxl} />
                ) : coverUrl && !coverFailed ? (
                  <Image
                    source={{ uri: coverUrl }}
                    style={styles.fill}
                    contentFit="cover"
                    transition={220}
                    cachePolicy="memory-disk"
                    recyclingKey={coverUrl}
                    accessible={false}
                    onError={() => setCoverFailed(true)}
                  />
                ) : (
                  <LinearGradient
                    colors={fallbackGradient}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={styles.coverFallback}
                  >
                    <Text style={styles.coverInitial}>{initialOf(storyTitle)}</Text>
                  </LinearGradient>
                )}
              </View>
            </View>

            {/* Điểm đánh giá (MÔ PHỎNG tất định từ id truyện — xem simulatedRating) */}
            <View style={styles.ratingRow}>
              <Text style={styles.ratingValue}>{ratingText}</Text>
              <Stars rating={rating} />
            </View>

            {/* Tên chương */}
            {title ? (
              <Text style={styles.title} numberOfLines={2}>
                {title}
              </Text>
            ) : (
              <View style={styles.titleSkeleton}>
                <Skeleton width="72%" height={18} radius={Radius.xs} />
              </View>
            )}

            {/* Tác giả + vị trí chương */}
            <View style={styles.metaRow}>
              <Ionicons name="person-outline" size={13} color={Palette.muted} />
              <Text style={styles.metaText} numberOfLines={1}>
                {authorLabel}
              </Text>
              <View style={styles.metaDot} />
              <Text style={styles.metaText} numberOfLines={1}>
                {totalChapters != null
                  ? `Chapter ${chapterNumber}/${totalChapters}`
                  : `Chapter ${chapterNumber}`}
              </Text>
            </View>
          </ScrollView>

          {/* ---------- Khối điều khiển ---------- */}
          <View style={[styles.bottom, { paddingBottom: Math.max(insets.bottom, Spacing.md) }]}>
            {notice === 'no-audio' ? (
              <Notice
                icon="mic-off-outline"
                title="No audio for this chapter yet"
                description="You can read the text instead, or pick another chapter that has audio."
                actionLabel="Read text instead"
                onAction={openReader}
              />
            ) : notice === 'locked' ? (
              <Notice
                icon="lock-closed-outline"
                title="This chapter is locked"
                description={`Unlock it with ${formatCoins(COIN_PER_CHAPTER)} in the reader, then come back to listen.`}
                actionLabel="Unlock in reader"
                onAction={openReader}
              />
            ) : hasAudio && playbackError ? (
              <Notice
                danger
                icon="alert-circle-outline"
                title="Couldn't play this audio"
                description="Check your connection and try again."
                actionLabel="Retry"
                onAction={retry}
              />
            ) : null}

            {/* Thanh tiến trình */}
            <Slider
              style={styles.slider}
              minimumValue={0}
              maximumValue={duration > 0 ? duration : 1}
              value={duration > 0 ? Math.min(position, duration) : 0}
              disabled={!hasAudio || duration <= 0}
              minimumTrackTintColor={hasAudio ? Palette.accentDeep : Palette.borderStrong}
              maximumTrackTintColor={Palette.borderStrong}
              thumbTintColor={hasAudio ? Palette.accentDeep : Palette.faint}
              tapToSeek
              onSlidingStart={handleSeekStart}
              onValueChange={handleSeekChange}
              onSlidingComplete={handleSeekEnd}
              accessibilityLabel="Playback progress"
            />

            {/* Nút tua · thời gian */}
            <View style={styles.timeRow}>
              <SkipButton
                direction="back"
                disabled={!hasAudio}
                onPress={() => skip(-SKIP_SECONDS)}
              />
              <Text style={styles.timeText}>{formatTime(position)}</Text>
              <View style={styles.flex} />
              <Text style={styles.timeText}>{hasAudio ? formatTime(duration) : '0:00'}</Text>
              <SkipButton
                direction="forward"
                disabled={!hasAudio}
                onPress={() => skip(SKIP_SECONDS)}
              />
            </View>

            {/* Mục lục · Chương trước · Phát/Dừng · Chương sau · Đọc chữ */}
            <View style={styles.controls}>
              <ControlItem
                icon="list-outline"
                label="Contents"
                accessibilityLabel="Open chapter contents"
                disabled={chapterRows.length === 0}
                onPress={() => setTocOpen(true)}
              />
              <ControlItem
                icon="play-skip-back"
                label="Prev"
                accessibilityLabel="Previous chapter"
                disabled={chapter?.prev == null}
                onPress={() => goChapter(chapter?.prev ?? null)}
              />

              <Pressable
                onPress={togglePlay}
                disabled={!hasAudio}
                accessibilityRole="button"
                accessibilityLabel={playing ? 'Pause' : 'Play'}
                accessibilityState={{ disabled: !hasAudio }}
                style={({ pressed }) => [
                  styles.playBtn,
                  !hasAudio && styles.playBtnOff,
                  pressed && hasAudio && styles.pressed,
                ]}
              >
                {hasAudio ? (
                  <LinearGradient
                    colors={Gradients.accent}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 1 }}
                    style={StyleSheet.absoluteFill}
                  />
                ) : null}
                {busy ? (
                  <ActivityIndicator
                    size="small"
                    color={hasAudio ? Palette.onAccent : Palette.faint}
                  />
                ) : (
                  <Ionicons
                    name={playing ? 'pause' : 'play'}
                    size={30}
                    color={hasAudio ? Palette.onAccent : Palette.faint}
                    style={playing ? undefined : styles.playIcon}
                  />
                )}
              </Pressable>

              <ControlItem
                icon="play-skip-forward"
                label="Next"
                accessibilityLabel="Next chapter"
                disabled={chapter?.next == null}
                onPress={() => goChapter(chapter?.next ?? null)}
              />
              <ControlItem
                icon="book-outline"
                label="Read text"
                accessibilityLabel="Back to reading"
                onPress={openReader}
              />
            </View>
          </View>
        </>
      )}

      {/* ---------- Mục lục ---------- */}
      <ChapterSheet
        visible={tocOpen && focused}
        rows={chapterRows}
        current={chapterNumber}
        storyTitle={storyTitle}
        bottomInset={insets.bottom}
        onSelect={goChapter}
        onClose={() => setTocOpen(false)}
      />
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* THÀNH PHẦN PHỤ                                                      */
/* ------------------------------------------------------------------ */

/** 5 ngôi sao theo điểm đánh giá (mô phỏng). */
function Stars({ rating }: { rating: number }) {
  return (
    <View style={styles.starsRow}>
      {[0, 1, 2, 3, 4].map((i) => {
        const full = rating >= i + 1;
        const half = !full && rating >= i + 0.5;
        return (
          <Ionicons
            key={i}
            name={full ? 'star' : half ? 'star-half' : 'star-outline'}
            size={14}
            color={full || half ? Palette.coin : Palette.borderStrong}
          />
        );
      })}
    </View>
  );
}

/** Nút tua 15 giây. */
function SkipButton({
  direction,
  disabled,
  onPress,
}: {
  direction: 'back' | 'forward';
  disabled: boolean;
  onPress: () => void;
}) {
  const back = direction === 'back';
  const color = disabled ? Palette.faint : Palette.text;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={back ? `Back ${SKIP_SECONDS} seconds` : `Forward ${SKIP_SECONDS} seconds`}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [
        styles.skipBtn,
        disabled && styles.skipBtnOff,
        pressed && !disabled && styles.pressed,
      ]}
    >
      {back ? <Ionicons name="play-back" size={12} color={color} /> : null}
      <Text style={[styles.skipText, { color }]}>{SKIP_SECONDS}</Text>
      {back ? null : <Ionicons name="play-forward" size={12} color={color} />}
    </Pressable>
  );
}

/** Nút phụ trong hàng điều khiển (icon tròn + nhãn nhỏ). */
function ControlItem({
  icon,
  label,
  accessibilityLabel,
  disabled = false,
  onPress,
}: {
  icon: IoniconName;
  label: string;
  accessibilityLabel: string;
  disabled?: boolean;
  onPress: () => void;
}) {
  const color = disabled ? Palette.faint : Palette.text;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [styles.control, pressed && !disabled && styles.pressed]}
    >
      <View style={[styles.controlIcon, disabled && styles.controlIconOff]}>
        <Ionicons name={icon} size={19} color={color} />
      </View>
      <Text style={[styles.controlLabel, { color: disabled ? Palette.faint : Palette.muted }]} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

/** Thẻ thông báo (chưa có audio / chương khoá / lỗi phát). */
function Notice({
  icon,
  title,
  description,
  actionLabel,
  onAction,
  danger = false,
}: {
  icon: IoniconName;
  title: string;
  description: string;
  actionLabel: string;
  onAction: () => void;
  danger?: boolean;
}) {
  const tint = danger ? Palette.dangerDeep : Palette.accentDeep;
  return (
    <View
      style={[
        styles.notice,
        {
          backgroundColor: danger ? Palette.dangerDim : Palette.accentDim,
          borderColor: danger ? withAlpha(Palette.danger, 0.3) : Palette.accentBorder,
        },
      ]}
    >
      <Ionicons name={icon} size={20} color={tint} />
      <View style={styles.flex}>
        <Text style={[styles.noticeTitle, { color: tint }]}>{title}</Text>
        <Text style={styles.noticeDesc}>{description}</Text>
      </View>
      <Pressable
        onPress={onAction}
        accessibilityRole="button"
        accessibilityLabel={actionLabel}
        style={({ pressed }) => [
          styles.noticeBtn,
          { backgroundColor: tint },
          pressed && styles.pressed,
        ]}
      >
        <Text style={styles.noticeBtnText}>{actionLabel}</Text>
      </Pressable>
    </View>
  );
}

/* ---------------------- Mục lục dạng bottom sheet ---------------------- */

interface ChapterSheetProps {
  visible: boolean;
  rows: ChapterRow[];
  current: number;
  storyTitle: string | null;
  bottomInset: number;
  onSelect: (n: number) => void;
  onClose: () => void;
}

function ChapterSheet({
  visible,
  rows,
  current,
  storyTitle,
  bottomInset,
  onSelect,
  onClose,
}: ChapterSheetProps) {
  const currentIndex = rows.findIndex((r) => r.number === current);

  const renderItem = useCallback(
    ({ item }: { item: ChapterRow }) => (
      <Pressable
        onPress={() => onSelect(item.number)}
        accessibilityRole="button"
        accessibilityState={{ selected: item.number === current }}
        accessibilityLabel={`Chapter ${item.number}. ${item.title}. ${
          item.hasAudio ? 'Audio available' : 'No audio yet'
        }`}
        style={({ pressed }) => [
          styles.row,
          item.number === current && styles.rowActive,
          pressed && styles.pressed,
        ]}
      >
        <View style={[styles.rowNum, item.number === current && styles.rowNumActive]}>
          <Text style={[styles.rowNumText, item.number === current && styles.rowNumTextActive]}>
            {item.number}
          </Text>
        </View>
        <Text
          style={[styles.rowTitle, item.number === current && styles.rowTitleActive]}
          numberOfLines={1}
        >
          {item.title}
        </Text>
        {item.locked ? <Ionicons name="lock-closed" size={13} color={Palette.faint} /> : null}
        {item.hasAudio ? (
          <Ionicons name="headset" size={16} color={Palette.accentDeep} />
        ) : (
          <Text style={styles.rowNoAudio}>No audio</Text>
        )}
      </Pressable>
    ),
    [current, onSelect],
  );

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={styles.sheetWrap}>
        <Pressable
          style={StyleSheet.absoluteFill}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="Close contents"
        />
        <View style={[styles.sheet, { paddingBottom: Math.max(bottomInset, Spacing.md) }]}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHead}>
            <View style={styles.flex}>
              <Text style={styles.sheetTitle}>Contents</Text>
              {storyTitle ? (
                <Text style={styles.sheetSubtitle} numberOfLines={1}>
                  {storyTitle}
                </Text>
              ) : null}
            </View>
            <Pressable
              onPress={onClose}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Close contents"
              style={({ pressed }) => [styles.iconBtn, pressed && styles.pressed]}
            >
              <Ionicons name="close" size={20} color={Palette.text} />
            </Pressable>
          </View>

          <FlatList
            data={rows}
            keyExtractor={(item) => String(item.number)}
            renderItem={renderItem}
            getItemLayout={(_, index) => ({
              length: SHEET_ROW_HEIGHT,
              offset: SHEET_ROW_HEIGHT * index,
              index,
            })}
            initialScrollIndex={currentIndex > 0 ? currentIndex : undefined}
            showsVerticalScrollIndicator={false}
            contentContainerStyle={styles.sheetList}
          />
        </View>
      </View>
    </Modal>
  );
}

/* ------------------------------------------------------------------ */
/* STYLE                                                               */
/* ------------------------------------------------------------------ */

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  flex: {
    flex: 1,
  },
  fill: {
    width: '100%',
    height: '100%',
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },

  // --- Header ---
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    paddingHorizontal: Spacing.screen,
    paddingBottom: Spacing.md,
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
  headerCenter: {
    flex: 1,
    alignItems: 'center',
    gap: 2,
  },
  headerLabel: {
    color: Palette.faint,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.bold,
    letterSpacing: 1.1,
  },
  headerTitle: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small,
    textAlign: 'center',
  },
  speedBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    minWidth: 62,
    justifyContent: 'center',
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 7,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accentDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.accentBorder,
  },
  speedBtnOff: {
    backgroundColor: Palette.surfaceAlt,
    borderColor: Palette.border,
  },
  speedText: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.black,
  },
  speedTextOff: {
    color: Palette.faint,
  },

  // --- Thân màn ---
  body: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: Spacing.xl,
    paddingVertical: Spacing.xl,
    gap: Spacing.md,
  },

  // --- Bìa + đĩa than ---
  stage: {
    alignSelf: 'center',
    marginBottom: Spacing.sm,
    // chỉ để nhìn — không nhận chạm (props.pointerEvents đã bị deprecated)
    pointerEvents: 'none',
  },
  disc: {
    position: 'absolute',
    right: 0,
    backgroundColor: Palette.text,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadow.card,
  },
  discRing: {
    position: 'absolute',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: withAlpha(Palette.white, 0.18),
  },
  discStreak: {
    position: 'absolute',
    width: 2,
    borderRadius: 2,
    backgroundColor: withAlpha(Palette.white, 0.22),
  },
  discLabel: {
    position: 'absolute',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDeep,
  },
  discHole: {
    backgroundColor: Palette.bg,
  },
  coverBox: {
    position: 'absolute',
    left: 0,
    top: 0,
    borderRadius: Radius.xxl,
    overflow: 'hidden',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.float,
  },
  coverFallback: {
    width: '100%',
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  coverInitial: {
    color: withAlpha(Palette.white, 0.92),
    fontSize: 56,
    fontWeight: FontWeight.black,
  },

  // --- Đánh giá / tên chương / tác giả ---
  ratingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
  },
  ratingValue: {
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  starsRow: {
    flexDirection: 'row',
    gap: 2,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.h1,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h1,
    textAlign: 'center',
  },
  titleSkeleton: {
    alignSelf: 'stretch',
    alignItems: 'center',
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    maxWidth: '100%',
  },
  metaText: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    flexShrink: 1,
  },
  metaDot: {
    width: 3,
    height: 3,
    borderRadius: 2,
    backgroundColor: Palette.faint,
    marginHorizontal: Spacing.xxs,
  },

  // --- Khối điều khiển dưới ---
  bottom: {
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.md,
    gap: Spacing.sm,
    backgroundColor: Palette.bgDeep,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Palette.border,
    ...Shadow.sheet,
  },
  slider: {
    width: '100%',
    height: 32,
  },
  timeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    marginTop: -Spacing.xs,
  },
  timeText: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    fontVariant: ['tabular-nums'],
  },
  skipBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    height: 30,
    paddingHorizontal: Spacing.sm + 2,
    borderRadius: Radius.pill,
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.sm,
  },
  skipBtnOff: {
    opacity: 0.5,
  },
  skipText: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.black,
  },

  controls: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.xs,
    marginTop: Spacing.xs,
  },
  control: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.xs,
  },
  controlIcon: {
    width: 42,
    height: 42,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  controlIconOff: {
    backgroundColor: Palette.surface,
    opacity: 0.6,
  },
  controlLabel: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
  },
  playBtn: {
    width: 74,
    height: 74,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    marginHorizontal: Spacing.xs,
    marginBottom: Spacing.lg,
    backgroundColor: Palette.accentDeep,
    ...Shadow.float,
  },
  playBtnOff: {
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    shadowOpacity: 0,
    elevation: 0,
  },
  playIcon: {
    marginLeft: 4,
  },

  // --- Thông báo ---
  notice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    padding: Spacing.md,
    borderRadius: Radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    marginBottom: Spacing.xs,
  },
  noticeTitle: {
    fontSize: FontSize.small,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.small,
  },
  noticeDesc: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.caption + 2,
    marginTop: 2,
  },
  noticeBtn: {
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm,
    borderRadius: Radius.pill,
  },
  noticeBtnText: {
    color: Palette.onAccent,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.black,
  },

  // --- Mục lục ---
  sheetWrap: {
    flex: 1,
    justifyContent: 'flex-end',
    // cùng độ mờ với bảng cài đặt đọc để 2 bottom sheet đồng bộ
    backgroundColor: Palette.overlay,
  },
  sheet: {
    maxHeight: '72%',
    backgroundColor: Palette.surfaceHigh,
    borderTopLeftRadius: Radius.xxl,
    borderTopRightRadius: Radius.xxl,
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.sm,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.sheet,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: Radius.pill,
    backgroundColor: Palette.borderStrong,
    marginBottom: Spacing.md,
  },
  sheetHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    paddingBottom: Spacing.md,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  sheetTitle: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h2,
  },
  sheetSubtitle: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
  },
  sheetList: {
    paddingVertical: Spacing.sm,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    height: SHEET_ROW_HEIGHT,
    paddingHorizontal: Spacing.sm,
    borderRadius: Radius.md,
  },
  rowActive: {
    backgroundColor: Palette.accentDim,
  },
  rowNum: {
    minWidth: 30,
    height: 26,
    paddingHorizontal: Spacing.xs,
    borderRadius: Radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  rowNumActive: {
    backgroundColor: Palette.accentDeep,
    borderColor: 'transparent',
  },
  rowNumText: {
    color: Palette.muted,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.black,
  },
  rowNumTextActive: {
    color: Palette.onAccent,
  },
  rowTitle: {
    flex: 1,
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.small,
  },
  rowTitleActive: {
    color: Palette.accentDeep,
    fontWeight: FontWeight.black,
  },
  rowNoAudio: {
    color: Palette.faint,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.semibold,
  },

});
