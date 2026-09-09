<?php

/*
|--------------------------------------------------------------------------
| Cấu hình CORS (Cross-Origin Resource Sharing)
|--------------------------------------------------------------------------
|
| API /api của ứng dụng là API công khai CHỈ ĐỌC (không đăng nhập, không cookie),
| nên mặc định cho phép mọi origin. Nếu muốn siết lại, đặt biến môi trường
| CORS_ALLOWED_ORIGINS trong .env với danh sách origin cách nhau bởi dấu phẩy, ví dụ:
|
|   CORS_ALLOWED_ORIGINS=https://stories.example.com,https://admin.example.com
|
| Middleware xử lý CORS (Illuminate\Http\Middleware\HandleCors) đã nằm sẵn trong
| nhóm middleware toàn cục của Laravel 12 nên không cần đăng ký thêm.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
), static fn (string $origin): bool => $origin !== ''));

return [

    // Các đường dẫn được áp dụng CORS: API công khai và file tĩnh trong storage
    // (ảnh bìa truyện, file audio TTS) khi chúng được phục vụ qua PHP.
    'paths' => ['api/*', 'storage/*'],

    // API chỉ đọc: chỉ cần GET/HEAD và OPTIONS (preflight).
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    // Lấy từ env CORS_ALLOWED_ORIGINS, mặc định '*' cho API công khai chỉ đọc.
    'allowed_origins' => $origins !== [] ? $origins : ['*'],

    // Không dùng mẫu regex cho origin.
    'allowed_origins_patterns' => [],

    // Cho phép mọi header từ client (Accept, Content-Type, Range...).
    'allowed_headers' => ['*'],

    // Header cho phép JavaScript phía client đọc được (hỗ trợ tải audio theo Range).
    'exposed_headers' => ['Content-Length', 'Content-Range', 'Accept-Ranges'],

    // Thời gian (giây) trình duyệt được cache kết quả preflight. 24 giờ.
    'max_age' => 86400,

    // API không dùng cookie/session nên KHÔNG bật credentials.
    // (Bật cùng lúc với allowed_origins = '*' cũng bị trình duyệt từ chối.)
    'supports_credentials' => false,

];
