import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Mã ngẫu nhiên của máy này, sinh lần đầu cần tới rồi giữ trong máy (`stories:reader-id`).
 *
 * App không có tài khoản; mã này chỉ để server phân biệt hai máy: phiếu khuôn mặt cuối chương
 * (bấm lại là đổi phiếu) và lượt bấm nghe audio (đếm số NGƯỜI muốn nghe mỗi chương). Server chỉ
 * lưu bản băm. Không cần ngẫu nhiên mật mã.
 */
const KEY_READER_ID = 'stories:reader-id';

let readerIdPromise: Promise<string> | null = null;

export function getReaderId(): Promise<string> {
  readerIdPromise ??= AsyncStorage.getItem(KEY_READER_ID)
    .catch(() => null)
    .then((stored) => {
      if (stored && /^[A-Za-z0-9_-]{16,64}$/.test(stored)) return stored;
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let id = '';
      for (let i = 0; i < 32; i++) id += chars[Math.floor(Math.random() * chars.length)];
      AsyncStorage.setItem(KEY_READER_ID, id).catch(() => {});
      return id;
    });
  return readerIdPromise;
}
