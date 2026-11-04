import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState, Linking, Platform } from 'react-native';

import { API_URL } from '@/lib/api';

/**
 * Thông báo "truyện mới hôm nay" — tối đa một lần mỗi ngày, vào buổi tối theo giờ máy.
 *
 * Server chọn truyện và gửi (backend/app/Services/DailyStoryNotifier.php); app chỉ:
 * xin quyền, đăng ký Expo push token kèm múi giờ, và mở trang truyện khi người dùng
 * bấm vào thông báo. Mặc định TẮT: chỉ bật khi người dùng đồng ý (Guideline 4.5.4 —
 * thông báo giới thiệu nội dung phải được người dùng chủ động bật và tắt được trong app).
 *
 * Tắt trong app = xoá token trên server. Tắt trong Cài đặt iOS thì token chết, server
 * tự xoá ở lần gửi sau. Privacy Policy mục "New-story notifications" mô tả đúng điều này.
 */

const KEY_PREFS = 'stories:notify';
const KEY_TOKEN = 'stories:pushToken';

const SUPPORTED = Platform.OS === 'ios' || Platform.OS === 'android';

/**
 * Lời mời bật thông báo (bảng trượt trong components/app-open-prompts.tsx) hiện tối đa chừng
 * này lần, cách nhau ít nhất ASK_AGAIN_MS. Hỏi lại có giới hạn chứ không hỏi mãi: Guideline
 * 5.1.1(iv) cấm ép người dùng đồng ý, và hỏi dồn chỉ dạy người ta bấm "Not now" cho xong.
 * Tắt hẳn trong Library thì không bao giờ hỏi lại (xem askDue).
 */
const MAX_ASKS = 3;
const ASK_AGAIN_MS = 3 * 24 * 60 * 60 * 1000;

export type NotifyPermission = 'unknown' | 'undetermined' | 'granted' | 'denied';

interface NotifyPrefs {
  /** người dùng đã bật trong app */
  enabled: boolean;
  /** số lần đã hiện lời mời bật thông báo */
  asks: number;
  /** chưa tới lúc này (ms) thì không mời lại */
  nextAskAt: number;
}

const DEFAULT_PREFS: NotifyPrefs = { enabled: false, asks: 0, nextAskAt: 0 };

function parsePrefs(raw: string | null): NotifyPrefs {
  try {
    const data = raw ? JSON.parse(raw) : null;
    if (!data || typeof data !== 'object') return DEFAULT_PREFS;
    const num = (v: unknown) => (typeof v === 'number' && Number.isFinite(v) ? v : null);
    return {
      enabled: data.enabled === true,
      // Bản 1.0.2 chỉ lưu `prompted` (đã mời đúng một lần): tính là 1 lần, mời lại được ngay.
      asks: num(data.asks) ?? (data.prompted === true ? 1 : 0),
      nextAskAt: num(data.nextAskAt) ?? 0,
    };
  } catch {
    return DEFAULT_PREFS;
  }
}

interface NotificationsContextValue {
  supported: boolean;
  ready: boolean;
  enabled: boolean;
  permission: NotifyPermission;
  /** Bật: xin quyền nếu chưa hỏi; iOS đã từ chối thì mở Cài đặt. Trả true nếu bật được. */
  enable: () => Promise<boolean>;
  disable: () => Promise<void>;
  /** Đã tới lúc hiện lời mời bật thông báo chưa (đọc giá trị mới nhất, gọi lúc sắp hiện). */
  askDue: () => boolean;
  /** Ghi nhận vừa hiện lời mời: tăng số lần, hẹn lần sau. Gọi lúc HIỆN, không phải lúc đóng. */
  markAsked: () => void;
}

const NotificationsContext = createContext<NotificationsContextValue | null>(null);

if (SUPPORTED) {
  // Đang mở app mà thông báo tới thì vẫn hiện banner, nhưng không kêu.
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: false,
      shouldSetBadge: false,
    }),
  });
}

function toPermission(res: Notifications.NotificationPermissionsStatus): NotifyPermission {
  if (
    res.granted ||
    res.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL ||
    res.ios?.status === Notifications.IosAuthorizationStatus.EPHEMERAL
  ) {
    return 'granted';
  }
  return res.status === 'undetermined' ? 'undetermined' : 'denied';
}

function deviceTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

/** Lấy token và gửi lên server. Lỗi thì thôi — lần mở app sau đăng ký lại. */
async function registerDevice(): Promise<void> {
  const projectId = (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
    ?.eas?.projectId;
  const { data: token } = await Notifications.getExpoPushTokenAsync(
    projectId ? { projectId } : undefined,
  );
  const res = await fetch(`${API_URL}/push-devices`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, platform: Platform.OS, timezone: deviceTimezone() }),
  });
  if (!res.ok) throw new Error(`push-devices ${res.status}`);
  await AsyncStorage.setItem(KEY_TOKEN, token);
}

async function unregisterDevice(): Promise<void> {
  const token = await AsyncStorage.getItem(KEY_TOKEN);
  if (!token) return;
  const res = await fetch(`${API_URL}/push-devices`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ token }),
  });
  if (res.ok) await AsyncStorage.removeItem(KEY_TOKEN);
}

/** Lần bấm thông báo gần nhất (ms) — lời mời "đọc tiếp" nhường chỗ cho trang truyện vừa mở. */
let lastTapAt = 0;

export function openedFromNotificationRecently(withinMs = 10_000): boolean {
  return Date.now() - lastTapAt < withinMs;
}

function openFromResponse(response: Notifications.NotificationResponse | null) {
  const storyId = response?.notification.request.content.data?.storyId;
  if (typeof storyId !== 'number' && typeof storyId !== 'string') return;
  if (!/^\d+$/.test(String(storyId))) return;
  lastTapAt = Date.now();
  router.push(`/story/${storyId}`);
}

export function NotificationsProvider({ children }: { children: React.ReactNode }) {
  const [prefs, setPrefs] = useState<NotifyPrefs>(DEFAULT_PREFS);
  const [permission, setPermission] = useState<NotifyPermission>('unknown');
  const [ready, setReady] = useState(!SUPPORTED);
  const prefsRef = useRef(prefs);
  const permissionRef = useRef<NotifyPermission>('unknown');
  const readyRef = useRef(ready);
  /** enable() vừa mở Cài đặt iOS (quyền đã bị từ chối): quay lại mà quyền đã có thì bật luôn. */
  const awaitingSettingsRef = useRef(false);

  const savePrefs = useCallback((next: NotifyPrefs) => {
    prefsRef.current = next;
    setPrefs(next);
    AsyncStorage.setItem(KEY_PREFS, JSON.stringify(next)).catch(() => {});
  }, []);

  // Đọc tuỳ chọn + quyền hiện tại; đã bật và còn quyền thì đăng ký lại (token có thể đổi,
  // và múi giờ đi theo người dùng khi họ đi xa).
  const refresh = useCallback(async () => {
    const perm = toPermission(await Notifications.getPermissionsAsync());
    permissionRef.current = perm;
    setPermission(perm);
    if (perm === 'granted' && awaitingSettingsRef.current && !prefsRef.current.enabled) {
      // Người dùng bấm "Open Settings" trong lời mời rồi bật quyền ở đó: coi như đã bật
      // trong app luôn, khỏi bắt họ vào Library bật thêm lần nữa.
      savePrefs({ ...prefsRef.current, enabled: true });
    }
    awaitingSettingsRef.current = false;
    if (perm === 'granted' && prefsRef.current.enabled) {
      registerDevice().catch(() => {});
    }
  }, [savePrefs]);

  useEffect(() => {
    if (!SUPPORTED) return;
    let alive = true;
    AsyncStorage.getItem(KEY_PREFS)
      .catch(() => null)
      .then(async (raw) => {
        const stored = parsePrefs(raw);
        if (!alive) return;
        prefsRef.current = stored;
        setPrefs(stored);
        await refresh().catch(() => {});
        if (!alive) return;
        readyRef.current = true;
        setReady(true);
      });
    // Quay lại từ Cài đặt iOS: quyền có thể vừa đổi.
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') refresh().catch(() => {});
    });
    return () => {
      alive = false;
      sub.remove();
    };
  }, [refresh]);

  // Bấm thông báo: lúc app đang chạy (listener) và lúc app được mở lạnh từ thông báo.
  useEffect(() => {
    if (!SUPPORTED) return;
    openFromResponse(Notifications.getLastNotificationResponse());
    Notifications.clearLastNotificationResponse();
    const sub = Notifications.addNotificationResponseReceivedListener((response) => {
      openFromResponse(response);
      Notifications.clearLastNotificationResponse();
    });
    return () => sub.remove();
  }, []);

  const enable = useCallback(async () => {
    if (!SUPPORTED) return false;
    if (Platform.OS === 'android') {
      // Android 13+ chỉ hiện hộp xin quyền khi đã có kênh thông báo.
      await Notifications.setNotificationChannelAsync('default', {
        name: 'New stories',
        importance: Notifications.AndroidImportance.DEFAULT,
      }).catch(() => {});
    }
    let perm = toPermission(await Notifications.getPermissionsAsync());
    const askedNow = perm === 'undetermined';
    if (askedNow) {
      perm = toPermission(
        await Notifications.requestPermissionsAsync({
          ios: { allowAlert: true, allowSound: true, allowBadge: false },
        }),
      );
    }
    permissionRef.current = perm;
    setPermission(perm);
    if (perm !== 'granted') {
      // iOS chỉ hỏi một lần; đã từ chối từ TRƯỚC thì chỉ bật lại được trong Cài đặt. Vừa bấm
      // "Don't Allow" trong hộp của iOS thì thôi — đẩy người ta sang Cài đặt ngay sau khi họ
      // vừa từ chối là phớt lờ lựa chọn của họ.
      if (perm === 'denied' && !askedNow) {
        awaitingSettingsRef.current = true;
        Linking.openSettings().catch(() => {
          awaitingSettingsRef.current = false;
        });
      }
      return false;
    }
    savePrefs({ ...prefsRef.current, enabled: true });
    registerDevice().catch(() => {});
    return true;
  }, [savePrefs]);

  const disable = useCallback(async () => {
    savePrefs({ ...prefsRef.current, enabled: false });
    await unregisterDevice().catch(() => {});
  }, [savePrefs]);

  const askDue = useCallback(() => {
    if (!SUPPORTED || !readyRef.current) return false;
    const p = prefsRef.current;
    // 'granted' mà chưa bật = người dùng đã tự tắt trong Library: tôn trọng, không mời lại.
    const perm = permissionRef.current;
    if (p.enabled || (perm !== 'undetermined' && perm !== 'denied')) return false;
    return p.asks < MAX_ASKS && Date.now() >= p.nextAskAt;
  }, []);

  const markAsked = useCallback(() => {
    const p = prefsRef.current;
    savePrefs({ ...p, asks: p.asks + 1, nextAskAt: Date.now() + ASK_AGAIN_MS });
  }, [savePrefs]);

  const value = useMemo(
    () => ({
      supported: SUPPORTED,
      ready,
      enabled: prefs.enabled,
      permission,
      enable,
      disable,
      askDue,
      markAsked,
    }),
    [ready, prefs, permission, enable, disable, askDue, markAsked],
  );

  return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}

export function useNotifications(): NotificationsContextValue {
  const ctx = useContext(NotificationsContext);
  if (!ctx) throw new Error('useNotifications must be used within a NotificationsProvider');
  return ctx;
}
