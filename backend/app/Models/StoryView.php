<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một người đọc, một truyện (hoặc một chương), một ngày, một nguồn.
 *
 * Không phải mỗi lần tải trang một dòng: lần đầu trong ngày tạo dòng mới, các lần
 * sau chỉ cộng vào `hits`. Nhờ vậy đếm được cả hai con số mà chỉ tốn một bảng —
 * số dòng là lượt xem duy nhất, tổng `hits` là tổng lượt xem.
 */
class StoryView extends Model
{
    protected $fillable = ['story_id', 'chapter_id', 'source', 'device', 'visitor_hash', 'viewed_on', 'hits'];

    protected $casts = ['viewed_on' => 'date', 'hits' => 'integer', 'chapter_id' => 'integer'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
