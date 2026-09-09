<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Email mặc định khi không khai báo ADMIN_EMAIL.
     */
    private const DEFAULT_EMAIL = 'admin@stories.test';

    /**
     * Mật khẩu dự phòng CHỈ dùng cho môi trường phát triển (local/testing).
     * Trên production bắt buộc phải khai báo ADMIN_PASSWORD.
     */
    private const DEV_FALLBACK_PASSWORD = 'password';

    public function run(): void
    {
        // Đọc qua config (config/admin.php) để vẫn hoạt động sau `php artisan config:cache`,
        // có fallback gọi thẳng env() phòng trường hợp file config bị thiếu.
        $email = trim((string) (config('admin.email') ?? env('ADMIN_EMAIL', self::DEFAULT_EMAIL)));

        if ($email === '') {
            $email = self::DEFAULT_EMAIL;
        }

        $password = (string) (config('admin.password') ?? env('ADMIN_PASSWORD', ''));

        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'Thiếu biến môi trường ADMIN_PASSWORD. Trên môi trường production, seeder từ chối '
                    .'tạo tài khoản quản trị bằng mật khẩu mặc định yếu. Hãy khai báo ADMIN_PASSWORD '
                    .'(và ADMIN_EMAIL nếu muốn đổi email) trong file .env của máy chủ rồi chạy lại lệnh seed. '
                    .'Gợi ý sinh mật khẩu mạnh: openssl rand -base64 24'
                );
            }

            $password = self::DEV_FALLBACK_PASSWORD;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );
    }
}
