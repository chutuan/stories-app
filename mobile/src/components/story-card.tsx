import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { Link } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import type { StyleProp, ViewStyle } from 'react-native';

import { Skeleton } from '@/components/skeleton';
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
  withAlpha,
} from '@/constants/theme';
import type { StoryCard } from '@/lib/api';
import { storyRibbonLabel } from '@/lib/format';

/** Lấy chữ cái đầu của tên truyện để làm bìa dự phòng. */
function initialOf(title?: string | null): string {
  if (!title) return '?';
  const ch = title.trim().charAt(0);
  return ch ? ch.toUpperCase() : '?';
}

export interface StoryCoverProps {
  /** URL ảnh bìa; null/lỗi -> bìa gradient ấm + chữ cái đầu */
  thumbnailUrl: string | null;
  /** tên truyện — dùng cho bìa dự phòng và accessibility */
  title?: string;
  /** số chương; <= 0 hoặc bỏ trống thì ẩn badge */
  chaptersCount?: number;
  /** 'completed' | 'ongoing' */
  status?: string;
  /** mặc định 3/4 */
  aspectRatio?: number;
  /** mặc định Radius.cover = 14 */
  radius?: number;
  /** ẩn/hiện ribbon trạng thái, mặc định true */
  showRibbon?: boolean;
  /** ẩn/hiện badge số chương, mặc định true */
  showChapters?: boolean;
  /** khoá chọn màu gradient dự phòng, mặc định là title */
  seed?: string | number;
  style?: StyleProp<ViewStyle>;
}

/**
 * Bìa truyện 3:4: ảnh + ribbon trạng thái (trên-trái) + badge số chương (dưới-phải).
 *
 * Ảnh bìa là artwork TỐI nên mọi thứ đè lên nó vẫn dùng nền tối mờ / màu đặc
 * + chữ trắng — đây là ngoại lệ duy nhất của giao diện sáng.
 */
export function StoryCover({
  thumbnailUrl,
  title,
  chaptersCount,
  status,
  aspectRatio = Layout.coverAspect,
  radius = Radius.cover,
  showRibbon = true,
  showChapters = true,
  seed,
  style,
}: StoryCoverProps) {
  /**
   * Kết quả tải ảnh, gắn kèm URL của nó. Nhờ vậy khi FlatList tái sử dụng thẻ
   * (đổi thumbnailUrl) trạng thái tự quay về 'loading' mà không cần useEffect.
   */
  const [loaded, setLoaded] = useState<{ url: string; status: 'ready' | 'error' } | null>(null);

  const state: 'loading' | 'ready' | 'error' = !thumbnailUrl
    ? 'error'
    : loaded?.url === thumbnailUrl
      ? loaded.status
      : 'loading';

  const markLoaded = (url: string, result: 'ready' | 'error') => {
    setLoaded((prev) => (prev?.url === url ? prev : { url, status: result }));
  };

  const completed = status === 'completed';
  // nhãn ribbon lấy từ `status`, KHÔNG dùng `status_label` tiếng Việt của API
  const ribbonLabel = storyRibbonLabel(status ?? '');
  const hasChapters = showChapters && typeof chaptersCount === 'number' && chaptersCount > 0;
  const fallback = coverGradient(seed ?? title ?? '');

  return (
    <View style={[styles.coverWrap, { aspectRatio, borderRadius: radius }, style]}>
      {/* Ảnh bìa */}
      {thumbnailUrl && state !== 'error' ? (
        <Image
          source={{ uri: thumbnailUrl }}
          style={styles.fill}
          contentFit="cover"
          transition={220}
          cachePolicy="memory-disk"
          recyclingKey={thumbnailUrl}
          accessible={false}
          onLoad={() => markLoaded(thumbnailUrl, 'ready')}
          onError={() => markLoaded(thumbnailUrl, 'error')}
          // chốt chặn: nếu onLoad không bắn (ảnh cache), vẫn tắt skeleton
          onLoadEnd={() => markLoaded(thumbnailUrl, 'ready')}
        />
      ) : null}

      {/* Bìa dự phòng: gradient ấm theo tên truyện + chữ cái đầu */}
      {state === 'error' ? (
        <LinearGradient colors={fallback} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.fill}>
          <View style={styles.fallbackInner}>
            <Text style={styles.fallbackLetter} numberOfLines={1}>
              {initialOf(title)}
            </Text>
          </View>
        </LinearGradient>
      ) : null}

      {/* Skeleton khi đang tải ảnh */}
      {state === 'loading' ? (
        <Skeleton style={StyleSheet.absoluteFill} width="100%" height="100%" radius={0} />
      ) : null}

      {/* Scrim dưới (tối, nằm TRÊN ẢNH) để badge luôn đọc được */}
      <LinearGradient colors={Gradients.scrimCover} style={styles.scrim} pointerEvents="none" />

      {/* Ribbon trạng thái — góc trên trái, gọn */}
      {showRibbon && status ? (
        <View style={[styles.ribbon, completed ? styles.ribbonFull : styles.ribbonOngoing]}>
          <Text style={styles.ribbonText} numberOfLines={1}>
            {ribbonLabel}
          </Text>
        </View>
      ) : null}

      {/* Badge số chương — góc dưới phải, nền tối mờ vì đè lên ảnh */}
      {hasChapters ? (
        <View style={styles.chapBadge}>
          <Ionicons name="layers-outline" size={11} color={Palette.white} />
          <Text style={styles.chapBadgeText}>{chaptersCount}</Text>
        </View>
      ) : null}
    </View>
  );
}

export interface StoryCardViewProps {
  story: StoryCard;
  /** bề rộng cố định (hàng ngang); bỏ trống -> flex: 1 trong lưới */
  width?: number;
  /** hiện dòng thể loại, mặc định true */
  showCategory?: boolean;
  style?: StyleProp<ViewStyle>;
}

/**
 * Card truyện: khối TRẮNG bo 16 + bóng mềm, bên trong là bìa + tên (2 dòng)
 * + thể loại (1 dòng). Tap -> /story/[id].
 */
export function StoryCardView({ story, width, showCategory = true, style }: StoryCardViewProps) {
  const categoryText = story.categories.map((c) => c.name).join(' · ');

  return (
    <Link href={`/story/${story.id}`} asChild>
      {/* Link asChild KHÔNG nhận mảng style -> phải StyleSheet.flatten */}
      <Pressable
        accessibilityRole="link"
        accessibilityLabel={story.title}
        style={StyleSheet.flatten([styles.card, width != null ? { width } : styles.cardFlex, style])}
      >
        {({ pressed }) => (
          <View style={[styles.cardInner, pressed && styles.pressed]}>
            <StoryCover
              thumbnailUrl={story.thumbnail_url}
              title={story.title}
              chaptersCount={story.chapters_count}
              status={story.status}
              radius={Radius.md}
              seed={story.id}
            />
            <View style={styles.meta}>
              <Text style={styles.title} numberOfLines={2}>
                {story.title}
              </Text>
              {showCategory && categoryText ? (
                <Text style={styles.category} numberOfLines={1}>
                  {categoryText}
                </Text>
              ) : null}
            </View>
          </View>
        )}
      </Pressable>
    </Link>
  );
}

export interface StoryCardSkeletonProps {
  /** bề rộng cố định; bỏ trống -> flex: 1 */
  width?: number;
  style?: StyleProp<ViewStyle>;
}

/** Khung xương của StoryCardView — dùng khi danh sách đang tải. */
export function StoryCardSkeleton({ width, style }: StoryCardSkeletonProps) {
  return (
    <View style={[styles.card, width != null ? { width } : styles.cardFlex, style]}>
      <View style={styles.cardInner}>
        <Skeleton aspectRatio={Layout.coverAspect} radius={Radius.md} />
        <View style={styles.meta}>
          <Skeleton height={12} radius={Radius.xs} />
          <Skeleton height={10} width="65%" radius={Radius.xs} />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  // --- Bìa ---
  coverWrap: {
    width: '100%',
    overflow: 'hidden',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  fill: {
    ...StyleSheet.absoluteFill,
  },
  fallbackInner: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fallbackLetter: {
    color: withAlpha(Palette.white, 0.92),
    fontSize: 40,
    fontWeight: FontWeight.black,
  },
  scrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '45%',
  },

  // --- Ribbon trạng thái (đè lên ảnh -> màu đặc + chữ trắng) ---
  ribbon: {
    position: 'absolute',
    top: Spacing.xs + 2,
    left: Spacing.xs + 2,
    maxWidth: '75%',
    paddingHorizontal: Spacing.sm - 1,
    paddingVertical: 2,
    borderRadius: Radius.xs,
  },
  ribbonFull: {
    backgroundColor: Palette.freeDeep,
  },
  ribbonOngoing: {
    backgroundColor: Palette.accentDeep,
  },
  ribbonText: {
    color: Palette.onAccent,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.black,
    letterSpacing: 0.4,
  },

  // --- Badge số chương (đè lên ảnh -> nền tối mờ + chữ trắng) ---
  chapBadge: {
    position: 'absolute',
    bottom: Spacing.xs + 2,
    right: Spacing.xs + 2,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: Palette.overlay,
    paddingHorizontal: Spacing.xs + 2,
    paddingVertical: 2,
    borderRadius: Radius.badge,
  },
  chapBadgeText: {
    color: Palette.white,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },

  // --- Card trắng nổi trên nền kem ---
  card: {
    backgroundColor: Palette.surface,
    borderRadius: Radius.card,
    padding: Spacing.sm - 2,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  cardFlex: {
    flex: 1,
  },
  cardInner: {
    gap: Spacing.sm,
  },
  meta: {
    gap: 2,
    paddingHorizontal: Spacing.xs - 2,
    paddingBottom: Spacing.xs - 2,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small,
  },
  category: {
    color: Palette.muted,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
  },
});
