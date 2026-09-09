/**
 * DESIGN SYSTEM — app đọc truyện "Stories".
 *
 * ⚠️ LIGHT THEME (giao diện SÁNG) — nền trắng/kem ấm, accent CAM NHẸ.
 * Trước đây app dùng tông tối tím; toàn bộ GIÁ TRỊ đã đổi sang nền sáng,
 * TÊN TOKEN giữ nguyên để màn hình cũ không vỡ.
 *
 * Quy tắc sống còn của nền sáng:
 * - Chữ mặc định là `Palette.text` (TỐI). Không còn chữ trắng trên nền trắng.
 * - Phân tầng bằng BÓNG MỀM (`Shadow.*`) + viền mảnh (`Palette.border`),
 *   KHÔNG bằng "surface sáng hơn" như dark theme.
 * - Gradient hoà vào nền phải fade về `Palette.bg` (#FDF8F4), không fade về đen.
 * - Chỉ badge/ribbon ĐÈ LÊN ẢNH BÌA (ảnh artwork tối) mới dùng nền tối mờ
 *   `Palette.overlay` + chữ trắng.
 * - Chữ/icon màu nhấn trên nền sáng: dùng `Palette.accentDeep` (đậm, đọc được).
 *   `Palette.accentSoft` chỉ để TÔ (gradient, halo), KHÔNG dùng làm màu chữ.
 *
 * Cần một sắc độ mờ của màu có sẵn? Dùng `withAlpha(Palette.accent, 0.35)`,
 * ĐỪNG viết chuỗi 'rgba(...)' rải rác trong màn hình.
 */

import { Platform } from 'react-native';
import type { TextStyle, ViewStyle } from 'react-native';

/* ------------------------------------------------------------------ */
/* HÀM PHA MÀU                                                         */
/* ------------------------------------------------------------------ */

/**
 * Pha độ mờ cho một màu hex (#RGB hoặc #RRGGBB) -> chuỗi 'rgba(r,g,b,a)'.
 * Nhờ vậy mọi sắc độ đều bắt nguồn từ token trong Palette.
 */
export function withAlpha(hex: string, alpha: number): string {
  let value = hex.replace('#', '');
  if (value.length === 3) {
    value = value
      .split('')
      .map((c) => c + c)
      .join('');
  }
  const int = parseInt(value, 16);
  const r = (int >> 16) & 255;
  const g = (int >> 8) & 255;
  const b = int & 255;
  return `rgba(${r},${g},${b},${alpha})`;
}

/* ------------------------------------------------------------------ */
/* PALETTE — LIGHT                                                     */
/* ------------------------------------------------------------------ */

/* Màu gốc — chỉ dùng trong file này để dẫn xuất các sắc độ bên dưới. */
const BG_HEX = '#FDF8F4';
const BG_DEEP_HEX = '#FFFFFF';
const SURFACE_HEX = '#FFFFFF';
/** mực (chữ tối) — gốc của border, overlay, bóng đổ */
const INK_HEX = '#1A1614';
const ACCENT_HEX = '#FF9052';
const ACCENT_DEEP_HEX = '#F2703A';
const COIN_HEX = '#E8961B';
const FREE_HEX = '#17A97C';
const DANGER_HEX = '#E0574B';
const WHITE_HEX = '#FFFFFF';
const BLACK_HEX = '#000000';
/** màu bóng đổ: mực hơi ấm, dịu hơn đen thuần trên nền kem */
const SHADOW_HEX = '#2C2018';

export const Palette = {
  // --- Nền ---
  /** nền chính của app — trắng ngả ấm */
  bg: BG_HEX,
  /** nền trắng tinh: tab bar, header (tách khỏi bg bằng viền/bóng) */
  bgDeep: BG_DEEP_HEX,
  /** bề mặt card — trắng, nổi lên nhờ Shadow.card */
  surface: SURFACE_HEX,
  /** bề mặt phụ: chip nền mờ, skeleton, ô nhập */
  surfaceAlt: '#F6EFE8',
  /** bề mặt cao nhất: sheet, popover — vẫn trắng, tách bằng Shadow.sheet */
  surfaceHigh: '#FFFFFF',

  // --- Đường viền ---
  border: withAlpha(INK_HEX, 0.08),
  borderStrong: withAlpha(INK_HEX, 0.16),

  // --- Nhấn: cam nhẹ ---
  accent: ACCENT_HEX,
  /** cam nhạt — CHỈ để tô (gradient/halo), KHÔNG làm màu chữ trên nền sáng */
  accentSoft: '#FFB185',
  /** cam đậm — màu chữ/icon nhấn & nền nút đặc trên nền sáng */
  accentDeep: ACCENT_DEEP_HEX,
  /** nền mờ cam cho chip/badge trên nền sáng */
  accentDim: withAlpha(ACCENT_HEX, 0.14),
  /** viền cam cho chip/ô nhập đang chọn */
  accentBorder: withAlpha(ACCENT_HEX, 0.35),

  // --- Xu (vàng cam) ---
  coin: COIN_HEX,
  /** vàng nhạt — để tô, không làm màu chữ */
  coinSoft: '#F5C173',
  /** vàng đậm — màu chữ/icon xu trên nền sáng */
  coinDeep: '#B9740C',
  coinDim: withAlpha(COIN_HEX, 0.14),
  coinBorder: withAlpha(COIN_HEX, 0.35),

  // --- Miễn phí (xanh lá) ---
  free: FREE_HEX,
  /** xanh nhạt — để tô, không làm màu chữ */
  freeSoft: '#7FD8BB',
  /** xanh đậm — màu chữ/icon "miễn phí" trên nền sáng */
  freeDeep: '#0E8862',
  freeDim: withAlpha(FREE_HEX, 0.13),
  freeBorder: withAlpha(FREE_HEX, 0.32),

  // --- Trạng thái khác ---
  danger: DANGER_HEX,
  /** đỏ đậm — màu chữ/icon lỗi trên nền sáng */
  dangerDeep: '#C13A2F',
  dangerDim: withAlpha(DANGER_HEX, 0.12),

  // --- Chữ ---
  text: INK_HEX,
  muted: '#6E635C',
  faint: '#9C918A',
  /** chữ TỐI đặt trên nền màu sáng đặc (pill vàng/xanh) */
  onLight: INK_HEX,
  /** chữ/icon TRẮNG trên nền cam đặc (dùng với accentDeep cho đủ tương phản) */
  onAccent: WHITE_HEX,
  /** trắng thuần — cũng là gốc của các lớp phủ sáng */
  white: WHITE_HEX,
  /** đen thuần — hạn chế dùng; bóng đổ đã có Shadow.* */
  black: BLACK_HEX,

  // --- Lớp phủ (chỉ dùng KHI ĐÈ LÊN ẢNH BÌA TỐI) ---
  /** nền badge nổi trên ảnh bìa */
  overlay: withAlpha(INK_HEX, 0.55),
  overlayStrong: withAlpha(INK_HEX, 0.72),
  /** nền skeleton — xám ấm nhạt */
  skeleton: '#EDE4DB',
} as const;

/* ------------------------------------------------------------------ */
/* NỀN TRANG ĐỌC (reader)                                              */
/* ------------------------------------------------------------------ */

/**
 * 4 bảng màu của VÙNG NỘI DUNG trong màn đọc (Trắng / Kem / Xanh / Đêm).
 * Đây là bảng màu RIÊNG của trang đọc, do người dùng tự chọn — KHÔNG phải
 * giao diện chung của app (phần khung màn đọc vẫn dùng `Palette` sáng).
 * Đặt ở đây để mọi mã màu của app đều nằm trong một file duy nhất;
 * `src/store/reader-prefs.tsx` chỉ gắn thêm `key` + nhãn tiếng Việt.
 *
 * `dark: true` = nền TỐI -> màu nhấn phải dùng sắc độ NHẠT mới đọc được.
 */

/** mực của từng nền đọc — gốc để pha viền mảnh cùng tông */
const READER_INK = {
  white: INK_HEX,
  sepia: '#3A2C18',
  mint: '#16302A',
  night: '#EFE7DF',
} as const;

export const ReaderSurfaces = {
  white: {
    bg: WHITE_HEX,
    surface: '#F4EFEA',
    text: READER_INK.white,
    muted: '#6E635C',
    faint: '#9C918A',
    border: withAlpha(READER_INK.white, 0.1),
    dark: false,
  },
  sepia: {
    bg: '#FBF3E4',
    surface: '#F2E6CD',
    text: READER_INK.sepia,
    muted: '#7A6746',
    faint: '#A28E6C',
    border: withAlpha(READER_INK.sepia, 0.14),
    dark: false,
  },
  mint: {
    bg: '#E8F3EC',
    surface: '#D7E9DE',
    text: READER_INK.mint,
    muted: '#4C6A5E',
    faint: '#7B968A',
    border: withAlpha(READER_INK.mint, 0.14),
    dark: false,
  },
  night: {
    bg: INK_HEX,
    surface: '#272019',
    text: READER_INK.night,
    muted: '#A79C93',
    faint: '#7D736C',
    border: withAlpha(READER_INK.night, 0.16),
    dark: true,
  },
} as const;

export type ReaderSurfaceKey = keyof typeof ReaderSurfaces;

/* ------------------------------------------------------------------ */
/* GRADIENTS (dùng với expo-linear-gradient)                           */
/* ------------------------------------------------------------------ */
/* SDK 57: `colors` là `readonly [ColorValue, ColorValue, ...]` (>= 2 màu)
   nên tất cả đều khai báo `as const`. */

export const Gradients = {
  /**
   * Scrim ở ĐÁY BÌA NHỎ — làm tối mép dưới ẢNH để badge số chương đọc được.
   * Đây là ngoại lệ hợp lệ của nền tối: nó nằm trên ảnh, không trên nền app.
   */
  scrimCover: [withAlpha(INK_HEX, 0), withAlpha(INK_HEX, 0.1), withAlpha(INK_HEX, 0.62)] as const,
  /** scrim đáy ảnh hero: trong suốt -> hoà hẳn vào NỀN SÁNG của app */
  heroScrim: [
    withAlpha(BG_HEX, 0),
    withAlpha(BG_HEX, 0.5),
    withAlpha(BG_HEX, 0.88),
    BG_HEX,
  ] as const,
  /** hoà mép trên của ảnh hero vào nền sáng */
  heroTopFade: [BG_HEX, withAlpha(BG_HEX, 0.4), withAlpha(BG_HEX, 0)] as const,
  /** phủ SÁNG lên ảnh nền mờ ở màn chi tiết truyện (làm ảnh nhạt đi, không tối đi) */
  backdropScrim: [
    withAlpha(BG_HEX, 0.55),
    withAlpha(BG_HEX, 0.8),
    withAlpha(BG_HEX, 0.96),
    BG_HEX,
  ] as const,
  /** vị trí các điểm dừng của `backdropScrim` */
  backdropScrimLocations: [0, 0.42, 0.78, 1] as const,
  /** nền hero / thẻ nổi bật — kem ấm nhạt dần về trắng */
  hero: ['#FFE9D8', '#FFF5EC', SURFACE_HEX] as const,
  /** nút / pill nhấn — cam sáng -> cam đậm (chữ trắng đọc tốt) */
  accent: [ACCENT_HEX, ACCENT_DEEP_HEX] as const,
} as const;

/**
 * Gradient cho bìa dự phòng (khi ảnh lỗi / chưa có).
 * Tông ấm: cam / hồng / đào — đủ đậm để chữ cái trắng ở giữa vẫn đọc được.
 * Chọn bằng `coverGradient(seed)` để mỗi truyện có một tông riêng nhưng ổn định.
 */
export const CoverGradients = [
  ['#F79B6B', '#E0603A'] as const,
  ['#F8B074', '#DE7A32'] as const,
  ['#F58C7E', '#D94F52'] as const,
  ['#EE9A78', '#C9603F'] as const,
  ['#F5A879', '#D97544'] as const,
  ['#E88C74', '#B95851'] as const,
  ['#F79A5E', '#DA6532'] as const,
  ['#E894A0', '#C25264'] as const,
] as const;

export type CoverGradient = (typeof CoverGradients)[number];

/** Hash ổn định (djb2 rút gọn) — dùng chọn gradient theo tên/id truyện. */
export function hashSeed(seed: string | number): number {
  const str = String(seed);
  let h = 5381;
  for (let i = 0; i < str.length; i += 1) {
    h = ((h << 5) + h + str.charCodeAt(i)) >>> 0;
  }
  return h;
}

/** Trả về cặp màu gradient ổn định theo seed (tên hoặc id truyện). */
export function coverGradient(seed: string | number): CoverGradient {
  return CoverGradients[hashSeed(seed) % CoverGradients.length];
}

/* ------------------------------------------------------------------ */
/* SPACING — thang 4 / 8 / 12 / 16 / 20 / 24 / 32                      */
/* ------------------------------------------------------------------ */

export const Spacing = {
  /** 2 */
  xxs: 2,
  /** 4 */
  xs: 4,
  /** 8 */
  sm: 8,
  /** 12 */
  md: 12,
  /** 16 */
  lg: 16,
  /** 20 */
  xl: 20,
  /** 24 */
  xxl: 24,
  /** 32 */
  xxxl: 32,
  /** 48 */
  huge: 48,

  /** lề trái/phải mặc định của màn hình = 16 */
  screen: 16,
  /** khoảng cách giữa 2 card trong lưới = 12 */
  gutter: 12,
} as const;

/* ------------------------------------------------------------------ */
/* RADIUS                                                              */
/* ------------------------------------------------------------------ */

export const Radius = {
  /** 6 */
  xs: 6,
  /** 8 */
  sm: 8,
  /** 12 */
  md: 12,
  /** 16 */
  lg: 16,
  /** 22 */
  xl: 22,
  /** 28 */
  xxl: 28,

  /** bìa truyện = 14 */
  cover: 14,
  /** card / khối nội dung = 16 */
  card: 16,
  /** badge nhỏ trên ảnh = 8 */
  badge: 8,
  /** pill / chip = 999 */
  pill: 999,
} as const;

/* ------------------------------------------------------------------ */
/* TYPOGRAPHY                                                          */
/* ------------------------------------------------------------------ */

export const FontSize = {
  /** 26 — tiêu đề lớn đầu màn hình */
  display: 26,
  /** 20 — tiêu đề mục lớn */
  h1: 20,
  /** 17 — tiêu đề mục / tên truyện lớn */
  h2: 17,
  /** 15 — chữ thường */
  body: 15,
  /** 13 — chữ nhỏ (tên truyện trong card) */
  small: 13,
  /** 12 — caption / meta */
  caption: 12,
  /** 11 — badge, nhãn siêu nhỏ */
  tiny: 11,
  /** 9 — ribbon */
  micro: 9,
  /** 17 — nội dung chương đọc */
  reader: 17,
} as const;

export const FontWeight = {
  regular: '400',
  medium: '500',
  semibold: '600',
  bold: '700',
  black: '800',
} as const satisfies Record<string, NonNullable<TextStyle['fontWeight']>>;

export const LineHeight = {
  /** 32 */
  display: 32,
  /** 26 */
  h1: 26,
  /** 23 */
  h2: 23,
  /** 22 */
  body: 22,
  /** 18 */
  small: 18,
  /** 16 */
  caption: 16,
  /** 30 = 17 * 1.75 — nội dung chương */
  reader: 30,
} as const;

/** Style chữ dựng sẵn — spread vào StyleSheet.create để đồng bộ toàn app. */
export const Typography = {
  display: {
    fontSize: FontSize.display,
    fontWeight: FontWeight.black,
    lineHeight: LineHeight.display,
    color: Palette.text,
  },
  h1: {
    fontSize: FontSize.h1,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h1,
    color: Palette.text,
  },
  h2: {
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h2,
    color: Palette.text,
  },
  body: {
    fontSize: FontSize.body,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.body,
    color: Palette.text,
  },
  bodyMuted: {
    fontSize: FontSize.body,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.body,
    color: Palette.muted,
  },
  caption: {
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    color: Palette.muted,
  },
  reader: {
    fontSize: FontSize.reader,
    fontWeight: FontWeight.regular,
    lineHeight: LineHeight.reader,
    color: Palette.text,
  },
} as const satisfies Record<string, TextStyle>;

export const Fonts = Platform.select({
  ios: {
    sans: 'system-ui',
    serif: 'Georgia',
    rounded: 'ui-rounded',
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'system-ui',
    serif: 'Georgia, serif',
    rounded: 'system-ui',
    mono: 'monospace',
  },
}) as { sans: string; serif: string; rounded: string; mono: string };

/* ------------------------------------------------------------------ */
/* SHADOW / LAYOUT                                                     */
/* ------------------------------------------------------------------ */

/**
 * Bóng mềm nhiều cấp — CÁCH PHÂN TẦNG CHÍNH của giao diện sáng.
 * Nền sáng không thể "sáng hơn" để nổi lên, nên mọi lớp nổi đều cần Shadow.*.
 * Kèm theo nên có `borderWidth: StyleSheet.hairlineWidth` + `Palette.border`
 * để giữ hình khối trên Android (Android chỉ đọc `elevation`).
 */
export const Shadow = {
  /** viền bóng rất nhẹ: chip nổi, ô nhập, hàng danh sách */
  sm: {
    shadowColor: SHADOW_HEX,
    shadowOpacity: 0.05,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  /** card truyện / khối nội dung trắng trên nền kem */
  card: {
    shadowColor: SHADOW_HEX,
    shadowOpacity: 0.08,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  /** nút nổi, thanh dính (sticky bar), popover */
  float: {
    shadowColor: SHADOW_HEX,
    shadowOpacity: 0.12,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 8 },
    elevation: 8,
  },
  /** bottom sheet / modal — bóng hắt LÊN trên */
  sheet: {
    shadowColor: SHADOW_HEX,
    shadowOpacity: 0.16,
    shadowRadius: 28,
    shadowOffset: { width: 0, height: -4 },
    elevation: 16,
  },
} as const satisfies Record<string, ViewStyle>;

export const Layout = {
  /** tỉ lệ bìa truyện 3:4 */
  coverAspect: 3 / 4,
  /** bề rộng card trong hàng ngang */
  cardWidth: 116,
  /** bề rộng card lớn (hàng "Nổi bật") */
  cardWidthLarge: 140,
  /** chiều cao tối thiểu vùng chạm */
  hitSize: 44,
  /** độ mờ khi nhấn Pressable */
  pressedOpacity: 0.6,
} as const;

/* ------------------------------------------------------------------ */
/* Kiểu tiện dụng                                                      */
/* ------------------------------------------------------------------ */

export type PaletteColor = (typeof Palette)[keyof typeof Palette];
export type SpacingKey = keyof typeof Spacing;
export type RadiusKey = keyof typeof Radius;
export type TypographyKey = keyof typeof Typography;
export type ShadowKey = keyof typeof Shadow;
