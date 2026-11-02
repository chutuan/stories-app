<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IndexNow;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Gửi URL mới/sửa lên IndexNow (Bing và các máy tìm kiếm đi cùng). Chạy mỗi giờ theo lịch
 * ở routes/console.php — xem App\Services\IndexNow.
 *
 * Mốc "từ lúc nào" là lúc BẮT ĐẦU lần gửi thành công trước, lưu trong cache. Lưu lúc bắt
 * đầu chứ không phải lúc xong: truyện lên kệ đúng lúc lệnh đang chạy vẫn được lần sau bắt.
 * Gửi lỗi thì không cập nhật mốc, lần sau gửi bù cả khoảng đó.
 *
 *   php artisan seo:indexnow           # URL sửa từ lần gửi trước
 *   php artisan seo:indexnow --all     # toàn bộ site (lần đầu, hoặc sau khi đổi tên miền)
 *   php artisan seo:indexnow --dry     # chỉ in URL, không gửi
 */
class SubmitIndexNow extends Command
{
    private const LAST_RUN = 'indexnow:last-run';

    /** Chưa có mốc nào (cache bị xoá) thì lùi chừng này — đủ phủ một ngày lỡ lịch. */
    private const FALLBACK_HOURS = 26;

    protected $signature = 'seo:indexnow
        {--all : Gửi toàn bộ URL index được, không chỉ URL vừa sửa}
        {--dry : Chỉ in danh sách URL, không gửi}';

    protected $description = 'Báo URL mới/sửa cho Bing và các máy tìm kiếm dùng IndexNow';

    public function handle(IndexNow $indexNow): int
    {
        $started = Carbon::now();
        $since = $this->option('all')
            ? null
            : Carbon::parse(Cache::get(self::LAST_RUN, $started->copy()->subHours(self::FALLBACK_HOURS)->toIso8601String()));

        $urls = $indexNow->urls($since);

        if ($this->option('dry')) {
            foreach ($urls as $url) {
                $this->line("  · {$url}");
            }
            $this->info(count($urls).' URL'.($since ? ' sửa từ '.$since->toIso8601String() : '').' — không gửi (--dry).');

            return self::SUCCESS;
        }

        if (! $indexNow->enabled()) {
            $this->warn('IndexNow đang tắt (INDEXNOW_ENABLED) — không gửi.');

            return self::SUCCESS;
        }

        if ($urls !== []) {
            try {
                $indexNow->submit($urls);
            } catch (Throwable $e) {
                $this->error('IndexNow từ chối: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        Cache::forever(self::LAST_RUN, $started->toIso8601String());
        $this->info('Đã gửi '.count($urls).' URL lên IndexNow.');

        return self::SUCCESS;
    }
}
