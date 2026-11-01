<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Câu mồi của truyện — dòng phụ đề dưới tựa và nửa sau của thẻ <title>.
 *
 * 01/11/2026 tựa kiểu Facebook ("Twelve hours after my husband slapped me across the
 * face in a crowded restaurant…") được rút thành tựa ngắn ("The Office My Mother Kept")
 * cho đỡ giống tin câu view. Nhưng Search Console cho thấy người đọc tìm ĐÚNG câu kiểu
 * cũ ("my husband slapped me at a restaurant for his first love"), và tựa ngắn không
 * còn chữ nào khớp. Tuấn chọn (02/11) giữ tựa ngắn làm tựa chính, đưa câu cũ về đây.
 *
 * Dữ liệu ban đầu lấy từ bản sao lưu trước khi đổi tựa
 * (/root/db-backups/stories-table-before-titles-20261101-0846.sql.gz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('hook')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('hook');
        });
    }
};
