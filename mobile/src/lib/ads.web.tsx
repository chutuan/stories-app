import { StyleSheet, Text, View } from 'react-native';

import { FontSize, FontWeight, Palette, Radius, Spacing } from '@/constants/theme';

/**
 * Phiên bản WEB của module ads.
 *
 * Metro tự ưu tiên file `.web.tsx` khi build web, nên bản này KHÔNG import
 * `react-native-google-mobile-ads` (package đó import native internals của
 * react-native -> bundle web sẽ lỗi).
 *
 * Trên web: banner là placeholder, rewarded trả thưởng ngay để test luồng xu.
 */

export const adsAvailable = false;

/** Banner ở đầu màn đọc — trên web luôn là placeholder. */
export function BannerAd() {
  return (
    <View style={styles.placeholder}>
      <Text style={styles.placeholderText}>Quảng cáo</Text>
    </View>
  );
}

export interface RewardedResult {
  rewarded: boolean;
}

/** Trên web không có rewarded thật -> trả thưởng ngay để thử luồng xu. */
export function showRewarded(): Promise<RewardedResult> {
  return Promise.resolve({ rewarded: true });
}

const styles = StyleSheet.create({
  placeholder: {
    height: 50,
    alignItems: 'center',
    justifyContent: 'center',
    // nền sáng: ô quảng cáo là khối kem nhạt, viền mảnh, chữ mờ
    backgroundColor: Palette.surfaceAlt,
    borderRadius: Radius.sm,
    marginHorizontal: Spacing.sm,
    marginVertical: Spacing.xs,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  placeholderText: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    letterSpacing: 1.2,
  },
});
