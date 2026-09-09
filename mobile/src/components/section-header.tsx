import { Ionicons } from '@expo/vector-icons';
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
} from '@/constants/theme';

export type IoniconName = keyof typeof Ionicons.glyphMap;

export interface SectionHeaderProps {
  /** tiêu đề mục (TIẾNG VIỆT) */
  title: string;
  /** dòng phụ mờ dưới tiêu đề */
  subtitle?: string;
  /** icon Ionicons đứng trước tiêu đề (thay cho thanh cam) */
  icon?: IoniconName;
  /** chữ của nút bên phải, mặc định 'Xem thêm' */
  actionLabel?: string;
  /** có onPress mới hiện nút "Xem thêm ›" */
  onPress?: () => void;
  style?: StyleProp<ViewStyle>;
}

/** Tiêu đề một mục + nút "Xem thêm ›" tuỳ chọn. */
export function SectionHeader({
  title,
  subtitle,
  icon,
  actionLabel = 'Xem thêm',
  onPress,
  style,
}: SectionHeaderProps) {
  return (
    <View style={[styles.row, style]}>
      <View style={styles.left}>
        {icon ? (
          <Ionicons name={icon} size={18} color={Palette.accentDeep} style={styles.icon} />
        ) : (
          <View style={styles.bar} />
        )}
        <View style={styles.titleWrap}>
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
          {subtitle ? (
            <Text style={styles.subtitle} numberOfLines={1}>
              {subtitle}
            </Text>
          ) : null}
        </View>
      </View>

      {onPress ? (
        <Pressable
          onPress={onPress}
          accessibilityRole="button"
          accessibilityLabel={actionLabel}
          hitSlop={8}
          style={({ pressed }) => [styles.action, pressed && styles.pressed]}
        >
          <Text style={styles.actionText}>{actionLabel}</Text>
          <Ionicons name="chevron-forward" size={14} color={Palette.accentDeep} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.md,
    paddingHorizontal: Spacing.screen,
    marginBottom: Spacing.md,
  },
  left: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    flexShrink: 1,
  },
  bar: {
    width: 4,
    height: 18,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accent,
  },
  icon: {
    marginRight: -2,
  },
  titleWrap: {
    flexShrink: 1,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h2,
  },
  subtitle: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    marginTop: 1,
  },
  action: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    paddingVertical: Spacing.xs,
    paddingLeft: Spacing.sm,
  },
  actionText: {
    color: Palette.accentDeep,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },
});
