import { useMemo } from 'react';
import { useWindowDimensions } from 'react-native';

import { Spacing } from '@/constants/theme';

/*
 * Bố cục theo BỀ NGANG CỬA SỔ, không theo loại máy. Trên iPad cửa sổ app có thể là toàn
 * màn hình (744–1376pt), xoay ngang, hoặc chỉ một phần màn hình khi chạy Split View /
 * Stage Manager — lúc đó hẹp như iPhone. Nên mọi màn đọc `useResponsive()` chứ không hỏi
 * "có phải iPad không", và đổi bố cục ngay khi người dùng xoay máy hay kéo cửa sổ.
 */

/** Từ bề ngang này trở lên là màn rộng. iPhone rộng nhất (Pro Max, dọc) là 440pt. */
export const WIDE_MIN_WIDTH = 600;
/** Cột nội dung (chữ, nút, danh sách chương) trên màn rộng không rộng quá mức này: dòng
 *  chữ dài hơn thì mắt khó dò về đầu dòng sau, nút trải dài cả màn trông như bản phóng to. */
export const CONTENT_MAX_WIDTH = 720;
/** Bottom sheet / hộp thoại trên màn rộng: nổi giữa màn, rộng tối đa chừng này. */
export const SHEET_MAX_WIDTH = 520;

/** Khe giữa các ô lưới trên màn rộng. Lề trái/phải giữ Spacing.screen như iPhone để thẳng
 *  hàng với header, tab và tiêu đề mục (các phần đó dùng chung hằng số này). */
const WIDE_GAP = 16;
/** Ô lưới truyện hẹp nhất cho phép trên màn rộng; iPhone giữ 2 cột như cũ. */
const GRID_CELL_MIN = 150;
const GRID_MAX_COLUMNS = 7;
/** Card trong hàng cuộn ngang: iPhone 140 (Layout.cardWidthLarge). Màn rộng: chia đều bề
 *  ngang thành các cột ≥ 160pt để hàng lấp kín màn, không hở một khoảng bên phải. */
const ROW_CARD_PHONE = 140;
const ROW_CARD_WIDE_MIN = 160;

export interface ResponsiveLayout {
  width: number;
  height: number;
  /** cửa sổ ≥ WIDE_MIN_WIDTH */
  wide: boolean;
  /** lề trái/phải của nội dung tràn màn (lưới, hàng cuộn ngang) */
  gutter: number;
  /** khe giữa các ô lưới */
  gap: number;
  /** số cột của lưới truyện */
  columns: number;
  /** bề rộng 1 ô lưới truyện */
  cellWidth: number;
  /** bề rộng card trong hàng cuộn ngang */
  rowCardWidth: number;
  /** lề trái/phải để cột nội dung rộng tối đa CONTENT_MAX_WIDTH nằm giữa màn */
  contentInset: number;
  /** left/right cho bottom sheet `position: absolute` để rộng tối đa SHEET_MAX_WIDTH, nằm giữa */
  sheetInset: number;
}

/** Số cột và bề rộng ô cho một bề ngang bất kỳ (dùng cả cho lưới thể loại với ô khác cỡ). */
export function gridFor(width: number, cellMin = GRID_CELL_MIN, maxColumns = GRID_MAX_COLUMNS) {
  const wide = width >= WIDE_MIN_WIDTH;
  const gutter = Spacing.screen;
  const gap = wide ? WIDE_GAP : Spacing.gutter;
  const inner = width - gutter * 2;
  const columns = wide
    ? Math.max(3, Math.min(maxColumns, Math.floor((inner + gap) / (cellMin + gap))))
    : 2;
  const cellWidth = Math.floor((inner - gap * (columns - 1)) / columns);
  return { wide, gutter, gap, columns, cellWidth };
}

export function useResponsive(): ResponsiveLayout {
  const { width, height } = useWindowDimensions();
  return useMemo(() => {
    const grid = gridFor(width);
    return {
      width,
      height,
      ...grid,
      rowCardWidth: grid.wide ? gridFor(width, ROW_CARD_WIDE_MIN).cellWidth : ROW_CARD_PHONE,
      contentInset: Math.max(Spacing.screen, Math.round((width - CONTENT_MAX_WIDTH) / 2)),
      sheetInset: Math.max(0, Math.round((width - SHEET_MAX_WIDTH) / 2)),
    };
  }, [width, height]);
}
