<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Dựng ảnh chia sẻ mạng xã hội (Open Graph) 1200×630 cho từng truyện.
 *
 * Vì sao cần: ảnh bìa truyện là khổ DỌC 600×800 (tỉ lệ 0,75), còn ô xem trước của
 * Facebook, Zalo, Twitter và iMessage là khổ NGANG 1,91:1. Đưa ảnh dọc vào ô ngang
 * thì các nền tảng cắt lấy dải giữa — thường ra khúc thân người, mất mặt nhân vật
 * và mất luôn toàn bộ bố cục đã dựng công phu. Tệ hơn, Twitter có thể hạ cấp thẻ
 * `summary_large_image` xuống ô vuông nhỏ khi ảnh sai tỉ lệ.
 *
 * Cách dựng: chính ảnh bìa làm nền (phóng to, làm mờ, phủ tối) để giữ đúng tông
 * màu của truyện, rồi đặt ảnh bìa sắc nét bên trái và chữ bên phải.
 *
 * Ảnh được ĐỆM vào đĩa và chỉ dựng lại khi ảnh bìa đổi (so theo mtime), vì mỗi lần
 * dựng tốn khoảng 150ms CPU — không đáng làm lại cho mỗi lượt trình thu thập ghé qua.
 */
class SocialCardGenerator
{
    private const W = 1200;

    private const H = 630;

    /** Cột ảnh bìa sắc nét bên trái. 3:4 nên 380 rộng ứng với 507 cao, vừa khung. */
    private const COVER_W = 380;

    /**
     * Font theo thứ tự ưu tiên. Máy chủ Ubuntu có DejaVu; máy dev macOS thì không,
     * nên phải dò thay vì viết cứng — imagettftext gặp đường dẫn không tồn tại sẽ
     * ném lỗi và cả ảnh hỏng, mà lỗi đó chỉ lộ ra khi có người bấm chia sẻ.
     *
     * @var array<string, list<string>>
     */
    private const FONTS = [
        'serif-bold' => [
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Georgia Bold.ttf',
            '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf',
        ],
        'sans' => [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ],
        'sans-bold' => [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ],
    ];

    /** @var array<string, string> */
    private array $resolved = [];

    private function font(string $key): string
    {
        return $this->resolved[$key] ??= (function () use ($key): string {
            foreach (self::FONTS[$key] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }

            throw new RuntimeException("Không tìm thấy font cho '{$key}'. Cài fonts-dejavu-core.");
        })();
    }

    /**
     * Trả về đường dẫn tương đối trên disk `public`, dựng lại nếu cần.
     *
     * @return string ví dụ `og/the-sixty-dollar-suit.jpg`
     */
    public function forStory(Story $story): string
    {
        $path = 'og/'.$story->slug.'.jpg';
        $disk = Storage::disk('public');

        // Đệm còn dùng được khi nó MỚI HƠN ảnh bìa. So theo mtime chứ không theo
        // updated_at của truyện: sửa mô tả truyện không làm ảnh chia sẻ khác đi,
        // mà vẽ lại bìa thì có.
        if ($disk->exists($path) && $story->thumbnail && $disk->exists($story->thumbnail)
            && $disk->lastModified($path) >= $disk->lastModified($story->thumbnail)) {
            return $path;
        }

        $disk->put($path, $this->render($story));

        return $path;
    }

    /** @return string dữ liệu JPEG */
    private function render(Story $story): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Thiếu phần mở rộng GD, không dựng được ảnh chia sẻ.');
        }

        $canvas = imagecreatetruecolor(self::W, self::H);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 26, 22, 20));

        $cover = $this->loadCover($story);

        if ($cover) {
            $this->paintBackdrop($canvas, $cover);
            $this->paintCover($canvas, $cover);
            imagedestroy($cover);
        }

        $this->paintText($canvas, $story, hasCover: $cover !== null);

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return $jpeg;
    }

    /** @return \GdImage|null */
    private function loadCover(Story $story)
    {
        if (! $story->thumbnail || ! Storage::disk('public')->exists($story->thumbnail)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($story->thumbnail);

        return @imagecreatefromstring((string) $bytes) ?: null;
    }

    /**
     * Nền: phóng ảnh bìa phủ kín khung, làm mờ, rồi phủ tối cho chữ nổi lên.
     *
     * Làm mờ trên bản THU NHỎ rồi mới phóng to: bộ lọc gaussian của GD chạy theo
     * từng điểm ảnh nên mờ thẳng ở 1200×630 tốn hàng trăm mili-giây, trong khi mờ
     * ở 60×32 rồi phóng lên cho kết quả nhìn còn mượt hơn.
     */
    private function paintBackdrop($canvas, $cover): void
    {
        $small = imagecreatetruecolor(60, 32);
        imagecopyresampled($small, $cover, 0, 0, 0, 0, 60, 32,
            imagesx($cover), imagesy($cover));
        for ($i = 0; $i < 3; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagecopyresampled($canvas, $small, 0, 0, 0, 0, self::W, self::H, 60, 32);
        imagedestroy($small);

        // Phủ tối. imagefilledrectangle không có alpha nên dùng lớp phủ riêng.
        $veil = imagecreatetruecolor(self::W, self::H);
        imagefill($veil, 0, 0, imagecolorallocate($veil, 20, 16, 14));
        imagecopymerge($canvas, $veil, 0, 0, 0, 0, self::W, self::H, 72);
        imagedestroy($veil);
    }

    /** Ảnh bìa sắc nét bên trái, canh giữa theo chiều dọc, cắt theo 3:4. */
    private function paintCover($canvas, $cover): void
    {
        $h = (int) round(self::COVER_W * 4 / 3);
        $x = 64;
        $y = (int) round((self::H - $h) / 2);

        [$sx, $sy, $sw, $sh] = $this->coverCrop(imagesx($cover), imagesy($cover), self::COVER_W / $h);

        // Viền sáng mảnh quanh ảnh để tách khỏi nền mờ
        $edge = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, $x - 2, $y - 2, $x + self::COVER_W + 1, $y + $h + 1, $edge);

        imagecopyresampled($canvas, $cover, $x, $y, $sx, $sy, self::COVER_W, $h, $sw, $sh);
    }

    /**
     * Vùng cắt giữa ảnh nguồn sao cho đúng tỉ lệ đích mà không bóp méo.
     *
     * @return array{0:int,1:int,2:int,3:int} [x, y, rộng, cao]
     */
    private function coverCrop(int $w, int $h, float $targetRatio): array
    {
        if ($w / $h > $targetRatio) {          // nguồn rộng hơn -> cắt hai bên
            $sw = (int) round($h * $targetRatio);

            return [(int) round(($w - $sw) / 2), 0, $sw, $h];
        }

        $sh = (int) round($w / $targetRatio);  // nguồn cao hơn -> cắt trên dưới

        return [0, (int) round(($h - $sh) / 2), $w, $sh];
    }

    private function paintText($canvas, Story $story, bool $hasCover): void
    {
        $left = $hasCover ? 64 + self::COVER_W + 56 : 72;
        $right = self::W - 64;
        $maxW = $right - $left;

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $accent = imagecolorallocate($canvas, 255, 144, 82);
        $muted = imagecolorallocate($canvas, 214, 203, 196);

        // Nhãn thương hiệu trên cùng
        imagettftext($canvas, 19, 0, $left, 132, $accent, $this->font('sans-bold'), 'STORIES');

        // Tiêu đề: giảm cỡ dần cho tới khi vừa 3 dòng, tránh cắt cụt tên truyện dài
        $title = (string) $story->title;
        foreach ([54, 48, 42, 37] as $size) {
            $lines = $this->wrap($title, $this->font('serif-bold'), $size, $maxW);
            if (count($lines) <= 3) {
                break;
            }
        }
        $lines = array_slice($lines, 0, 3);

        $y = 212;
        foreach ($lines as $line) {
            imagettftext($canvas, $size, 0, $left, $y, $white, $this->font('serif-bold'), $line);
            $y += (int) round($size * 1.32);
        }

        // Dòng phụ: tác giả · số chương · trạng thái
        $meta = array_filter([
            $story->author ?: null,
            $story->chapters()->count().' chapters',
            $story->free_chapters >= $story->chapters()->count() ? 'Free to read' : null,
        ]);
        imagettftext($canvas, 21, 0, $left, $y + 34, $muted, $this->font('sans'), implode('  ·  ', $meta));

        // Chân: tên miền
        imagettftext($canvas, 18, 0, $left, self::H - 62, $accent, $this->font('sans-bold'), 'tunastory.com');
    }

    /**
     * Ngắt dòng theo bề rộng THẬT của chữ (imagettfbbox) chứ không theo số ký tự:
     * "Whose Son Are You" và "The Bracelet on the Wrong Child" cùng đếm ký tự vẫn
     * cho ra hai bề rộng rất khác nhau.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $try);
            if ($box && ($box[2] - $box[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [$text];
    }
}
