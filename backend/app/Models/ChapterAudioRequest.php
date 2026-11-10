<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một người (máy) đã bấm nghe audio của một chương trong app — xem migration
 * create_chapter_audio_requests_table và Api\ChapterAudioRequestController.
 */
class ChapterAudioRequest extends Model
{
    protected $fillable = ['chapter_id', 'reader_hash', 'platform', 'clicks', 'unlocks'];

    protected $casts = [
        'clicks' => 'integer',
        'unlocks' => 'integer',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
