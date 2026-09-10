<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    | Email hỗ trợ hiển thị trên trang công khai (privacy/terms). Store yêu cầu
    | phải có kênh liên hệ thật.
    */
    'support_email' => env('SUPPORT_EMAIL', 'support@tunastory.com'),

    /*
    | Publisher ID của AdMob (dạng pub-XXXXXXXXXXXXXXXX), dùng để phục vụ
    | /app-ads.txt. Để trống -> route trả 404.
    */
    'admob_publisher_id' => env('ADMOB_PUBLISHER_ID'),

    /*
     | Ad unit AdSense cho website. Bỏ trống thì các ô quảng cáo trong bài KHÔNG
     | được in ra (xem resources/views/public/partials/ad.blade.php) — chỉ riêng
     | thẻ script trong layout vẫn đủ để chạy Auto ads.
     |
     | Lấy giá trị ở AdSense -> Quảng cáo -> Theo đơn vị quảng cáo -> mã data-ad-slot.
     */
    'adsense_slot_article' => env('ADSENSE_SLOT_ARTICLE'),

    /*
     | Google Analytics 4 cho WEBSITE (không phải app — app không có SDK phân tích
     | nào, và lời khai gửi Apple dựa vào điều đó).
     |
     | Bỏ trống thì thẻ gtag KHÔNG được in ra: máy dev không nên bơm dữ liệu giả
     | vào báo cáo, và mọi lần chạy test cũng không được tính thành phiên truy cập.
     */
    'ga_measurement_id' => env('GA_MEASUREMENT_ID'),

    /*
    | Ngày cập nhật văn bản pháp lý, hiện trên trang Privacy và Terms.
    */
    'legal_updated' => env('LEGAL_UPDATED', '9 September 2026'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
