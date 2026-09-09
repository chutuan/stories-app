import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import type { StyleProp, TextStyle, ViewStyle } from 'react-native';

import { FontSize, FontWeight, Layout, Palette, Radius, Spacing } from '@/constants/theme';

export type ChipVariant = 'default' | 'accent' | 'coin' | 'free';
export type ChipSize = 'sm' | 'md';
export type IoniconName = keyof typeof Ionicons.glyphMap;

interface Tone {
  bg: string;
  fg: string;
  border: string;
  solidBg: string;
  solidFg: string;
}

/**
 * Bảng màu từng biến thể trên NỀN SÁNG.
 * - `bg`/`fg`/`border`: chip nền mờ (mặc định) — chữ dùng bản ĐẬM (accentDeep,
 *   coinDeep, freeDeep) để đọc được trên nền sáng.
 * - `solidBg`/`solidFg`: chip tô đặc (solid / selected).
 */
const TONES: Record<ChipVariant, Tone> = {
  default: {
    bg: Palette.surfaceAlt,
    fg: Palette.muted,
    border: Palette.border,
    // chip lọc khi ĐƯỢC CHỌN -> tô cam đậm, chữ trắng
    solidBg: Palette.accentDeep,
    solidFg: Palette.onAccent,
  },
  accent: {
    bg: Palette.accentDim,
    fg: Palette.accentDeep,
    border: Palette.accentBorder,
    solidBg: Palette.accentDeep,
    solidFg: Palette.onAccent,
  },
  coin: {
    bg: Palette.coinDim,
    fg: Palette.coinDeep,
    border: Palette.coinBorder,
    solidBg: Palette.coin,
    solidFg: Palette.onLight,
  },
  free: {
    bg: Palette.freeDim,
    fg: Palette.freeDeep,
    border: Palette.freeBorder,
    solidBg: Palette.free,
    solidFg: Palette.onLight,
  },
};

export interface ChipProps {
  /** chữ hiển thị (TIẾNG VIỆT) */
  label: string;
  /** biến thể màu, mặc định 'default' */
  variant?: ChipVariant;
  /** kích thước, mặc định 'md' */
  size?: ChipSize;
  /** icon Ionicons đứng trước chữ */
  icon?: IoniconName;
  /** tô đặc thay vì nền mờ */
  solid?: boolean;
  /** đang được chọn (dùng cho bộ lọc thể loại) — tự tô đặc */
  selected?: boolean;
  /** có onPress thì render Pressable, không thì render View */
  onPress?: () => void;
  style?: StyleProp<ViewStyle>;
  textStyle?: StyleProp<TextStyle>;
}

/** Pill nhỏ cho thể loại / trạng thái / nhãn xu. */
export function Chip({
  label,
  variant = 'default',
  size = 'md',
  icon,
  solid = false,
  selected = false,
  onPress,
  style,
  textStyle,
}: ChipProps) {
  const tone = TONES[variant];
  const filled = solid || selected;
  const bg = filled ? tone.solidBg : tone.bg;
  const fg = filled ? tone.solidFg : tone.fg;
  const borderColor = filled ? 'transparent' : tone.border;
  const sized = size === 'sm' ? styles.sm : styles.md;
  const iconSize = size === 'sm' ? 11 : 13;

  const content = (
    <>
      {icon ? <Ionicons name={icon} size={iconSize} color={fg} /> : null}
      <Text
        numberOfLines={1}
        style={[styles.label, size === 'sm' ? styles.labelSm : styles.labelMd, { color: fg }, textStyle]}
      >
        {label}
      </Text>
    </>
  );

  if (onPress) {
    return (
      <Pressable
        onPress={onPress}
        accessibilityRole="button"
        accessibilityState={{ selected }}
        style={({ pressed }) => [
          styles.base,
          sized,
          { backgroundColor: bg, borderColor },
          pressed && styles.pressed,
          style,
        ]}
      >
        {content}
      </Pressable>
    );
  }

  return <View style={[styles.base, sized, { backgroundColor: bg, borderColor }, style]}>{content}</View>;
}

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: Spacing.xs,
    borderRadius: Radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
  },
  sm: {
    paddingHorizontal: Spacing.sm,
    paddingVertical: 3,
  },
  md: {
    paddingHorizontal: Spacing.md,
    paddingVertical: 6,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
  label: {
    fontWeight: FontWeight.semibold,
  },
  labelSm: {
    fontSize: FontSize.tiny,
  },
  labelMd: {
    fontSize: FontSize.caption,
  },
});
