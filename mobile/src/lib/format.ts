/**
 * Hàm định dạng chuỗi hiển thị (giao diện TIẾNG ANH).
 *
 * Lưu ý: API trả `status_label` bằng TIẾNG VIỆT ('Đang ra' / 'Hoàn thành').
 * Đó là NHÃN giao diện nên KHÔNG dùng trực tiếp — hãy map từ `status`
 * ('ongoing' | 'completed') qua `storyStatusLabel()` / `storyRibbonLabel()`.
 */

/**
 * Nhãn trạng thái truyện cho giao diện.
 *
 *   storyStatusLabel('ongoing')   -> 'Ongoing'
 *   storyStatusLabel('completed') -> 'Completed'
 *   storyStatusLabel('paused')    -> 'paused'   (giá trị lạ: trả về chính nó)
 *
 * Dùng THAY CHO `story.status_label` của API.
 */
export function storyStatusLabel(status: string): string {
  switch (status) {
    case 'ongoing':
      return 'Ongoing';
    case 'completed':
      return 'Completed';
    default:
      return status;
  }
}

/**
 * Nhãn ribbon trên bìa truyện — ngắn hơn nhãn trạng thái.
 *
 *   storyRibbonLabel('completed') -> 'FULL'
 *   storyRibbonLabel('ongoing')   -> 'Ongoing'
 *   storyRibbonLabel('')          -> 'Ongoing'  (mọi giá trị khác)
 */
export function storyRibbonLabel(status: string): string {
  return status === 'completed' ? 'FULL' : 'Ongoing';
}

/** Cắt (không làm tròn lên) còn 1 chữ số thập phân rồi bỏ đuôi '.0'. */
function short(value: number): string {
  const truncated = Math.floor(value * 10) / 10;
  return truncated.toFixed(1).replace(/\.0$/, '');
}

/**
 * Rút gọn lượt xem theo kiểu tiếng Anh (K / M / B).
 *
 *   formatViews(999)      -> '999'
 *   formatViews(1_200)    -> '1.2K'
 *   formatViews(481_530)  -> '481.5K'
 *   formatViews(3_400_000)-> '3.4M'
 *
 * Số dưới 1000 giữ nguyên. KHÔNG dùng 'N' / 'Tr' kiểu Việt nữa.
 */
export function formatViews(n: number): string {
  if (!Number.isFinite(n)) return '0';

  const sign = n < 0 ? '-' : '';
  const abs = Math.abs(n);

  if (abs >= 1_000_000_000) return `${sign}${short(abs / 1_000_000_000)}B`;
  if (abs >= 1_000_000) return `${sign}${short(abs / 1_000_000)}M`;
  if (abs >= 1_000) return `${sign}${short(abs / 1_000)}K`;
  return `${sign}${Math.floor(abs)}`;
}

/**
 * Ghép số với danh từ, tự chọn số ít / số nhiều.
 *
 *   plural(1, 'chapter', 'chapters') -> '1 chapter'
 *   plural(5, 'chapter', 'chapters') -> '5 chapters'
 *   plural(0, 'chapter', 'chapters') -> '0 chapters'
 *
 * CHÚ Ý: trả về CẢ SỐ lẫn danh từ (không phải chỉ danh từ).
 */
export function plural(n: number, one: string, many: string): string {
  return `${n} ${Math.abs(n) === 1 ? one : many}`;
}

/**
 * Số xu kèm danh từ đúng số nhiều.
 *
 *   formatCoins(1)  -> '1 coin'
 *   formatCoins(10) -> '10 coins'
 *   formatCoins(0)  -> '0 coins'
 */
export function formatCoins(n: number): string {
  return plural(n, 'coin', 'coins');
}
