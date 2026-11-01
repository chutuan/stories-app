import { Ionicons } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { Linking, Pressable, StyleSheet, Switch, Text, View } from 'react-native';

import { FontSize, FontWeight, Palette, Radius, Spacing } from '@/constants/theme';
import { useNotifications } from '@/store/notifications';

/**
 * Link Chính sách quyền riêng tư, Điều khoản và Liên hệ — hiện NGAY TRONG app.
 *
 * VÌ SAO BẮT BUỘC: Guideline 5.1.1(i) của App Store yêu cầu mọi app có link tới
 * chính sách quyền riêng tư "within the app in an easily accessible manner", không
 * chỉ trong metadata trên App Store Connect. App này có SDK quảng cáo (Google Mobile
 * Ads) thu dữ liệu, nên chắc chắn thuộc diện áp dụng. Tới bản 1.0 (6) app KHÔNG có
 * link nào như vậy — phát hiện khi soát lần cuối trước lần nộp thứ năm (28/09/2026).
 *
 * Đặt ở cuối tab Library và tab Rewards: Library là chỗ người ta hay tìm phần cài
 * đặt, còn Rewards là nơi nút "Earn coins for free" dẫn tới, nên reviewer chắc chắn
 * đi qua. Ghi chú gửi Apple nói rõ vị trí này.
 *
 * Mở bằng trình duyệt nội bộ của iOS (SFSafariViewController) để người dùng không
 * bị đẩy ra khỏi app. Nếu vì lý do gì đó không mở được thì rơi về Safari ngoài.
 */

const SITE = 'https://tunastory.com';

const LINKS = [
  { label: 'Privacy Policy', url: `${SITE}/privacy`, icon: 'shield-checkmark-outline' },
  { label: 'Terms of Service', url: `${SITE}/terms`, icon: 'document-text-outline' },
  { label: 'Contact us', url: `${SITE}/contact`, icon: 'mail-outline' },
] as const;

async function open(url: string) {
  try {
    await WebBrowser.openBrowserAsync(url);
  } catch {
    // openBrowserAsync từ chối khi đang có một trình duyệt khác mở; khi đó dùng Safari.
    Linking.openURL(url).catch(() => {});
  }
}

/**
 * Công tắc thông báo "truyện mới hôm nay". Nằm cùng khối với các link trên vì đây là
 * chỗ người ta tìm phần cài đặt — và là lối tắt thông báo mà Guideline 4.5.4 đòi phải
 * có ngay trong app. Privacy Policy chỉ đường tới đây ("Library → Notifications").
 */
function NotificationSetting() {
  const { supported, ready, enabled, permission, enable, disable } = useNotifications();
  if (!supported || !ready) return null;

  const on = enabled && permission === 'granted';
  const toggle = (next: boolean) => {
    (next ? enable() : disable()).catch(() => {});
  };
  const hint = !enabled
    ? 'Off'
    : permission === 'denied'
      ? 'Turned off in iOS Settings — tap to open'
      : 'At most one a day, in the evening';

  return (
    <>
      <Text style={styles.heading}>Notifications</Text>
      <View style={[styles.card, styles.cardGap]}>
        <Pressable
          onPress={() => toggle(!on)}
          accessibilityRole="switch"
          accessibilityState={{ checked: on }}
          accessibilityLabel="New story notifications"
          accessibilityHint={hint}
          style={({ pressed }) => [styles.row, pressed && styles.pressed]}
        >
          <Ionicons name="notifications-outline" size={18} color={Palette.muted} />
          <View style={styles.labelWrap}>
            <Text style={styles.switchLabel}>New story each evening</Text>
            <Text style={styles.hint}>{hint}</Text>
          </View>
          <Switch
            value={on}
            onValueChange={toggle}
            trackColor={{ true: Palette.accent, false: Palette.borderStrong }}
            accessibilityElementsHidden
            importantForAccessibility="no-hide-descendants"
          />
        </Pressable>
      </View>
    </>
  );
}

export function AboutLinks() {
  return (
    <View style={styles.wrap}>
      <NotificationSetting />
      <Text style={styles.heading}>About Stories</Text>
      <View style={styles.card}>
        {LINKS.map((link, i) => (
          <Pressable
            key={link.url}
            onPress={() => open(link.url)}
            accessibilityRole="link"
            accessibilityLabel={link.label}
            style={({ pressed }) => [
              styles.row,
              i < LINKS.length - 1 && styles.rowDivider,
              pressed && styles.pressed,
            ]}
          >
            <Ionicons name={link.icon} size={18} color={Palette.muted} />
            <Text style={styles.label}>{link.label}</Text>
            <Ionicons name="open-outline" size={15} color={Palette.faint} />
          </Pressable>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: Spacing.lg,
    marginBottom: Spacing.md,
  },
  heading: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    letterSpacing: 0.6,
    textTransform: 'uppercase',
    marginBottom: Spacing.sm,
    paddingHorizontal: Spacing.xs,
  },
  card: {
    backgroundColor: Palette.surface,
    borderRadius: Radius.lg,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    overflow: 'hidden',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    paddingVertical: Spacing.md,
    paddingHorizontal: Spacing.md,
  },
  rowDivider: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: Palette.border,
  },
  cardGap: {
    marginBottom: Spacing.lg,
  },
  label: {
    flex: 1,
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.medium,
  },
  labelWrap: {
    flex: 1,
    gap: 2,
  },
  // Không flex: 1 như `label` — nằm trong cột cao tự động thì flex-basis 0 làm chữ sụp mất.
  switchLabel: {
    color: Palette.text,
    fontSize: FontSize.body,
    fontWeight: FontWeight.medium,
  },
  hint: {
    color: Palette.faint,
    fontSize: FontSize.caption,
  },
  pressed: {
    opacity: 0.6,
  },
});
