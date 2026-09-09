import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import type { StyleProp, ViewStyle } from 'react-native';

import {
  FontSize,
  FontWeight,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Spacing,
  withAlpha,
} from '@/constants/theme';

export type IoniconName = keyof typeof Ionicons.glyphMap;

export interface EmptyStateProps {
  /** icon Ionicons, mặc định 'sparkles-outline' */
  icon?: IoniconName;
  /** tiêu đề (TIẾNG VIỆT) */
  title: string;
  /** mô tả gợi ý người dùng làm gì tiếp */
  description?: string;
  /** chữ nút hành động; phải kèm onAction mới hiện */
  actionLabel?: string;
  onAction?: () => void;
  /** true = tông đỏ, dùng cho màn lỗi */
  danger?: boolean;
  style?: StyleProp<ViewStyle>;
}

/** Icon + tiêu đề + mô tả cho màn hình rỗng hoặc lỗi. */
export function EmptyState({
  icon = 'sparkles-outline',
  title,
  description,
  actionLabel,
  onAction,
  danger = false,
  style,
}: EmptyStateProps) {
  // nền sáng -> phải dùng bản ĐẬM của màu nhấn cho chữ/icon
  const tint = danger ? Palette.dangerDeep : Palette.accentDeep;
  const halo = danger
    ? ([withAlpha(Palette.danger, 0.18), withAlpha(Palette.danger, 0.05)] as const)
    : ([withAlpha(Palette.accent, 0.22), withAlpha(Palette.accent, 0.06)] as const);

  return (
    <View style={[styles.wrap, style]}>
      <LinearGradient colors={halo} style={styles.halo}>
        <Ionicons name={icon} size={30} color={tint} />
      </LinearGradient>

      <Text style={styles.title}>{title}</Text>
      {description ? <Text style={styles.desc}>{description}</Text> : null}

      {actionLabel && onAction ? (
        <Pressable
          onPress={onAction}
          accessibilityRole="button"
          style={({ pressed }) => [
            styles.button,
            {
              backgroundColor: danger ? Palette.dangerDim : Palette.accentDim,
              borderColor: danger ? withAlpha(Palette.danger, 0.3) : Palette.accentBorder,
            },
            pressed && styles.pressed,
          ]}
        >
          <Text style={[styles.buttonText, { color: tint }]}>{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: Spacing.xxl,
    paddingVertical: Spacing.xxxl,
    gap: Spacing.md,
  },
  halo: {
    width: 72,
    height: 72,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h2,
    textAlign: 'center',
  },
  desc: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.body,
    textAlign: 'center',
    marginTop: -Spacing.xs,
  },
  button: {
    marginTop: Spacing.xs,
    borderWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: Spacing.xl,
    paddingVertical: Spacing.sm + 2,
    borderRadius: Radius.pill,
  },
  buttonText: {
    fontSize: FontSize.body,
    fontWeight: FontWeight.bold,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
});
