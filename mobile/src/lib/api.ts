import Constants from 'expo-constants';
import { Platform } from 'react-native';

import { getReaderId } from '@/lib/reader-id';

/**
 * Base URL của REST API.
 * - Trên trình giả lập Android dùng `http://10.0.2.2:8001/api` (10.0.2.2 = localhost của máy host).
 * - Trên thiết bị thật dùng IP LAN của máy chạy backend, vd `http://192.168.1.10:8001/api`.
 * Cấu hình qua biến môi trường EXPO_PUBLIC_API_URL (app.config.js đọc biến này
 * và đưa vào expo.extra.apiUrl). Xem .env.example và mục `env` trong eas.json.
 */
export const API_URL: string =
  (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl ??
  'http://localhost:8001/api';

// ---- Types (khớp SPEC §5) ----

export type Status = 'ongoing' | 'completed';

/**
 * Thể loại rút gọn — dạng NHÚNG bên trong truyện (`StoryCard.categories`).
 * Backend chỉ trả `{id, name, slug}` ở đây, KHÔNG có `cover_url`/`stories_count`.
 */
export interface CategoryRef {
  id: number;
  name: string;
  slug: string;
}

/**
 * Thể loại đầy đủ — chỉ có ở `GET /api/categories`.
 * `cover_url`: ảnh bìa của truyện mới nhất trong thể loại (đã kèm `?v=<mtime>`).
 */
export interface Category extends CategoryRef {
  cover_url: string | null;
  stories_count: number;
}

export interface StoryCard {
  id: number;
  title: string;
  slug: string;
  author: string | null;
  thumbnail_url: string | null;
  status: Status;
  status_label: string;
  categories: CategoryRef[];
  /** lượt xem — dùng cho tab "Xếp hạng" */
  views: number;
  /**
   * Lượt xem 7 ngày qua, cả web lẫn app — chỉ có trong danh sách sort=weekly. Khác thước đo
   * với `views` (chỉ lượt đọc chương trong app) nên app không hiện hai số cạnh nhau.
   */
  week_views?: number;
  chapters_count: number;
  latest_chapter_number: number | null;
  updated_at: string;
}

export interface Story extends StoryCard {
  description: string | null;
  free_chapters: number;
  is_featured: boolean;
  /** Có mặt khi gọi GET /api/stories/{id} */
  chapters?: ChapterMeta[];
}

/** Chương trong danh sách (không kèm content) */
export interface ChapterMeta {
  id: number;
  number: number;
  title: string;
  is_free: boolean;
}

/** Nội dung 1 chương */
export interface Chapter {
  id: number;
  story_id: number;
  number: number;
  title: string;
  content: string;
  is_free: boolean;
  prev: number | null;
  next: number | null;
  story: { id: number; title: string; free_chapters: number };
  /** Năm khuôn mặt cuối chương. Server trước 08/11/2026 không có trường này. */
  reaction?: ReactionTotals;
}

/** Số phiếu chấm chương (thang 1–5) và điểm trung bình — null khi chưa đủ 3 phiếu. */
export interface ReactionTotals {
  count: number;
  average: number | null;
}

export interface HomeData {
  featured: Story | null;
  /** Ứng viên nổi bật theo lượt xem 7 ngày qua, nhiều nhất trước (featured = phần tử đầu). */
  featured_pool?: Story[];
  updated: StoryCard[];
  newest: StoryCard[];
  /**
   * Nhiều lượt xem nhất 7 ngày qua, nhiều nhất trước, không có truyện ẩn trên web. Gửi dư so
   * với một hàng để app bỏ truyện đang nổi bật/đã đọc hết. Server cũ chưa có thì undefined.
   */
  weekly?: StoryCard[];
}

/**
 * Cấu hình chỉnh từ trang admin (GET /api/config). Là chính sách CHUNG cho mọi
 * người dùng — xem backend/app/Support/AppSettings.php để biết vì sao không có
 * khoá nào kiểu "chế độ Apple review".
 */
export interface AppConfig {
  ad_fallback: {
    /** Không có quảng cáo thưởng mà người đọc thiếu xu ở màn khoá: có cấp bù không. */
    enabled: boolean;
    /** Số lần cấp bù tối đa mỗi ngày trên máy này. */
    daily_limit: number;
  };
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Kiểu sắp xếp của `GET /api/stories`:
 * - `updated` (mặc định): truyện vừa có chương mới lên trước
 * - `newest`: truyện mới lên kệ trước
 * - `views`: lượt xem giảm dần (tab "Xếp hạng" → All time)
 * - `weekly`: lượt xem 7 ngày qua giảm dần, không có truyện ẩn trên web (tab "Xếp hạng" →
 *   This week); mỗi thẻ có thêm `week_views`. Server từ 04/11/2026.
 */
export type StorySort = 'updated' | 'newest' | 'views' | 'weekly';

export interface StoriesParams {
  search?: string;
  /** slug thể loại */
  category?: string;
  sort?: StorySort;
  /** true = chỉ truyện MIỄN PHÍ TOÀN BỘ (free_chapters >= chapters_count) */
  free?: boolean;
  page?: number;
}

// ---- Fetch helper ----

/** Lỗi HTTP từ API; giữ mã trạng thái để phân biệt "không còn nữa" (404) với lỗi mạng. */
export class ApiError extends Error {
  status: number;

  constructor(status: number, path: string) {
    super(`API ${status}: ${path}`);
    this.status = status;
  }
}

async function request<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
  // Tự build query string bằng encodeURIComponent thay vì URL.searchParams,
  // vì URL của React Native (không có polyfill) có searchParams không đáng tin.
  let url = `${API_URL}${path}`;
  if (params) {
    const query = Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== '')
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
      .join('&');
    if (query) {
      url += `?${query}`;
    }
  }
  const res = await fetch(url, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    throw new ApiError(res.status, path);
  }
  return (await res.json()) as T;
}

// ---- Endpoints ----

export function getHome(): Promise<HomeData> {
  return request<HomeData>('/home');
}

/** Toàn bộ thể loại (mảng, đã sắp theo tên) kèm ảnh bìa + số truyện. */
export function getCategories(): Promise<Category[]> {
  return request<Category[]>('/categories');
}

/**
 * Danh sách truyện có phân trang (20/trang). Mọi tham số đều kết hợp được.
 * `free` gửi lên dạng `free=1` (backend nhận 1|true|on|yes); bỏ hẳn khi false
 * để URL không có tham số thừa.
 */
export function getStories(params: StoriesParams = {}): Promise<Paginated<StoryCard>> {
  return request<Paginated<StoryCard>>('/stories', {
    search: params.search,
    category: params.category,
    sort: params.sort,
    free: params.free ? 1 : undefined,
    page: params.page,
  });
}

export function getStory(id: number | string): Promise<Story> {
  return request<Story>(`/stories/${id}`);
}

export function getConfig(): Promise<AppConfig> {
  return request<AppConfig>('/config');
}

export function getChapter(storyId: number | string, number: number | string): Promise<Chapter> {
  return request<Chapter>(`/stories/${storyId}/chapters/${number}`);
}

/**
 * Chấm một chương (1 = Hated it … 5 = Loved it). Bấm lại là đổi phiếu, không nhân đôi.
 * `readerId`: mã ngẫu nhiên app tự sinh và giữ trong máy (xem components/chapter-reaction).
 */
export async function reactToChapter(
  storyId: number | string,
  number: number | string,
  score: number,
  readerId: string,
): Promise<ReactionTotals & { mine: number }> {
  const path = `/stories/${storyId}/chapters/${number}/reaction`;
  const res = await fetch(`${API_URL}${path}`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ score, reader_id: readerId }),
  });
  if (!res.ok) throw new ApiError(res.status, path);
  return (await res.json()) as ReactionTotals & { mine: number };
}

/**
 * Báo server một lượt bấm nghe audio (`click`, mỗi lần mở màn nghe một chương) hoặc một lần xem
 * hết quảng cáo để mở audio chương (`unlock`). Trang admin/audio xếp chương theo số người muốn
 * nghe để Tuấn chọn chương tạo giọng đọc. Bắn rồi quên: lỗi mạng không bao giờ chặn người nghe.
 */
export function recordAudioRequest(
  storyId: number | string,
  number: number | string,
  event: 'click' | 'unlock',
): void {
  getReaderId()
    .then((readerId) =>
      fetch(`${API_URL}/stories/${storyId}/chapters/${number}/audio-request`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event,
          reader_id: readerId,
          platform: Platform.OS === 'ios' || Platform.OS === 'android' ? Platform.OS : undefined,
        }),
      }),
    )
    .catch(() => {});
}
