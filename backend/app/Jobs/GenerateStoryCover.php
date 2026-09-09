<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Story;
use App\Services\StoryCoverGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vẽ ảnh bìa AI cho 1 truyện trong HÀNG ĐỢI.
 *
 * Vì sao phải xếp hàng: mỗi bìa là HAI request tới OpenAI (đọc truyện viết chỉ đạo,
 * rồi vẽ ảnh), riêng bước vẽ ở chất lượng cao mất hàng chục giây. Chạy thẳng trong
 * request HTTP thì nginx/php-fpm trên production sẽ timeout (502/504).
 *
 * Trạng thái ghi vào `stories.cover_status`: queued -> processing -> done | failed
 */
class GenerateStoryCover implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * MỘT TRUYỆN CHỈ CÓ MỘT ẢNH BÌA — và mỗi lần vẽ là một lần trả tiền.
     * Khoá theo id truyện: trong lúc một job còn nằm trong hàng đợi hoặc đang chạy,
     * mọi lần dispatch trùng đều bị bỏ đi thay vì xếp thêm. Khoá tự nhả khi job xong
     * hoặc sau `uniqueFor` giây (phòng khi tiến trình chết giữa chừng).
     */
    public function uniqueId(): string
    {
        return 'story-cover-'.$this->story->id;
    }

    /** Bằng đúng $timeout: job chết bất thường thì khoá không kẹt lâu hơn job. */
    public int $uniqueFor = 900;

    /** Rộng rãi cho 2 request nối nhau (bước vẽ đã có timeout 300s + retry). */
    public int $timeout = 900;

    /** Thử lại 1 lần nữa nếu OpenAI lỗi tạm thời (429 / 5xx / rớt mạng). */
    public int $tries = 2;

    /** Không tính lần chạy quá giờ là "hết lượt" âm thầm — cứ để thất bại rõ ràng. */
    public bool $failOnTimeout = true;

    public function __construct(
        public Story $story,
        public bool $force = false,
    ) {}

    /** Chờ 60s trước khi thử lại (tránh dập liên tiếp vào rate limit của OpenAI). */
    public function backoff(): int
    {
        return 60;
    }

    public function handle(StoryCoverGenerator $generator): void
    {
        // Service tự cập nhật processing/done/failed vào stories.cover_status.
        $path = $generator->generate($this->story, $this->force);

        Log::info('Đã vẽ ảnh bìa cho truyện.', [
            'story_id' => $this->story->id,
            'slug' => $this->story->slug,
            'path' => $path,
        ]);
    }

    /**
     * Chạy khi job hết lượt thử (hoặc quá giờ): ghi log và bảo đảm truyện không bị
     * kẹt ở trạng thái processing, kể cả khi lỗi xảy ra ngoài service.
     */
    public function failed(?Throwable $e): void
    {
        $message = $e?->getMessage() ?? 'Job thất bại không rõ nguyên nhân.';

        Log::error('Vẽ ảnh bìa THẤT BẠI.', [
            'story_id' => $this->story->id,
            'slug' => $this->story->slug,
            'force' => $this->force,
            'error' => $message,
            'exception' => $e ? get_class($e) : null,
        ]);

        // Lấy bản mới nhất: truyện có thể đã bị xoá trong lúc job chạy.
        $story = Story::find($this->story->id);
        $story?->forceFill([
            'cover_status' => 'failed',
            'cover_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
