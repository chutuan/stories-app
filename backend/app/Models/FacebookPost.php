<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một truyện đã đăng lên Page Facebook — xem migration create_facebook_posts_table. */
class FacebookPost extends Model
{
    protected $fillable = [
        'story_id',
        'post_id',
        'comment_id',
        'posted_at',
        'error',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
