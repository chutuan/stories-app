import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Brightness from 'expo-brightness';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Platform } from 'react-native';

import { ReaderSurfaces, type ReaderSurfaceKey } from '@/constants/theme';

/**
 * TÙY CHỌN ĐỌC — cỡ chữ, giãn dòng, nền đọc, độ sáng màn hình.
 *
 * Chỉ dùng trong màn đọc: Provider được bọc NGAY TRONG src/app/reader/...,
 * KHÔNG bọc ở src/app/_layout.tsx (tránh đụng vào layout gốc).
 *
 * Mọi giá trị lưu xuống AsyncStorage theo từng khoá riêng để việc ghi
 * không bao giờ ghi đè nhầm trường khác.
 *
 * Độ sáng dùng expo-brightness (SDK 57):
 * - `Brightness.setBrightnessAsync(0..1)` đổi độ sáng của MÀN HÌNH APP, không
 *   cần xin quyền (khác `setSystemBrightnessAsync` — Android + quyền
 *   SYSTEM_BRIGHTNESS, ta KHÔNG dùng).
 * - Trên web module không hỗ trợ -> mọi lời gọi đều bọc try/catch và bỏ qua
 *   lặng lẽ, KHÔNG được làm vỡ màn đọc.
 */

/* ------------------------------------------------------------------ */
/* GIỚI HẠN & MẶC ĐỊNH                                                 */
/* ------------------------------------------------------------------ */

/** cỡ chữ nhỏ nhất */
export const FONT_SIZE_MIN = 14;
/** cỡ chữ lớn nhất */
export const FONT_SIZE_MAX = 26;
/** mỗi lần bấm Aa- / Aa+ đổi 1pt */
export const FONT_SIZE_STEP = 1;
export const FONT_SIZE_DEFAULT = 17;

/** hệ số giãn dòng nhỏ nhất (dòng sát nhau) */
export const LINE_HEIGHT_MIN = 1.4;
/** hệ số giãn dòng lớn nhất (dòng thưa) */
export const LINE_HEIGHT_MAX = 2.2;
/** 1.4 → 2.2 chia đúng 6 nấc, và 1.75 nằm trên lưới */
export const LINE_HEIGHT_STEP = 0.15;
export const LINE_HEIGHT_DEFAULT = 1.75;

export const BRIGHTNESS_MIN = 0;
export const BRIGHTNESS_MAX = 1;
export const BRIGHTNESS_DEFAULT = 1;

/* ------------------------------------------------------------------ */
/* NỀN ĐỌC                                                             */
/* ------------------------------------------------------------------ */

export type ReaderThemeKey = ReaderSurfaceKey;

export interface ReaderTheme {
  key: ReaderThemeKey;
  /** nhãn TIẾNG VIỆT hiện trong bảng cài đặt */
  label: string;
  /** nền trang đọc */
  bg: string;
  /** khối nổi nhẹ trên trang (pill đầu chương, vạch kết chương) */
  surface: string;
  /** màu chữ nội dung */
  text: string;
  /** chữ phụ */
  muted: string;
  /** chữ mờ nhất */
  faint: string;
  /** viền mảnh hợp với nền */
  border: string;
  /** true = nền TỐI -> màu nhấn phải dùng sắc độ NHẠT mới đọc được */
  dark: boolean;
}

/** Nhãn tiếng Việt của từng nền đọc; MÀU lấy từ `ReaderSurfaces` trong theme.ts. */
const READER_THEME_LABELS: Record<ReaderThemeKey, string> = {
  white: 'Trắng',
  sepia: 'Kem',
  mint: 'Xanh',
  night: 'Đêm',
};

export const READER_THEMES: Record<ReaderThemeKey, ReaderTheme> = {
  white: { key: 'white', label: READER_THEME_LABELS.white, ...ReaderSurfaces.white },
  sepia: { key: 'sepia', label: READER_THEME_LABELS.sepia, ...ReaderSurfaces.sepia },
  mint: { key: 'mint', label: READER_THEME_LABELS.mint, ...ReaderSurfaces.mint },
  night: { key: 'night', label: READER_THEME_LABELS.night, ...ReaderSurfaces.night },
};

/** Thứ tự hiện 4 ô chọn nền trong bảng cài đặt. */
export const READER_THEME_ORDER: readonly ReaderThemeKey[] = [
  'white',
  'sepia',
  'mint',
  'night',
] as const;

function isReaderThemeKey(value: string): value is ReaderThemeKey {
  return value === 'white' || value === 'sepia' || value === 'mint' || value === 'night';
}

/* ------------------------------------------------------------------ */
/* TIỆN ÍCH                                                            */
/* ------------------------------------------------------------------ */

const KEY_FONT_SIZE = 'stories:reader-font-size';
const KEY_LINE_HEIGHT = 'stories:reader-line-height';
const KEY_THEME = 'stories:reader-theme';
const KEY_BRIGHTNESS = 'stories:reader-brightness';

function clamp(value: number, min: number, max: number): number {
  if (Number.isNaN(value)) return min;
  return Math.min(max, Math.max(min, value));
}

/** Làm tròn 2 chữ số để 1.75 + 0.15 không thành 1.9000000000000001. */
function round2(value: number): number {
  return Math.round(value * 100) / 100;
}

export function clampFontSize(value: number): number {
  return Math.round(clamp(value, FONT_SIZE_MIN, FONT_SIZE_MAX));
}

export function clampLineHeight(value: number): number {
  return round2(clamp(value, LINE_HEIGHT_MIN, LINE_HEIGHT_MAX));
}

export function clampBrightness(value: number): number {
  return round2(clamp(value, BRIGHTNESS_MIN, BRIGHTNESS_MAX));
}

/** expo-brightness chỉ chạy trên iOS/Android; web luôn bỏ qua. */
const brightnessSupported = Platform.OS === 'ios' || Platform.OS === 'android';

/** Đổi độ sáng màn hình app; mọi lỗi đều nuốt để không làm vỡ màn đọc. */
async function applyBrightness(value: number): Promise<void> {
  if (!brightnessSupported) return;
  try {
    await Brightness.setBrightnessAsync(value);
  } catch {
    // thiết bị/hệ điều hành từ chối -> giữ nguyên độ sáng hệ thống
  }
}

/* ------------------------------------------------------------------ */
/* CONTEXT                                                             */
/* ------------------------------------------------------------------ */

export interface ReaderPrefsValue {
  /** cỡ chữ nội dung, 14–26 */
  fontSize: number;
  /** hệ số giãn dòng, 1.4–2.2 (lineHeight thật = fontSize * hệ số) */
  lineHeight: number;
  /** khoá nền đọc đang chọn */
  theme: ReaderThemeKey;
  /** bảng màu của nền đọc đang chọn — dùng trực tiếp cho style */
  colors: ReaderTheme;
  /** độ sáng màn hình 0–1 */
  brightness: number;
  /** true = độ sáng có thể chỉnh trên nền tảng này */
  brightnessSupported: boolean;
  /** đã đọc xong AsyncStorage chưa (tránh nhảy cỡ chữ khi mở màn) */
  ready: boolean;

  setFontSize: (value: number) => void;
  /** cộng/trừ cỡ chữ theo bước, tự kẹp trong khoảng cho phép */
  stepFontSize: (direction: 1 | -1) => void;
  setLineHeight: (value: number) => void;
  stepLineHeight: (direction: 1 | -1) => void;
  setTheme: (key: ReaderThemeKey) => void;
  /**
   * Đang KÉO thanh trượt: đổi độ sáng màn hình ngay lập tức nhưng KHÔNG đổi
   * state — nếu đổi state, prop `value` của Slider sẽ nhảy lại và giật tay kéo.
   */
  previewBrightness: (value: number) => void;
  /** THẢ tay: chốt độ sáng vào state + ghi xuống AsyncStorage */
  commitBrightness: (value: number) => void;
  /** đưa mọi tùy chọn về mặc định */
  reset: () => void;
}

const ReaderPrefsContext = createContext<ReaderPrefsValue | null>(null);

export function ReaderPrefsProvider({ children }: { children: React.ReactNode }) {
  const [fontSize, setFontSizeState] = useState(FONT_SIZE_DEFAULT);
  const [lineHeight, setLineHeightState] = useState(LINE_HEIGHT_DEFAULT);
  const [theme, setThemeState] = useState<ReaderThemeKey>('white');
  const [brightness, setBrightnessState] = useState(BRIGHTNESS_DEFAULT);
  const [ready, setReady] = useState(false);

  /** Độ sáng của máy TRƯỚC khi vào màn đọc — dùng để trả lại khi rời màn. */
  const originalBrightnessRef = useRef<number | null>(null);

  // --- Nạp tùy chọn đã lưu + áp độ sáng ---
  useEffect(() => {
    let alive = true;

    (async () => {
      // Ghi nhớ độ sáng gốc trước khi ta đụng vào nó.
      if (brightnessSupported) {
        try {
          originalBrightnessRef.current = await Brightness.getBrightnessAsync();
        } catch {
          originalBrightnessRef.current = null;
        }
      }

      let stored: (string | null)[] = [null, null, null, null];
      try {
        stored = await Promise.all([
          AsyncStorage.getItem(KEY_FONT_SIZE),
          AsyncStorage.getItem(KEY_LINE_HEIGHT),
          AsyncStorage.getItem(KEY_THEME),
          AsyncStorage.getItem(KEY_BRIGHTNESS),
        ]);
      } catch {
        // đọc lỗi -> dùng mặc định
      }
      if (!alive) return;

      const [rawFont, rawLine, rawTheme, rawBrightness] = stored;

      if (rawFont != null) {
        const parsed = Number(rawFont);
        if (Number.isFinite(parsed)) setFontSizeState(clampFontSize(parsed));
      }
      if (rawLine != null) {
        const parsed = Number(rawLine);
        if (Number.isFinite(parsed)) setLineHeightState(clampLineHeight(parsed));
      }
      if (rawTheme != null && isReaderThemeKey(rawTheme)) {
        setThemeState(rawTheme);
      }

      if (rawBrightness != null && Number.isFinite(Number(rawBrightness))) {
        // Người dùng đã từng chỉnh -> khôi phục đúng mức đó.
        const value = clampBrightness(Number(rawBrightness));
        setBrightnessState(value);
        void applyBrightness(value);
      } else if (originalBrightnessRef.current != null) {
        // Chưa từng chỉnh -> thanh trượt khởi điểm bằng độ sáng hiện tại của máy,
        // và KHÔNG đụng gì vào độ sáng.
        setBrightnessState(clampBrightness(originalBrightnessRef.current));
      }

      setReady(true);
    })();

    return () => {
      alive = false;
      // Rời màn đọc -> trả độ sáng về như trước.
      if (!brightnessSupported) return;
      if (Platform.OS === 'android') {
        Brightness.restoreSystemBrightnessAsync().catch(() => {});
        return;
      }
      const original = originalBrightnessRef.current;
      if (original != null) {
        Brightness.setBrightnessAsync(original).catch(() => {});
      }
    };
  }, []);

  // --- Setter ---

  const setFontSize = useCallback((value: number) => {
    const next = clampFontSize(value);
    setFontSizeState(next);
    AsyncStorage.setItem(KEY_FONT_SIZE, String(next)).catch(() => {});
  }, []);

  const stepFontSize = useCallback(
    (direction: 1 | -1) => {
      setFontSizeState((prev) => {
        const next = clampFontSize(prev + direction * FONT_SIZE_STEP);
        AsyncStorage.setItem(KEY_FONT_SIZE, String(next)).catch(() => {});
        return next;
      });
    },
    [],
  );

  const setLineHeight = useCallback((value: number) => {
    const next = clampLineHeight(value);
    setLineHeightState(next);
    AsyncStorage.setItem(KEY_LINE_HEIGHT, String(next)).catch(() => {});
  }, []);

  const stepLineHeight = useCallback(
    (direction: 1 | -1) => {
      setLineHeightState((prev) => {
        const next = clampLineHeight(prev + direction * LINE_HEIGHT_STEP);
        AsyncStorage.setItem(KEY_LINE_HEIGHT, String(next)).catch(() => {});
        return next;
      });
    },
    [],
  );

  const setTheme = useCallback((key: ReaderThemeKey) => {
    setThemeState(key);
    AsyncStorage.setItem(KEY_THEME, key).catch(() => {});
  }, []);

  const previewBrightness = useCallback((value: number) => {
    // CHỦ Ý không setState ở đây: Slider của @react-native-community là
    // uncontrolled, đổi prop `value` giữa chừng sẽ kéo nút trượt về và giật.
    void applyBrightness(clampBrightness(value));
  }, []);

  const commitBrightness = useCallback((value: number) => {
    const next = clampBrightness(value);
    setBrightnessState(next);
    void applyBrightness(next);
    AsyncStorage.setItem(KEY_BRIGHTNESS, String(next)).catch(() => {});
  }, []);

  const reset = useCallback(() => {
    setFontSizeState(FONT_SIZE_DEFAULT);
    setLineHeightState(LINE_HEIGHT_DEFAULT);
    setThemeState('white');
    AsyncStorage.multiSet([
      [KEY_FONT_SIZE, String(FONT_SIZE_DEFAULT)],
      [KEY_LINE_HEIGHT, String(LINE_HEIGHT_DEFAULT)],
      [KEY_THEME, 'white'],
    ]).catch(() => {});
    // Độ sáng: trả về mức của máy lúc vào màn, và quên lựa chọn đã lưu.
    setBrightnessState((prev) => {
      const original = originalBrightnessRef.current;
      const next = original != null ? clampBrightness(original) : prev;
      void applyBrightness(next);
      return next;
    });
    AsyncStorage.removeItem(KEY_BRIGHTNESS).catch(() => {});
  }, []);

  const value = useMemo<ReaderPrefsValue>(
    () => ({
      fontSize,
      lineHeight,
      theme,
      colors: READER_THEMES[theme],
      brightness,
      brightnessSupported,
      ready,
      setFontSize,
      stepFontSize,
      setLineHeight,
      stepLineHeight,
      setTheme,
      previewBrightness,
      commitBrightness,
      reset,
    }),
    [
      fontSize,
      lineHeight,
      theme,
      brightness,
      ready,
      setFontSize,
      stepFontSize,
      setLineHeight,
      stepLineHeight,
      setTheme,
      previewBrightness,
      commitBrightness,
      reset,
    ],
  );

  return <ReaderPrefsContext.Provider value={value}>{children}</ReaderPrefsContext.Provider>;
}

export function useReaderPrefs(): ReaderPrefsValue {
  const ctx = useContext(ReaderPrefsContext);
  if (!ctx) {
    throw new Error('useReaderPrefs phải nằm trong <ReaderPrefsProvider>');
  }
  return ctx;
}
