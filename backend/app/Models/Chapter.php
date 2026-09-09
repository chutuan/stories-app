<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chapter extends Model
{
    protected $fillable = [
        'story_id',
        'number',
        'title',
        'content',
        'audio_path',
        'published_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Chương ĐÃ tới giờ đăng.
     * NULL = đăng ngay (dữ liệu có từ trước tính năng hẹn giờ), nên phải kèm
     * whereNull chứ không chỉ so sánh thời gian.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('published_at')
            ->orWhere('published_at', '<=', now()));
    }

    /** Chương CHƯA tới giờ đăng. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '>', now());
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
