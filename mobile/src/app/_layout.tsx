import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { Palette } from '@/constants/theme';
import { WalletProvider } from '@/store/wallet';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <WalletProvider>
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
      </WalletProvider>
    </SafeAreaProvider>
  );
}
