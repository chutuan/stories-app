import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { Palette } from '@/constants/theme';
import { RewardsProvider } from '@/store/rewards';
import { SubscriptionProvider } from '@/store/subscription';
import { WalletProvider } from '@/store/wallet';

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
            <Stack.Screen name="reader/[storyId]/[number]" />
          </Stack>
          </SubscriptionProvider>
        </RewardsProvider>
      </WalletProvider>
    </SafeAreaProvider>
  );
}
