<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một phiếu hài lòng cho một chương. Chỉ dùng ở bản web.
 *
 * KHÔNG bao giờ phơi qua API cho ứng dụng di động: hồ sơ gửi Apple khai app không
 * có bình luận và không có tính năng xã hội. Phiếu này tuy chỉ là một con số,
 * nhưng để nguyên ranh giới đó cho sạch.
 */
class ChapterReaction extends Model
{
    protected $fillable = ['chapter_id', 'visitor_hash', 'score'];

    protected $casts = ['score' => 'integer'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
