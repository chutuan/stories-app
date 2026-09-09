import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { StyleSheet, View } from 'react-native';
import type { ColorValue } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { FontSize, FontWeight, Palette, Radius, Shadow, Spacing } from '@/constants/theme';

type IoniconName = keyof typeof Ionicons.glyphMap;

/** Chiều cao vùng nội dung của thanh tab (chưa cộng safe-area đáy). */
const TAB_BAR_CONTENT_HEIGHT = 60;
/** Viên nang bao quanh icon của tab đang chọn. */
const PILL_WIDTH = 52;
const PILL_HEIGHT = 28;

/**
 * Bóng mềm hắt LÊN, tách thanh tab trắng khỏi nền kem của trang.
 * Android không dùng elevation ở đây (dễ tạo vệt xám dưới thanh tab) —
 * hình khối do viền trên mảnh đảm nhiệm.
 */
const BAR_SHADOW = {
  ...Shadow.float,
  shadowOffset: { width: 0, height: -6 },
  elevation: 0,
} as const;

interface TabIconProps {
  /** icon nét đặc — dùng khi tab đang chọn */
  solid: IoniconName;
  /** icon nét mảnh — dùng khi tab không được chọn */
  outline: IoniconName;
  focused: boolean;
  color: ColorValue;
}

/**
 * Icon tab. React Navigation vẽ đồng thời bản "focused" và "không focused"
 * rồi cross-fade, nên viên nang cam cũng mờ dần/hiện dần theo.
 */
function TabIcon({ solid, outline, focused, color }: TabIconProps) {
  return (
    <View style={[StyleSheet.absoluteFill, styles.pill, focused && styles.pillActive]}>
      <Ionicons name={focused ? solid : outline} size={19} color={color} />
    </View>
  );
}

export default function TabsLayout() {
  const insets = useSafeAreaInsets();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        // nền sáng -> màu nhấn của chữ/icon phải là bản ĐẬM mới đủ tương phản
        tabBarActiveTintColor: Palette.accentDeep,
        tabBarInactiveTintColor: Palette.muted,
        tabBarHideOnKeyboard: true,
        tabBarStyle: {
          // thanh tab trắng đặc + viền trên mảnh (không blur: thanh tab chiếm
          // chỗ trong layout nên phía sau nó không có nội dung để làm mờ)
          backgroundColor: Palette.bgDeep,
          borderTopWidth: StyleSheet.hairlineWidth,
          borderTopColor: Palette.border,
          height: TAB_BAR_CONTENT_HEIGHT + insets.bottom,
          paddingTop: Spacing.xs + 2,
          ...BAR_SHADOW,
        },
        tabBarIconStyle: {
          width: PILL_WIDTH,
          height: PILL_HEIGHT,
        },
        tabBarLabelStyle: {
          fontSize: FontSize.tiny,
          fontWeight: FontWeight.semibold,
          marginTop: 3,
        },
        tabBarItemStyle: {
          paddingHorizontal: Spacing.xxs,
        },
        sceneStyle: { backgroundColor: Palette.bg },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Trang chủ',
          tabBarIcon: ({ color, focused }) => (
            <TabIcon solid="home" outline="home-outline" focused={focused} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="search"
        options={{
          title: 'Tìm kiếm',
          tabBarIcon: ({ color, focused }) => (
            <TabIcon solid="search" outline="search-outline" focused={focused} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="rewards"
        options={{
          title: 'Phần thưởng',
          tabBarIcon: ({ color, focused }) => (
            <TabIcon solid="gift" outline="gift-outline" focused={focused} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="saved"
        options={{
          title: 'Đã lưu',
          tabBarIcon: ({ color, focused }) => (
            <TabIcon solid="bookmark" outline="bookmark-outline" focused={focused} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  pill: {
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: Radius.pill,
  },
  pillActive: {
    backgroundColor: Palette.accentDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.accentBorder,
  },
});
