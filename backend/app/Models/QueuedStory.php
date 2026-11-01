<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một truyện trong kho chờ đăng — xem migration create_story_queue_table. */
class QueuedStory extends Model
{
    protected $table = 'story_queue';

    protected $fillable = [
        'source',
        'source_key',
        'title',
        'position',
        'payload',
        'cover_path',
        'noindex',
        'status',
        'story_id',
        'released_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'noindex' => 'boolean',
        'position' => 'integer',
        'released_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /** Hàng chờ, theo đúng thứ tự sẽ đăng. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')->orderBy('position')->orderBy('id');
    }
}
