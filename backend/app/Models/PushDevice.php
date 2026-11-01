<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một máy đã bật thông báo "truyện mới mỗi ngày" trong app di động.
 * Xem App\Services\DailyStoryNotifier.
 */
class PushDevice extends Model
{
    protected $fillable = ['token', 'platform', 'timezone', 'last_notified_on', 'last_story_id'];

    protected $casts = ['last_notified_on' => 'date', 'last_story_id' => 'integer'];
}
