<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ai đã bấm nghe audio chương nào trong app (Tuấn yêu cầu 10/11/2026).
 *
 * Nút tai nghe trong app LUÔN bật, kể cả ở chương chưa có file MP3 (chỉ ~4% chương có). Mỗi
 * lần bấm được ghi ở đây để trang admin/audio xếp chương theo số người muốn nghe, rồi Tuấn tự
 * bấm tạo giọng đọc AI cho từng chương.
 *
 * Mỗi người MỘT dòng cho mỗi chương: `clicks` cộng dồn số lần bấm, `unlocks` số lần xem hết
 * quảng cáo để mở audio chương đó (mỗi lần mở 24 giờ). Đếm "bao nhiêu người" = đếm dòng.
 *
 * Danh tính như phiếu khuôn mặt: mã ngẫu nhiên app tự sinh, băm HMAC với tiền tố "app:"
 * (Web\ReactionController::hash) — bảng không giữ thứ gì truy ngược được về máy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_audio_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->char('reader_hash', 64);
            /** 'ios' | 'android' — nền tảng lúc bấm lần đầu */
            $table->string('platform', 16)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unlocks')->default(0);
            // created_at = lần bấm đầu, updated_at = lần bấm/mở khoá gần nhất.
            $table->timestamps();

            $table->unique(['chapter_id', 'reader_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_audio_requests');
    }
};
