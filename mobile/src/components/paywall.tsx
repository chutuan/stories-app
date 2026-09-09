import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import {
  FontSize,
  FontWeight,
  Gradients,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  withAlpha,
} from '@/constants/theme';
import { useSubscription } from '@/store/subscription';

type IoniconName = keyof typeof Ionicons.glyphMap;

/** Trang công khai đã dựng sẵn ở backend — Apple bắt buộc phải liên kết được từ màn bán hàng. */
const TERMS_URL = 'https://tunastory.com/terms';
const PRIVACY_URL = 'https://tunastory.com/privacy';

const BENEFITS: { icon: IoniconName; title: string; body: string }[] = [
  {
    icon: 'lock-open',
    title: 'Every chapter unlocked',
    body: 'Read the whole library straight through. No coins, no waiting.',
  },
  {
    icon: 'headset',
    title: 'Listen to every chapter',
    body: 'Full audio narration on all chapters, not just the free ones.',
  },
  {
    icon: 'infinite',
    title: 'Keep listening back to back',
    body: 'Chapters play one after another without stopping to unlock.',
  },
];

export interface PaywallProps {
  visible: boolean;
  onClose: () => void;
  /** Câu dẫn theo ngữ cảnh, vd "Audio for this chapter is for members." */
  reason?: string;
}

/**
 * Màn bán gói Premium.
 *
 * Bắt buộc theo quy định App Store, đừng bỏ khi chỉnh giao diện:
 *  - nói rõ tên gói, ĐỘ DÀI chu kỳ và GIÁ mỗi chu kỳ;
 *  - nói rõ gói TỰ ĐỘNG GIA HẠN và huỷ ở đâu;
 *  - có nút "Restore purchases";
 *  - có liên kết tới Điều khoản và Chính sách riêng tư.
 */
export function Paywall({ visible, onClose, reason }: PaywallProps) {
  const { subscribe, restore, priceLabel, busy } = useSubscription();
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);

  const handleSubscribe = useCallback(async () => {
    setMessage(null);
    const result = await subscribe();
    if (result.ok) {
      setMessage({ text: result.message ?? "You're all set. Enjoy Premium!", ok: true });
      return;
    }
    // Người dùng tự bấm huỷ thì im lặng, không dựng báo đỏ.
    if (result.cancelled) return;
    if (result.message) setMessage({ text: result.message, ok: false });
  }, [subscribe]);

  const handleRestore = useCallback(async () => {
    setMessage(null);
    const result = await restore();
    setMessage({
      text: result.ok ? 'Your subscription is active again.' : (result.message ?? 'Nothing to restore.'),
      ok: result.ok,
    });
  }, [restore]);

  const openLink = useCallback((url: string) => {
    Linking.openURL(url).catch(() => {});
  }, []);

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={styles.wrap}>
        <Pressable
          style={StyleSheet.absoluteFill}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="Dismiss"
        />
        <View style={styles.sheet}>
          <View style={styles.handle} />

          <ScrollView
            contentContainerStyle={styles.body}
            showsVerticalScrollIndicator={false}
            bounces={false}
          >
            <View style={styles.crown}>
              <Ionicons name="sparkles" size={24} color={Palette.onAccent} />
            </View>

            <Text style={styles.title}>Stories Premium</Text>
            <Text style={styles.subtitle}>
              {reason ?? 'Unlock the whole library and listen to every chapter.'}
            </Text>

            <View style={styles.benefits}>
              {BENEFITS.map((b) => (
                <View key={b.title} style={styles.benefit}>
                  <View style={styles.benefitIcon}>
                    <Ionicons name={b.icon} size={17} color={Palette.accentDeep} />
                  </View>
                  <View style={styles.flex}>
                    <Text style={styles.benefitTitle}>{b.title}</Text>
                    <Text style={styles.benefitBody}>{b.body}</Text>
                  </View>
                </View>
              ))}
            </View>

            <View style={styles.priceRow}>
              <Text style={styles.price}>{priceLabel}</Text>
              <Text style={styles.period}>/ month</Text>
            </View>

            {message ? (
              <View style={[styles.banner, message.ok ? styles.bannerOk : styles.bannerWarn]}>
                <Ionicons
                  name={message.ok ? 'checkmark-circle' : 'alert-circle'}
                  size={16}
                  color={message.ok ? Palette.freeDeep : Palette.coinDeep}
                />
                <Text
                  style={[
                    styles.bannerText,
                    { color: message.ok ? Palette.freeDeep : Palette.coinDeep },
                  ]}
                >
                  {message.text}
                </Text>
              </View>
            ) : null}

            <Pressable
              onPress={handleSubscribe}
              disabled={busy}
              accessibilityRole="button"
              accessibilityState={{ disabled: busy }}
              accessibilityLabel={`Subscribe for ${priceLabel} per month`}
              style={({ pressed }) => [
                styles.primaryBtn,
                busy && styles.dim,
                pressed && !busy && styles.pressed,
              ]}
            >
              <LinearGradient
                colors={Gradients.accent}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={StyleSheet.absoluteFill}
              />
              {busy ? (
                <ActivityIndicator color={Palette.onAccent} size="small" />
              ) : (
                <Text style={styles.primaryBtnText}>Subscribe · {priceLabel}/month</Text>
              )}
            </Pressable>

            <Pressable
              onPress={handleRestore}
              disabled={busy}
              accessibilityRole="button"
              accessibilityLabel="Restore purchases"
              style={({ pressed }) => [styles.restoreBtn, pressed && !busy && styles.pressed]}
            >
              <Text style={styles.restoreText}>Restore purchases</Text>
            </Pressable>

            {/* Bắt buộc theo App Store Review Guideline 3.1.2 */}
            <Text style={styles.legal}>
              Auto-renews every month at {priceLabel} until cancelled. Cancel any time in your
              device&apos;s account settings, at least 24 hours before the period ends.
            </Text>

            <View style={styles.links}>
              <Pressable onPress={() => openLink(TERMS_URL)} accessibilityRole="link">
                <Text style={styles.link}>Terms of Use</Text>
              </Pressable>
              <View style={styles.linkDot} />
              <Pressable onPress={() => openLink(PRIVACY_URL)} accessibilityRole="link">
                <Text style={styles.link}>Privacy Policy</Text>
              </Pressable>
            </View>

            <Pressable
              onPress={onClose}
              accessibilityRole="button"
              accessibilityLabel="Not now"
              style={({ pressed }) => [styles.later, pressed && styles.pressed]}
            >
              <Text style={styles.laterText}>Not now</Text>
            </Pressable>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  pressed: { opacity: 0.75 },
  dim: { opacity: 0.6 },
  wrap: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: withAlpha(Palette.text, 0.45),
  },
  sheet: {
    maxHeight: '92%',
    borderTopLeftRadius: Radius.xxl,
    borderTopRightRadius: Radius.xxl,
    backgroundColor: Palette.surfaceHigh,
    ...Shadow.sheet,
  },
  handle: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: 2,
    marginTop: Spacing.md,
    backgroundColor: Palette.borderStrong,
  },
  body: {
    alignItems: 'center',
    gap: Spacing.md,
    paddingHorizontal: Spacing.xl,
    paddingTop: Spacing.lg,
    paddingBottom: Spacing.xxl,
  },
  crown: {
    width: 56,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 28,
    backgroundColor: Palette.accent,
  },
  title: {
    color: Palette.text,
    fontSize: FontSize.h1,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.h1,
    textAlign: 'center',
  },
  subtitle: {
    marginTop: -Spacing.sm,
    color: Palette.muted,
    fontSize: FontSize.small,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.small + 2,
    textAlign: 'center',
  },
  benefits: {
    alignSelf: 'stretch',
    gap: Spacing.md,
    padding: Spacing.lg,
    borderRadius: Radius.card,
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  benefit: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: Spacing.md,
  },
  benefitIcon: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 16,
    backgroundColor: Palette.accentDim,
  },
  benefitTitle: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.black,
  },
  benefitBody: {
    marginTop: 2,
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.caption + 3,
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: Spacing.xs,
  },
  price: {
    color: Palette.text,
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
  },
  period: {
    color: Palette.muted,
    fontSize: FontSize.body,
    fontWeight: FontWeight.semibold,
  },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    alignSelf: 'stretch',
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm + 2,
    borderRadius: Radius.md,
    borderWidth: StyleSheet.hairlineWidth,
  },
  bannerOk: {
    backgroundColor: Palette.freeDim,
    borderColor: Palette.freeBorder,
  },
  bannerWarn: {
    backgroundColor: Palette.coinDim,
    borderColor: Palette.coinBorder,
  },
  bannerText: {
    flex: 1,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption + 2,
  },
  primaryBtn: {
    alignSelf: 'stretch',
    height: 54,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: Radius.md,
    overflow: 'hidden',
  },
  primaryBtnText: {
    color: Palette.onAccent,
    fontSize: FontSize.body,
    fontWeight: FontWeight.black,
  },
  restoreBtn: {
    paddingVertical: Spacing.xs,
  },
  restoreText: {
    color: Palette.accentDeep,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
  },
  legal: {
    color: Palette.faint,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.caption,
    textAlign: 'center',
  },
  links: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
  },
  link: {
    color: Palette.muted,
    fontSize: FontSize.micro,
    fontWeight: FontWeight.bold,
    textDecorationLine: 'underline',
  },
  linkDot: {
    width: 3,
    height: 3,
    borderRadius: 1.5,
    backgroundColor: Palette.faint,
  },
  later: {
    paddingHorizontal: Spacing.lg,
    paddingVertical: Spacing.xs,
  },
  laterText: {
    color: Palette.faint,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
  },
});
