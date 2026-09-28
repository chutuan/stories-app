<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cấu hình chỉnh được từ trang admin mà không cần deploy hay build app mới.
        // Mỗi dòng một khoá. Danh sách khoá hợp lệ, kiểu và giá trị mặc định nằm ở
        // App\Support\AppSettings — khoá chưa có dòng nào thì dùng mặc định ở đó.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
