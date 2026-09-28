import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useContext, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';

import { type AppConfig, getConfig } from '@/lib/api';

/**
 * Cấu hình từ trang admin (GET /api/config).
 *
 * Thứ tự lấy: mặc định trong app -> bản lưu lần trước -> bản mới từ server. Server
 * lỗi hay mất mạng thì app vẫn chạy với bản gần nhất nó biết, không bao giờ chặn
 * giao diện để chờ.
 */

const KEY_CONFIG = 'stories:config';
/** Quay lại app sau khoảng này mới hỏi lại server. */
const REFRESH_MS = 10 * 60 * 1000;
const DAILY_LIMIT_MAX = 20;

/**
 * Mặc định khi chưa từng nói chuyện được với server. Cố ý RỘNG TAY (vẫn cấp bù):
 * máy mới cài mà mất mạng tới API thì cũng không đọc được chương nào, nhưng máy
 * vào được API mà không tới được Google (chặn quảng cáo, mạng công ty) thì không
 * được phép kẹt — đó là lỗi Guideline 2.1(a) của bản 1.0(3).
 */
export const DEFAULT_CONFIG: AppConfig = {
  ad_fallback: { enabled: true, daily_limit: 2 },
};

/** Nhận dữ liệu lạ (JSON từ server hay từ bộ nhớ); sai hình dạng thì trả null. */
function sanitize(raw: unknown): AppConfig | null {
  if (!raw || typeof raw !== 'object') return null;
  const fb = (raw as { ad_fallback?: unknown }).ad_fallback;
  if (!fb || typeof fb !== 'object') return null;
  const { enabled, daily_limit } = fb as { enabled?: unknown; daily_limit?: unknown };
  if (typeof enabled !== 'boolean') return null;
  if (typeof daily_limit !== 'number' || !Number.isFinite(daily_limit)) return null;
  return {
    ad_fallback: {
      enabled,
      daily_limit: Math.max(0, Math.min(DAILY_LIMIT_MAX, Math.floor(daily_limit))),
    },
  };
}

const ConfigContext = createContext<AppConfig>(DEFAULT_CONFIG);

export function ConfigProvider({ children }: { children: React.ReactNode }) {
  const [config, setConfig] = useState<AppConfig>(DEFAULT_CONFIG);
  const lastFetch = useRef(0);

  useEffect(() => {
    let alive = true;

    const refresh = async () => {
      lastFetch.current = Date.now();
      try {
        const fresh = sanitize(await getConfig());
        if (!fresh) return;
        if (alive) setConfig(fresh);
        AsyncStorage.setItem(KEY_CONFIG, JSON.stringify(fresh)).catch(() => {});
      } catch {
        // Mất mạng / server lỗi: giữ nguyên bản đang có.
      }
    };

    (async () => {
      try {
        const raw = await AsyncStorage.getItem(KEY_CONFIG);
        const cached = raw ? sanitize(JSON.parse(raw)) : null;
        // Chỉ dùng bản lưu nếu server chưa kịp trả bản mới trong lúc đang đọc.
        if (alive && cached && lastFetch.current === 0) setConfig(cached);
      } catch {
        // Bản lưu hỏng: bỏ qua, dùng mặc định.
      }
      await refresh();
    })();

    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active' && Date.now() - lastFetch.current > REFRESH_MS) {
        refresh();
      }
    });

    return () => {
      alive = false;
      sub.remove();
    };
  }, []);

  return <ConfigContext.Provider value={config}>{children}</ConfigContext.Provider>;
}

export function useAppConfig(): AppConfig {
  return useContext(ConfigContext);
}
