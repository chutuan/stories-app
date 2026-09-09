import Constants from 'expo-constants';

/**
 * Base URL của REST API.
 * - Trên trình giả lập Android dùng `http://10.0.2.2:8000/api` (10.0.2.2 = localhost của máy host).
 * - Trên thiết bị thật dùng IP LAN của máy chạy backend, vd `http://192.168.1.10:8000/api`.
 * Cấu hình qua app.json > expo.extra.apiUrl.
 */
export const API_URL: string =
  (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl ??
  'http://localhost:8000/api';

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
}

export interface HomeData {
  featured: Story | null;
  updated: StoryCard[];
  newest: StoryCard[];
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
 * - `views`: lượt xem giảm dần (tab "Xếp hạng")
 */
export type StorySort = 'updated' | 'newest' | 'views';

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
    throw new Error(`API ${res.status}: ${path}`);
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

export function getChapter(storyId: number | string, number: number | string): Promise<Chapter> {
  return request<Chapter>(`/stories/${storyId}/chapters/${number}`);
}
