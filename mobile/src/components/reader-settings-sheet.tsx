import { Ionicons } from '@expo/vector-icons';
import Slider from '@react-native-community/slider';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import {
  FontSize,
  FontWeight,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
} from '@/constants/theme';
import {
  FONT_SIZE_MAX,
  FONT_SIZE_MIN,
  LINE_HEIGHT_MAX,
  LINE_HEIGHT_MIN,
  READER_THEMES,
  READER_THEME_ORDER,
  useReaderPrefs,
  type ReaderThemeKey,
} from '@/store/reader-prefs';

/**
 * BẢNG CÀI ĐẶT ĐỌC — trượt lên từ đáy màn hình.
 *
 * Dùng <Modal> của React Native (transparent + animationType="slide"), KHÔNG
 * cần thêm thư viện bottom-sheet nào.
 *
 * Bảng này là "khung app" nên vẫn dùng Palette SÁNG; riêng 4 ô chọn nền đọc
 * hiện đúng màu của từng nền (kể cả nền Đêm) để xem trước.
 */

export interface ReaderSettingsSheetProps {
  visible: boolean;
  onClose: () => void;
}

/** Đổi 1.75 -> "1,75" (dấu phẩy thập phân kiểu Việt). */
function formatRatio(value: number): string {
  return value.toFixed(2).replace(/0$/, '').replace('.', ',');
}

export function ReaderSettingsSheet({ visible, onClose }: ReaderSettingsSheetProps) {
  const insets = useSafeAreaInsets();
  const {
    fontSize,
    lineHeight,
    theme,
    brightness,
    brightnessSupported,
    stepFontSize,
    stepLineHeight,
    setTheme,
    previewBrightness,
    commitBrightness,
    reset,
  } = useReaderPrefs();

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      statusBarTranslucent
      navigationBarTranslucent
      onRequestClose={onClose}
    >
      {/* Nền mờ — chạm ra ngoài để đóng */}
      <Pressable
        style={styles.backdrop}
        onPress={onClose}
        accessibilityRole="button"
        accessibilityLabel="Đóng bảng cài đặt"
      />

      <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, Spacing.lg) }]}>
        <View style={styles.grabber} />

        <View style={styles.titleRow}>
          <Text style={styles.title}>Tùy chỉnh đọc</Text>
          <Pressable
            onPress={onClose}
            hitSlop={10}
            accessibilityRole="button"
            accessibilityLabel="Đóng"
            style={({ pressed }) => [styles.closeBtn, pressed && styles.pressed]}
          >
            <Ionicons name="close" size={18} color={Palette.muted} />
          </Pressable>
        </View>

        {/* ---------- Độ sáng ---------- */}
        <View style={styles.brightnessRow}>
          <Ionicons name="sunny-outline" size={16} color={Palette.faint} />
          <Slider
            style={styles.slider}
            minimumValue={0}
            maximumValue={1}
            value={brightness}
            disabled={!brightnessSupported}
            onValueChange={previewBrightness}
            onSlidingComplete={commitBrightness}
            minimumTrackTintColor={Palette.accent}
            maximumTrackTintColor={Palette.surfaceAlt}
            thumbTintColor={Palette.accentDeep}
            accessibilityLabel="Độ sáng màn hình"
          />
          <Ionicons name="sunny" size={22} color={Palette.coinDeep} />
        </View>

        {/* ---------- Cỡ chữ ---------- */}
        <Section label="Cỡ chữ" value={`${fontSize}pt`}>
          <StepButton
            accessibilityLabel="Giảm cỡ chữ"
            disabled={fontSize <= FONT_SIZE_MIN}
            onPress={() => stepFontSize(-1)}
          >
            <Text style={[styles.aa, styles.aaSmall]}>Aa</Text>
            <Ionicons name="remove" size={16} color={Palette.muted} />
          </StepButton>
          <StepButton
            accessibilityLabel="Tăng cỡ chữ"
            disabled={fontSize >= FONT_SIZE_MAX}
            onPress={() => stepFontSize(1)}
          >
            <Text style={[styles.aa, styles.aaBig]}>Aa</Text>
            <Ionicons name="add" size={16} color={Palette.muted} />
          </StepButton>
        </Section>

        {/* ---------- Giãn dòng ---------- */}
        <Section label="Giãn dòng" value={formatRatio(lineHeight)}>
          <StepButton
            accessibilityLabel="Thu hẹp giãn dòng"
            disabled={lineHeight <= LINE_HEIGHT_MIN}
            onPress={() => stepLineHeight(-1)}
          >
            <LineIcon gap={3} />
            <Ionicons name="remove" size={16} color={Palette.muted} />
          </StepButton>
          <StepButton
            accessibilityLabel="Nới rộng giãn dòng"
            disabled={lineHeight >= LINE_HEIGHT_MAX}
            onPress={() => stepLineHeight(1)}
          >
            <LineIcon gap={7} />
            <Ionicons name="add" size={16} color={Palette.muted} />
          </StepButton>
        </Section>

        {/* ---------- Nền đọc ---------- */}
        <View style={styles.block}>
          <Text style={styles.blockLabel}>Nền đọc</Text>
          <View style={styles.themeRow}>
            {READER_THEME_ORDER.map((key) => (
              <ThemeSwatch
                key={key}
                themeKey={key}
                selected={key === theme}
                onPress={() => setTheme(key)}
              />
            ))}
          </View>
        </View>

        <Pressable
          onPress={reset}
          accessibilityRole="button"
          accessibilityLabel="Đặt lại về mặc định"
          style={({ pressed }) => [styles.resetBtn, pressed && styles.pressed]}
        >
          <Ionicons name="refresh" size={14} color={Palette.muted} />
          <Text style={styles.resetText}>Đặt lại mặc định</Text>
        </Pressable>
      </View>
    </Modal>
  );
}

/* ------------------------------------------------------------------ */
/* Khối "nhãn + giá trị" kèm 2 nút chỉnh                               */
/* ------------------------------------------------------------------ */

function Section({
  label,
  value,
  children,
}: {
  label: string;
  value: string;
  children: React.ReactNode;
}) {
  return (
    <View style={styles.block}>
      <View style={styles.blockHead}>
        <Text style={styles.blockLabel}>{label}</Text>
        <Text style={styles.blockValue}>{value}</Text>
      </View>
      <View style={styles.stepRow}>{children}</View>
    </View>
  );
}

function StepButton({
  disabled,
  onPress,
  accessibilityLabel,
  children,
}: {
  disabled: boolean;
  onPress: () => void;
  accessibilityLabel: string;
  children: React.ReactNode;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [
        styles.stepBtn,
        disabled && styles.stepBtnDisabled,
        pressed && !disabled && styles.pressed,
      ]}
    >
      {children}
    </Pressable>
  );
}

/** 3 vạch ngang minh hoạ độ giãn dòng — vạch càng thưa = giãn càng rộng. */
function LineIcon({ gap }: { gap: number }) {
  return (
    <View style={{ gap: gap - 2 }}>
      <View style={styles.lineBar} />
      <View style={styles.lineBar} />
      <View style={styles.lineBar} />
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* Ô chọn nền đọc                                                      */
/* ------------------------------------------------------------------ */

function ThemeSwatch({
  themeKey,
  selected,
  onPress,
}: {
  themeKey: ReaderThemeKey;
  selected: boolean;
  onPress: () => void;
}) {
  const t = READER_THEMES[themeKey];
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      accessibilityLabel={`Nền ${t.label}`}
      style={({ pressed }) => [styles.swatchWrap, pressed && styles.pressed]}
    >
      <View
        style={[
          styles.swatch,
          { backgroundColor: t.bg },
          selected ? styles.swatchSelected : { borderColor: Palette.borderStrong },
        ]}
      >
        <Text style={[styles.swatchAa, { color: t.text }]}>Aa</Text>
        {selected ? (
          <View style={styles.swatchCheck}>
            <Ionicons name="checkmark" size={11} color={Palette.onAccent} />
          </View>
        ) : null}
      </View>
      <Text style={[styles.swatchLabel, selected && styles.swatchLabelOn]} numberOfLines={1}>
        {t.label}
      </Text>
    </Pressable>
  );
}

/* ------------------------------------------------------------------ */

const styles = StyleSheet.create({
  // RN 0.86 đã bỏ `StyleSheet.absoluteFillObject` khỏi types -> viết tay 4 cạnh.
  backdrop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: Palette.overlay,
  },
  pressed: {
    opacity: Layout.pressedOpacity,
  },

  sheet: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    gap: Spacing.lg,
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.md,
    backgroundColor: Palette.surfaceHigh,
    borderTopLeftRadius: Radius.xxl,
    borderTopRightRadius: Radius.xxl,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Palette.border,
    ...Shadow.sheet,
  },
  grabber: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: Radius.pill,
    backgroundColor: Palette.borderStrong,
  },

  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: -Spacing.xs,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h2,
  },
  closeBtn: {
    width: 30,
    height: 30,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },

  // --- Độ sáng ---
  brightnessRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.xs,
    borderRadius: Radius.pill,
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  slider: {
    flex: 1,
    height: 36,
  },

  // --- Khối cỡ chữ / giãn dòng ---
  block: {
    gap: Spacing.sm,
  },
  blockHead: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
  },
  blockLabel: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.bold,
    letterSpacing: 0.6,
    textTransform: 'uppercase',
  },
  blockValue: {
    color: Palette.accentDeep,
    fontSize: FontSize.small,
    fontWeight: FontWeight.black,
  },
  stepRow: {
    flexDirection: 'row',
    gap: Spacing.md,
  },
  stepBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    height: 56,
    borderRadius: Radius.md,
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  stepBtnDisabled: {
    opacity: 0.4,
  },
  aa: {
    color: Palette.text,
    fontWeight: FontWeight.bold,
  },
  aaSmall: {
    fontSize: 15,
  },
  aaBig: {
    fontSize: 24,
  },
  lineBar: {
    width: 22,
    height: 2,
    borderRadius: Radius.pill,
    backgroundColor: Palette.text,
  },

  // --- Ô chọn nền ---
  themeRow: {
    flexDirection: 'row',
    gap: Spacing.md,
  },
  swatchWrap: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.xs,
  },
  swatch: {
    alignSelf: 'stretch',
    height: 56,
    borderRadius: Radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    ...Shadow.sm,
  },
  swatchSelected: {
    borderColor: Palette.accentDeep,
  },
  swatchAa: {
    fontSize: 17,
    fontWeight: FontWeight.bold,
  },
  swatchCheck: {
    position: 'absolute',
    top: -6,
    right: -6,
    width: 18,
    height: 18,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.accentDeep,
    borderWidth: 2,
    borderColor: Palette.surfaceHigh,
  },
  swatchLabel: {
    color: Palette.muted,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
  },
  swatchLabelOn: {
    color: Palette.accentDeep,
    fontWeight: FontWeight.bold,
  },

  // --- Đặt lại ---
  resetBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'center',
    gap: Spacing.xs,
    paddingHorizontal: Spacing.lg,
    paddingVertical: Spacing.sm,
    borderRadius: Radius.pill,
  },
  resetText: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
  },
});
