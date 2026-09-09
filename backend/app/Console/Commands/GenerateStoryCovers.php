<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateStoryCover as GenerateStoryCoverJob;
use App\Models\Story;
use App\Services\StoryCoverGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vẽ ảnh bìa AI (ảnh như chụp thật, có người) cho truyện.
 *
 *   php artisan stories:cover                  # mọi truyện chưa có bìa
 *   php artisan stories:cover --story=1        # riêng truyện 1
 *   php artisan stories:cover --force          # vẽ lại tất cả dù đã có bìa
 *   php artisan stories:cover --limit=3        # giới hạn số truyện (tiết kiệm chi phí)
 *   php artisan stories:cover --story=1 --dry  # CHỈ in prompt, KHÔNG vẽ, KHÔNG tốn tiền ảnh
 *   php artisan stories:cover --force --queue  # đẩy vào hàng đợi, cần `php artisan queue:work`
 *
 * Nên chạy --dry trước để đọc lại chỉ đạo hình ảnh: bước này quyết định bìa có bám
 * đúng bối cảnh truyện hay không, và nó rẻ hơn bước vẽ hàng chục lần.
 *
 * Mặc định chạy ĐỒNG BỘ (tiện ở CLI vì thấy kết quả ngay). Trên production nên dùng
 * --queue để không giữ tiến trình quá lâu và để worker tự retry khi OpenAI lỗi tạm thời.
 */
class GenerateStoryCovers extends Command
{
    protected $signature = 'stories:cover
        {--story= : ID truyện}
        {--force : Vẽ lại kể cả khi đã có ảnh bìa}
        {--limit= : Số truyện tối đa xử lý}
        {--dry : Chỉ in prompt để xem trước, không gọi model ảnh}
        {--queue : Đẩy vào hàng đợi thay vì chạy ngay (cần chạy queue:work)}';

    protected $description = 'Vẽ ảnh bìa AI như ảnh chụp thật cho truyện (OpenAI Images)';

    public function handle(StoryCoverGenerator $generator): int
    {
        if ((string) config('services.openai.key') === '') {
            $this->error('Chưa có OPENAI_API_KEY trong backend/.env — hãy điền key rồi chạy lại.');

            return self::FAILURE;
        }

        $query = Story::query()->with(['categories', 'chapters'])->orderBy('id');

        if ($storyId = $this->option('story')) {
            $query->whereKey((int) $storyId);
        }
        if (! $this->option('force') && ! $this->option('dry')) {
            $query->whereNull('thumbnail');
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $stories = $query->get();

        if ($stories->isEmpty()) {
            $this->info('Không có truyện nào cần vẽ bìa. Dùng --force để vẽ lại.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        // --- Chỉ xem trước prompt ---
        if ($this->option('dry')) {
            foreach ($stories as $story) {
                $this->newLine();
                $this->line("<fg=yellow>── [{$story->id}] {$story->title}</>");
                try {
                    $this->line($generator->promptFor($story));
                } catch (Throwable $e) {
                    $this->error('  ✗ '.$e->getMessage());
                }
            }

            $this->newLine();
            $this->info('Đây mới chỉ là prompt — chưa vẽ ảnh, chưa tốn tiền ảnh.');

            return self::SUCCESS;
        }

        // --- Đẩy vào hàng đợi ---
        if ($this->option('queue')) {
            foreach ($stories as $story) {
                $story->forceFill(['cover_status' => 'queued', 'cover_error' => null])->save();
                GenerateStoryCoverJob::dispatch($story, $force);
                $this->line("  → đã xếp hàng: [{$story->id}] {$story->title}");
            }

            $this->newLine();
            $this->info("Đã đưa {$stories->count()} truyện vào hàng đợi. Chạy `php artisan queue:work --timeout=900` để xử lý.");

            return self::SUCCESS;
        }

        // --- Chạy ngay ---
        $this->info("Sẽ vẽ bìa cho {$stories->count()} truyện.");
        $ok = 0;
        $failed = 0;

        foreach ($stories as $story) {
            $this->line("  → [{$story->id}] {$story->title}");

            try {
                $path = $generator->generate($story, force: $force);
                $bytes = strlen((string) @file_get_contents(storage_path("app/public/{$path}")));
                $this->info('    ✓ '.$path.' ('.round($bytes / 1024).' KB)');
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
