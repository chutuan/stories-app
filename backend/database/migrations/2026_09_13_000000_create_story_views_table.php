<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();

            // 0 = trang giới thiệu truyện, khác 0 = một chương cụ thể.
            //
            // Cố ý KHÔNG đặt khoá ngoại và KHÔNG cho null. Không null vì MySQL coi
            // mỗi NULL là một giá trị khác nhau, nên khoá duy nhất bên dưới sẽ
            // không gộp được lượt xem trang truyện. Không khoá ngoại vì đây là sổ
            // ghi việc đã xảy ra: xoá một chương không làm cho lượt đọc chương đó
            // chưa từng tồn tại, và cũng không nên kéo theo việc trừ lượt xem của
            // cả truyện.
            $table->unsignedBigInteger('chapter_id')->default(0);

            // 'app' = ứng dụng di động gọi /api/*, 'web' = trình duyệt vào trang web.
            $table->string('source', 8);
            // 'mobile' | 'desktop'. Với 'app' thì luôn là mobile.
            $table->string('device', 8);

            // Băm từ IP + User-Agent + ngày, có khoá ứng dụng. Xem App\Support\ViewCounter
            // để biết vì sao không dùng cookie.
            $table->char('visitor_hash', 64);
            $table->date('viewed_on');

            // Số lượt trong cùng ngày của cùng người. Số dòng = lượt xem duy nhất,
            // tổng cột này = tổng lượt xem.
            $table->unsignedInteger('hits')->default(1);

            $table->timestamps();

            $table->unique(['story_id', 'chapter_id', 'visitor_hash', 'viewed_on', 'source'], 'story_views_unique');
            $table->index(['story_id', 'viewed_on']);
            $table->index(['viewed_on', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_views');
    }
};
