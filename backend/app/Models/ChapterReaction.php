<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một phiếu hài lòng (1–5, năm khuôn mặt) cho một chương, từ web hoặc app.
 *
 * Web: Web\ReactionController, người bấm nhận diện bằng cookie `sid`.
 * App (từ 1.0.4, Tuấn yêu cầu 08/11/2026): Api\ChapterReactionController, nhận diện bằng
 * mã ngẫu nhiên app tự sinh và giữ trong máy — băm với tiền tố "app:" nên không lẫn với
 * cookie web. Cả hai chỉ lưu bản băm HMAC, không lưu mã gốc.
 *
 * Chỉ là một con số, KHÔNG có chữ người dùng viết: không phải bình luận hay nội dung do
 * người dùng tạo theo nghĩa Guideline 1.2, nên không cần báo cáo/chặn. Đừng thêm ô chữ
 * vào đây mà không tính lại chuyện đó.
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
