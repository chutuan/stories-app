import { useEffect, useMemo, useState } from 'react';
import { Animated, Easing, Platform, StyleSheet, View } from 'react-native';
import type { DimensionValue, StyleProp, ViewStyle } from 'react-native';

import { Palette, Radius, Spacing } from '@/constants/theme';

/**
 * Hiệu ứng nhấp nháy nhẹ dùng chung cho mọi skeleton.
 * Nền sáng: biên độ mờ hẹp hơn (0.55 -> 1) để khối xám ấm không nháy gắt.
 */
function usePulse(): Animated.AnimatedInterpolation<number> {
  const [progress] = useState(() => new Animated.Value(0));

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(progress, {
          toValue: 1,
          duration: 750,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: Platform.OS !== 'web',
        }),
        Animated.timing(progress, {
          toValue: 0,
          duration: 750,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: Platform.OS !== 'web',
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [progress]);

  return useMemo(
    () => progress.interpolate({ inputRange: [0, 1], outputRange: [0.55, 1] }),
    [progress],
  );
}

export interface SkeletonProps {
  /** mặc định '100%' */
  width?: DimensionValue;
  /** mặc định 12 */
  height?: DimensionValue;
  /** bo góc, mặc định Radius.sm (8) */
  radius?: number;
  /** dùng thay cho height khi cần khối theo tỉ lệ (vd bìa 3/4) */
  aspectRatio?: number;
  style?: StyleProp<ViewStyle>;
}

/** Khối xám ấm bo góc, nhấp nháy nhẹ — dùng thay ActivityIndicator khi đang tải. */
export function Skeleton({
  width = '100%',
  height = 12,
  radius = Radius.sm,
  aspectRatio,
  style,
}: SkeletonProps) {
  const opacity = usePulse();
  return (
    <Animated.View
      accessibilityRole="progressbar"
      accessibilityLabel="Loading"
      style={[
        styles.block,
        { width, borderRadius: radius, opacity },
        aspectRatio != null ? { aspectRatio } : { height },
        style,
      ]}
    />
  );
}

export interface SkeletonTextProps {
  /** số dòng, mặc định 2 */
  lines?: number;
  /** chiều cao mỗi dòng, mặc định 12 */
  lineHeight?: number;
  /** khoảng cách giữa các dòng, mặc định Spacing.sm (8) */
  gap?: number;
  /** bề rộng dòng cuối, mặc định '60%' */
  lastLineWidth?: DimensionValue;
  style?: StyleProp<ViewStyle>;
}

/** Nhiều dòng skeleton giả chữ; dòng cuối ngắn hơn cho tự nhiên. */
export function SkeletonText({
  lines = 2,
  lineHeight = 12,
  gap = Spacing.sm,
  lastLineWidth = '60%',
  style,
}: SkeletonTextProps) {
  return (
    <View style={[{ gap }, style]}>
      {Array.from({ length: Math.max(1, lines) }).map((_, i) => (
        <Skeleton
          key={i}
          height={lineHeight}
          radius={Radius.xs}
          width={i === lines - 1 && lines > 1 ? lastLineWidth : '100%'}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  block: {
    // xám ấm nhạt — hợp nền kem, không dùng xám xanh (giá trị ở theme.ts)
    backgroundColor: Palette.skeleton,
  },
});
