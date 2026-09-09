<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /**
     * API ghi cho máy (/api/ingest). Không đặt token = nhóm route tắt hoàn toàn.
     * Sinh token: `php -r "echo bin2hex(random_bytes(32));"`
     */
    'ingest' => [
        'token' => env('INGEST_TOKEN'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'tts_model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        // Giới hạn input mỗi request của /v1/audio/speech là 4096 ký tự.
        'tts_chunk_chars' => (int) env('OPENAI_TTS_CHUNK_CHARS', 3500),

        // --- Ảnh bìa (App\Services\StoryCoverGenerator) ---
        // Model ĐỌC truyện để viết chỉ đạo hình ảnh. Rẻ, chạy trước mỗi lần vẽ bìa.
        'cover_prompt_model' => env('OPENAI_COVER_PROMPT_MODEL', 'gpt-4o-mini'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        // Khổ dọc của gpt-image-1 (2:3); service cắt xuống 3:4 rồi thu về 600x800.
        'image_size' => env('OPENAI_IMAGE_SIZE', '1024x1536'),
        // low | medium | high | auto — bìa là thứ người dùng nhìn đầu tiên nên để high.
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'high'),
    ],

];