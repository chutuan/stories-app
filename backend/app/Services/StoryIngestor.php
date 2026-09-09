<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateChapterAudio as GenerateChapterAudioJob;
use App\Jobs\GenerateStoryCover as GenerateStoryCoverJob;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Nghiệp vụ đăng truyện dùng chung cho HAI lối vào:
 *
 *  - HTTP: App\Http\Controllers\Api\IngestController (AI gọi thẳng API)
 *  - File: App\Console\Commands\ImportStoryBundle   (AI xuất JSON, người/CI nạp hộ)
 *
 * Lối thứ hai tồn tại vì có môi trường chặn egress: AI viết truyện không ra được
 * Internet để gọi API. Khi đó nó chỉ cần GHI FILE, việc đăng chạy ở nơi đã có quyền.
 *
 * Quy tắc nghiệp vụ (giữ nguyên ở cả hai lối, đừng để lệch):
 *  - `slug` là khoá bất biến của truyện, `number` là khoá của chương -> gọi lại an toàn.
 *  - Việc nặng (vẽ bìa, đọc audio) chỉ XẾP HÀNG, không chạy trong request.
 *  - Đổi nội dung chương thì xoá MP3 cũ, vì bản đọc cũ không còn khớp chữ.
 */
class StoryIngestor
{
    /**
     * Luật kiểm tra dữ liệu truyện. Khai ở đây để controller và lệnh CLI dùng chung
     * một bộ — lệch luật giữa hai lối là cách chắc chắn nhất để sinh dữ liệu hỏng.
     *
     * @param  list<string>  $pendingCategorySlugs  slug SẮP được tạo trong cùng lượt nạp
     *                                                  (dùng cho `--dry`: thể loại chưa có
     *                                                  trong DB nhưng gói này khai tạo mới)
     * @return array<string, array<int, mixed>>
     */
    public static function storyRules(array $pendingCategorySlugs = []): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['ongoing', 'completed'])],
            'free_chapters' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            // Nhịp nhả chương, tính bằng giờ. Bỏ trống = đăng hết ngay (nếp cũ).
            // 24 = mỗi ngày một chương, 0 = nhả hết ngay dù có khai lịch.
            'publish_every_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            // Mốc của chương ĐẦU TIÊN (ISO 8601). Bỏ trống = ngay bây giờ.
            'publish_start_at' => ['nullable', 'date'],
            'categories' => ['nullable', 'array', 'max:5'],
            'categories.*' => [
                'string',
                function (string $attribute, mixed $value, callable $fail) use ($pendingCategorySlugs) {
                    if (in_array($value, $pendingCategorySlugs, true)) {
                        return;
                    }
                    if (! Category::query()->where('slug', $value)->exists()) {
                        $fail("Thể loại '{$value}' không tồn tại. Tạo trước bằng POST /api/ingest/categories".
                            ' hoặc khai trong `new_categories` của file JSON.');
                    }
                },
            ],
        ];
    }

    /**
     * Luật kiểm tra thể loại.
     *
     * `name` và `slug` đều UNIQUE trong bảng. Vì đăng lại cùng `slug` phải là CẬP
     * NHẬT chứ không được báo lỗi, luật `unique` trên `name` phải bỏ qua đúng bản
     * ghi đang mang slug đó — nếu không, gọi lại y hệt lần trước sẽ trả 422.
     *
     * @param  string|null  $slug  slug đích, để loại chính nó khỏi phép kiểm unique
     * @return array<string, array<int, mixed>>
     */
    public static function categoryRules(?string $slug = null): array
    {
        $existingId = $slug ? Category::query()->where('slug', $slug)->value('id') : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($existingId)],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function chapterRules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1', 'max:10000'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:200000'],
            // Ghi đè giờ đăng của riêng chương này; bỏ trống thì tính từ chương trước.
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Tạo mới hoặc cập nhật thể loại theo `slug`.
     *
     * @param  array<string, mixed>  $data  đã qua kiểm tra bằng categoryRules()
     * @return array{0: Category, 1: bool} [thể loại, có phải vừa tạo mới không]
     */
    public function upsertCategory(array $data): array
    {
        $slug = $data['slug'] ?? Str::slug((string) $data['name']);

        $category = Category::firstOrNew(['slug' => $slug]);
        $created = ! $category->exists;

        $category->fill(['name' => $data['name'], 'slug' => $slug])->save();

        return [$category, $created];
    }

    /**
     * Tạo mới hoặc cập nhật truyện theo `slug`.
     *
     * @param  array<string, mixed>  $data  đã qua kiểm tra bằng storyRules()
     * @return array{0: Story, 1: bool} [truyện, có phải vừa tạo mới không]
     */
    public function upsertStory(array $data): array
    {
        $slug = $data['slug'] ?? Str::slug((string) $data['title']);

        $story = Story::firstOrNew(['slug' => $slug]);
        $created = ! $story->exists;

        $story->fill([
            'title' => $data['title'],
            'slug' => $slug,
            'author' => $data['author'] ?? $story->author,
            'description' => $data['description'] ?? $story->description,
            // Truyện nộp qua đây LUÔN đủ chương, nên mặc định là 'completed'. Việc
            // người đọc thấy "Ongoing" hay không do LỊCH quyết định, không do cột này
            // (xem Story::publicStatus()).
            'status' => $data['status'] ?? ($story->status ?: 'completed'),
            'free_chapters' => $data['free_chapters'] ?? ($story->free_chapters ?: 1),
            'is_featured' => $data['is_featured'] ?? (bool) $story->is_featured,
            'publish_every_hours' => array_key_exists('publish_every_hours', $data)
                ? $data['publish_every_hours']
                : $story->publish_every_hours,
            'publish_start_at' => array_key_exists('publish_start_at', $data) && $data['publish_start_at']
                ? Carbon::parse($data['publish_start_at'])
                : $story->publish_start_at,
        ])->save();

        if (array_key_exists('categories', $data)) {
            $ids = Category::query()->whereIn('slug', $data['categories'] ?? [])->pluck('id');
            $story->categories()->sync($ids);
        }

        return [$story, $created];
    }

    /**
     * Tạo mới hoặc ghi đè một chương theo `number`.
     *
     * @param  array<string, mixed>  $data  đã qua kiểm tra bằng chapterRules()
     * @return array{0: Chapter, 1: bool} [chương, có phải vừa tạo mới không]
     */
    public function upsertChapter(Story $story, array $data): array
    {
        $chapter = Chapter::firstOrNew([
            'story_id' => $story->id,
            'number' => $data['number'],
        ]);
        $created = ! $chapter->exists;
        $contentChanged = $chapter->content !== $data['content'];

        $chapter->fill([
            'story_id' => $story->id,
            'number' => $data['number'],
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        // Giờ đăng chỉ đặt MỘT LẦN. Nạp lại nội dung (sửa chữ, sửa tiêu đề) không
        // được đẩy lịch đi, nếu không mỗi lần sửa là chương lại lùi ra xa thêm.
        if ($created || $chapter->published_at === null) {
            $chapter->published_at = $this->scheduleFor($story, (int) $data['number'], $data['published_at'] ?? null);
        }

        // Nội dung đổi -> MP3 cũ đọc sai chữ, phải bỏ để không phát nhầm bản cũ.
        if (! $created && $contentChanged && $chapter->audio_path) {
            $chapter->audio_path = null;
            $chapter->forceFill(['audio_status' => null, 'audio_error' => null]);
        }

        $chapter->save();

        return [$chapter, $created];
    }

    /**
     * Giờ đăng của một chương, TÍNH TỪ CHƯƠNG LIỀN TRƯỚC.
     *
     * Thứ tự ưu tiên:
     *  1. `published_at` khai thẳng cho chương đó;
     *  2. giờ của chương liền trước + `publish_every_hours` của truyện;
     *  3. chưa có chương nào trước -> `publish_start_at`, mặc định là ngay bây giờ.
     *
     * Truyện không khai `publish_every_hours` thì trả null = đăng ngay, giữ nguyên
     * hành vi của toàn bộ truyện có từ trước tính năng này.
     */
    private function scheduleFor(Story $story, int $number, ?string $explicit): ?CarbonImmutable
    {
        if ($explicit !== null && $explicit !== '') {
            return CarbonImmutable::parse($explicit);
        }

        $hours = $story->publish_every_hours;
        if ($hours === null) {
            return null;
        }

        $start = $story->publish_start_at
            ? CarbonImmutable::parse($story->publish_start_at)
            : CarbonImmutable::now();

        // Chương liền trước ĐÃ có trong cơ sở dữ liệu — kể cả chương đang hẹn giờ.
        // CỐ Ý không dùng $story->chapters(): quan hệ đó đã gắn orderBy('number','asc'),
        // nối thêm orderByDesc chỉ thành tiêu chí phụ nên first() vẫn trả về chương 1
        // -> mọi chương đều nhận cùng một giờ đăng.
        $previous = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', '<', $number)
            ->orderByDesc('number')
            ->first();

        if ($previous === null) {
            return $start;
        }

        $base = $previous->published_at
            ? CarbonImmutable::parse($previous->published_at)
            : $start;

        return $base->addHours($hours);
    }

    /**
     * Xếp hàng vẽ ảnh bìa.
     *
     * @return bool false khi truyện đã có bìa và không ép làm lại
     */
    public function queueCover(Story $story, bool $force = false): bool
    {
        if (! $force && $story->thumbnail) {
            return false;
        }

        // Đang dở thì thôi, kể cả khi $force: job đang chạy sẽ ra ảnh, xếp thêm
        // một job nữa chỉ là trả tiền hai lần cho cùng một tấm bìa.
        if (in_array($story->cover_status, ['queued', 'processing'], true)) {
            return false;
        }

        $story->forceFill(['cover_status' => 'queued', 'cover_error' => null])->save();
        GenerateStoryCoverJob::dispatch($story, $force);

        return true;
    }

    /**
     * Xếp hàng đọc audio cho một chương, hoặc cả truyện khi `$number` là null.
     *
     * @return Collection<int, Chapter> các chương vừa được xếp hàng
     */
    public function queueAudio(Story $story, ?int $number = null, bool $force = false): Collection
    {
        $query = $story->chapters()->getQuery();

        if ($number !== null) {
            $query->where('number', $number);
        }
        if (! $force) {
            $query->whereNull('audio_path');
        }

        // Chương đang chờ/đang đọc thì bỏ qua ở MỌI trường hợp, kể cả $force —
        // cùng lý do với ảnh bìa: mỗi lần đọc là một lần trả tiền.
        // PHẢI kèm whereNull: trong SQL, `audio_status NOT IN (...)` với giá trị NULL
        // cho ra NULL (không phải TRUE), nên chương mới tinh sẽ bị loại sạch.
        $query->where(fn ($q) => $q
            ->whereNull('audio_status')
            ->orWhereNotIn('audio_status', ['queued', 'processing']));

        $chapters = $query->orderBy('number')->get();

        foreach ($chapters as $chapter) {
            $chapter->forceFill(['audio_status' => 'queued', 'audio_error' => null])->save();
            GenerateChapterAudioJob::dispatch($chapter, $force);
        }

        return $chapters;
    }
}
