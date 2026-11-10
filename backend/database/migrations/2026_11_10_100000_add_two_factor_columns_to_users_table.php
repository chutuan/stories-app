<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xác thực 2 bước (TOTP) cho tài khoản quản trị — Tuấn yêu cầu 10/11/2026.
 *
 * - two_factor_secret: khoá base32 của app xác thực, MÃ HOÁ bằng APP_KEY (cast 'encrypted').
 * - two_factor_recovery_codes: HMAC của các mã khôi phục (không lưu mã gốc), cũng mã hoá.
 * - two_factor_confirmed_at: null = chưa bật xong; middleware bắt bật trước khi vào admin.
 * - two_factor_last_used_step: bước thời gian (30 giây) của mã cuối cùng đã dùng, để một mã
 *   bị nhìn trộm không dùng lại được trong lúc nó còn hiệu lực.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_step',
            ]);
        });
    }
};
