<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StoryIngestor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Nạp truyện từ FILE JSON — lối vào thứ hai bên cạnh API /api/ingest.
 *
 * Vì sao cần: có môi trường chạy AI bị chặn egress ở tầng tổ chức, AI viết truyện
 * không gọi ra Internet được. Bắt nó tự đăng qua HTTP là bế tắc, mà đi vòng chính
 * sách thì không được phép. Cách đúng là tách đôi: AI chỉ GHI FILE, còn việc đăng
 * chạy ở nơi vốn đã có quyền (máy của bạn, hoặc chính máy chủ).
 *
 * Định dạng file GIỐNG HỆT thân request của API, nên AI không phải học hai kiểu:
 *
 *   {
 *     "story":    { "title": "...", "author": "...", "categories": ["revenge"] },
 *     "chapters": [ { "number": 1, "title": "...", "content": "..." } ],
 *     "generate": { "cover": true, "audio": true }
 *   }
 *
 * Chấp nhận cả một MẢNG các gói như trên để nạp nhiều truyện một lần, và cả kiểu
 * rút gọn khi các trường của truyện nằm thẳng ở gốc cạnh `chapters`.
 *
 *   php artisan stories:import truyen.json            # nạp và xếp hàng bìa + audio
 *   php artisan stories:import truyen.json --dry      # CHỈ kiểm tra dữ liệu, không ghi
 *   php artisan stories:import truyen.json --force    # ghi đè cả bìa/audio đã có
 *   php artisan stories:import truyen.json --no-jobs  # chỉ nạp chữ, không đặt bìa/audio
 */
class ImportStoryBundle extends Command
{
    protected $signature = 'stories:import
        {path : Đường dẫn file JSON}
        {--dry : Chỉ kiểm tra dữ liệu, không ghi vào cơ sở dữ liệu}
        {--force : Vẽ lại bìa và đọc lại audio kể cả khi đã có}
        {--no-jobs : Bỏ qua việc xếp hàng bìa và audio}';

    protected $description = 'Nạp truyện từ file JSON do AI viết (dùng khi không gọi được API)';

    public function handle(StoryIngestor $ingestor): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Không đọc được file: {$path}");

            return self::FAILURE;
        }

        try {
            $raw = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('JSON hỏng: '.$e->getMessage());

            return self::FAILURE;
        }

        // Một gói, hay một mảng gói?
        $bundles = array_is_list($raw ?? []) ? $raw : [$raw];

        $dry = (bool) $this->option('dry');
        $force = (bool) $this->option('force');
        $withJobs = ! $this->option('no-jobs');

        $ok = 0;
        $failed = 0;

        foreach ($bundles as $i => $bundle) {
            $label = 'gói #'.($i + 1);

            if (! is_array($bundle)) {
                $this->error("  ✗ {$label}: không phải một object JSON");
                $failed++;

                continue;
            }

            // Kiểu rút gọn: các trường truyện nằm thẳng ở gốc.
            $storyData = is_array($bundle['story'] ?? null)
                ? $bundle['story']
                : array_diff_key($bundle, array_flip(['chapters', 'generate']));
            $chapters = is_array($bundle['chapters'] ?? null) ? $bundle['chapters'] : [];
            $generate = is_array($bundle['generate'] ?? null) ? $bundle['generate'] : [];

            $storyCheck = Validator::make($storyData, StoryIngestor::storyRules());
            if ($storyCheck->fails()) {
                $this->error("  ✗ {$label}: ".implode(' | ', $storyCheck->errors()->all()));
                $failed++;

                continue;
            }

            // Kiểm tra HẾT các chương TRƯỚC khi ghi bất cứ thứ gì: nạp nửa chừng rồi
            // hỏng ở chương cuối sẽ để lại một truyện cụt trong cơ sở dữ liệu.
            $chapterData = [];
            $bad = false;
            foreach ($chapters as $j => $chapter) {
                $check = Validator::make(is_array($chapter) ? $chapter : [], StoryIngestor::chapterRules());
                if ($check->fails()) {
                    $this->error("  ✗ {$label} chương thứ ".($j + 1).': '.implode(' | ', $check->errors()->all()));
                    $bad = true;

                    continue;
                }
                $chapterData[] = $check->validated();
            }
            if ($bad) {
                $failed++;

                continue;
            }

            $title = (string) $storyCheck->validated()['title'];

            if ($dry) {
                $this->info("  ✓ {$label} hợp lệ: “{$title}” · ".count($chapterData).' chương');
                $ok++;

                continue;
            }

            try {
                [$story, $created] = $ingestor->upsertStory($storyCheck->validated());

                foreach ($chapterData as $chapter) {
                    $ingestor->upsertChapter($story, $chapter);
                }

                $line = sprintf(
                    '  ✓ [%d] %s — %s, %d chương',
                    $story->id,
                    $title,
                    $created ? 'tạo mới' : 'cập nhật',
                    count($chapterData),
                );

                if ($withJobs && ($generate['cover'] ?? true)) {
                    $line .= $ingestor->queueCover($story, $force) ? ', đã xếp hàng bìa' : ', bìa đã có';
                }
                if ($withJobs && ($generate['audio'] ?? true)) {
                    $queued = $ingestor->queueAudio($story, null, $force);
                    $line .= ', audio xếp hàng '.$queued->count().' chương';
                }

                $this->info($line);
                $ok++;
            } catch (Throwable $e) {
                $this->error("  ✗ {$label}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        if ($dry) {
            $this->info("Kiểm tra xong: {$ok} gói hợp lệ, {$failed} gói hỏng. Chưa ghi gì cả.");
        } else {
            $this->info("Xong: {$ok} truyện nạp thành công, {$failed} lỗi.");
            if ($withJobs && $ok > 0) {
                $this->line('Việc nền chỉ chạy khi có worker: `php artisan queue:work --timeout=900`.');
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
