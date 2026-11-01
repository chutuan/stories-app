import { NativeModule, requireOptionalNativeModule } from 'expo';
import { type ReactNode, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import { SafeAreaInsetsContext, useSafeAreaInsets } from 'react-native-safe-area-context';

type WindowControlsEvents = {
  /** `top`: phần lề trên cần thêm (pt), gửi mỗi khi cụm nút cửa sổ hiện / ẩn / đổi chỗ. */
  onChange: (event: { top: number }) => void;
};

declare class WindowControlsModule extends NativeModule<WindowControlsEvents> {
  getTopInset(): Promise<number>;
}

/** modules/window-controls (Swift). Không có trên Android / bản build cũ -> null. */
const WindowControls = requireOptionalNativeModule<WindowControlsModule>('WindowControls');

/** Cụm nút cửa sổ cao chừng 30pt; số lớn hơn hẳn là đo sai thì bỏ, đừng đẩy cả màn xuống. */
const MAX_EXTRA_TOP = 80;

function sanitize(v: number): number {
  return v > 0 && v <= MAX_EXTRA_TOP ? Math.ceil(v) : 0;
}

/**
 * iPadOS 26, app chạy trong cửa sổ: cụm nút đóng / thu nhỏ / phóng to đè lên góc trên trái,
 * đúng chỗ logo, tiêu đề tab, nút Back và banner quảng cáo đầu màn đọc. Safe area của hệ
 * thống không tính cụm nút này (xem modules/window-controls/ios/WindowControlsModule.swift).
 *
 * Thay vì sửa từng màn, cộng phần lề cần thêm vào `top` của safe area ngay ở gốc: mọi màn
 * vốn đã đệm `useSafeAreaInsets().top` nên tự xuống dưới cụm nút. Toàn màn hình phần thêm
 * là 0, không đổi gì. Native báo `onChange` mỗi khi cụm nút hiện / ẩn / đổi chỗ.
 */
export function WindowControlsSafeArea({ children }: { children: ReactNode }) {
  const insets = useSafeAreaInsets();
  const [extraTop, setExtraTop] = useState(0);

  useEffect(() => {
    const native = WindowControls;
    if (!native || Platform.OS !== 'ios' || !Platform.isPad) return;
    let alive = true;
    const sub = native.addListener('onChange', ({ top }) => setExtraTop(sanitize(top)));
    native
      .getTopInset()
      .then((v) => {
        if (alive) setExtraTop(sanitize(v));
      })
      .catch(() => {});
    return () => {
      alive = false;
      sub.remove();
    };
  }, []);

  const value = useMemo(
    () => (extraTop ? { ...insets, top: insets.top + extraTop } : insets),
    [insets, extraTop],
  );

  return <SafeAreaInsetsContext.Provider value={value}>{children}</SafeAreaInsetsContext.Provider>;
}
