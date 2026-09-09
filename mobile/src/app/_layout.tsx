import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { Palette } from '@/constants/theme';
import { RewardsProvider } from '@/store/rewards';
import { SubscriptionProvider } from '@/store/subscription';
import { WalletProvider } from '@/store/wallet';

/**
 * Hoạt ảnh chuyển màn theo hướng đọc.
 * `dir=prev` -> trượt từ trái, `dir=next` -> từ phải, không có `dir` (mở lần đầu
 * từ nơi khác) -> dùng hoạt ảnh mặc định của màn đó.
 */
function readingAnimation(
  route: { params?: object },
  fallback: 'slide_from_right' | 'slide_from_bottom',
): 'slide_from_left' | 'slide_from_right' | 'slide_from_bottom' {
  const dir = (route.params as { dir?: string } | undefined)?.dir;
  if (dir === 'prev') return 'slide_from_left';
  if (dir === 'next') return 'slide_from_right';
  return fallback;
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <WalletProvider>
        {/* Sổ cái nhiệm vụ hằng ngày phải nằm ở GỐC. Quảng cáo thưởng xuất hiện ở
            cả tab Phần thưởng, màn đọc và màn nghe; chỉ khi ba nơi dùng CHUNG một
            sổ thì hạn mức AD_TASK_LIMIT lượt/ngày mới có hiệu lực. Bọc riêng lẻ ở
            từng màn sẽ tạo nhiều sổ độc lập cùng ghi đè một khoá AsyncStorage. */}
        <RewardsProvider>
          {/* Quyền Premium phải đọc được ở MỌI màn (đọc, nghe, chi tiết truyện). */}
          <SubscriptionProvider>
          {/* Giao diện SÁNG -> nội dung status bar phải TỐI */}
          <StatusBar style="dark" />
          <Stack
            screenOptions={{
              headerShown: false,
              contentStyle: { backgroundColor: Palette.bg },
              animation: 'slide_from_right',
            }}>
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="story/[id]" />
            {/*
              Lùi chương phải trượt vào từ TRÁI, tiến chương từ PHẢI. Prev và Next đều
              là router.replace trên CÙNG một route nên nếu để nguyên `slide_from_right`
              của screenOptions thì bấm Previous vẫn trượt như Next — đúng lỗi đã gặp.

              Hướng đi kèm trong tham số `dir` của route và phải đọc Ở ĐÂY, dạng hàm:
              options dạng hàm được tính lúc dựng descriptor, tức TRƯỚC khi hoạt ảnh
              chạy. Đặt trong màn hình bằng <Stack.Screen options={...}> thì tới lượt
              effect sau lần render đầu mới áp, khi đó hoạt ảnh đã bắt đầu rồi.
            */}
            <Stack.Screen
              name="reader/[storyId]/[number]"
              options={({ route }) => ({
                animation: readingAnimation(route, 'slide_from_right'),
              })}
            />
            {/* Mở trình phát thì trượt lên từ đáy; đổi chương bên trong thì trượt ngang. */}
            <Stack.Screen
              name="audio/[storyId]/[number]"
              options={({ route }) => ({
                animation: readingAnimation(route, 'slide_from_bottom'),
              })}
            />
          </Stack>
          </SubscriptionProvider>
        </RewardsProvider>
      </WalletProvider>
    </SafeAreaProvider>
  );
}
