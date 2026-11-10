import { Ionicons } from '@expo/vector-icons';
import { StyleSheet, Text, View } from 'react-native';
import type { StyleProp, ViewStyle } from 'react-native';

import { FontSize, FontWeight, Palette, Radius, Spacing } from '@/constants/theme';

/**
 * % ĐÃ ĐỌC dạng thanh tiến độ + con số bên phải (Tuấn 10/11/2026: nhãn "17%" nhỏ đè lên góc
 * bìa khó đọc -> đổi sang thanh). Đặt trên NỀN SÁNG (dưới tên truyện), cùng kiểu thanh ở lời
 * mời "Pick up where you left off": rãnh cam nhạt, phần đã đọc cam; đọc hết thì xanh + dấu ✓.
 *
 * `percent` lấy từ `readPercent()` / `useReadPercent()` (store/history): 1–99 khi đang đọc,
 * 100 khi đã đọc hết. Phần tô tối thiểu 4% bề rộng để mới mở chương đầu vẫn thấy vạch.
 */
export function ReadProgress({
  percent,
  style,
}: {
  percent: number;
  style?: StyleProp<ViewStyle>;
}) {
  const done = percent >= 100;
  const tone = done ? Palette.freeDeep : Palette.accentDeep;
  return (
    <View
      style={[styles.row, style]}
      accessible
      accessibilityRole="progressbar"
      accessibilityLabel={done ? 'Finished' : `${percent}% read`}
      accessibilityValue={{ min: 0, max: 100, now: percent }}
    >
      <View style={[styles.track, done && styles.trackDone]}>
        <View
          style={[styles.fill, done && styles.fillDone, { width: `${Math.max(4, Math.min(100, percent))}%` }]}
        />
      </View>
      <View style={styles.label}>
        {done ? <Ionicons name="checkmark-circle" size={12} color={tone} /> : null}
        <Text style={[styles.labelText, { color: tone }]}>{percent}%</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs + 2,
  },
  track: {
    flex: 1,
    height: 5,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accentDim,
    overflow: 'hidden',
  },
  trackDone: {
    backgroundColor: Palette.freeDim,
  },
  fill: {
    height: '100%',
    borderRadius: Radius.pill,
    backgroundColor: Palette.accent,
  },
  fillDone: {
    backgroundColor: Palette.free,
  },
  label: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  labelText: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
    fontVariant: ['tabular-nums'],
  },
});
