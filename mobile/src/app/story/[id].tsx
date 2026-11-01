import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import type { Href } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Chip } from '@/components/chip';
import { EmptyState } from '@/components/empty-state';
import { SectionHeader } from '@/components/section-header';
import { Skeleton, SkeletonText } from '@/components/skeleton';
import { StoryCover } from '@/components/story-card';
import {
  coverGradient,
  FontSize,
  FontWeight,
  Gradients,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
} from '@/constants/theme';
import { getStory, type ChapterMeta, type Story } from '@/lib/api';
import { formatCoins, formatViews, plural, storyStatusLabel } from '@/lib/format';
import { CONTENT_MAX_WIDTH, useResponsive } from '@/lib/responsive';
import { COIN_PER_CHAPTER, useWallet } from '@/store/wallet';

type IoniconName = keyof typeof Ionicons.glyphMap;

/**
 * Chương trong danh sách + trường `has_audio` mà backend đã bổ sung.
 * `ChapterMeta` trong `@/lib/api` chưa khai báo trường này (file api.ts do một
 * agent khác giữ), nên mở rộng tại chỗ. Nếu api.ts thêm `has_audio: boolean`
 * thì giao của hai kiểu vẫn ra `boolean`, không xung đột.
 */
type ChapterItem = ChapterMeta & { has_audio?: boolean };

/** Chiều cao vùng ảnh nền mờ (chưa tính safe-area); màn rộng bìa to hơn nên nền cao hơn. */
const BACKDROP_HEIGHT = 340;
const BACKDROP_HEIGHT_WIDE = 420;
/** Bìa sắc nét ở giữa hero. */
const COVER_WIDTH = 150;
/**
 * Màn rộng: bìa đứng bên trái, thông tin bên phải (như trang sách của Apple Books) thay
 * vì một cột iPhone đặt giữa màn. Cả trang nằm trong cột DETAIL_MAX_WIDTH; bìa co từ
 * COVER_WIDTH_WIDE xuống COVER_WIDTH_WIDE_MIN khi cửa sổ hẹp để cột thông tin còn đủ
 * INFO_MIN_WIDTH cho tên truyện và hàng nút.
 */
const DETAIL_MAX_WIDTH = 880;
const COVER_WIDTH_WIDE = 240;
const COVER_WIDTH_WIDE_MIN = 150;
const INFO_MIN_WIDTH = 340;
const SPLIT_GAP = Spacing.xxxl;
/** Số dòng mô tả khi thu gọn. */
const DESC_LINES = 4;
/** Mô tả dài hơn ngần này ký tự thì mới hiện nút "Xem thêm". */
const DESC_TOGGLE_MIN = 165;

function coverWidthFor(width: number, wide: boolean): number {
  if (!wide) return COVER_WIDTH;
  const inner = Math.min(width, DETAIL_MAX_WIDTH) - Spacing.screen * 2;
  return Math.round(
    Math.max(COVER_WIDTH_WIDE_MIN, Math.min(COVER_WIDTH_WIDE, inner - SPLIT_GAP - INFO_MIN_WIDTH)),
  );
}

/*
 * ĐÃ GỠ: `simulatedRating()` và `starIcons()`.
 *
 * Chúng bịa ra điểm 8.5–9.9 và vài trăm "lượt đánh giá" từ hash id truyện rồi bày
 * ra như đánh giá thật. Không có ai đánh giá cả — backend chưa hề có bảng rating.
 * Đó là bằng chứng xã hội giả với người đọc, và là thứ App Store soi.
 *
 * Khi nào backend có đánh giá THẬT thì dựng lại phần này từ dữ liệu thật.
 */
/**
 * Bỏ tiền tố "Chương N" trùng lặp trong tên chương, vì số chương đã hiện ở ô bên trái.
 * Không khớp thì giữ nguyên tên gốc từ API.
 */
function chapterLabel(chapter: ChapterItem): string {
  const raw = (chapter.title ?? '').trim();
  if (!raw) return `Chapter ${chapter.number}`;
  const prefix = new RegExp(`^(chương|chuong|chapter)\\s*0*${chapter.number}\\b\\s*[:.\\-–—]*\\s*`, 'i');
  const stripped = raw.replace(prefix, '').trim();
  return stripped.length > 0 ? stripped : raw;
}

export default function StoryDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const insets = useSafeAreaInsets();
  const { wide, width } = useResponsive();
  const coverWidth = coverWidthFor(width, wide);
  const router = useRouter();
  const { isSaved, toggleSaved, isUnlocked } = useWallet();

  const [story, setStory] = useState<Story | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [descExpanded, setDescExpanded] = useState(false);
  /** Tăng lên mỗi lần bấm "Thử lại" để chạy lại effect tải truyện. */
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    let alive = true;
    getStory(id)
      .then((data) => {
        if (!alive) return;
        setStory(data);
        setError(null);
      })
      .catch(() => {
        if (alive) setError('Unable to load this story.');
      })
      .finally(() => {
        if (alive) setLoading(false);
      });
    return () => {
      alive = false;
    };
  }, [id, retryCount]);

  /** Bấm "Thử lại" — hiện lại khung xương rồi gọi API. */
  const retry = useCallback(() => {
    setLoading(true);
    setError(null);
    setRetryCount((n) => n + 1);
  }, []);

  const chapters = useMemo<ChapterItem[]>(() => (story?.chapters ?? []) as ChapterItem[], [story]);

  /**
   * Chương đầu tiên CÓ audio — đích của nút "Nghe".
   * Thường là chương 1; nếu chương 1 chưa có MP3 thì nhảy tới chương có audio
   * gần nhất thay vì đưa người dùng vào màn nghe rỗng.
   */
  const audioChapter = useMemo(() => chapters.find((c) => c.has_audio === true) ?? null, [chapters]);

  const openChapter = useCallback(
    (number: number) => {
      if (!story) return;
      router.push(`/reader/${story.id}/${number}`);
    },
    [router, story],
  );

  const openAudio = useCallback(
    (number: number) => {
      if (!story) return;
      // Màn nghe nằm ở src/app/audio/[storyId]/[number].tsx. typedRoutes chỉ
      // sinh kiểu cho các route đã tồn tại lúc build type, nên ép kiểu Href một
      // lần tại đây; đường dẫn vẫn đúng dạng /audio/<id>/<số chương>.
      router.push(`/audio/${story.id}/${number}` as Href);
    },
    [router, story],
  );

  const description = (story?.description ?? '').trim();
  const canToggleDesc = description.length > DESC_TOGGLE_MIN;

  const backButton = (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Back"
      onPress={() => router.back()}
      hitSlop={10}
      style={({ pressed }) => [
        styles.backBtn,
        { top: insets.top + Spacing.sm },
        pressed && styles.pressed,
      ]}>
      <Ionicons name="chevron-back" size={22} color={Palette.text} />
    </Pressable>
  );

  if (loading) {
    return (
      <View style={styles.screen}>
        <Stack.Screen options={{ headerShown: false }} />
        <DetailSkeleton topInset={insets.top} />
        {backButton}
      </View>
    );
  }

  if (error || !story) {
    return (
      <View style={[styles.screen, styles.errorScreen]}>
        <Stack.Screen options={{ headerShown: false }} />
        <EmptyState
          danger
          icon="cloud-offline-outline"
          title="Couldn't load this story"
          description={error ?? 'No data to show. Check your connection and try again.'}
          actionLabel="Retry"
          onAction={retry}
        />
        {backButton}
      </View>
    );
  }

  const saved = isSaved(story.id);
  const completed = story.status === 'completed';
  const firstChapter = chapters[0]?.number ?? 1;

  return (
    <View style={styles.screen}>
      <Stack.Screen options={{ headerShown: false }} />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingBottom: insets.bottom + Spacing.xxxl }}>
        {/* ---------- HERO: ảnh bìa phóng to + làm mờ làm nền ---------- */}
        <View style={styles.hero}>
          <View
            pointerEvents="none"
            style={[
              styles.backdrop,
              { height: (wide ? BACKDROP_HEIGHT_WIDE : BACKDROP_HEIGHT) + insets.top },
            ]}>
            {story.thumbnail_url ? (
              <Image
                source={{ uri: story.thumbnail_url }}
                style={styles.backdropImage}
                contentFit="cover"
                blurRadius={40}
                transition={280}
                cachePolicy="memory-disk"
                accessible={false}
              />
            ) : (
              <LinearGradient
                colors={coverGradient(story.id)}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={styles.fill}
              />
            )}
            {/* Phủ SÁNG: ảnh nhạt dần rồi hoà hẳn vào nền kem của app */}
            <LinearGradient
              colors={Gradients.backdropScrim}
              locations={Gradients.backdropScrimLocations}
              style={styles.fill}
            />
          </View>

          <View
            style={[
              styles.heroContent,
              wide && styles.heroContentWide,
              { paddingTop: insets.top + 56 },
            ]}>
            {/* Bìa sắc nét, bo góc + bóng mềm */}
            <View style={[styles.coverShadow, { width: coverWidth }]}>
              <StoryCover
                thumbnailUrl={story.thumbnail_url}
                title={story.title}
                seed={story.id}
                showRibbon={false}
                showChapters={false}
              />
            </View>

            <View style={wide ? styles.infoWide : styles.info}>
              <Text style={[styles.title, wide && styles.titleWide]} numberOfLines={3}>
                {story.title}
              </Text>

              {story.author ? (
                <View style={styles.authorRow}>
                  <Ionicons name="create-outline" size={13} color={Palette.muted} />
                  <Text style={styles.author} numberOfLines={1}>
                    {story.author}
                  </Text>
                </View>
              ) : null}

              {/* ---------- Hàng thông tin nhanh: 3 ô ngăn cách (màn rộng: một dòng gọn) ---------- */}
              {wide ? (
                <View style={styles.statLine}>
                  <StatInline
                    icon={completed ? 'checkmark-done-outline' : 'time-outline'}
                    text={storyStatusLabel(story.status)}
                    tone={completed ? Palette.freeDeep : Palette.accentDeep}
                  />
                  <StatInline
                    icon="layers-outline"
                    text={plural(story.chapters_count, 'chapter', 'chapters')}
                    tone={Palette.muted}
                  />
                  <StatInline
                    icon="eye-outline"
                    text={`${formatViews(story.views)} ${story.views === 1 ? 'view' : 'views'}`}
                    tone={Palette.muted}
                  />
                </View>
              ) : (
                <View style={styles.statCard}>
                  <StatCell
                    icon={completed ? 'checkmark-done-outline' : 'time-outline'}
                    value={storyStatusLabel(story.status)}
                    label="Status"
                    tone={completed ? Palette.freeDeep : Palette.accentDeep}
                  />
                  <View style={styles.statDivider} />
                  <StatCell
                    icon="layers-outline"
                    value={String(story.chapters_count)}
                    label="Chapters"
                    tone={Palette.text}
                  />
                  <View style={styles.statDivider} />
                  <StatCell
                    icon="eye-outline"
                    value={formatViews(story.views)}
                    label="Views"
                    tone={Palette.text}
                  />
                </View>
              )}

              {/* ---------- Thể loại ---------- */}
              {story.categories.length > 0 ? (
                <View style={[styles.chips, wide && styles.chipsWide]}>
                  {story.categories.map((c) => (
                    <Chip key={c.id} label={c.name} variant="accent" size="sm" />
                  ))}
                </View>
              ) : null}

              {/* ---------- Màn rộng: tóm tắt nằm ngay cạnh bìa ---------- */}
              {wide && description ? (
                <View style={styles.synopsisWide}>
                  <Synopsis
                    text={description}
                    expanded={descExpanded}
                    canToggle={canToggleDesc}
                    onToggle={() => setDescExpanded((v) => !v)}
                  />
                </View>
              ) : null}

              {/* ---------- Hành động chính ---------- */}
              <View style={styles.actions}>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="Read now"
                  onPress={() => openChapter(firstChapter)}
                  style={({ pressed }) => [styles.readBtn, pressed && styles.pressed]}>
                  <Ionicons name="book" size={17} color={Palette.onAccent} />
                  <Text style={styles.readBtnText}>Read now</Text>
                </Pressable>

                <Pressable
                  accessibilityRole="button"
                  accessibilityState={{ selected: saved }}
                  accessibilityLabel={saved ? 'Remove from library' : 'Save to library'}
                  onPress={() => toggleSaved(story.id)}
                  style={({ pressed }) => [
                    styles.saveBtn,
                    saved && styles.saveBtnActive,
                    pressed && styles.pressed,
                  ]}>
                  <Ionicons
                    name={saved ? 'bookmark' : 'bookmark-outline'}
                    size={17}
                    color={saved ? Palette.accentDeep : Palette.muted}
                  />
                  <Text style={[styles.saveBtnText, saved && styles.saveBtnTextActive]}>
                    {saved ? 'Saved' : 'Save'}
                  </Text>
                </Pressable>
              </View>

              {/* ---------- Nghe truyện (mờ đi khi chưa có audio) ---------- */}
              <Pressable
                accessibilityRole="button"
                accessibilityLabel={
                  audioChapter ? `Listen to chapter ${audioChapter.number}` : 'No audio yet'
                }
                accessibilityState={{ disabled: !audioChapter }}
                disabled={!audioChapter}
                onPress={() => {
                  if (audioChapter) openAudio(audioChapter.number);
                }}
                style={({ pressed }) => [
                  styles.listenBtn,
                  !audioChapter && styles.listenBtnDisabled,
                  pressed && styles.pressed,
                ]}>
                <View
                  style={[styles.listenIcon, !audioChapter && styles.listenIconDisabled]}
                  pointerEvents="none">
                  <Ionicons
                    name="headset"
                    size={16}
                    color={audioChapter ? Palette.accentDeep : Palette.faint}
                  />
                </View>
                <Text
                  style={[styles.listenText, !audioChapter && styles.listenTextDisabled]}
                  numberOfLines={1}>
                  Listen
                </Text>
                <Text style={styles.listenHint} numberOfLines={1}>
                  {audioChapter ? `Chapter ${audioChapter.number}` : 'No audio yet'}
                </Text>
                {audioChapter ? (
                  <Ionicons name="chevron-forward" size={15} color={Palette.accentDeep} />
                ) : null}
              </Pressable>
            </View>
          </View>
        </View>

        {/* ---------- Giới thiệu ---------- */}
        {!wide && description ? (
          <View style={styles.section}>
            <SectionHeader title="Synopsis" icon="information-circle-outline" />
            <View style={styles.sectionBody}>
              <View style={styles.card}>
                <Synopsis
                  text={description}
                  expanded={descExpanded}
                  canToggle={canToggleDesc}
                  onToggle={() => setDescExpanded((v) => !v)}
                />
              </View>
            </View>
          </View>
        ) : null}

        {/* ---------- Danh sách chương ---------- */}
        <View style={[styles.section, wide && styles.sectionWide]}>
          <SectionHeader
            title="Chapters"
            icon="list-outline"
            subtitle={
              chapters.length > 0 ? plural(chapters.length, 'chapter', 'chapters') : undefined
            }
          />
          {chapters.length > 0 ? (
            <View style={styles.sectionBody}>
              <View style={styles.chapterList}>
                {chapters.map((ch, index) => (
                  <ChapterRow
                    key={ch.id}
                    chapter={ch}
                    first={index === 0}
                    unlocked={isUnlocked(story.id, ch.number)}
                    onPress={() => openChapter(ch.number)}
                  />
                ))}
              </View>
            </View>
          ) : (
            <EmptyState
              icon="book-outline"
              title="No chapters yet"
              description="This story has no published chapters. Check back later!"
            />
          )}
        </View>
      </ScrollView>

      {backButton}
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Ô thông tin nhanh                                                    */
/* ------------------------------------------------------------------ */

function StatCell({
  icon,
  value,
  label,
  tone,
}: {
  icon: IoniconName;
  value: string;
  label: string;
  tone: string;
}) {
  return (
    <View style={styles.statCell}>
      <Ionicons name={icon} size={15} color={tone} />
      <Text style={[styles.statValue, { color: tone }]} numberOfLines={1}>
        {value}
      </Text>
      <Text style={styles.statLabel} numberOfLines={1}>
        {label}
      </Text>
    </View>
  );
}

/** Một mục trong dòng thông tin gọn của màn rộng: icon + chữ cùng tông màu. */
function StatInline({ icon, text, tone }: { icon: IoniconName; text: string; tone: string }) {
  return (
    <View style={styles.statInline}>
      <Ionicons name={icon} size={15} color={tone} />
      <Text style={[styles.statInlineText, { color: tone }]} numberOfLines={1}>
        {text}
      </Text>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Tóm tắt: thu gọn DESC_LINES dòng + nút Xem thêm                     */
/* ------------------------------------------------------------------ */

function Synopsis({
  text,
  expanded,
  canToggle,
  onToggle,
}: {
  text: string;
  expanded: boolean;
  canToggle: boolean;
  onToggle: () => void;
}) {
  return (
    <>
      <Text style={styles.desc} numberOfLines={expanded || !canToggle ? undefined : DESC_LINES}>
        {text}
      </Text>
      {canToggle ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={expanded ? 'Show less' : 'Show more'}
          hitSlop={8}
          onPress={onToggle}
          style={({ pressed }) => [styles.descToggle, pressed && styles.pressed]}>
          <Text style={styles.descToggleText}>{expanded ? 'Show less' : 'Show more'}</Text>
          <Ionicons
            name={expanded ? 'chevron-up' : 'chevron-down'}
            size={14}
            color={Palette.accentDeep}
          />
        </Pressable>
      ) : null}
    </>
  );
}

/* ------------------------------------------------------------------ */
/* Một dòng chương                                                      */
/* ------------------------------------------------------------------ */

function ChapterRow({
  chapter,
  unlocked,
  first,
  onPress,
}: {
  chapter: ChapterItem;
  unlocked: boolean;
  first: boolean;
  onPress: () => void;
}) {
  const free = chapter.is_free;
  const open = free || unlocked;
  const label = chapterLabel(chapter);
  const hasAudio = chapter.has_audio === true;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Chapter ${chapter.number}: ${label}${hasAudio ? ', audio available' : ''}`}
      onPress={onPress}
      style={({ pressed }) => [
        styles.chapterRow,
        !first && styles.chapterRowDivider,
        pressed && styles.chapterRowPressed,
      ]}>
      <View
        style={[styles.numBox, free && styles.numBoxFree, unlocked && styles.numBoxUnlocked]}>
        <Text
          style={[styles.numText, free && styles.numTextFree, unlocked && styles.numTextUnlocked]}
          numberOfLines={1}>
          {chapter.number}
        </Text>
      </View>

      <Text style={[styles.chapterTitle, !open && styles.chapterTitleLocked]} numberOfLines={1}>
        {label}
      </Text>

      {/* Chỉ báo chương có audio — KHÔNG bọc Pressable: Pressable lồng nhau
          dựng ra <button> lồng <button> trên web (DOM không hợp lệ). Muốn nghe
          thì dùng nút "Nghe truyện" ở đầu trang. */}
      {hasAudio ? (
        <View style={styles.rowListen}>
          <Ionicons name="headset-outline" size={14} color={Palette.accentDeep} />
        </View>
      ) : null}

      {/* Chip mặc định `alignSelf: flex-start` -> phải kéo về giữa hàng cho thẳng với số chương */}
      {free ? (
        <Chip label="Free" variant="free" size="sm" style={styles.rowChip} />
      ) : unlocked ? (
        <Chip
          label="Unlocked"
          variant="accent"
          size="sm"
          icon="checkmark-circle"
          style={styles.rowChip}
        />
      ) : (
        <Chip
          label={formatCoins(COIN_PER_CHAPTER)}
          variant="coin"
          size="sm"
          icon="lock-closed"
          style={styles.rowChip}
        />
      )}
    </Pressable>
  );
}

/* ------------------------------------------------------------------ */
/* Khung xương khi đang tải                                             */
/* ------------------------------------------------------------------ */

function DetailSkeleton({ topInset }: { topInset: number }) {
  const { wide, width } = useResponsive();
  return (
    <View style={{ paddingTop: topInset + 56 }}>
      <View style={[styles.heroContent, wide && styles.heroContentWide]}>
        <Skeleton
          width={coverWidthFor(width, wide)}
          aspectRatio={Layout.coverAspect}
          radius={Radius.cover}
        />
        <View style={wide ? styles.infoWide : styles.info}>
          <Skeleton
            width="70%"
            height={wide ? 30 : 22}
            radius={Radius.xs}
            style={!wide && styles.skelTitle}
          />
          <Skeleton width="40%" height={13} radius={Radius.xs} style={styles.skelAuthor} />
          {wide ? (
            <>
              <Skeleton width={260} height={16} radius={Radius.pill} style={styles.skelRating} />
              <View style={styles.skelSynopsisWide}>
                <SkeletonText lines={4} lineHeight={12} lastLineWidth="55%" />
              </View>
            </>
          ) : (
            <>
              <Skeleton width={170} height={16} radius={Radius.pill} style={styles.skelRating} />
              <Skeleton height={72} radius={Radius.card} style={styles.skelStats} />
            </>
          )}
          <View style={styles.skelActions}>
            <Skeleton height={48} radius={Radius.pill} style={styles.skelFlex} />
            <Skeleton width={132} height={48} radius={Radius.pill} />
          </View>
          <Skeleton height={44} radius={Radius.pill} style={styles.skelListen} />
        </View>
      </View>

      {!wide ? (
        <View style={styles.section}>
          <Skeleton width="35%" height={17} radius={Radius.xs} style={styles.skelHeading} />
          <View style={styles.sectionBody}>
            <SkeletonText lines={3} lineHeight={12} lastLineWidth="55%" />
          </View>
        </View>
      ) : null}

      <View style={[styles.section, wide && styles.sectionWide]}>
        <Skeleton width="45%" height={17} radius={Radius.xs} style={styles.skelHeading} />
        <View style={styles.sectionBody}>
          <View style={styles.chapterList}>
            {[0, 1, 2, 3, 4, 5].map((i) => (
              <View key={i} style={[styles.chapterRow, i !== 0 && styles.chapterRowDivider]}>
                <Skeleton width={34} height={28} radius={Radius.sm} />
                <View style={styles.skelFlex}>
                  <Skeleton height={12} width={`${70 - (i % 3) * 12}%`} radius={Radius.xs} />
                </View>
                <Skeleton width={62} height={20} radius={Radius.pill} />
              </View>
            ))}
          </View>
        </View>
      </View>
    </View>
  );
}

/* ------------------------------------------------------------------ */

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  errorScreen: {
    justifyContent: 'center',
  },
  fill: {
    ...StyleSheet.absoluteFill,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  // --- Nút quay lại nổi (nền sáng -> viên trắng + bóng, icon tối) ---
  backBtn: {
    position: 'absolute',
    left: Spacing.screen,
    zIndex: 20,
    width: 38,
    height: 38,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.float,
  },

  // --- Hero ---
  hero: {
    position: 'relative',
  },
  backdrop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    overflow: 'hidden',
    backgroundColor: Palette.surfaceAlt,
  },
  /** ảnh nền phóng to hơn khung để mép mờ không lộ viền */
  backdropImage: {
    position: 'absolute',
    top: '-8%',
    left: '-8%',
    width: '116%',
    height: '116%',
  },
  heroContent: {
    // Màn rộng: cột nội dung ở giữa, ảnh nền mờ vẫn tràn hết bề ngang.
    width: '100%',
    maxWidth: CONTENT_MAX_WIDTH,
    alignSelf: 'center',
    alignItems: 'center',
    paddingHorizontal: Spacing.screen,
    paddingBottom: Spacing.sm,
    /** nằm TRÊN ảnh nền mờ (backdrop là anh em `position: absolute` phía trước) */
    zIndex: 1,
  },
  /** Màn rộng: bìa bên trái, cột thông tin bên phải, khung rộng hơn cột đọc thường. */
  heroContentWide: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: SPLIT_GAP,
    maxWidth: DETAIL_MAX_WIDTH,
  },
  /** iPhone: mọi thứ xếp dọc, căn giữa dưới bìa */
  info: {
    alignSelf: 'stretch',
    alignItems: 'center',
  },
  infoWide: {
    flex: 1,
    minWidth: 0,
    alignItems: 'flex-start',
  },
  coverShadow: {
    width: COVER_WIDTH,
    borderRadius: Radius.cover,
    // Android cần backgroundColor thì elevation mới đổ bóng
    backgroundColor: Palette.surfaceAlt,
    ...Shadow.float,
  },
  title: {
    marginTop: Spacing.lg,
    color: Palette.text,
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.display,
    textAlign: 'center',
  },
  titleWide: {
    marginTop: 0,
    fontSize: 30,
    lineHeight: 36,
    textAlign: 'left',
  },
  authorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs + 2,
    marginTop: Spacing.sm,
    maxWidth: '90%',
  },
  author: {
    color: Palette.muted,
    fontSize: FontSize.small,
    fontWeight: FontWeight.medium,
    flexShrink: 1,
  },

  // --- Đánh giá (mô phỏng) ---
  ratingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    marginTop: Spacing.md,
  },
  ratingScore: {
    color: Palette.accentDeep,
    fontSize: FontSize.h1,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h1,
  },
  stars: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 1,
  },
  ratingCount: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    flexShrink: 1,
  },

  // --- Hàng thông tin nhanh ---
  statCard: {
    flexDirection: 'row',
    alignItems: 'stretch',
    alignSelf: 'stretch',
    marginTop: Spacing.xl,
    paddingVertical: Spacing.md,
    borderRadius: Radius.card,
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  statCell: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.xs,
    paddingHorizontal: Spacing.xs,
  },
  statValue: {
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small,
  },
  statLabel: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
  },
  statDivider: {
    width: StyleSheet.hairlineWidth,
    alignSelf: 'center',
    height: 34,
    backgroundColor: Palette.borderStrong,
  },
  /** Màn rộng: trạng thái · số chương · lượt xem trên một dòng, thay cho thẻ 3 ô */
  statLine: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    columnGap: Spacing.lg,
    rowGap: Spacing.xs,
    marginTop: Spacing.md,
  },
  statInline: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs + 1,
  },
  statInlineText: {
    fontSize: FontSize.small,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.small,
  },

  // --- Thể loại ---
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: Spacing.sm,
    marginTop: Spacing.lg,
  },
  chipsWide: {
    justifyContent: 'flex-start',
  },
  synopsisWide: {
    alignSelf: 'stretch',
    marginTop: Spacing.lg,
  },

  // --- Hành động ---
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'stretch',
    gap: Spacing.md,
    marginTop: Spacing.xl,
  },
  readBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    height: 48,
    borderRadius: Radius.pill,
    // cam ĐẶC + chữ trắng (accentDeep mới đủ tương phản với onAccent)
    backgroundColor: Palette.accentDeep,
    ...Shadow.card,
  },
  readBtnText: {
    color: Palette.onAccent,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  saveBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm - 2,
    height: 48,
    paddingHorizontal: Spacing.lg,
    borderRadius: Radius.pill,
    borderWidth: 1,
    borderColor: Palette.borderStrong,
    backgroundColor: Palette.surface,
    ...Shadow.sm,
  },
  saveBtnActive: {
    backgroundColor: Palette.accentDim,
    borderColor: Palette.accentBorder,
  },
  saveBtnText: {
    color: Palette.muted,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
  },
  saveBtnTextActive: {
    color: Palette.accentDeep,
  },

  // --- Nghe truyện ---
  listenBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'stretch',
    gap: Spacing.sm,
    height: 46,
    marginTop: Spacing.md,
    paddingHorizontal: Spacing.md,
    borderRadius: Radius.pill,
    backgroundColor: Palette.surface,
    borderWidth: 1,
    borderColor: Palette.accentBorder,
    ...Shadow.sm,
  },
  listenBtnDisabled: {
    borderColor: Palette.border,
    backgroundColor: Palette.surfaceAlt,
    shadowOpacity: 0,
    elevation: 0,
  },
  listenIcon: {
    width: 28,
    height: 28,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDim,
  },
  listenIconDisabled: {
    backgroundColor: Palette.border,
  },
  listenText: {
    flex: 1,
    color: Palette.accentDeep,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
  },
  listenTextDisabled: {
    color: Palette.faint,
  },
  listenHint: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
  },

  // --- Mục nội dung ---
  section: {
    width: '100%',
    maxWidth: CONTENT_MAX_WIDTH,
    alignSelf: 'center',
    marginTop: Spacing.xxl,
  },
  sectionWide: {
    maxWidth: DETAIL_MAX_WIDTH,
  },
  sectionBody: {
    paddingHorizontal: Spacing.screen,
  },
  card: {
    padding: Spacing.lg,
    borderRadius: Radius.card,
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  desc: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.body,
  },
  descToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: Spacing.xs,
    marginTop: Spacing.sm,
    paddingVertical: Spacing.xs,
  },
  descToggleText: {
    color: Palette.accentDeep,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.bold,
  },

  // --- Danh sách chương ---
  chapterList: {
    borderRadius: Radius.card,
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    overflow: 'hidden',
    ...Shadow.card,
  },
  chapterRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md,
    minHeight: 56,
  },
  chapterRowDivider: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Palette.border,
  },
  chapterRowPressed: {
    backgroundColor: Palette.surfaceAlt,
  },
  numBox: {
    minWidth: 34,
    height: 28,
    paddingHorizontal: Spacing.xs + 2,
    borderRadius: Radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
  },
  numBoxFree: {
    backgroundColor: Palette.freeDim,
  },
  numBoxUnlocked: {
    backgroundColor: Palette.accentDim,
  },
  numText: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.bold,
  },
  numTextFree: {
    color: Palette.freeDeep,
  },
  numTextUnlocked: {
    color: Palette.accentDeep,
  },
  chapterTitle: {
    flex: 1,
    color: Palette.text,
    fontSize: FontSize.small + 1,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.small,
  },
  chapterTitleLocked: {
    color: Palette.muted,
    fontWeight: FontWeight.medium,
  },
  rowChip: {
    alignSelf: 'center',
  },
  rowListen: {
    width: 26,
    height: 26,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDim,
  },

  // --- Skeleton ---
  skelTitle: {
    marginTop: Spacing.lg,
  },
  skelAuthor: {
    marginTop: Spacing.sm,
  },
  skelRating: {
    marginTop: Spacing.md,
  },
  skelStats: {
    marginTop: Spacing.xl,
  },
  skelActions: {
    flexDirection: 'row',
    alignSelf: 'stretch',
    gap: Spacing.md,
    marginTop: Spacing.xl,
  },
  skelSynopsisWide: {
    alignSelf: 'stretch',
    marginTop: Spacing.lg,
  },
  skelListen: {
    alignSelf: 'stretch',
    marginTop: Spacing.md,
  },
  skelFlex: {
    flex: 1,
  },
  skelHeading: {
    marginLeft: Spacing.screen,
    marginBottom: Spacing.md,
  },
});
