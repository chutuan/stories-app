<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lưu Page access token của Page TunaStory vào .env — Tuấn tự chạy trên server:
 *
 *   ssh -t root@147.182.221.253 'cd /var/www/stories/backend && sudo -u www-data HOME=/tmp php artisan facebook:set-token'
 *
 * Token gõ vào ô ẩn (không hiện trên màn hình, không vào lịch sử shell, không phải dán vào chat).
 * Lệnh kiểm token với Graph API trước khi ghi: phải là token của một PAGE, không phải token
 * người dùng — dán nhầm token người dùng thì bài sẽ lên tường cá nhân thay vì Page.
 */
class SetFacebookToken extends Command
{
    protected $signature = 'facebook:set-token';

    protected $description = 'Kiểm Page access token Facebook rồi ghi FACEBOOK_PAGE_ID / FACEBOOK_PAGE_TOKEN vào .env';

    public function handle(): int
    {
        $token = trim((string) $this->secret('Dán Page access token (gõ/dán sẽ không hiện ra)'));
        if ($token === '') {
            $this->error('Chưa nhập token.');

            return self::FAILURE;
        }

        // `category` chỉ có trên Page: token người dùng hỏi trường này sẽ bị Graph API trả lỗi.
        $page = Http::timeout(20)->get($this->endpoint('me'), [
            'fields' => 'id,name,category',
            'access_token' => $token,
        ]);
        if ($page->failed() || ! $page->json('id')) {
            $this->error('Token sai, hết hạn, hoặc không phải Page token: '.($page->json('error.message') ?? $page->status()));

            return self::FAILURE;
        }

        $this->reportToken($token);

        $this->writeEnv([
            'FACEBOOK_ENABLED' => 'true',
            'FACEBOOK_PAGE_ID' => (string) $page->json('id'),
            'FACEBOOK_PAGE_TOKEN' => $token,
        ]);

        if ($this->laravel->configurationIsCached()) {
            $this->call('config:cache');
        }

        $this->info("Đã lưu token cho Page \"{$page->json('name')}\" ({$page->json('id')}). Lệnh facebook:post-next sẽ đăng ở phút 30 mỗi giờ.");

        return self::SUCCESS;
    }

    /** Thời hạn và quyền của token — chỉ để cảnh báo, hỏi không được thì bỏ qua. */
    private function reportToken(string $token): void
    {
        $data = Http::timeout(20)->get($this->endpoint('debug_token'), [
            'input_token' => $token,
            'access_token' => $token,
        ])->json('data');
        if (! is_array($data)) {
            return;
        }

        $missing = array_diff(['pages_manage_posts', 'pages_manage_engagement'], (array) ($data['scopes'] ?? []));
        if ($missing !== []) {
            $this->warn('Token thiếu quyền: '.implode(', ', $missing).' — đăng bài hoặc bình luận link sẽ lỗi.');
        }

        $expires = (int) ($data['expires_at'] ?? 0);
        $expires === 0
            ? $this->line('Token không hết hạn.')
            : $this->warn('Token hết hạn lúc '.date('Y-m-d H:i', $expires).' — nên dùng token dài hạn (đổi qua Access Token Tool rồi lấy lại từ me/accounts).');
    }

    /** @param array<string, string> $values */
    private function writeEnv(array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $env = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $env = preg_match($pattern, $env)
                ? (string) preg_replace_callback($pattern, static fn (): string => $line, $env)
                : rtrim($env, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $env);
    }

    private function endpoint(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.facebook.graph_version').'/'.$path;
    }
}
