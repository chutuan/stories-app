<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Slug cũ của một truyện — xem migration create_story_slug_redirects_table. */
class StorySlugRedirect extends Model
{
    protected $fillable = [
        'old_slug',
        'story_id',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
