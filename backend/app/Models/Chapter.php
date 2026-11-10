<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    protected $fillable = [
        'story_id',
        'number',
        'title',
        'content',
        'audio_path',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    /**
     * Tên chương để HIỂN THỊ, đã bỏ phần trùng với số chương; chuỗi rỗng nếu tên chỉ là số.
     *
     * Nhiều tên trong kho mang sẵn tiền tố "Chapter 3: ..." nên in thẳng ra "Chapter 3:
     * Chapter 3: ...". Lại có 148 chương (truyện nhập từ blog) tên đúng "Part 2" — số
     * phần đó trùng số chương, nên tiêu đề từng ra "Chapter 2: Part 2" và mô tả meta
     * "Part 2, chapter 2 of …". Trả rỗng để nơi gọi tự dùng "Chapter N".
     */
    public function cleanTitle(): string
    {
        $title = trim(preg_replace('/^\s*Chapter\s+\d+\s*[:\-–—.]\s*/iu', '', (string) $this->title) ?? '');

        return preg_match('/^(?:Chapter|Part)\s+\d+\.?$/iu', $title) ? '' : $title;
    }

    /** Phiếu hài lòng ở cuối chương — chỉ bản web dùng, xem ChapterReaction. */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChapterReaction::class);
    }

    /** Lượt bấm nghe audio trong app, mỗi máy một dòng — xem ChapterAudioRequest. */
    public function audioRequests(): HasMany
    {
        return $this->hasMany(ChapterAudioRequest::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    protected function isFree(): Attribute
    {
        return Attribute::get(function () {
            $freeChapters = $this->relationLoaded('story') && $this->story
                ? $this->story->free_chapters
                : ($this->story()->value('free_chapters') ?? 1);

            return $this->number <= $freeChapters;
        });
    }

    /**
     * URL tuyệt đối tới file MP3 của chương (disk public) + `?v=<mtime>`.
     * Trả null nếu chương chưa có audio hoặc file đã bị xóa khỏi disk.
     */
    protected function audioUrl(): Attribute
    {
        return Attribute::get(fn () => PublicFileUrl::make($this->audio_path));
    }

    protected function hasAudio(): Attribute
    {
        return Attribute::get(fn () => $this->audio_url !== null);
    }

    /**
     * Nhãn tiếng Việt của tiến trình sinh giọng đọc AI (cột `audio_status`).
     * Trả null khi chương chưa từng được đưa vào hàng đợi.
     */
    protected function audioStatusLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->audio_status) {
            'queued' => 'Đang chờ',
            'processing' => 'Đang tạo',
            'done' => 'Xong',
            'failed' => 'Lỗi',
            default => null,
        });
    }

    /** Class badge Bootstrap tương ứng với `audio_status`. */
    protected function audioStatusBadge(): Attribute
    {
        return Attribute::get(fn () => match ($this->audio_status) {
            'queued' => 'bg-secondary',
            'processing' => 'bg-info text-dark',
            'done' => 'bg-success',
            'failed' => 'bg-danger',
            default => 'bg-light text-dark',
        });
    }
}
