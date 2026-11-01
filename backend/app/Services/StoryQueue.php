<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QueuedStory;
use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Kho truyện chờ đăng: xếp hàng cả lô một lần, mỗi ngày đăng vài truyện đầu hàng.
 *
 * Lúc đăng đi qua đúng StoryIngestor như `stories:import` và API ingest, nên mọi
 * chính sách ở đó (miễn phí đúng chương 1, slug là khoá...) giữ nguyên.
 */
class StoryQueue
{
    /** Thư mục bìa chờ trên đĩa `local` — không công khai cho tới khi truyện được đăng. */
    public const COVER_DIR = 'story-queue/covers';

    public function __construct(private readonly StoryIngestor $ingestor) {}

    /**
     * Đưa một gói vào kho (hoặc cập nhật nếu nó vẫn đang chờ).
     *
     * Truyện đã đăng hay đã bị bỏ khỏi kho thì giữ nguyên: xếp hàng lại cả lô sau khi
     * sửa vài tóm tắt không được làm một truyện đã lên sóng bị đăng lần nữa.
     *
     * @param  array{story: array<string, mixed>, chapters: list<array<string, mixed>>}  $bundle
     * @return array{0: QueuedStory, 1: 'added'|'updated'|'kept'}
     */
    public function enqueue(array $bundle, string $source, string $sourceKey, ?string $coverFile, bool $noindex): array
    {
        $this->validate($bundle);

        $item = QueuedStory::query()->firstOrNew(['source' => $source, 'source_key' => $sourceKey]);
        if ($item->exists && $item->status !== 'pending') {
            return [$item, 'kept'];
        }
        $state = $item->exists ? 'updated' : 'added';

        if ($coverFile !== null) {
            if (! is_file($coverFile)) {
                throw new RuntimeException("không thấy file bìa: {$coverFile}");
            }
            $path = self::COVER_DIR.'/'.$source.'-'.md5($sourceKey).'.'.strtolower(pathinfo($coverFile, PATHINFO_EXTENSION));
            Storage::disk('local')->put($path, (string) file_get_contents($coverFile));
            $item->cover_path = $path;
        }

        $item->fill([
            'title' => (string) $bundle['story']['title'],
            'payload' => ['story' => $bundle['story'], 'chapters' => $bundle['chapters']],
            'noindex' => $noindex,
            'status' => 'pending',
            'error' => null,
        ]);
        // Truyện mới vào cuối hàng; truyện đang chờ được cập nhật thì giữ chỗ cũ.
        $item->position ??= (int) QueuedStory::query()->max('position') + 1;
        $item->save();

        return [$item, $state];
    }

    /**
     * Đăng một truyện trong kho thành truyện thật. Lỗi thì đánh dấu `failed` kèm lý do
     * và ném lại — lần chạy kế tiếp không thử lại nó, tránh kẹt cả hàng sau một gói hỏng.
     */
    public function release(QueuedStory $item): Story
    {
        try {
            $bundle = $item->payload;
            // Kiểm tra lại lúc đăng: thể loại có thể đã bị xoá trong lúc truyện nằm chờ.
            [$storyData, $chapters] = $this->validate($bundle);

            $storyData['slug'] = $this->freeSlug($storyData['slug'] ?? null, (string) $storyData['title']);

            $story = DB::transaction(function () use ($storyData, $chapters, $item) {
                [$story] = $this->ingestor->upsertStory($storyData);
                foreach ($chapters as $chapter) {
                    $this->ingestor->upsertChapter($story, $chapter);
                }
                $story->forceFill(['noindex' => $item->noindex])->save();

                return $story;
            });

            if ($item->cover_path && Storage::disk('local')->exists($item->cover_path)) {
                $target = 'stories/'.$story->slug.'.'.pathinfo($item->cover_path, PATHINFO_EXTENSION);
                Storage::disk('public')->put($target, Storage::disk('local')->get($item->cover_path));
                $story->forceFill(['thumbnail' => $target])->save();
                Storage::disk('local')->delete($item->cover_path);
            }

            $item->forceFill([
                'status' => 'released',
                'story_id' => $story->id,
                'released_at' => now(),
                'error' => null,
            ])->save();

            return $story;
        } catch (Throwable $e) {
            $item->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();

            throw $e;
        }
    }

    /**
     * Slug của gói có thể đã bị một truyện khác chiếm trong lúc chờ. StoryIngestor coi
     * slug là khoá nên đăng thẳng sẽ GHI ĐÈ truyện đó — phải đổi sang slug còn trống.
     */
    private function freeSlug(?string $slug, string $title): string
    {
        $base = $slug ?: Str::slug($title);
        $candidate = $base;
        // slugTaken: cả slug cũ đang chuyển hướng về truyện khác cũng tính là đã có chủ.
        for ($n = 2; Story::slugTaken($candidate); $n++) {
            $candidate = mb_substr($base, 0, 245).'-'.$n;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function validate(array $bundle): array
    {
        $story = is_array($bundle['story'] ?? null) ? $bundle['story'] : [];
        $chapters = is_array($bundle['chapters'] ?? null) ? $bundle['chapters'] : [];
        if ($chapters === []) {
            throw new RuntimeException('gói không có chương nào');
        }

        try {
            $storyData = Validator::make($story, StoryIngestor::storyRules())->validate();
        } catch (ValidationException $e) {
            throw new RuntimeException(implode(' | ', $e->validator->errors()->all()));
        }

        $chapterData = [];
        foreach ($chapters as $i => $chapter) {
            try {
                $chapterData[] = Validator::make(is_array($chapter) ? $chapter : [], StoryIngestor::chapterRules())->validate();
            } catch (ValidationException $e) {
                throw new RuntimeException('chương thứ '.($i + 1).': '.implode(' | ', $e->validator->errors()->all()));
            }
        }

        return [$storyData, $chapterData];
    }
}
