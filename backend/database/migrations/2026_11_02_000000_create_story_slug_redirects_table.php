<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slug cũ của truyện → truyện hiện tại, để URL cũ 301 sang URL mới vĩnh viễn.
 *
 * Tuấn chọn (01/11/2026) đổi slug theo tiêu đề: 140 slug không còn liên quan tới tên
 * truyện (tên đã rút gọn, slug giữ nguyên) và 222 slug dài quá 80 ký tự. Google đã
 * index và người đọc đã lưu URL cũ, nên slug cũ KHÔNG được chết — Story tự ghi một dòng
 * vào đây mỗi khi slug đổi (kể cả khi admin sửa tiêu đề), và route binding
 * {story:slug} tra bảng này khi không tìm thấy truyện. Xem Story::resolveRouteBinding().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_slug_redirects');
    }
};
