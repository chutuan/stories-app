<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi tiến trình sinh giọng đọc AI.
 *
 * Vì sinh audio chạy trong HÀNG ĐỢI (App\Jobs\GenerateChapterAudio), admin bấm nút xong
 * là trả về ngay -> cần cột trạng thái để biết job đang chạy tới đâu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            // null | queued | processing | done | failed
            $table->string('audio_status', 20)->nullable()->after('audio_path');
            // Thông báo lỗi của lần chạy gần nhất (chỉ có ý nghĩa khi audio_status = failed).
            $table->text('audio_error')->nullable()->after('audio_status');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['audio_status', 'audio_error']);
        });
    }
};
