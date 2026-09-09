<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateChapterAudio as GenerateChapterAudioJob;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\ChapterAudioGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sinh giọng đọc AI cho các chương.
 *
 *   php artisan chapters:audio                     # tất cả chương chưa có audio
 *   php artisan chapters:audio --story=3           # riêng truyện 3
 *   php artisan chapters:audio --story=3 --chapter=1
 *   php artisan chapters:audio --story=3 --force   # tạo lại dù đã có
 *   php artisan chapters:audio --limit=5           # giới hạn số chương (tiết kiệm chi phí)
 *   php artisan chapters:audio --story=3 --queue   # đẩy vào hàng đợi, cần `php artisan queue:work`
 *
 * Mặc định chạy ĐỒNG BỘ (tiện ở máy local/CLI vì thấy kết quả ngay). Trên production nên
 * dùng --queue để không giữ tiến trình quá lâu và để worker tự retry khi OpenAI lỗi tạm thời.
 */
class GenerateChapterAudio extends Command
{
    protected $signature = 'chapters:audio
        {--story= : ID truyện}
        {--chapter= : Số chương (dùng kèm --story)}
        {--force : Tạo lại kể cả khi đã có audio}
        {--limit= : Số chương tối đa xử lý}
        {--queue : Đẩy vào hàng đợi thay vì chạy ngay (cần chạy queue:work)}';

    protected $description = 'Sinh giọng đọc AI (OpenAI TTS) cho chương truyện';

    public function handle(ChapterAudioGenerator $generator): int
    {
        if ((string) config('services.openai.key') === '') {
            $this->error('Chưa có OPENAI_API_KEY trong backend/.env — hãy điền key rồi chạy lại.');

            return self::FAILURE;
        }

        $query = Chapter::query()->with('story')->orderBy('story_id')->orderBy('number');

        if ($storyId = $this->option('story')) {
            $query->where('story_id', (int) $storyId);
        }
        if ($number = $this->option('chapter')) {
            $query->where('number', (int) $number);
        }
        if (! $this->option('force')) {
            $query->whereNull('audio_path');
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $chapters = $query->get();

        if ($chapters->isEmpty()) {
            $this->info('Không có chương nào cần tạo audio.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        if ($this->option('queue')) {
            foreach ($chapters as $chapter) {
                $chapter->forceFill(['audio_status' => 'queued', 'audio_error' => null])->save();
                GenerateChapterAudioJob::dispatch($chapter, $force);
                $this->line("  → đã xếp hàng: truyện {$chapter->story_id} chương {$chapter->number}");
            }

            $this->newLine();
            $this->info("Đã đưa {$chapters->count()} chương vào hàng đợi. Chạy `php artisan queue:work --timeout=900` để xử lý.");

            return self::SUCCESS;
        }

        $this->info("Sẽ tạo audio cho {$chapters->count()} chương.");
        $ok = 0;
        $failed = 0;

        foreach ($chapters as $chapter) {
            $story = $chapter->story ?? Story::find($chapter->story_id);
            $label = "[{$story?->title}] Chương {$chapter->number}";
            $profile = $generator->profileFor($chapter);

            $this->line("  → {$label} (giọng: {$profile['voice']})");

            try {
                $path = $generator->generate($chapter, force: $force);
                $size = round(strlen((string) @file_get_contents(storage_path("app/public/{$path}"))) / 1024);
                $this->info("    ✓ {$path} ({$size} KB)");
                $ok++;
            } catch (Throwable $e) {
                $this->error('    ✗ '.$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Xong: {$ok} thành công, {$failed} lỗi.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
