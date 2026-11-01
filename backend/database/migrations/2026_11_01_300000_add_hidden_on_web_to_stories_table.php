<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Truyện ẩn trên web, app vẫn hiện bình thường.
 *
 * Web chạy AdSense: truyện có nội dung nhà quảng cáo chặn (tình dục, máu me, tự sát,
 * bạo hành trẻ em…) mà nằm trên web thì trang đó mất quảng cáo, nặng thì cả site trượt
 * duyệt. Tuấn chọn (01/11/2026) chỉ giấu những truyện này khỏi web — app giữ nguyên.
 *
 * Khác `noindex`: noindex vẫn đọc được trên web, chỉ bảo Google đừng lập chỉ mục.
 * `hidden_on_web` thì web coi như truyện không tồn tại (404, không ở lưới, sitemap, tìm kiếm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->boolean('hidden_on_web')->default(false)->after('noindex');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('hidden_on_web');
        });
    }
};
