import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import {
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/empty-state';
import { StoryCardSkeleton, StoryCardView } from '@/components/story-card';
import {
  FontSize,
  FontWeight,
  LineHeight,
  Palette,
  Spacing,
  Typography,
} from '@/constants/theme';
import { getStory, type Story } from '@/lib/api';
import { plural } from '@/lib/format';
import { useWallet } from '@/store/wallet';

/** Số thẻ giả hiển thị khi đang tải. */
const SKELETON_COUNT = 4;

export default function SavedScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { width } = useWindowDimensions();
  const { savedIds, ready } = useWallet();
  const [stories, setStories] = useState<Story[]>([]);
  const [loading, setLoading] = useState(true);

  /** Bề rộng 1 thẻ trong lưới 2 cột — tính sẵn để hàng lẻ không bị giãn. */
  const cardWidth = useMemo(
    () => Math.floor((width - Spacing.screen * 2 - Spacing.gutter) / 2),
    [width],
  );

  const load = useCallback(async () => {
    // Chờ AsyncStorage đọc xong danh sách đã lưu, tránh nháy "Chưa lưu truyện nào".
    if (!ready) {
      setLoading(true);
      return;
    }
    if (savedIds.length === 0) {
      setStories([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const results = await Promise.all(
        savedIds.map((id) => getStory(id).catch(() => null)),
      );
      setStories(results.filter((s): s is Story => s !== null));
    } finally {
      setLoading(false);
    }
  }, [savedIds, ready]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  // Chỉ hiện skeleton ở lần tải đầu; tải lại khi quay lại tab thì giữ nguyên lưới cũ.
  const showSkeleton = loading && stories.length === 0;
  const showSubtitle = stories.length > 0;

  return (
    <View style={[styles.screen, { paddingTop: insets.top + Spacing.sm }]}>
      <View style={styles.header}>
        <Text style={styles.heading}>Library</Text>
        <Text style={styles.subheading}>
          {showSubtitle
            ? `${plural(stories.length, 'story', 'stories')} in your library`
            : 'Your own shelf of stories, no account needed'}
        </Text>
      </View>

      {showSkeleton ? (
        <View style={styles.skeletonGrid}>
          {Array.from({ length: SKELETON_COUNT }).map((_, i) => (
            <StoryCardSkeleton key={i} width={cardWidth} />
          ))}
        </View>
      ) : stories.length === 0 ? (
        <View style={styles.stateWrap}>
          <EmptyState
            icon="bookmark-outline"
            title="Your library is empty"
            description={'Open a story and tap "Save" to keep it here and read it anytime.'}
            actionLabel="Discover stories"
            onAction={() => router.navigate('/')}
          />
        </View>
      ) : (
        <FlatList
          data={stories}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          columnWrapperStyle={styles.gridRow}
          contentContainerStyle={styles.grid}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={loading}
              onRefresh={load}
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

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  header: {
    paddingHorizontal: Spacing.screen,
    marginBottom: Spacing.sm,
  },
  heading: {
    ...Typography.display,
  },
  subheading: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    marginTop: Spacing.xxs,
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
  skeletonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.md,
    columnGap: Spacing.gutter,
    rowGap: Spacing.xl,
  },

  // --- Trạng thái rỗng ---
  stateWrap: {
    flex: 1,
    justifyContent: 'center',
    paddingBottom: Spacing.huge,
  },
});
