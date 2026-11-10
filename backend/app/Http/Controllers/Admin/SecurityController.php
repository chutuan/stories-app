<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Trang Bảo mật: bật 2FA lần đầu, chuyển 2FA sang điện thoại khác, cấp lại mã khôi phục.
 *
 * KHÔNG có nút tắt 2FA. Khoá mới chỉ nằm trong phiên (mã hoá) cho tới khi người dùng nhập
 * đúng một mã sinh từ nó — nên đổi điện thoại giữa chừng rồi bỏ dở thì khoá cũ vẫn chạy,
 * tài khoản không lúc nào rơi về trạng thái chỉ cần mật khẩu. Mất cả điện thoại lẫn mã
 * khôi phục thì gỡ trên server: `php artisan admin:2fa-reset {email}`.
 */
class SecurityController extends Controller
{
    private const SETUP_KEY = 'admin.2fa_setup';

    public const ISSUER = 'TunaStory Admin';

    public function show(Request $request): View
    {
        $user = $request->user();

        // Chưa bật thì mở sẵn khoá mới để quét ngay; đã bật thì chỉ khi bấm "Đổi điện thoại".
        if (! $user->hasTwoFactorEnabled() && ! $request->session()->has(self::SETUP_KEY)) {
            $this->startSetup($request);
        }

        $setup = null;
        if ($encrypted = $request->session()->get(self::SETUP_KEY)) {
            $secret = Crypt::decryptString($encrypted);
            $setup = [
                'qr' => Totp::qrSvg(Totp::uri($secret, $user->email, self::ISSUER)),
                'secret' => Totp::formatSecret($secret),
            ];
        }

        return view('admin.security.index', [
            'user' => $user,
            'setup' => $setup,
            'recoveryCodes' => $request->session()->get('admin.recovery_codes'),
        ]);
    }

    /** "Đổi điện thoại": tạo khoá mới (chưa thay khoá cũ). Hỏi lại mật khẩu. */
    public function start(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            $request->validate(['password' => ['required', 'current_password']]);
        }

        $this->startSetup($request);

        return redirect()->route('admin.security');
    }

    public function cancel(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            $request->session()->forget(self::SETUP_KEY);
        }

        return redirect()->route('admin.security');
    }

    /** Nhập mã từ app vừa quét: đúng thì khoá mới thay khoá cũ + cấp bộ mã khôi phục mới. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $encrypted = $request->session()->get(self::SETUP_KEY);
        if (! $encrypted) {
            return redirect()->route('admin.security')->with('error', 'Phiên cài đặt đã hết, hãy quét lại mã QR.');
        }

        $secret = Crypt::decryptString($encrypted);
        $step = Totp::verify($secret, (string) $request->input('code'));

        if ($step === null) {
            return back()->withErrors(['code' => 'Mã không đúng. Kiểm tra giờ trên điện thoại rồi thử mã mới.']);
        }

        $user = $request->user();
        $first = ! $user->hasTwoFactorEnabled();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $step,
        ]);
        $codes = $user->issueRecoveryCodes();
        // Cookie "ghi nhớ đăng nhập" phát ra trước đó (ở máy khác) hết hiệu lực.
        $user->setRememberToken(Str::random(60));
        $user->save();

        $request->session()->forget(self::SETUP_KEY);

        return redirect()->route('admin.security')
            ->with('status', $first ? 'Đã bật xác thực 2 bước.' : 'Đã chuyển xác thực 2 bước sang ứng dụng mới.')
            ->with('admin.recovery_codes', $codes);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 409);

        $codes = $user->issueRecoveryCodes();
        $user->save();

        return redirect()->route('admin.security')
            ->with('status', 'Đã tạo bộ mã khôi phục mới; bộ cũ không dùng được nữa.')
            ->with('admin.recovery_codes', $codes);
    }

    private function startSetup(Request $request): void
    {
        $request->session()->put(self::SETUP_KEY, Crypt::encryptString(Totp::generateSecret()));
    }
}
