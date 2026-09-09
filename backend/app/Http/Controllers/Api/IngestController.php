<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API GHI cho máy: một AI khác viết truyện xong thì gọi lần lượt các bước ở đây
 * để đăng truyện, thêm chương, rồi đặt hàng ảnh bìa và giọng đọc.
 *
 * Bảo vệ bằng App\Http\Middleware\VerifyIngestToken (token dùng chung).
 *
 * Nguyên tắc thiết kế, đừng phá khi sửa về sau:
 *
 *  1. BẤT BIẾN THEO SLUG. Đăng lại cùng một `slug` là CẬP NHẬT, không tạo bản sao;
 *     nạp lại cùng `number` là ghi đè nội dung chương. Client là máy, sẽ có lúc gọi
 *     lại vì timeout — gọi lại phải an toàn.
 *  2. VIỆC NẶNG ĐI QUA HÀNG ĐỢI. Vẽ bìa và đọc audio mất hàng chục giây tới vài
 *     phút; làm ngay trong request thì nginx/php-fpm timeout. Ở đây chỉ XẾP HÀNG rồi
 *     trả về ngay, client tự hỏi lại bằng GET .../status.
 *  3. SỬA CHƯƠNG THÌ AUDIO CŨ HẾT GIÁ TRỊ. Ghi đè nội dung mà giữ MP3 cũ là để lại
 *     một chương đọc một đằng nghe một nẻo, nên chỗ đó xoá audio cũ đi.
 *
 * Toàn bộ nghiệp vụ nằm ở App\Services\StoryIngestor, dùng chung với lệnh
 * `stories:import` (dành cho môi trường bị chặn egress, AI chỉ xuất được file JSON).
 * Controller ở đây chỉ lo kiểm tra dữ liệu và định dạng phản hồi.
 */
class IngestController extends Controller
{
    public function __construct(private readonly StoryIngestor $ingestor) {}

    /** Danh sách thể loại hợp lệ để AI chọn `categories`. */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
        ]);
    }

    /**
     * BƯỚC 0 (chỉ khi cần) — tạo hoặc cập nhật một thể loại.
     * POST /api/ingest/categories
     *
     * Chỉ dùng khi thể loại cần thiết CHƯA có trong danh sách ở GET /categories.
     * Đăng lại cùng `slug` là cập nhật tên, không tạo bản trùng.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        // Xác định slug đích TRƯỚC khi kiểm tra, để luật unique trên `name` biết
        // phải bỏ qua bản ghi nào (xem StoryIngestor::categoryRules).
        $slug = $request->filled('slug')
            ? (string) $request->input('slug')
            : Str::slug((string) $request->input('name'));

        $data = $request->validate(StoryIngestor::categoryRules($slug));

        [$category, $created] = $this->ingestor->upsertCategory($data + ['slug' => $slug]);

        return response()->json([
            'created' => $created,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'next' => 'Dùng slug này trong trường `categories` khi POST /api/ingest/stories',
        ], $created ? 201 : 200);
    }

    /**
     * Liệt kê truyện, lọc theo trạng thái.
     * GET /api/ingest/stories?status=ongoing
     *
     * Dành cho AI viết tiếp: `status=ongoing` cho ra những truyện chưa đóng, kèm
     * `next_chapter_number` để biết viết tiếp từ chương nào mà không phải tự đếm.
     * Cần nội dung các chương cũ để giữ mạch thì đọc qua API công khai
     * `GET /api/stories/{id}/chapters/{number}`.
     */
    public function stories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['ongoing', 'completed'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $stories = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->when(
                $data['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            // Truyện lâu chưa đụng tới lên trước: AI nên viết tiếp cái nguội nhất.
            ->orderBy('updated_at')
            ->limit((int) ($data['limit'] ?? 50))
            ->get();

        return response()->json([
            'count' => $stories->count(),
            'data' => $stories->map(fn (Story $story) => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'author' => $story->author,
                'description' => $story->description,
                'status' => $story->status,
                'categories' => $story->categories->pluck('slug'),
                'free_chapters' => (int) $story->free_chapters,
                'chapters_count' => (int) $story->chapters_count,
                'latest_chapter_number' => $story->chapters_max_number !== null
                    ? (int) $story->chapters_max_number
                    : null,
                // Số chương cần viết tiếp — dùng thẳng cho POST .../chapters.
                'next_chapter_number' => (int) ($story->chapters_max_number ?? 0) + 1,
                'thumbnail_url' => $story->thumbnail_url,
                'cover_status' => $story->cover_status,
                'updated_at' => optional($story->updated_at)->toISOString(),
            ]),
            'next' => 'Viết tiếp: POST /api/ingest/stories/{id}/chapters với number = next_chapter_number. '
                .'Xong hẳn thì POST /api/ingest/stories với cùng slug và status = "completed".',
        ]);
    }

    /**
     * BƯỚC 1 — tạo hoặc cập nhật truyện.
     * POST /api/ingest/stories
     */
    public function storeStory(Request $request): JsonResponse
    {
        $data = $request->validate(StoryIngestor::storyRules());

        [$story, $created] = $this->ingestor->upsertStory($data);

        return response()->json([
            'created' => $created,
            'story' => $this->storyPayload($story->fresh(['categories', 'chapters'])),
            'next' => "POST /api/ingest/stories/{$story->id}/chapters cho từng chương",
        ], $created ? 201 : 200);
    }

    /**
     * BƯỚC 2 — thêm hoặc thay nội dung một chương.
     * POST /api/ingest/stories/{story}/chapters
     */
    public function storeChapter(Request $request, Story $story): JsonResponse
    {
        $data = $request->validate(StoryIngestor::chapterRules());

        [$chapter, $created] = $this->ingestor->upsertChapter($story, $data);

        return response()->json([
            'created' => $created,
            'chapter' => [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
                'characters' => mb_strlen($chapter->content),
                'has_audio' => $chapter->audio_path !== null,
            ],
            'next' => "POST /api/ingest/stories/{$story->id}/chapters/{$chapter->number}/audio để đặt giọng đọc",
        ], $created ? 201 : 200);
    }

    /**
     * BƯỚC 3 — đặt hàng ảnh bìa (chạy nền).
     * POST /api/ingest/stories/{story}/cover
     */
    public function generateCover(Request $request, Story $story): JsonResponse
    {
        $force = $request->boolean('force');

        if (! $this->ingestor->queueCover($story, $force)) {
            return response()->json([
                'queued' => false,
                'message' => 'Truyện đã có ảnh bìa. Gửi force=true để vẽ lại.',
                'thumbnail_url' => $story->thumbnail_url,
            ]);
        }

        return response()->json([
            'queued' => true,
            'story_id' => $story->id,
            'next' => "GET /api/ingest/stories/{$story->id}/status để theo dõi",
        ], 202);
    }

    /**
     * BƯỚC 4 — đặt hàng giọng đọc cho MỘT chương, hoặc bỏ `number` để đặt cả truyện.
     * POST /api/ingest/stories/{story}/chapters/{number?}/audio
     */
    public function generateAudio(Request $request, Story $story, ?int $number = null): JsonResponse
    {
        $force = $request->boolean('force');

        $chapters = $this->ingestor->queueAudio($story, $number, $force);

        if ($chapters->isEmpty()) {
            return response()->json([
                'queued' => 0,
                'message' => $number !== null
                    ? 'Chương không tồn tại, hoặc đã có audio (gửi force=true để đọc lại).'
                    : 'Mọi chương đều đã có audio. Gửi force=true để đọc lại.',
            ]);
        }

        return response()->json([
            'queued' => $chapters->count(),
            'chapters' => $chapters->pluck('number'),
            'next' => "GET /api/ingest/stories/{$story->id}/status để theo dõi",
        ], 202);
    }

    /**
     * Theo dõi tiến trình — AI gọi lại cho tới khi bìa và mọi chương đều `done`.
     * GET /api/ingest/stories/{story}/status
     */
    public function status(Story $story): JsonResponse
    {
        $story->load(['categories', 'chapters']);

        $chapters = $story->chapters->map(fn (Chapter $c) => [
            'number' => $c->number,
            'title' => $c->title,
            'audio_status' => $c->audio_status ?? ($c->audio_path ? 'done' : null),
            'audio_error' => $c->audio_error,
            'has_audio' => $c->audio_path !== null,
        ]);

        $pending = $chapters->whereIn('audio_status', ['queued', 'processing'])->count()
            + (in_array($story->cover_status, ['queued', 'processing'], true) ? 1 : 0);

        return response()->json([
            'story' => $this->storyPayload($story),
            'chapters' => $chapters->values(),
            // false = mọi việc nền đã xong, AI có thể dừng hỏi lại.
            'pending' => $pending > 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function storyPayload(Story $story): array
    {
        return [
            'id' => $story->id,
            'slug' => $story->slug,
            'title' => $story->title,
            'author' => $story->author,
            'status' => $story->status,
            'free_chapters' => $story->free_chapters,
            'is_featured' => (bool) $story->is_featured,
            'categories' => $story->categories->pluck('slug'),
            'chapters_count' => $story->chapters->count(),
            'thumbnail_url' => $story->thumbnail_url,
            'cover_status' => $story->cover_status,
            'cover_error' => $story->cover_error,
        ];
    }
}
