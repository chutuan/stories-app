<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();

            // Expo push token, dạng "ExponentPushToken[...]". Không gắn với tài khoản nào
            // (app không có tài khoản): tắt thông báo trong app là xoá hẳn dòng này.
            $table->string('token', 191)->unique();
            $table->string('platform', 8);

            // Múi giờ IANA của máy, để gửi đúng giờ tối của từng người thay vì một giờ
            // UTC chung — 19:00 ở New York là 7 giờ sáng ở Việt Nam.
            $table->string('timezone', 64)->default('UTC');

            // Ngày (theo giờ địa phương của máy) đã gửi — chốt "mỗi ngày một lần".
            $table->date('last_notified_on')->nullable();
            // Truyện đã báo lần trước, để không báo lại đúng truyện đó hôm sau.
            $table->unsignedBigInteger('last_story_id')->nullable();

            $table->timestamps();

            $table->index('timezone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
