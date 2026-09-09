<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateChapterAudio as GenerateChapterAudioJob;
use App\Jobs\GenerateStoryCover as GenerateStoryCoverJob;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
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
     * @return array<string, array<int, mixed>>
     */
    public static function storyRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['ongoing', 'completed'])],
            'free_chapters' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_featured' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array', 'max:5'],
            'categories.*' => ['string', 'exists:categories,slug'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function chapterRules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1', 'max:10000'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:200000'],
        ];
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
            'status' => $data['status'] ?? ($story->status ?: 'ongoing'),
            'free_chapters' => $data['free_chapters'] ?? ($story->free_chapters ?: 1),
            'is_featured' => $data['is_featured'] ?? (bool) $story->is_featured,
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

        // Nội dung đổi -> MP3 cũ đọc sai chữ, phải bỏ để không phát nhầm bản cũ.
        if (! $created && $contentChanged && $chapter->audio_path) {
            $chapter->audio_path = null;
            $chapter->forceFill(['audio_status' => null, 'audio_error' => null]);
        }

        $chapter->save();

        return [$chapter, $created];
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

        $chapters = $query->orderBy('number')->get();

        foreach ($chapters as $chapter) {
            $chapter->forceFill(['audio_status' => 'queued', 'audio_error' => null])->save();
            GenerateChapterAudioJob::dispatch($chapter, $force);
        }

        return $chapters;
    }
}
