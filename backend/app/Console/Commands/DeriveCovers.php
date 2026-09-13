<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sinh bản WebP nhỏ của ảnh bìa cho bản web.
 *
 * VÌ SAO CẦN: ảnh gốc là 600x800 và 1120x1400, nặng 68-388 KB mỗi cái, trong khi
 * ô bìa ngoài lưới rộng khoảng 250 CSS px. Trang /browse vì thế tải 2,9 MB ảnh để
 * hiển thị thứ chỉ cần vài trăm KB.
 *
 * KHÔNG đụng vào file gốc và KHÔNG đổi `thumbnail_url`: app di động dùng đúng
 * thuộc tính đó, và nó đang chờ Apple duyệt. Bản dẫn xuất chỉ được dùng thêm
 * trong thẻ <picture> ở web, còn <img> vẫn trỏ ảnh gốc làm bản dự phòng.
 *
 * Cắt về đúng tỉ lệ 2:3 vì CSS đang `aspect-ratio:2/3; object-fit:cover` — để
 * trình duyệt tự cắt nghĩa là tải về những hàng điểm ảnh rồi vứt đi.
 */
class DeriveCovers extends Command
{
    protected $signature = 'covers:derive {--force : làm lại cả những bản đã có}';

    protected $description = 'Sinh ảnh bìa WebP 2:3 cho web (320w và 640w)';

    /** Bề rộng cần: 320 cho lưới ở 1x, 640 cho màn Retina và ảnh lớn trang truyện. */
    private const WIDTHS = [320, 640];

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('PHP thiếu hỗ trợ WebP của GD.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $made = 0;
        $saved = 0;

        foreach (Story::whereNotNull('thumbnail')->get() as $story) {
            $src = (string) $story->thumbnail;

            if (! $disk->exists($src)) {
                $this->warn("thiếu file: {$src}");

                continue;
            }

            $original = $disk->path($src);
            $image = @imagecreatefromstring((string) file_get_contents($original));

            if ($image === false) {
                $this->warn("không đọc được ảnh: {$src}");

                continue;
            }

            foreach (self::WIDTHS as $width) {
                $target = self::derivedPath($src, $width);

                if (! $this->option('force') && $disk->exists($target)
                    && $disk->lastModified($target) >= $disk->lastModified($src)) {
                    continue;
                }

                $height = (int) round($width * 1.5);   // 2:3
                $canvas = imagecreatetruecolor($width, $height);

                // Cắt giữa theo chiều dài hơn, giống hệt object-fit:cover.
                $sw = imagesx($image);
                $sh = imagesy($image);
                $scale = max($width / $sw, $height / $sh);
                $cw = (int) round($width / $scale);
                $ch = (int) round($height / $scale);

                imagecopyresampled(
                    $canvas, $image,
                    0, 0,
                    (int) round(($sw - $cw) / 2), (int) round(($sh - $ch) / 2),
                    $width, $height, $cw, $ch,
                );

                ob_start();
                imagewebp($canvas, null, 82);
                $binary = (string) ob_get_clean();
                imagedestroy($canvas);

                $disk->put($target, $binary);
                $made++;
                $saved += filesize($original) - strlen($binary);

                $this->line(sprintf('  %-48s %sw  %d KB', basename($target), $width, strlen($binary) / 1024));
            }

            imagedestroy($image);
        }

        $this->info(sprintf('Xong: %d bản dẫn xuất.', $made));

        return self::SUCCESS;
    }

    /** stories/x.jpg + 320 -> stories/derived/x-320.webp */
    public static function derivedPath(string $source, int $width): string
    {
        $dir = trim(dirname($source), '.');
        $name = pathinfo($source, PATHINFO_FILENAME);

        return ($dir !== '' ? $dir.'/' : '').'derived/'.$name.'-'.$width.'.webp';
    }
}
