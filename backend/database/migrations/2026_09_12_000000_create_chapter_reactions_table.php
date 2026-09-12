<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thang hài lòng một-lần-bấm ở cuối mỗi chương, CHỈ có trên bản web.
 *
 * Cố ý KHÔNG làm bình luận: bình luận ẩn danh trên site mới bị spam trong vài giờ,
 * mà nội dung spam nằm cạnh quảng cáo là đúng thứ Google Publisher Policies phạt.
 * Nó cũng sẽ phá lời khai gửi Apple rằng app "không có bình luận, không có tính
 * năng xã hội" nếu dữ liệu đó lỡ lọt ra API.
 *
 * Điểm là 1..5 (giận dữ -> cười tươi). Không có bảng người dùng nên danh tính là
 * `visitor_hash`: một mã ngẫu nhiên lưu trong cookie dài hạn rồi băm lại. Băm để
 * bảng này không giữ thứ gì truy ngược được về người đọc, kể cả khi lộ dữ liệu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            // Mỗi người một phiếu cho mỗi chương; bấm lại là ĐỔI phiếu, không cộng thêm.
            $table->unique(['chapter_id', 'visitor_hash']);
            // Truy vấn duy nhất chạy thường xuyên: gộp điểm theo chương.
            $table->index(['chapter_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_reactions');
    }
};
