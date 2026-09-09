<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Chapter;
use App\Services\ChapterAudioGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinh giọng đọc AI cho 1 chương trong HÀNG ĐỢI.
 *
 * Vì sao phải đưa vào hàng đợi: mỗi chương dài bị cắt thành nhiều đoạn, mỗi đoạn là 1 request
 * tới OpenAI TTS rồi nối lại bằng ffmpeg -> tổng cộng hàng chục giây tới vài phút.
 * Nếu chạy thẳng trong request HTTP thì nginx/php-fpm trên production sẽ timeout (502/504).
 *
 * Trạng thái được ghi vào `chapters.audio_status` để admin theo dõi:
 *   queued -> processing -> done | failed
 */
class GenerateChapterAudio implements ShouldQueue
{
    use Queueable;

    /**
     * Đủ dài cho chương nhiều đoạn (mỗi request TTS đã có timeout 180s + retry).
     * LƯU Ý khi chạy worker phải để --timeout >= giá trị này, xem SPEC mục 9.
     */
    public int $timeout = 900;

    /** Thử lại 1 lần nữa nếu OpenAI lỗi tạm thời (429 / 5xx / rớt mạng). */
    public int $tries = 2;

    /** Không tính lần chạy quá giờ là "hết lượt" một cách âm thầm — cứ để thất bại rõ ràng. */
    public bool $failOnTimeout = true;

    public function __construct(
        public Chapter $chapter,
        public bool $force = false,
    ) {}

    /** Chờ 60s trước khi thử lại (tránh dập liên tiếp vào rate limit của OpenAI). */
    public function backoff(): int
    {
        return 60;
    }

    public function handle(ChapterAudioGenerator $generator): void
    {
        // Service tự cập nhật processing/done/failed vào chapters.audio_status.
        $path = $generator->generate($this->chapter, $this->force);

        Log::info('Đã sinh audio cho chương.', [
            'chapter_id' => $this->chapter->id,
            'story_id' => $this->chapter->story_id,
            'number' => $this->chapter->number,
            'path' => $path,
        ]);
    }

    /**
     * Chạy khi job hết lượt thử (hoặc quá giờ). Ghi log rõ ràng và bảo đảm chương
     * không bị kẹt ở trạng thái processing — kể cả khi lỗi xảy ra ngoài service.
     */
    public function failed(?Throwable $e): void
    {
        $message = $e?->getMessage() ?? 'Job thất bại không rõ nguyên nhân.';

        Log::error('Sinh audio cho chương THẤT BẠI.', [
            'chapter_id' => $this->chapter->id,
            'story_id' => $this->chapter->story_id,
            'number' => $this->chapter->number,
            'force' => $this->force,
            'error' => $message,
            'exception' => $e ? get_class($e) : null,
        ]);

        // Lấy bản mới nhất trong DB: chương có thể đã bị xóa trong lúc job chạy.
        $chapter = Chapter::find($this->chapter->id);
        $chapter?->forceFill([
            'audio_status' => 'failed',
            'audio_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
