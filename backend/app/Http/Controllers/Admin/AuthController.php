<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Phiên đăng nhập đang chờ mã 2FA (đúng mật khẩu, CHƯA đăng nhập). */
    public const PENDING_KEY = 'admin.2fa_pending';

    /** Sai mật khẩu quá ngần này lần trong một phút (cùng email + IP) thì khoá tạm. */
    private const MAX_PASSWORD_ATTEMPTS = 5;

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Bước 1: mật khẩu. Tài khoản đã bật 2FA thì CHƯA đăng nhập ở đây mà chuyển sang trang nhập
     * mã (TwoFactorChallengeController); tài khoản chưa bật thì đăng nhập rồi bị middleware
     * admin.2fa đưa thẳng tới trang bật.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Trước 10/11/2026 trang này không giới hạn số lần thử. Đếm theo email + IP để người
        // khác đoán bừa không khoá được Tuấn ở máy của Tuấn.
        $throttleKey = 'admin-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PASSWORD_ATTEMPTS)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Sai quá nhiều lần. Thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.']);
        }

        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        RateLimiter::clear($throttleKey);
        $provider->rehashPasswordIfRequired($user, $credentials);

        // Đổi id phiên ngay khi qua được mật khẩu (giữ url.intended đã lưu trong phiên).
        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_KEY, [
                'id' => $user->getKey(),
                'remember' => $request->boolean('remember'),
                'at' => now()->getTimestamp(),
                'fails' => 0,
            ]);

            return redirect()->route('admin.two-factor');
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
