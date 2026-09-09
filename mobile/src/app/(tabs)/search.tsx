import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Chip } from '@/components/chip';
import { EmptyState } from '@/components/empty-state';
import { StoryCardSkeleton, StoryCardView } from '@/components/story-card';
import {
  FontSize,
  FontWeight,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  Typography,
} from '@/constants/theme';
import { getCategories, getStories, type Category, type StoryCard } from '@/lib/api';

/** Số thẻ giả hiển thị khi đang tải. */
const SKELETON_COUNT = 6;
/** Thời gian chờ trước khi gọi API sau mỗi lần gõ. */
const DEBOUNCE_MS = 350;

export default function SearchScreen() {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const [query, setQuery] = useState('');
  const [inputFocused, setInputFocused] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [results, setResults] = useState<StoryCard[]>([]);
  // bật sẵn để lần vào đầu tiên thấy skeleton thay vì nháy màn hình rỗng trong lúc debounce
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /** Số thứ tự lần gọi API gần nhất — bỏ qua kết quả về muộn của lần gõ cũ. */
  const requestId = useRef(0);

  /** Bề rộng 1 thẻ trong lưới 2 cột — tính sẵn để hàng lẻ không bị giãn. */
  const cardWidth = useMemo(
    () => Math.floor((width - Spacing.screen * 2 - Spacing.gutter) / 2),
    [width],
  );

  const activeCategoryName = useMemo(
    () => categories.find((c) => c.slug === activeCategory)?.name ?? null,
    [categories, activeCategory],
  );

  useEffect(() => {
    getCategories()
      .then(setCategories)
      .catch(() => {});
  }, []);

  const search = useCallback(async (searchText: string, category: string | null) => {
    // tăng bộ đếm NGOÀI thân render (trong callback) — hợp lệ với React Compiler
    const id = requestId.current + 1;
    requestId.current = id;

    setLoading(true);
    setError(null);
    try {
      const res = await getStories({
        search: searchText || undefined,
        category: category || undefined,
      });
      if (requestId.current !== id) return; // đã có lần tìm mới hơn
      setResults(res.data);
    } catch {
      if (requestId.current !== id) return;
      setError('Không kết nối được máy chủ. Kiểm tra đường truyền rồi thử lại nhé.');
      setResults([]);
    } finally {
      if (requestId.current === id) setLoading(false);
    }
  }, []);

  // Tìm kiếm khi query (debounce) hoặc category đổi
  useEffect(() => {
    const t = setTimeout(() => {
      search(query, activeCategory);
    }, DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [query, activeCategory, search]);

  const toggleCategory = useCallback((slug: string) => {
    setActiveCategory((prev) => (prev === slug ? null : slug));
  }, []);

  const clearFilters = useCallback(() => {
    setQuery('');
    setActiveCategory(null);
  }, []);

  const hasFilter = query.trim().length > 0 || activeCategory !== null;
  const showSkeleton = loading && results.length === 0;

  return (
    <View style={[styles.screen, { paddingTop: insets.top + Spacing.sm }]}>
      <View style={styles.header}>
        <Text style={styles.heading}>Tìm kiếm</Text>
        <Text style={styles.subheading}>Tìm theo tên truyện, tác giả hoặc thể loại</Text>
      </View>

      {/* Ô nhập từ khoá */}
      <View style={[styles.searchBar, inputFocused && styles.searchBarFocused]}>
        <Ionicons
          name="search"
          size={18}
          color={inputFocused ? Palette.accentDeep : Palette.faint}
        />
        <TextInput
          style={styles.input}
          placeholder="Tìm truyện, tác giả..."
          placeholderTextColor={Palette.faint}
          value={query}
          onChangeText={setQuery}
          onFocus={() => setInputFocused(true)}
          onBlur={() => setInputFocused(false)}
          returnKeyType="search"
          autoCorrect={false}
          selectionColor={Palette.accentDeep}
          accessibilityLabel="Ô tìm truyện"
        />
        {query.length > 0 ? (
          <Pressable
            onPress={() => setQuery('')}
            hitSlop={10}
            accessibilityRole="button"
            accessibilityLabel="Xoá từ khoá"
            style={({ pressed }) => (pressed ? styles.pressed : undefined)}>
            <Ionicons name="close-circle" size={19} color={Palette.faint} />
          </Pressable>
        ) : null}
      </View>

      {/* Bộ lọc thể loại — chip đang chọn tô cam đặc, chữ trắng */}
      <FlatList
        horizontal
        data={categories}
        keyExtractor={(item) => item.slug}
        showsHorizontalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        style={styles.chipsRow}
        contentContainerStyle={styles.chipsList}
        ListHeaderComponent={
          <Chip
            label="Tất cả"
            icon="apps-outline"
            selected={activeCategory === null}
            onPress={() => setActiveCategory(null)}
            style={[styles.chip, activeCategory !== null && styles.chipIdle]}
          />
        }
        renderItem={({ item }) => {
          const active = item.slug === activeCategory;
          return (
            <Chip
              label={item.name}
              selected={active}
              onPress={() => toggleCategory(item.slug)}
              style={[styles.chip, !active && styles.chipIdle]}
            />
          );
        }}
      />

      {showSkeleton ? (
        <View style={styles.skeletonGrid}>
          {Array.from({ length: SKELETON_COUNT }).map((_, i) => (
            <StoryCardSkeleton key={i} width={cardWidth} />
          ))}
        </View>
      ) : error ? (
        <View style={styles.stateWrap}>
          <EmptyState
            danger
            icon="cloud-offline-outline"
            title="Không tải được kết quả"
            description={error}
            actionLabel="Thử lại"
            onAction={() => search(query, activeCategory)}
          />
        </View>
      ) : results.length === 0 ? (
        <View style={styles.stateWrap}>
          <EmptyState
            icon="search-outline"
            title="Không tìm thấy truyện nào"
            description={
              hasFilter
                ? 'Thử từ khoá khác hoặc bỏ bớt bộ lọc thể loại nhé.'
                : 'Nhập tên truyện hoặc chọn một thể loại để bắt đầu.'
            }
            actionLabel={hasFilter ? 'Xoá bộ lọc' : undefined}
            onAction={hasFilter ? clearFilters : undefined}
          />
        </View>
      ) : (
        <FlatList
          data={results}
          keyExtractor={(item) => String(item.id)}
          numColumns={2}
          // đang tải lại (đổi từ khoá/thể loại) thì làm mờ kết quả cũ thay vì nháy skeleton
          style={loading ? styles.dimmed : undefined}
          columnWrapperStyle={styles.gridRow}
          contentContainerStyle={styles.grid}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          showsVerticalScrollIndicator={false}
          ListHeaderComponent={
            <Text style={styles.resultCount}>
              Tìm thấy {results.length} truyện
              {activeCategoryName ? ` thuộc ${activeCategoryName}` : ''}
            </Text>
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
    marginBottom: Spacing.md,
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

  // --- Ô tìm kiếm ---
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    marginHorizontal: Spacing.screen,
    paddingHorizontal: Spacing.lg,
    height: 50,
    backgroundColor: Palette.surfaceAlt,
    borderRadius: Radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  // đang gõ -> nền trắng nổi lên + viền cam mảnh
  searchBarFocused: {
    backgroundColor: Palette.surface,
    borderColor: Palette.accentBorder,
    ...Shadow.sm,
  },
  input: {
    flex: 1,
    height: '100%',
    paddingVertical: 0,
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.medium,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  // --- Chip thể loại ---
  chipsRow: {
    // ScrollView mặc định có flexShrink: 1 -> khi lưới kết quả bên dưới dài,
    // hàng chip bị bóp dẹp và cắt mất chữ. Khoá lại cả grow lẫn shrink.
    flexGrow: 0,
    flexShrink: 0,
    marginTop: Spacing.md,
  },
  chipsList: {
    paddingHorizontal: Spacing.screen,
    gap: Spacing.sm,
    paddingVertical: Spacing.xs,
  },
  chip: {
    ...Shadow.sm,
  },
  // chip chưa chọn: khối trắng nổi nhẹ trên nền kem (chip đã chọn giữ nền cam đặc)
  chipIdle: {
    backgroundColor: Palette.surface,
  },

  // --- Lưới kết quả ---
  grid: {
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.md,
    paddingBottom: Spacing.xxxl,
    rowGap: Spacing.xl,
  },
  gridRow: {
    gap: Spacing.gutter,
  },
  resultCount: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
  },
  dimmed: {
    opacity: 0.45,
  },
  skeletonGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: Spacing.screen,
    paddingTop: Spacing.lg,
    columnGap: Spacing.gutter,
    rowGap: Spacing.xl,
  },

  // --- Trạng thái rỗng / lỗi ---
  stateWrap: {
    flex: 1,
    justifyContent: 'center',
    paddingBottom: Spacing.huge,
  },
});
