<?php

/*
|--------------------------------------------------------------------------
| Tài khoản quản trị khởi tạo
|--------------------------------------------------------------------------
|
| Giá trị dùng cho Database\Seeders\AdminUserSeeder. Đọc từ biến môi trường
| ADMIN_EMAIL / ADMIN_PASSWORD. Khai báo qua config (thay vì gọi env() trực tiếp
| trong seeder) để vẫn đọc được sau `php artisan config:cache`.
|
| KHÔNG đặt mật khẩu thật vào file này — chỉ đặt trong .env của máy chủ.
|
*/

return [

    'email' => env('ADMIN_EMAIL', 'admin@stories.test'),

    // Không có giá trị mặc định: trên production, thiếu biến này thì seeder
    // sẽ ném RuntimeException thay vì tạo tài khoản mật khẩu yếu.
    'password' => env('ADMIN_PASSWORD'),

];
