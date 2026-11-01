<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Truyện không cho Google lập chỉ mục trên web.
 *
 * Dùng cho truyện đã đăng ở site khác của chính mình (vd. 120 truyện nạp từ
 * mundodehistorias ngày 31/10/2026): hai bản giống nhau trên hai tên miền là nội
 * dung trùng lặp, Google chỉ giữ một bản và AdSense coi trang trùng là nội dung
 * kém giá trị. Truyện vẫn đọc được bình thường trên web lẫn app, chỉ có thêm
 * `<meta name="robots" content="noindex, follow">` và vắng mặt trong sitemap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->boolean('noindex')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('noindex');
        });
    }
};
