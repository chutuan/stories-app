<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hẹn giờ nhả từng chương.
 *
 * AI luôn nộp truyện ĐỦ CHƯƠNG một lần, nhưng người đọc không nên thấy hết ngay:
 * chương mở dần theo lịch, mỗi chương tính từ CHƯƠNG LIỀN TRƯỚC cộng thêm nhịp.
 *
 * `chapters.published_at`
 *   NULL              = đã đăng (mọi truyện có trước tính năng này giữ nguyên hành vi)
 *   thời điểm tương lai = chưa tới giờ, API công khai phải giấu hoàn toàn
 *
 * `stories.publish_every_hours`
 *   NULL = truyện không hẹn giờ, mọi chương đăng ngay khi nạp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('number');
            // Mọi truy vấn công khai đều lọc theo (truyện, thời điểm đăng).
            $table->index(['story_id', 'published_at']);
        });

        Schema::table('stories', function (Blueprint $table) {
            // Nhịp giữa hai chương liên tiếp, tính bằng giờ. 0 = nhả hết ngay.
            $table->unsignedSmallInteger('publish_every_hours')->nullable()->after('free_chapters');
            // Mốc của chương ĐẦU TIÊN; các chương sau cộng dồn từ chương trước.
            $table->timestamp('publish_start_at')->nullable()->after('publish_every_hours');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropIndex(['story_id', 'published_at']);
            $table->dropColumn('published_at');
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['publish_every_hours', 'publish_start_at']);
        });
    }
};
