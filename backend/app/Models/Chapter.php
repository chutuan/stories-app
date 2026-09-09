<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
