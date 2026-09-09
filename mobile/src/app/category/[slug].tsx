import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/empty-state';
import { Skeleton } from '@/components/skeleton';
import { StoryCardSkeleton, StoryCardView } from '@/components/story-card';
import {
  coverGradient,
  FontSize,
  FontWeight,
  Gradients,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Spacing,
  withAlpha,
} from '@/constants/theme';
import { getCategories, getStories, type Category, type StoryCard } from '@/lib/api';
import { plural } from '@/lib/format';

/** Số thẻ giả hiển thị khi lưới đang tải. */
const SKELETON_COUNT = 6;
/** Chiều cao ảnh bìa thể loại (chưa cộng safe-area trên). */
const BANNER_HEIGHT = 132;

const NETWORK_ERROR = 'Unable to load the story list. Check your connection and try again.';

export default function CategoryScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { width } = useWindowDimensions();

  /** Bề rộng 1 thẻ trong lưới 2 cột — tính sẵn để hàng lẻ không bị giãn. */
  const cardWidth = useMemo(
    () => Math.floor((width - Spacing.screen * 2 - Spacing.gutter) / 2),
    [width],
  );

  // --- Thông tin thể loại (tên + ảnh bìa) lấy từ /api/categories ---
  const [category, setCategory] = useState<Category | null>(null);

  // --- Danh sách truyện, phân trang 20/trang ---
  const [items, setItems] = useState<StoryCard[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  /** Tăng lên mỗi lần cần gọi lại API. */
  const [reloadCount, setReloadCount] = useState(0);

  useEffect(() => {
    if (!slug) return;
    let alive = true;
    getCategories()
      .then((list) => {
        if (!alive) return;
        setCategory(list.find((c) => c.slug === slug) ?? null);
      })
      .catch(() => {});
    return () => {
      alive = false;
    };
  }, [slug]);

  useEffect(() => {
    // thiếu slug -> xử lý ở phần render, KHÔNG setState đồng bộ trong effect
    if (!slug) return;
    let alive = true;
    getStories({ category: slug, page: 1 })
      .then((res) => {
        if (!alive) return;
        setItems(res.data);
        setPage(res.current_page);
        setLastPage(res.last_page);
        setTotal(res.total);
        setError(null);
      })
      .catch(() => {
        if (!alive) return;
        setError(NETWORK_ERROR);
        setItems([]);
        setTotal(0);
      })
      .finally(() => {
        if (!alive) return;
        setLoading(false);
        setRefreshing(false);
      });
    return () => {
      alive = false;
    };
  }, [slug, reloadCount]);

  const hasMore = page < lastPage;

  const loadMore = useCallback(() => {
    if (!slug || loading || loadingMore || refreshing || page >= lastPage) return;
    const next = page + 1;
    setLoadingMore(true);
    getStories({ category: slug, page: next })
      .then((res) => {
        // lọc trùng phòng khi thứ tự đổi giữa hai lần gọi
        setItems((prev) => {
          const seen = new Set(prev.map((s) => s.id));
          return [...prev, ...res.data.filter((s) => !seen.has(s.id))];
        });
        setPage(res.current_page);
        setLastPage(res.last_page);
        setTotal(res.total);
      })
      .catch(() => {})
      .finally(() => setLoadingMore(false));
  }, [slug, loading, loadingMore, refreshing, page, lastPage]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    setReloadCount((n) => n + 1);
  }, []);

  const retry = useCallback(() => {
    setLoading(true);
    setReloadCount((n) => n + 1);
  }, []);

  const goBack = useCallback(() => {
    if (router.canGoBack()) router.back();
    else router.replace('/');
  }, [router]);

  const title = category?.name ?? 'Genre';
  const count = category?.stories_count ?? total;

  return (
    <View style={styles.screen}>
      <Banner
        title={title}
        count={count}
        coverUrl={category?.cover_url ?? null}
        seed={slug ?? 'the-loai'}
        topInset={insets.top}
        onBack={goBack}
        loading={category === null}
      />

      {!slug ? (
        <View style={styles.stateWrap}>
          <EmptyState
            danger
            icon="alert-circle-outline"
            title="Genre not found"
            description="This link has no genre id, so the list could not be loaded."
            actionLabel="Go back"
            onAction={goBack}
          />
        </View>
      ) : loading && items.length === 0 ? (
        <View style={styles.skeletonGrid}>
          {Array.from({ length: SKELETON_COUNT }).map((_, i) => (
            <StoryCardSkeleton key={i} width={cardWidth} />
          ))}
        </View>
      ) : error && items.length === 0 ? (
        <View style={styles.stateWrap}>
          <EmptyState
            danger
            icon="cloud-offline-outline"
            title="Connection lost"
            description={error}
            actionLabel="Retry"
            onAction={retry}
          />
        </View>
      ) : items.length === 0 ? (
        <View style={styles.stateWrap}>
          <EmptyState
            icon="library-outline"
            title="No stories in this genre yet"
            description={`${title} has no stories yet. Check back later or pick another genre.`}
            actionLabel="Go back"
            onAction={goBack}
          />
        </View>
      ) : (
        /* số truyện đã hiện ở banner nên dòng đầu danh sách nói cách sắp xếp */
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.gridRow}
          contentContainerStyle={styles.grid}
          showsVerticalScrollIndicator={false}
          onEndReached={loadMore}
          onEndReachedThreshold={0.4}
          ListHeaderComponent={<Text style={styles.countText}>Sorted by recently updated</Text>}
          ListFooterComponent={
            loadingMore ? (
              <View style={styles.footer}>
                <ActivityIndicator color={Palette.accentDeep} />
              </View>
            ) : !hasMore ? (
              <View style={styles.footer}>
                <Text style={styles.footerText}>No more stories</Text>
              </View>
            ) : null
          }
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={Palette.accentDeep}
              colors={[Palette.accentDeep]}
              progressBackgroundColor={Palette.surface}
            />
          }
          renderItem={({ item }) => <StoryCardView story={item} width={cardWidth} />}
        />
      )}
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* BANNER — ảnh bìa thể loại + nút quay lại                            */
/* ------------------------------------------------------------------ */

function Banner({
  title,
  count,
  coverUrl,
  seed,
  topInset,
  onBack,
  loading,
}: {
  title: string;
  count: number;
  coverUrl: string | null;
  seed: string;
  topInset: number;
  onBack: () => void;
  loading: boolean;
}) {
  const [failed, setFailed] = useState(false);
  const showImage = Boolean(coverUrl) && !failed;

  return (
    <View style={[styles.banner, { height: topInset + BANNER_HEIGHT, paddingTop: topInset }]}>
      {showImage && coverUrl ? (
        <Image
          source={{ uri: coverUrl }}
          style={StyleSheet.absoluteFill}
          contentFit="cover"
          contentPosition="top center"
          transition={240}
          cachePolicy="memory-disk"
          recyclingKey={coverUrl}
          accessible={false}
          onError={() => setFailed(true)}
        />
      ) : (
        <LinearGradient
          colors={coverGradient(seed)}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      )}

      {/* Lớp tối mờ + scrim: chữ TRẮNG nằm trên ảnh bìa nên vẫn dùng nền tối */}
      <View style={styles.bannerVeil} pointerEvents="none" />
      <LinearGradient
        colors={Gradients.scrimCover}
        style={styles.bannerScrim}
        pointerEvents="none"
      />

      <View style={[styles.bannerBar, { top: topInset + Spacing.sm }]}>
        <Pressable
          onPress={onBack}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Back"
          style={({ pressed }) => [styles.backBtn, pressed && styles.pressed]}>
          <Ionicons name="chevron-back" size={20} color={Palette.white} />
        </Pressable>
      </View>

      <View style={styles.bannerText} pointerEvents="none">
        {loading ? (
          <Skeleton width={160} height={22} radius={Radius.xs} />
        ) : (
          <Text style={styles.bannerTitle} numberOfLines={1}>
            {title}
          </Text>
        )}
        <Text style={styles.bannerCount} numberOfLines={1}>
          {plural(count, 'story', 'stories')}
        </Text>
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
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  // --- Banner ---
  banner: {
    width: '100%',
    backgroundColor: Palette.surfaceAlt,
    overflow: 'hidden',
    justifyContent: 'flex-end',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  bannerVeil: {
    ...StyleSheet.absoluteFill,
    backgroundColor: Palette.overlay,
  },
  bannerScrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '70%',
  },
  bannerBar: {
    position: 'absolute',
    left: Spacing.screen,
    right: Spacing.screen,
    flexDirection: 'row',
    alignItems: 'center',
  },
  backBtn: {
    width: 38,
    height: 38,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.overlayStrong,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: withAlpha(Palette.white, 0.22),
  },
  bannerText: {
    paddingHorizontal: Spacing.screen,
    paddingBottom: Spacing.md,
    gap: 2,
  },
  bannerTitle: {
    color: Palette.white,
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.display,
    letterSpacing: -0.4,
  },
  bannerCount: {
    color: withAlpha(Palette.white, 0.85),
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
  },

  // --- Lưới truyện ---
  grid: {
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.lg,
    paddingBottom: Spacing.xxxl,
    rowGap: Spacing.xl,
  },
  gridRow: {
    gap: Spacing.gutter,
  },
  countText: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
  },
  skeletonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.lg,
    columnGap: Spacing.gutter,
    rowGap: Spacing.xl,
  },

  // --- Chân danh sách ---
  footer: {
    paddingVertical: Spacing.xl,
    alignItems: 'center',
  },
  footerText: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
  },

  // --- Trạng thái rỗng / lỗi ---
  stateWrap: {
    flex: 1,
    justifyContent: 'center',
    paddingBottom: Spacing.huge,
  },
});
