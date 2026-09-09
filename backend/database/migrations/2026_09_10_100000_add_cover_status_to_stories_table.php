<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi tiến trình vẽ ảnh bìa bằng AI.
 *
 * Song song với chapters.audio_status: vẽ bìa cũng chạy trong HÀNG ĐỢI
 * (App\Jobs\GenerateStoryCover) nên cần cột trạng thái để biết job tới đâu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // null | queued | processing | done | failed
            $table->string('cover_status', 20)->nullable()->after('thumbnail');
            // Thông báo lỗi của lần chạy gần nhất (chỉ có nghĩa khi cover_status = failed).
            $table->text('cover_error')->nullable()->after('cover_status');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['cover_status', 'cover_error']);
        });
    }
};
