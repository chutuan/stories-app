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
  hashSeed,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
} from '@/constants/theme';
import { getStory, type ChapterMeta, type Story } from '@/lib/api';
import { COIN_PER_CHAPTER, useWallet } from '@/store/wallet';

type IoniconName = keyof typeof Ionicons.glyphMap;

/**
 * Chương trong danh sách + trường `has_audio` mà backend đã bổ sung.
 * `ChapterMeta` trong `@/lib/api` chưa khai báo trường này (file api.ts do một
 * agent khác giữ), nên mở rộng tại chỗ. Nếu api.ts thêm `has_audio: boolean`
 * thì giao của hai kiểu vẫn ra `boolean`, không xung đột.
 */
type ChapterItem = ChapterMeta & { has_audio?: boolean };

/** Chiều cao vùng ảnh nền mờ (chưa tính safe-area). */
const BACKDROP_HEIGHT = 340;
/** Bìa sắc nét ở giữa hero. */
const COVER_WIDTH = 150;
/** Số dòng mô tả khi thu gọn. */
const DESC_LINES = 4;
/** Mô tả dài hơn ngần này ký tự thì mới hiện nút "Xem thêm". */
const DESC_TOGGLE_MIN = 165;

/** 1,2 N · 3,4 Tr — rút gọn số lớn theo cách viết tiếng Việt. */
function formatCount(value: number): string {
  if (!Number.isFinite(value) || value <= 0) return '0';
  const short = (n: number) => n.toFixed(1).replace(/\.0$/, '').replace('.', ',');
  if (value >= 1_000_000) return `${short(value / 1_000_000)} Tr`;
  if (value >= 1_000) return `${short(value / 1_000)} N`;
  return String(Math.round(value));
}

/**
 * ⚠️ DỮ LIỆU MÔ PHỎNG — API hiện CHƯA có trường đánh giá (rating).
 * Điểm số và số lượt đánh giá được suy ra TẤT ĐỊNH từ id truyện: cùng một
 * truyện luôn ra cùng con số, không nhảy mỗi lần render hay mỗi lần mở app.
 * Khi backend có rating thật, xoá hàm này và đọc thẳng từ `story`.
 *
 * @returns `score` trong khoảng 8.5–9.9; `count` là số lượt đánh giá giả lập
 *          (một phần cố định theo id + một phần tỉ lệ với lượt xem).
 */
function simulatedRating(storyId: number, views: number): { score: number; count: number } {
  const h = hashSeed(`rating:${storyId}`);
  const score = 8.5 + (h % 15) / 10;
  const count = 180 + (h % 620) + Math.round(Math.max(0, views) / 260);
  return { score, count };
}

/** Đổi điểm thang 10 thành 5 ngôi sao đầy / nửa / rỗng. */
function starIcons(score10: number): IoniconName[] {
  const stars = score10 / 2;
  return [0, 1, 2, 3, 4].map((i) => {
    if (stars >= i + 0.75) return 'star';
    if (stars >= i + 0.25) return 'star-half';
    return 'star-outline';
  });
}

/**
 * Bỏ tiền tố "Chương N" trùng lặp trong tên chương, vì số chương đã hiện ở ô bên trái.
 * Không khớp thì giữ nguyên tên gốc từ API.
 */
function chapterLabel(chapter: ChapterItem): string {
  const raw = (chapter.title ?? '').trim();
  if (!raw) return `Chương ${chapter.number}`;
  const prefix = new RegExp(`^(chương|chuong|chapter)\\s*0*${chapter.number}\\b\\s*[:.\\-–—]*\\s*`, 'i');
  const stripped = raw.replace(prefix, '').trim();
  return stripped.length > 0 ? stripped : raw;
}

export default function StoryDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const insets = useSafeAreaInsets();
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
        if (alive) setError('Không tải được truyện.');
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
      accessibilityLabel="Quay lại"
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
          title="Không tải được truyện"
          description={error ?? 'Không có dữ liệu để hiển thị. Kiểm tra kết nối rồi thử lại nhé.'}
          actionLabel="Thử lại"
          onAction={retry}
        />
        {backButton}
      </View>
    );
  }

  const saved = isSaved(story.id);
  const completed = story.status === 'completed';
  const firstChapter = chapters[0]?.number ?? 1;
  const rating = simulatedRating(story.id, story.views);

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
            style={[styles.backdrop, { height: BACKDROP_HEIGHT + insets.top }]}>
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

          <View style={[styles.heroContent, { paddingTop: insets.top + 56 }]}>
            {/* Bìa sắc nét, bo góc + bóng mềm */}
            <View style={styles.coverShadow}>
              <StoryCover
                thumbnailUrl={story.thumbnail_url}
                title={story.title}
                seed={story.id}
                showRibbon={false}
                showChapters={false}
              />
            </View>

            <Text style={styles.title} numberOfLines={3}>
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

            {/* ---------- Đánh giá (dữ liệu mô phỏng, xem simulatedRating) ---------- */}
            <View
              style={styles.ratingRow}
              accessibilityRole="text"
              accessibilityLabel={`Đánh giá ${rating.score.toFixed(1)} trên 10, ${rating.count} lượt`}>
              <Text style={styles.ratingScore}>{rating.score.toFixed(1)}</Text>
              <View style={styles.stars}>
                {starIcons(rating.score).map((name, i) => (
                  <Ionicons key={i} name={name} size={14} color={Palette.coin} />
                ))}
              </View>
              <Text style={styles.ratingCount} numberOfLines={1}>
                {formatCount(rating.count)} đánh giá
              </Text>
            </View>

            {/* ---------- Hàng thông tin nhanh: 3 ô ngăn cách ---------- */}
            <View style={styles.statCard}>
              <StatCell
                icon={completed ? 'checkmark-done-outline' : 'time-outline'}
                value={story.status_label}
                label="Trạng thái"
                tone={completed ? Palette.freeDeep : Palette.accentDeep}
              />
              <View style={styles.statDivider} />
              <StatCell
                icon="layers-outline"
                value={String(story.chapters_count)}
                label="Số chương"
                tone={Palette.text}
              />
              <View style={styles.statDivider} />
              <StatCell
                icon="eye-outline"
                value={formatCount(story.views)}
                label="Lượt xem"
                tone={Palette.text}
              />
            </View>

            {/* ---------- Thể loại ---------- */}
            {story.categories.length > 0 ? (
              <View style={styles.chips}>
                {story.categories.map((c) => (
                  <Chip key={c.id} label={c.name} variant="accent" size="sm" />
                ))}
              </View>
            ) : null}

            {/* ---------- Hành động chính ---------- */}
            <View style={styles.actions}>
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Đọc ngay"
                onPress={() => openChapter(firstChapter)}
                style={({ pressed }) => [styles.readBtn, pressed && styles.pressed]}>
                <Ionicons name="book" size={17} color={Palette.onAccent} />
                <Text style={styles.readBtnText}>Đọc ngay</Text>
              </Pressable>

              <Pressable
                accessibilityRole="button"
                accessibilityState={{ selected: saved }}
                accessibilityLabel={saved ? 'Bỏ lưu truyện' : 'Lưu truyện'}
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
                  {saved ? 'Đã lưu' : 'Lưu truyện'}
                </Text>
              </Pressable>
            </View>

            {/* ---------- Nghe truyện (mờ đi khi chưa có audio) ---------- */}
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={
                audioChapter ? `Nghe chương ${audioChapter.number}` : 'Chưa có audio'
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
                Nghe truyện
              </Text>
              <Text style={styles.listenHint} numberOfLines={1}>
                {audioChapter ? `Chương ${audioChapter.number}` : 'Chưa có audio'}
              </Text>
              {audioChapter ? (
                <Ionicons name="chevron-forward" size={15} color={Palette.accentDeep} />
              ) : null}
            </Pressable>
          </View>
        </View>

        {/* ---------- Giới thiệu ---------- */}
        {description ? (
          <View style={styles.section}>
            <SectionHeader title="Giới thiệu" icon="information-circle-outline" />
            <View style={styles.sectionBody}>
              <View style={styles.card}>
                <Text
                  style={styles.desc}
                  numberOfLines={descExpanded || !canToggleDesc ? undefined : DESC_LINES}>
                  {description}
                </Text>
                {canToggleDesc ? (
                  <Pressable
                    accessibilityRole="button"
                    accessibilityLabel={descExpanded ? 'Thu gọn giới thiệu' : 'Xem thêm giới thiệu'}
                    hitSlop={8}
                    onPress={() => setDescExpanded((v) => !v)}
                    style={({ pressed }) => [styles.descToggle, pressed && styles.pressed]}>
                    <Text style={styles.descToggleText}>
                      {descExpanded ? 'Thu gọn' : 'Xem thêm'}
                    </Text>
                    <Ionicons
                      name={descExpanded ? 'chevron-up' : 'chevron-down'}
                      size={14}
                      color={Palette.accentDeep}
                    />
                  </Pressable>
                ) : null}
              </View>
            </View>
          </View>
        ) : null}

        {/* ---------- Danh sách chương ---------- */}
        <View style={styles.section}>
          <SectionHeader
            title="Danh sách chương"
            icon="list-outline"
            subtitle={chapters.length > 0 ? `${chapters.length} chương` : undefined}
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
              title="Chưa có chương nào"
              description="Truyện này chưa được đăng chương. Quay lại sau nhé!"
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
      accessibilityLabel={`Chương ${chapter.number}: ${label}${hasAudio ? ', có audio' : ''}`}
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

      {free ? (
        <Chip label="Miễn phí" variant="free" size="sm" />
      ) : unlocked ? (
        <Chip label="Đã mở" variant="accent" size="sm" icon="checkmark-circle" />
      ) : (
        <Chip label={`${COIN_PER_CHAPTER} xu`} variant="coin" size="sm" icon="lock-closed" />
      )}
    </Pressable>
  );
}

/* ------------------------------------------------------------------ */
/* Khung xương khi đang tải                                             */
/* ------------------------------------------------------------------ */

function DetailSkeleton({ topInset }: { topInset: number }) {
  return (
    <View style={{ paddingTop: topInset + 56 }}>
      <View style={styles.heroContent}>
        <Skeleton width={COVER_WIDTH} aspectRatio={Layout.coverAspect} radius={Radius.cover} />
        <Skeleton width="70%" height={22} radius={Radius.xs} style={styles.skelTitle} />
        <Skeleton width="40%" height={13} radius={Radius.xs} style={styles.skelAuthor} />
        <Skeleton width={170} height={16} radius={Radius.pill} style={styles.skelRating} />
        <Skeleton height={72} radius={Radius.card} style={styles.skelStats} />
        <View style={styles.skelActions}>
          <Skeleton height={48} radius={Radius.pill} style={styles.skelFlex} />
          <Skeleton width={132} height={48} radius={Radius.pill} />
        </View>
        <Skeleton height={44} radius={Radius.pill} style={styles.skelListen} />
      </View>

      <View style={styles.section}>
        <Skeleton width="35%" height={17} radius={Radius.xs} style={styles.skelHeading} />
        <View style={styles.sectionBody}>
          <SkeletonText lines={3} lineHeight={12} lastLineWidth="55%" />
        </View>
      </View>

      <View style={styles.section}>
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
    alignItems: 'center',
    paddingHorizontal: Spacing.screen,
    paddingBottom: Spacing.sm,
    /** nằm TRÊN ảnh nền mờ (backdrop là anh em `position: absolute` phía trước) */
    zIndex: 1,
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

  // --- Thể loại ---
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: Spacing.sm,
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
    marginTop: Spacing.xxl,
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
