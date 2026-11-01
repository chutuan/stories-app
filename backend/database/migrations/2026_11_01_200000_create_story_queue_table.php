<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho truyện chờ đăng: nạp sẵn cả lô, mỗi ngày lệnh `stories:release-queued` (chạy theo
 * lịch) lấy vài truyện đầu hàng ra đăng thật. Có từ 01/11/2026 cho 845 truyện kéo về từ
 * darkchapter.cafex.biz — đăng một lần thì kho gấp ba qua đêm và mục "Newest" chỉ còn
 * truyện của một nguồn; nhỏ giọt thì ngày nào cũng có truyện mới.
 *
 * `payload` là đúng một gói của `stories:import` ({story, chapters}), kiểm tra lại lúc
 * đăng chứ không tin dữ liệu lúc xếp hàng (thể loại có thể đã bị xoá trong lúc chờ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_queue', function (Blueprint $table) {
            $table->id();
            // Nguồn + khoá ở nguồn (id series Blogbio): xếp hàng lại cùng truyện là cập nhật, không nhân đôi.
            $table->string('source', 50);
            $table->string('source_key', 191);
            $table->string('title');
            // Số nhỏ đăng trước.
            $table->unsignedInteger('position');
            $table->longText('payload');
            // File bìa trên đĩa `local` (không công khai cho tới khi đăng).
            $table->string('cover_path')->nullable();
            $table->boolean('noindex')->default(false);
            // pending | released | skipped | failed
            $table->string('status', 20)->default('pending');
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_queue');
    }
};
