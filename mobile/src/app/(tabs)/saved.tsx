import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  AppState,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AboutLinks } from '@/components/about-links';
import { Chip } from '@/components/chip';
import { EmptyState } from '@/components/empty-state';
import { ReadProgress } from '@/components/read-progress';
import { StoryCardSkeleton, StoryCardView, StoryCover } from '@/components/story-card';
import {
  FontSize,
  FontWeight,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  Typography,
} from '@/constants/theme';
import { getStory, type Story } from '@/lib/api';
import { plural, timeAgo } from '@/lib/format';
import { CONTENT_MAX_WIDTH, useResponsive } from '@/lib/responsive';
import { type HistoryEntry, isFinished, readPercent, useHistory } from '@/store/history';
import { useWallet } from '@/store/wallet';

/** Số thẻ giả hiển thị khi đang tải. */
const SKELETON_COUNT = 4;
/** Bề rộng bìa trong một dòng lịch sử. */
const HISTORY_COVER_WIDTH = 56;

type LibraryTab = 'saved' | 'history';

export default function SavedScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { wide, columns, cellWidth, gap } = useResponsive();
  const { savedIds, ready } = useWallet();
  const { entries, ready: historyReady, remove, clear } = useHistory();
  const [tab, setTab] = useState<LibraryTab>('saved');
  const [stories, setStories] = useState<Story[]>([]);
  const [loading, setLoading] = useState(true);
  // Giờ hiện tại cho nhãn "2h ago", cập nhật mỗi lần quay lại tab thay vì mỗi lần render.
  const [now, setNow] = useState(() => Date.now());


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
      setNow(Date.now());
      load();
    }, [load]),
  );

  // Quay lại app từ nền không bắn focus của React Navigation -> tự làm mới mốc giờ.
  useEffect(() => {
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') setNow(Date.now());
    });
    return () => sub.remove();
  }, []);

  const switchTab = useCallback((next: LibraryTab) => {
    setNow(Date.now());
    setTab(next);
  }, []);

  // Đọc dở thì mở thẳng chương đang đọc; đã đọc hết thì về trang truyện (mở lại chương
  // cuối chẳng để làm gì).
  const openEntry = useCallback(
    (entry: HistoryEntry) =>
      router.push(
        isFinished(entry) ? `/story/${entry.storyId}` : `/reader/${entry.storyId}/${entry.chapter}`,
      ),
    [router],
  );

  const confirmRemove = useCallback(
    (entry: HistoryEntry) =>
      Alert.alert('Remove from history?', entry.title, [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Remove', style: 'destructive', onPress: () => remove(entry.storyId) },
      ]),
    [remove],
  );

  const confirmClear = useCallback(
    () =>
      Alert.alert(
        'Clear reading history?',
        'This only removes the list on this device. Your coins, saved stories and unlocked chapters stay as they are.',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Clear', style: 'destructive', onPress: clear },
        ],
      ),
    [clear],
  );

  // Chỉ hiện skeleton ở lần tải đầu; tải lại khi quay lại tab thì giữ nguyên lưới cũ.
  const showSkeleton = loading && stories.length === 0;

  const subtitle =
    tab === 'saved'
      ? stories.length > 0
        ? `${plural(stories.length, 'story', 'stories')} in your library`
        : 'Your own shelf of stories, no account needed'
      : entries.length > 0
        ? `${plural(entries.length, 'story', 'stories')} you've read`
        : 'Pick up where you left off';

  return (
    <View style={[styles.screen, { paddingTop: insets.top + Spacing.sm }]}>
      <View style={styles.header}>
        <View style={styles.headingRow}>
          <Text style={styles.heading}>Library</Text>
          {tab === 'history' && entries.length > 0 ? (
            <Pressable
              onPress={confirmClear}
              hitSlop={10}
              accessibilityRole="button"
              accessibilityLabel="Clear reading history">
              {({ pressed }) => <Text style={[styles.clear, pressed && styles.pressed]}>Clear</Text>}
            </Pressable>
          ) : null}
        </View>
        <Text style={styles.subheading}>{subtitle}</Text>
        <View style={styles.tabs} accessibilityRole="tablist">
          <Chip
            label="Saved"
            icon="bookmark-outline"
            selected={tab === 'saved'}
            onPress={() => switchTab('saved')}
          />
          <Chip
            label="History"
            icon="time-outline"
            selected={tab === 'history'}
            onPress={() => switchTab('history')}
          />
        </View>
      </View>

      {tab === 'history' ? (
        !historyReady ? null : entries.length === 0 ? (
          <ScrollView
            contentContainerStyle={styles.stateWrap}
            showsVerticalScrollIndicator={false}>
            <EmptyState
              icon="time-outline"
              title="No reading history yet"
              description="Chapters you read or listen to will show up here, so you can pick up where you left off."
              actionLabel="Discover stories"
              onAction={() => router.navigate('/')}
            />
            {/* Link quyền riêng tư / điều khoản / liên hệ — Guideline 5.1.1(i). */}
            <View style={styles.aboutWrap}>
              <AboutLinks />
            </View>
          </ScrollView>
        ) : wide ? (
          // Màn rộng: lưới thẻ cùng cỡ với tab Saved, thẳng hàng với tiêu đề, thay cho danh
          // sách hàng chữ ở cột giữa (bìa 56pt trông lọt thỏm trên iPad).
          <FlatList
            data={entries}
            keyExtractor={(item) => String(item.storyId)}
            key={`history-${columns}`}
            numColumns={columns}
            columnWrapperStyle={[styles.gridRow, { gap }]}
            contentContainerStyle={styles.grid}
            showsVerticalScrollIndicator={false}
            renderItem={({ item }) => (
              <HistoryCard
                entry={item}
                now={now}
                width={cellWidth}
                onPress={() => openEntry(item)}
                onLongPress={() => confirmRemove(item)}
              />
            )}
            ListFooterComponent={<AboutLinks />}
          />
        ) : (
          <FlatList
            data={entries}
            keyExtractor={(item) => String(item.storyId)}
            contentContainerStyle={styles.list}
            showsVerticalScrollIndicator={false}
            ItemSeparatorComponent={() => <View style={styles.separator} />}
            renderItem={({ item }) => (
              <HistoryRow
                entry={item}
                now={now}
                onPress={() => openEntry(item)}
                onLongPress={() => confirmRemove(item)}
              />
            )}
            ListFooterComponent={<AboutLinks />}
          />
        )
      ) : showSkeleton ? (
        <View style={[styles.skeletonGrid, { columnGap: gap }]}>
          {Array.from({ length: Math.max(SKELETON_COUNT, columns * 2) }).map((_, i) => (
            <StoryCardSkeleton key={i} width={cellWidth} />
          ))}
        </View>
      ) : stories.length === 0 ? (
        <ScrollView contentContainerStyle={styles.stateWrap} showsVerticalScrollIndicator={false}>
          <EmptyState
            icon="bookmark-outline"
            title="Your library is empty"
            description={'Open a story and tap "Save" to keep it here and read it anytime.'}
            actionLabel="Discover stories"
            onAction={() => router.navigate('/')}
          />
          {/* Link quyền riêng tư / điều khoản / liên hệ — Guideline 5.1.1(i). */}
          <View style={styles.aboutWrap}>
            <AboutLinks />
          </View>
        </ScrollView>
      ) : (
        <FlatList
          data={stories}
          keyExtractor={(item) => String(item.id)}
          // FlatList không đổi numColumns tại chỗ được: xoay máy / kéo cửa sổ thì dựng lại.
          key={`grid-${columns}`}
          numColumns={columns}
          columnWrapperStyle={[styles.gridRow, { gap }]}
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
          renderItem={({ item }) => <StoryCardView story={item} width={cellWidth} />}
          ListFooterComponent={<AboutLinks />}
        />
      )}
    </View>
  );
}

/** "Chapter 2 of 5" / "Finished" — dùng chung cho dòng lịch sử (iPhone) và thẻ (màn rộng). */
function progressLabel(entry: HistoryEntry, finished: boolean): string {
  if (finished) return 'Finished';
  return entry.chaptersCount != null
    ? `Chapter ${entry.chapter} of ${entry.chaptersCount}`
    : `Chapter ${entry.chapter}`;
}

/**
 * Thẻ lịch sử trên màn rộng: bìa như lưới Saved, thanh tiến độ (chương xa nhất đã mở / tổng
 * số chương) ngay dưới bìa, rồi tên truyện và lúc đọc. Bấm để đọc tiếp, nhấn giữ để xoá.
 */
function HistoryCard({
  entry,
  now,
  width,
  onPress,
  onLongPress,
}: {
  entry: HistoryEntry;
  now: number;
  width: number;
  onPress: () => void;
  onLongPress: () => void;
}) {
  const finished = isFinished(entry);
  const progress = progressLabel(entry, finished);
  const total = entry.chaptersCount;
  const percent = finished ? 100 : readPercent(entry.maxChapter, total);
  return (
    <Pressable
      onPress={onPress}
      onLongPress={onLongPress}
      accessibilityRole="button"
      accessibilityLabel={`${entry.title}, ${progress}, read ${timeAgo(entry.readAt, now)}`}
      accessibilityHint={`${finished ? 'Opens the story' : 'Continues reading'}. Long press to remove from history.`}
      style={({ pressed }) => [styles.card, { width }, pressed && styles.pressed]}>
      <StoryCover
        thumbnailUrl={entry.thumbnailUrl}
        title={entry.title}
        seed={entry.storyId}
        radius={Radius.md}
        showRibbon={false}
        showChapters={false}
      />
      {percent != null ? <ReadProgress percent={percent} style={styles.cardProgress} /> : null}
      <View style={styles.cardMeta}>
        <Text style={styles.cardTitle} numberOfLines={2}>
          {entry.title}
        </Text>
        {/* Thời gian xuống dòng riêng: chung một dòng thì ô hẹp cắt mất ("Chapter 1 of 10 · just n…") */}
        <Text style={[styles.cardSub, finished && styles.rowFinished]} numberOfLines={1}>
          {progress}
        </Text>
        <Text style={[styles.cardSub, styles.cardSubTime]} numberOfLines={1}>
          {timeAgo(entry.readAt, now)}
        </Text>
      </View>
    </Pressable>
  );
}

/** Một dòng lịch sử: bìa nhỏ, tên truyện, chương đang dở, thời điểm đọc. Bấm để đọc tiếp. */
function HistoryRow({
  entry,
  now,
  onPress,
  onLongPress,
}: {
  entry: HistoryEntry;
  now: number;
  onPress: () => void;
  onLongPress: () => void;
}) {
  const finished = isFinished(entry);
  const progress = progressLabel(entry, finished);
  const percent = finished ? 100 : readPercent(entry.maxChapter, entry.chaptersCount);
  return (
    <Pressable
      onPress={onPress}
      onLongPress={onLongPress}
      accessibilityRole="button"
      accessibilityLabel={`${entry.title}, ${progress}, read ${timeAgo(entry.readAt, now)}`}
      accessibilityHint={`${finished ? 'Opens the story' : 'Continues reading'}. Long press to remove from history.`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
      <StoryCover
        thumbnailUrl={entry.thumbnailUrl}
        title={entry.title}
        seed={entry.storyId}
        radius={Radius.sm}
        showRibbon={false}
        showChapters={false}
        style={styles.rowCover}
      />
      <View style={styles.rowBody}>
        <Text style={styles.rowTitle} numberOfLines={2}>
          {entry.title}
        </Text>
        <Text style={styles.rowMeta} numberOfLines={1}>
          {finished ? (
            <Text style={styles.rowFinished}>
              <Ionicons name="checkmark-circle" size={12} color={Palette.freeDeep} /> {progress}
            </Text>
          ) : (
            progress
          )}{' '}
          · {timeAgo(entry.readAt, now)}
        </Text>
        {entry.chapterTitle && !finished ? (
          <Text style={styles.rowChapter} numberOfLines={1}>
            {entry.chapterTitle}
          </Text>
        ) : null}
        {percent != null ? <ReadProgress percent={percent} style={styles.rowProgress} /> : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color={Palette.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  aboutWrap: {
    alignSelf: 'stretch',
    paddingHorizontal: Spacing.screen,
  },
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  header: {
    paddingHorizontal: Spacing.screen,
    marginBottom: Spacing.sm,
  },
  headingRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
  },
  heading: {
    ...Typography.display,
  },
  clear: {
    color: Palette.accentDeep,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
  },
  subheading: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    marginTop: Spacing.xxs,
  },
  tabs: {
    flexDirection: 'row',
    gap: Spacing.sm,
    marginTop: Spacing.md,
  },
  pressed: {
    opacity: 0.6,
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

  // --- Lịch sử ---
  list: {
    // Màn rộng: lịch sử đọc là danh sách hàng chữ — giữ ở cột giữa như trang chi tiết.
    width: '100%',
    maxWidth: CONTENT_MAX_WIDTH,
    alignSelf: 'center',
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.sm,
    paddingBottom: Spacing.xxxl,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    paddingVertical: Spacing.md,
  },
  rowCover: {
    width: HISTORY_COVER_WIDTH,
  },
  rowBody: {
    flex: 1,
    minWidth: 0,
  },
  rowTitle: {
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.body,
  },
  rowMeta: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    marginTop: Spacing.xxs,
  },
  rowFinished: {
    color: Palette.freeDeep,
    fontWeight: FontWeight.semibold,
  },
  rowProgress: {
    marginTop: Spacing.xs,
    maxWidth: 240,
  },
  rowChapter: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontStyle: 'italic',
    marginTop: Spacing.xxs,
  },
  separator: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: Palette.border,
  },

  // --- Thẻ lịch sử (màn rộng) — cùng khung với StoryCardView ---
  card: {
    gap: Spacing.sm,
    padding: Spacing.sm - 2,
    borderRadius: Radius.card,
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  cardProgress: {
    marginHorizontal: Spacing.xs - 2,
  },
  cardMeta: {
    gap: 2,
    paddingHorizontal: Spacing.xs - 2,
    paddingBottom: Spacing.xs - 2,
  },
  cardTitle: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.small,
  },
  cardSub: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
  },
  cardSubTime: {
    color: Palette.muted,
    fontWeight: FontWeight.medium,
  },

  // --- Trạng thái rỗng ---
  // Nằm trong ScrollView: chữ cỡ lớn hoặc máy màn nhỏ thì cuộn được, không tràn
  // đè lên hàng chip Saved | History hay chui xuống dưới thanh tab.
  stateWrap: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingBottom: Spacing.huge,
  },
});
