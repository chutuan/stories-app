import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import type { LayoutChangeEvent } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Chip } from '@/components/chip';
import { EmptyState } from '@/components/empty-state';
import { SectionHeader } from '@/components/section-header';
import { Skeleton } from '@/components/skeleton';
import { StoryCardSkeleton, StoryCardView, StoryCover } from '@/components/story-card';
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
import {
  getCategories,
  getHome,
  getStories,
  type Category,
  type HomeData,
  type Story,
  type StoryCard,
  type StorySort,
} from '@/lib/api';
import { formatViews, plural, storyStatusLabel } from '@/lib/format';

type IoniconName = keyof typeof Ionicons.glyphMap;

/* ------------------------------------------------------------------ */
/* HẰNG SỐ                                                             */
/* ------------------------------------------------------------------ */

/** Bề rộng 1 card trong hàng ngang + khoảng cách -> bước snap. */
const CARD_WIDTH = Layout.cardWidthLarge;
const CARD_GAP = Spacing.gutter;
const SNAP_INTERVAL = CARD_WIDTH + CARD_GAP;

/** Số thẻ giả khi lưới đang tải. */
const GRID_SKELETON_COUNT = 6;
/** Số hàng giả khi bảng xếp hạng đang tải. */
const RANK_SKELETON_COUNT = 5;

const NETWORK_ERROR = 'Could not load data. Check your connection and try again.';

/** 4 tab ngang dưới header — đổi tab là đổi nguồn dữ liệu. */
type TabKey = 'discover' | 'newest' | 'ranking' | 'free';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'discover', label: 'Discover' },
  { key: 'newest', label: 'New' },
  { key: 'ranking', label: 'Ranking' },
  { key: 'free', label: 'Free' },
];

/* ------------------------------------------------------------------ */
/* TIỆN ÍCH                                                            */
/* ------------------------------------------------------------------ */

/** Bề rộng 1 ô trong lưới 2 cột, đã trừ lề màn hình và khe giữa. */
function twoColumnWidth(screenWidth: number): number {
  return Math.floor((screenWidth - Spacing.screen * 2 - Spacing.gutter) / 2);
}

/* ------------------------------------------------------------------ */
/* MÀN HÌNH                                                            */
/* ------------------------------------------------------------------ */

export default function HomeScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const [tab, setTab] = useState<TabKey>('discover');
  /** Tăng lên mỗi lần bấm icon "Thể loại" -> tab Khám phá cuộn tới mục Thể loại. */
  const [categoryFocus, setCategoryFocus] = useState(0);

  const openSearch = useCallback(() => {
    router.push('/search');
  }, [router]);

  const focusCategories = useCallback(() => {
    setTab('discover');
    setCategoryFocus((n) => n + 1);
  }, []);

  const showNewest = useCallback(() => {
    setTab('newest');
  }, []);

  return (
    <View style={[styles.screen, { paddingTop: insets.top + Spacing.xs }]}>
      {/* ---------- Header: logo + tìm kiếm + thể loại ---------- */}
      <View style={styles.header}>
        <Text style={styles.logo} accessibilityRole="header">
          <Text style={styles.logoAccent}>Sto</Text>ries
        </Text>
        <View style={styles.headerActions}>
          <IconButton icon="search-outline" label="Search stories" onPress={openSearch} />
          <IconButton icon="grid-outline" label="Browse genres" onPress={focusCategories} />
        </View>
      </View>

      {/* ---------- Hàng tab ---------- */}
      <TabRow value={tab} onChange={setTab} />

      {/* ---------- Nội dung theo tab ---------- */}
      <View style={styles.body}>
        {tab === 'discover' ? (
          <DiscoverTab focusToken={categoryFocus} onSeeNewest={showNewest} />
        ) : tab === 'ranking' ? (
          <RankFeed key="ranking" />
        ) : (
          <GridFeed
            key={tab}
            sort={tab === 'newest' ? 'newest' : undefined}
            free={tab === 'free'}
            emptyIcon={tab === 'free' ? 'gift-outline' : 'sparkles-outline'}
            emptyTitle={tab === 'free' ? 'No free stories yet' : 'No new stories yet'}
            emptyDescription={
              tab === 'free'
                ? 'No fully free stories right now. Check back soon.'
                : 'The shelf is empty. Pull down to refresh.'
            }
            countLabel={(n) =>
              tab === 'free'
                ? plural(n, 'free story', 'free stories')
                : plural(n, 'new story', 'new stories')
            }
          />
        )}
      </View>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* HEADER                                                              */
/* ------------------------------------------------------------------ */

function IconButton({
  icon,
  label,
  onPress,
}: {
  icon: IoniconName;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.iconBtn, pressed && styles.pressed]}>
      <Ionicons name={icon} size={19} color={Palette.text} />
    </Pressable>
  );
}

function TabRow({ value, onChange }: { value: TabKey; onChange: (key: TabKey) => void }) {
  return (
    <View style={styles.tabRowWrap}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.tabScroll}
        contentContainerStyle={styles.tabRow}>
        {TABS.map((item) => {
          const active = item.key === value;
          return (
            <Pressable
              key={item.key}
              onPress={() => onChange(item.key)}
              accessibilityRole="tab"
              accessibilityState={{ selected: active }}
              accessibilityLabel={item.label}
              style={({ pressed }) => [styles.tabItem, pressed && styles.pressed]}>
              <Text style={[styles.tabLabel, active && styles.tabLabelActive]} numberOfLines={1}>
                {item.label}
              </Text>
              <View style={[styles.tabUnderline, active && styles.tabUnderlineActive]} />
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* TAB "KHÁM PHÁ" — /api/home + /api/categories                        */
/* ------------------------------------------------------------------ */

function DiscoverTab({ focusToken, onSeeNewest }: { focusToken: number; onSeeNewest: () => void }) {
  const router = useRouter();
  const { width, height } = useWindowDimensions();
  const [home, setHome] = useState<HomeData | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  /** Tăng lên mỗi lần cần gọi lại API. */
  const [reloadCount, setReloadCount] = useState(0);

  const scrollRef = useRef<ScrollView | null>(null);
  /** Toạ độ y của mục "Thể loại" trong nội dung cuộn. */
  const categoryY = useRef(0);
  /** true khi đã yêu cầu cuộn nhưng mục Thể loại chưa được đo. */
  const scrollPending = useRef(false);

  const heroHeight = Math.round(Math.min(width * 1.02, height * 0.44));
  const tileWidth = useMemo(() => twoColumnWidth(width), [width]);

  useEffect(() => {
    let alive = true;
    // Thể loại lỗi thì vẫn hiện được trang chủ -> nuốt lỗi riêng của nó.
    Promise.all([getHome(), getCategories().catch(() => [] as Category[])])
      .then(([homeData, cats]) => {
        if (!alive) return;
        setHome(homeData);
        setCategories(cats);
        setError(null);
      })
      .catch(() => {
        if (alive) setError(NETWORK_ERROR);
      })
      .finally(() => {
        if (!alive) return;
        setLoading(false);
        setRefreshing(false);
      });
    return () => {
      alive = false;
    };
  }, [reloadCount]);

  const reload = useCallback(() => {
    setLoading(true);
    setReloadCount((n) => n + 1);
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    setReloadCount((n) => n + 1);
  }, []);

  /** Cuộn tới mục Thể loại; trả về false nếu chưa đo được vị trí. */
  const scrollToCategories = useCallback(() => {
    const y = categoryY.current;
    if (y <= 0) return false;
    scrollRef.current?.scrollTo({ y: Math.max(0, y - Spacing.md), animated: true });
    return true;
  }, []);

  useEffect(() => {
    if (focusToken === 0) return;
    // chưa đo được vị trí (dữ liệu chưa về) -> hẹn cuộn lại ngay khi đo xong
    if (!scrollToCategories()) scrollPending.current = true;
  }, [focusToken, scrollToCategories]);

  /** Ghi lại vị trí mục Thể loại (ghi ref trong callback, không trong render). */
  const onCategoryLayout = useCallback(
    (e: LayoutChangeEvent) => {
      categoryY.current = e.nativeEvent.layout.y;
      if (scrollPending.current) {
        scrollPending.current = false;
        scrollToCategories();
      }
    },
    [scrollToCategories],
  );

  const openSearch = useCallback(() => {
    router.push('/search');
  }, [router]);

  const updated = home?.updated ?? [];
  const newest = home?.newest ?? [];
  const isEmpty =
    !loading && !error && !home?.featured && updated.length === 0 && newest.length === 0;

  return (
    <ScrollView
      ref={scrollRef}
      style={styles.flex}
      contentContainerStyle={styles.discoverContent}
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={onRefresh}
          tintColor={Palette.accentDeep}
          colors={[Palette.accentDeep]}
          progressBackgroundColor={Palette.surface}
        />
      }>
      {loading ? (
        <DiscoverSkeleton heroHeight={heroHeight} tileWidth={tileWidth} />
      ) : error ? (
        <View style={[styles.stateWrap, { minHeight: height * 0.45 }]}>
          <EmptyState
            danger
            icon="cloud-offline-outline"
            title="Connection lost"
            description={error}
            actionLabel="Retry"
            onAction={reload}
          />
        </View>
      ) : isEmpty ? (
        <View style={[styles.stateWrap, { minHeight: height * 0.45 }]}>
          <EmptyState
            icon="library-outline"
            title="No stories yet"
            description="The shelf is empty. Pull down or tap the button below to reload."
            actionLabel="Reload"
            onAction={reload}
          />
        </View>
      ) : (
        <>
          {home?.featured ? <Hero story={home.featured} height={heroHeight} /> : null}

          <Row
            title="Recently updated"
            subtitle="Stories with new chapters"
            icon="flame"
            stories={updated}
            onSeeMore={openSearch}
          />
          <Row
            title="Newest"
            subtitle="Just added to the shelf"
            icon="sparkles"
            stories={newest}
            onSeeMore={onSeeNewest}
          />

          {categories.length > 0 ? (
            <View style={styles.section} onLayout={onCategoryLayout}>
              <SectionHeader title="Genres" subtitle="Pick a genre you love" icon="grid" />
              <View style={styles.catGrid}>
                {categories.map((category) => (
                  <CategoryTile key={category.id} category={category} width={tileWidth} />
                ))}
              </View>
            </View>
          ) : null}
        </>
      )}
    </ScrollView>
  );
}

/* ------------------------------------------------------------------ */
/* HERO — truyện nổi bật                                               */
/* ------------------------------------------------------------------ */

function Hero({ story, height }: { story: Story; height: number }) {
  const router = useRouter();
  const [imgState, setImgState] = useState<'loading' | 'ready' | 'error'>(
    story.thumbnail_url ? 'loading' : 'error',
  );

  const completed = story.status === 'completed';
  const canRead = story.chapters_count > 0;
  const categories = story.categories.slice(0, 2);
  const meta = [
    story.author,
    canRead ? plural(story.chapters_count, 'chapter', 'chapters') : null,
  ]
    .filter(Boolean)
    .join('  ·  ');

  const openDetail = useCallback(() => {
    router.push(`/story/${story.id}`);
  }, [router, story.id]);

  const openFirstChapter = useCallback(() => {
    router.push(`/reader/${story.id}/1`);
  }, [router, story.id]);

  return (
    <View style={[styles.hero, { height }]}>
      {story.thumbnail_url && imgState !== 'error' ? (
        <Image
          source={{ uri: story.thumbnail_url }}
          style={styles.heroImg}
          contentFit="cover"
          contentPosition="top center"
          transition={280}
          cachePolicy="memory-disk"
          recyclingKey={story.thumbnail_url}
          accessible={false}
          onLoad={() => setImgState('ready')}
          onError={() => setImgState('error')}
          onLoadEnd={() => setImgState((s) => (s === 'loading' ? 'ready' : s))}
        />
      ) : null}

      {imgState === 'error' ? (
        <LinearGradient
          colors={coverGradient(story.id)}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.heroImg}>
          <View style={styles.heroFallbackInner}>
            <Ionicons name="book" size={56} color={withAlpha(Palette.white, 0.35)} />
          </View>
        </LinearGradient>
      ) : null}

      {imgState === 'loading' ? (
        <Skeleton style={StyleSheet.absoluteFill} width="100%" height="100%" radius={0} />
      ) : null}

      {/* Hai đầu ảnh hoà vào NỀN SÁNG của trang */}
      <LinearGradient
        colors={Gradients.heroTopFade}
        style={styles.heroTopFade}
        pointerEvents="none"
      />
      <LinearGradient colors={Gradients.heroScrim} style={styles.heroScrim} pointerEvents="none" />

      {/* Vùng chạm phủ toàn hero -> mở trang truyện */}
      <Pressable
        style={StyleSheet.absoluteFill}
        onPress={openDetail}
        accessibilityRole="button"
        accessibilityLabel={`Featured story: ${story.title}`}
      />

      <View style={styles.heroBadge} pointerEvents="box-none">
        <Chip label="Featured" icon="sparkles" variant="accent" size="sm" solid />
      </View>

      <View style={styles.heroContent} pointerEvents="box-none">
        <View style={styles.chipRow} pointerEvents="none">
          <Chip
            label={storyStatusLabel(story.status)}
            icon={completed ? 'checkmark-circle' : 'flash'}
            variant={completed ? 'free' : 'accent'}
            size="sm"
            solid
          />
          {categories.map((c) => (
            <Chip key={c.id} label={c.name} size="sm" style={styles.heroChip} />
          ))}
        </View>

        <Text style={styles.heroTitle} numberOfLines={2}>
          {story.title}
        </Text>

        {meta ? (
          <Text style={styles.heroMeta} numberOfLines={1}>
            {meta}
          </Text>
        ) : null}

        <View style={styles.heroActions}>
          {canRead ? (
            <>
              <Pressable
                onPress={openFirstChapter}
                accessibilityRole="button"
                accessibilityLabel="Read chapter 1 now"
                style={({ pressed }) => [styles.primaryBtn, pressed && styles.pressed]}>
                <LinearGradient
                  colors={Gradients.accent}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 1 }}
                  style={styles.primaryFill}>
                  <Ionicons name="play" size={15} color={Palette.onAccent} />
                  <Text style={styles.primaryText}>Read now</Text>
                </LinearGradient>
              </Pressable>

              <Pressable
                onPress={openDetail}
                accessibilityRole="button"
                accessibilityLabel="View story details"
                style={({ pressed }) => [styles.ghostBtn, pressed && styles.pressed]}>
                <Ionicons name="list-outline" size={15} color={Palette.text} />
                <Text style={styles.ghostText}>Details</Text>
              </Pressable>
            </>
          ) : (
            <Pressable
              onPress={openDetail}
              accessibilityRole="button"
              accessibilityLabel="View story details"
              style={({ pressed }) => [styles.primaryBtn, pressed && styles.pressed]}>
              <LinearGradient
                colors={Gradients.accent}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={styles.primaryFill}>
                <Ionicons name="information-circle-outline" size={15} color={Palette.onAccent} />
                <Text style={styles.primaryText}>View details</Text>
              </LinearGradient>
            </Pressable>
          )}
        </View>
      </View>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* HÀNG NGANG                                                          */
/* ------------------------------------------------------------------ */

function Row({
  title,
  subtitle,
  icon,
  stories,
  onSeeMore,
}: {
  title: string;
  subtitle?: string;
  icon?: IoniconName;
  stories: StoryCard[];
  onSeeMore?: () => void;
}) {
  if (stories.length === 0) return null;
  return (
    <View style={styles.section}>
      <SectionHeader title={title} subtitle={subtitle} icon={icon} onPress={onSeeMore} />
      <FlatList
        horizontal
        data={stories}
        keyExtractor={(item) => String(item.id)}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rowList}
        snapToInterval={SNAP_INTERVAL}
        snapToAlignment="start"
        decelerationRate="fast"
        disableIntervalMomentum
        initialNumToRender={4}
        renderItem={({ item }) => <StoryCardView story={item} width={CARD_WIDTH} />}
      />
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Ô THỂ LOẠI — ảnh nền + lớp tối mờ + chữ trắng                       */
/* ------------------------------------------------------------------ */

function CategoryTile({ category, width }: { category: Category; width: number }) {
  const router = useRouter();
  const [failed, setFailed] = useState(false);
  const showImage = Boolean(category.cover_url) && !failed;

  const open = useCallback(() => {
    router.push({ pathname: '/category/[slug]', params: { slug: category.slug } });
  }, [router, category.slug]);

  return (
    <Pressable
      onPress={open}
      accessibilityRole="link"
      accessibilityLabel={`${category.name} genre, ${plural(category.stories_count, 'story', 'stories')}`}
      style={({ pressed }) => [styles.catTile, { width }, pressed && styles.pressed]}>
      {showImage && category.cover_url ? (
        <Image
          source={{ uri: category.cover_url }}
          style={StyleSheet.absoluteFill}
          contentFit="cover"
          transition={220}
          cachePolicy="memory-disk"
          recyclingKey={category.cover_url}
          accessible={false}
          onError={() => setFailed(true)}
        />
      ) : (
        <LinearGradient
          colors={coverGradient(category.slug)}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      )}

      {/* Lớp tối mờ phủ toàn ô để chữ TRẮNG luôn đọc được trên ảnh bìa */}
      <View style={styles.catVeil} pointerEvents="none" />
      <LinearGradient colors={Gradients.scrimCover} style={styles.catScrim} pointerEvents="none" />

      <View style={styles.catText} pointerEvents="none">
        <Text style={styles.catName} numberOfLines={1}>
          {category.name}
        </Text>
        <Text style={styles.catCount} numberOfLines={1}>
          {plural(category.stories_count, 'story', 'stories')}
        </Text>
      </View>
    </Pressable>
  );
}

/* ------------------------------------------------------------------ */
/* NGUỒN DỮ LIỆU CÓ PHÂN TRANG                                         */
/* ------------------------------------------------------------------ */

interface FeedParams {
  sort?: StorySort;
  free?: boolean;
}

/**
 * Tải `/api/stories` theo trang: trang 1 khi tham số đổi, nối thêm khi cuộn.
 * Chỉ nhận tham số nguyên thuỷ để mảng phụ thuộc của effect luôn ổn định.
 */
function useStoryFeed({ sort, free }: FeedParams) {
  const [items, setItems] = useState<StoryCard[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadCount, setReloadCount] = useState(0);

  useEffect(() => {
    let alive = true;
    getStories({ sort, free, page: 1 })
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
  }, [sort, free, reloadCount]);

  const hasMore = page < lastPage;

  const loadMore = useCallback(() => {
    if (loading || loadingMore || refreshing || page >= lastPage) return;
    const next = page + 1;
    setLoadingMore(true);
    getStories({ sort, free, page: next })
      .then((res) => {
        // lọc trùng phòng khi backend xáo thứ tự giữa hai lần gọi
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
  }, [loading, loadingMore, refreshing, page, lastPage, sort, free]);

  const refresh = useCallback(() => {
    setRefreshing(true);
    setReloadCount((n) => n + 1);
  }, []);

  const retry = useCallback(() => {
    setLoading(true);
    setReloadCount((n) => n + 1);
  }, []);

  return { items, total, loading, loadingMore, refreshing, error, hasMore, loadMore, refresh, retry };
}

/** Chân danh sách: vòng xoay khi tải thêm, hoặc dòng "đã hết". */
function FeedFooter({
  loadingMore,
  hasMore,
  count,
}: {
  loadingMore: boolean;
  hasMore: boolean;
  count: number;
}) {
  if (loadingMore) {
    return (
      <View style={styles.footer}>
        <ActivityIndicator color={Palette.accentDeep} />
      </View>
    );
  }
  if (!hasMore && count > 0) {
    return (
      <View style={styles.footer}>
        <Text style={styles.footerText}>No more stories</Text>
      </View>
    );
  }
  return null;
}

/* ------------------------------------------------------------------ */
/* TAB LƯỚI — "Mới" và "Miễn phí"                                      */
/* ------------------------------------------------------------------ */

function GridFeed({
  sort,
  free,
  emptyIcon,
  emptyTitle,
  emptyDescription,
  countLabel,
}: {
  sort?: StorySort;
  free?: boolean;
  emptyIcon: IoniconName;
  emptyTitle: string;
  emptyDescription: string;
  countLabel: (total: number) => string;
}) {
  const { width } = useWindowDimensions();
  const cardWidth = useMemo(() => twoColumnWidth(width), [width]);
  const feed = useStoryFeed({ sort, free });

  if (feed.loading && feed.items.length === 0) {
    return (
      <View style={styles.skeletonGrid}>
        {Array.from({ length: GRID_SKELETON_COUNT }).map((_, i) => (
          <StoryCardSkeleton key={i} width={cardWidth} />
        ))}
      </View>
    );
  }

  if (feed.error && feed.items.length === 0) {
    return (
      <View style={styles.stateFlex}>
        <EmptyState
          danger
          icon="cloud-offline-outline"
          title="Connection lost"
          description={feed.error}
          actionLabel="Retry"
          onAction={feed.retry}
        />
      </View>
    );
  }

  if (feed.items.length === 0) {
    return (
      <View style={styles.stateFlex}>
        <EmptyState
          icon={emptyIcon}
          title={emptyTitle}
          description={emptyDescription}
          actionLabel="Reload"
          onAction={feed.retry}
        />
      </View>
    );
  }

  return (
    <FlatList
      data={feed.items}
      keyExtractor={(item) => String(item.id)}
      numColumns={2}
      columnWrapperStyle={styles.gridRow}
      contentContainerStyle={styles.grid}
      showsVerticalScrollIndicator={false}
      onEndReached={feed.loadMore}
      onEndReachedThreshold={0.4}
      ListHeaderComponent={<Text style={styles.countText}>{countLabel(feed.total)}</Text>}
      ListFooterComponent={
        <FeedFooter
          loadingMore={feed.loadingMore}
          hasMore={feed.hasMore}
          count={feed.items.length}
        />
      }
      refreshControl={
        <RefreshControl
          refreshing={feed.refreshing}
          onRefresh={feed.refresh}
          tintColor={Palette.accentDeep}
          colors={[Palette.accentDeep]}
          progressBackgroundColor={Palette.surface}
        />
      }
      renderItem={({ item }) => <StoryCardView story={item} width={cardWidth} />}
    />
  );
}

/* ------------------------------------------------------------------ */
/* TAB "XẾP HẠNG" — sort=views, hiện thứ hạng + lượt xem               */
/* ------------------------------------------------------------------ */

function RankFeed() {
  const feed = useStoryFeed({ sort: 'views' });

  if (feed.loading && feed.items.length === 0) {
    return (
      <View style={styles.rankSkeletonWrap}>
        {Array.from({ length: RANK_SKELETON_COUNT }).map((_, i) => (
          <RankRowSkeleton key={i} />
        ))}
      </View>
    );
  }

  if (feed.error && feed.items.length === 0) {
    return (
      <View style={styles.stateFlex}>
        <EmptyState
          danger
          icon="cloud-offline-outline"
          title="Connection lost"
          description={feed.error}
          actionLabel="Retry"
          onAction={feed.retry}
        />
      </View>
    );
  }

  if (feed.items.length === 0) {
    return (
      <View style={styles.stateFlex}>
        <EmptyState
          icon="trophy-outline"
          title="No ranking yet"
          description="Not enough view data to rank stories yet. Check back soon."
          actionLabel="Reload"
          onAction={feed.retry}
        />
      </View>
    );
  }

  return (
    <FlatList
      data={feed.items}
      keyExtractor={(item) => String(item.id)}
      contentContainerStyle={styles.rankList}
      showsVerticalScrollIndicator={false}
      onEndReached={feed.loadMore}
      onEndReachedThreshold={0.4}
      ListHeaderComponent={<Text style={styles.countText}>Ranked by most views</Text>}
      ListFooterComponent={
        <FeedFooter
          loadingMore={feed.loadingMore}
          hasMore={feed.hasMore}
          count={feed.items.length}
        />
      }
      refreshControl={
        <RefreshControl
          refreshing={feed.refreshing}
          onRefresh={feed.refresh}
          tintColor={Palette.accentDeep}
          colors={[Palette.accentDeep]}
          progressBackgroundColor={Palette.surface}
        />
      }
      renderItem={({ item, index }) => <RankRow story={item} rank={index + 1} />}
    />
  );
}

function RankRow({ story, rank }: { story: StoryCard; rank: number }) {
  const router = useRouter();
  const top = rank <= 3;

  const open = useCallback(() => {
    router.push(`/story/${story.id}`);
  }, [router, story.id]);

  return (
    <Pressable
      onPress={open}
      accessibilityRole="link"
      accessibilityLabel={`Rank ${rank}: ${story.title}`}
      style={({ pressed }) => [styles.rankRow, pressed && styles.pressed]}>
      <View style={styles.rankBadge}>
        <Text style={[styles.rankNum, top && styles.rankNumTop]}>{rank}</Text>
      </View>

      <StoryCover
        thumbnailUrl={story.thumbnail_url}
        title={story.title}
        status={story.status}
        showRibbon={false}
        showChapters={false}
        radius={Radius.md}
        seed={story.id}
        style={styles.rankCover}
      />

      <View style={styles.rankInfo}>
        <Text style={styles.rankTitle} numberOfLines={2}>
          {story.title}
        </Text>
        {story.author ? (
          <Text style={styles.rankAuthor} numberOfLines={1}>
            {story.author}
          </Text>
        ) : null}
        <View style={styles.rankMeta}>
          <Ionicons name="eye-outline" size={12} color={Palette.accentDeep} />
          <Text style={styles.rankViews}>
            {formatViews(story.views)} {story.views === 1 ? 'view' : 'views'}
          </Text>
          <Text style={styles.rankSep}>·</Text>
          <Text style={styles.rankChapters}>{plural(story.chapters_count, 'chapter', 'chapters')}</Text>
        </View>
      </View>

      <Ionicons name="chevron-forward" size={16} color={Palette.faint} />
    </Pressable>
  );
}

function RankRowSkeleton() {
  return (
    <View style={styles.rankRow}>
      <Skeleton width={22} height={18} radius={Radius.xs} />
      <Skeleton width={54} aspectRatio={Layout.coverAspect} radius={Radius.md} />
      <View style={styles.rankInfo}>
        <Skeleton height={13} radius={Radius.xs} />
        <Skeleton height={11} width="55%" radius={Radius.xs} />
        <Skeleton height={11} width="72%" radius={Radius.xs} />
      </View>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* SKELETON TAB KHÁM PHÁ                                               */
/* ------------------------------------------------------------------ */

function DiscoverSkeleton({ heroHeight, tileWidth }: { heroHeight: number; tileWidth: number }) {
  return (
    <View>
      <View style={[styles.hero, { height: heroHeight }]}>
        <Skeleton style={StyleSheet.absoluteFill} width="100%" height="100%" radius={0} />
        <LinearGradient colors={Gradients.heroScrim} style={styles.heroScrim} pointerEvents="none" />
        <View style={styles.heroContent} pointerEvents="none">
          <View style={styles.chipRow}>
            <Skeleton width={78} height={20} radius={Radius.pill} />
            <Skeleton width={62} height={20} radius={Radius.pill} />
          </View>
          <Skeleton width="82%" height={24} radius={Radius.xs} />
          <Skeleton width="52%" height={24} radius={Radius.xs} />
          <View style={styles.heroActions}>
            <Skeleton width={132} height={40} radius={Radius.pill} />
            <Skeleton width={104} height={40} radius={Radius.pill} />
          </View>
        </View>
      </View>

      <RowSkeleton />
      <RowSkeleton />

      <View style={styles.section}>
        <View style={styles.skelHeader}>
          <Skeleton width={96} height={18} radius={Radius.xs} />
        </View>
        <View style={styles.catGrid}>
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} width={tileWidth} height={88} radius={Radius.card} />
          ))}
        </View>
      </View>
    </View>
  );
}

function RowSkeleton() {
  return (
    <View style={styles.section}>
      <View style={styles.skelHeader}>
        <Skeleton width={128} height={18} radius={Radius.xs} />
      </View>
      <View style={styles.skelRow}>
        {[0, 1, 2].map((i) => (
          <StoryCardSkeleton key={i} width={CARD_WIDTH} />
        ))}
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
  flex: {
    flex: 1,
  },
  body: {
    flex: 1,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  // --- Header ---
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.md,
    paddingHorizontal: Spacing.screen,
    paddingBottom: Spacing.sm,
  },
  logo: {
    color: Palette.text,
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.display,
    letterSpacing: -0.6,
  },
  logoAccent: {
    color: Palette.accentDeep,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
  },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.sm,
  },

  // --- Hàng tab ---
  tabRowWrap: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  tabScroll: {
    flexGrow: 0,
  },
  tabRow: {
    paddingHorizontal: Spacing.screen,
    gap: Spacing.xl,
  },
  tabItem: {
    alignItems: 'center',
    paddingTop: Spacing.sm,
    gap: Spacing.sm - 2,
  },
  tabLabel: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.body,
  },
  tabLabelActive: {
    color: Palette.accentDeep,
    fontWeight: FontWeight.bold,
  },
  tabUnderline: {
    height: 3,
    width: '100%',
    borderRadius: Radius.pill,
    backgroundColor: 'transparent',
  },
  tabUnderlineActive: {
    backgroundColor: Palette.accent,
  },

  // --- Trạng thái rỗng / lỗi ---
  stateWrap: {
    justifyContent: 'center',
  },
  stateFlex: {
    flex: 1,
    justifyContent: 'center',
    paddingBottom: Spacing.huge,
  },

  // --- Tab Khám phá ---
  discoverContent: {
    paddingTop: Spacing.md,
    paddingBottom: Spacing.xxxl,
  },
  section: {
    marginTop: Spacing.xxl,
  },
  rowList: {
    paddingHorizontal: Spacing.screen,
    gap: CARD_GAP,
  },

  // --- Hero ---
  hero: {
    width: '100%',
    backgroundColor: Palette.surfaceAlt,
    overflow: 'hidden',
  },
  heroImg: {
    ...StyleSheet.absoluteFill,
  },
  heroFallbackInner: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroTopFade: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: '20%',
  },
  heroScrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '78%',
  },
  heroBadge: {
    position: 'absolute',
    top: Spacing.md,
    left: Spacing.screen,
  },
  heroContent: {
    position: 'absolute',
    left: Spacing.screen,
    right: Spacing.screen,
    bottom: Spacing.lg,
    gap: Spacing.sm,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: Spacing.xs + 2,
  },
  /** chip thể loại nằm trên vùng scrim đã ngả sáng -> nền trắng mờ, chữ tối */
  heroChip: {
    backgroundColor: withAlpha(Palette.white, 0.9),
    borderColor: Palette.borderStrong,
  },
  heroTitle: {
    color: Palette.text,
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.display,
    letterSpacing: -0.3,
    // quầng SÁNG (không phải bóng đen) để chữ tối tách khỏi ảnh bìa
    textShadowColor: withAlpha(Palette.white, 0.75),
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 8,
  },
  heroMeta: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
    marginTop: -Spacing.xs,
  },
  heroActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm + 2,
    marginTop: Spacing.xs,
  },
  primaryBtn: {
    borderRadius: Radius.pill,
    overflow: 'hidden',
    ...Shadow.float,
  },
  primaryFill: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm - 2,
    paddingHorizontal: Spacing.xl,
    paddingVertical: Spacing.md - 1,
  },
  primaryText: {
    color: Palette.onAccent,
    fontSize: FontSize.body,
    fontWeight: FontWeight.bold,
  },
  ghostBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm - 2,
    paddingHorizontal: Spacing.lg,
    paddingVertical: Spacing.md - 1,
    borderRadius: Radius.pill,
    backgroundColor: withAlpha(Palette.white, 0.9),
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.borderStrong,
    ...Shadow.sm,
  },
  ghostText: {
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
  },

  // --- Ô thể loại ---
  catGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: Spacing.screen,
    columnGap: Spacing.gutter,
    rowGap: Spacing.gutter,
  },
  catTile: {
    height: 88,
    borderRadius: Radius.card,
    overflow: 'hidden',
    justifyContent: 'flex-end',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  catVeil: {
    ...StyleSheet.absoluteFill,
    backgroundColor: Palette.overlay,
  },
  catScrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '65%',
  },
  catText: {
    paddingHorizontal: Spacing.md,
    paddingBottom: Spacing.sm + 2,
  },
  catName: {
    color: Palette.white,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h2,
  },
  catCount: {
    color: withAlpha(Palette.white, 0.82),
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
  },

  // --- Lưới truyện ---
  grid: {
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.md,
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

  // --- Bảng xếp hạng ---
  rankList: {
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.md,
    paddingBottom: Spacing.xxxl,
    gap: Spacing.md,
  },
  rankSkeletonWrap: {
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.lg,
    gap: Spacing.md,
  },
  rankRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    padding: Spacing.sm + 2,
    backgroundColor: Palette.surface,
    borderRadius: Radius.card,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.sm,
  },
  rankBadge: {
    width: 26,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rankNum: {
    color: Palette.faint,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h2,
  },
  rankNumTop: {
    color: Palette.accentDeep,
    fontSize: FontSize.h1,
    lineHeight: LineHeight.h1,
  },
  rankCover: {
    width: 54,
  },
  rankInfo: {
    flex: 1,
    gap: 3,
  },
  rankTitle: {
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small + 2,
  },
  rankAuthor: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
  },
  rankMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    marginTop: 1,
  },
  rankViews: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },
  rankSep: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },
  rankChapters: {
    color: Palette.muted,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
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

  // --- Skeleton ---
  skelHeader: {
    paddingHorizontal: Spacing.screen,
    marginBottom: Spacing.md,
  },
  skelRow: {
    flexDirection: 'row',
    gap: CARD_GAP,
    paddingHorizontal: Spacing.screen,
    overflow: 'hidden',
  },
});
