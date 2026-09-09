<?php

/*
|--------------------------------------------------------------------------
| Cấu hình API công khai
|--------------------------------------------------------------------------
|
| Ngưỡng giới hạn tần suất (rate limit) cho nhóm route "api". Đặt ở đây thay vì
| gọi thẳng env() trong AppServiceProvider để vẫn chạy đúng sau khi chạy
| `php artisan config:cache` trên production (lúc đó env() trả về null).
|
*/

return [

    'rate_limit' => [

        // Số request/phút cho mỗi IP với các endpoint API thông thường
        // (trang chủ, danh mục, danh sách truyện, chi tiết truyện).
        'default' => (int) env('API_RATE_LIMIT', 60),

        // Endpoint đọc nội dung chương được nới rộng hơn vì người dùng lật chương
        // liên tục khi đang đọc, nhưng vẫn PHẢI có trần để chặn cào dữ liệu.
        'read' => (int) env('API_RATE_LIMIT_READ', 180),

    ],

];
