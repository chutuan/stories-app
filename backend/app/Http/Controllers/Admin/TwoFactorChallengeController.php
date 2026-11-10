<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bước 2 của đăng nhập admin: mã 6 số từ app xác thực, hoặc một mã khôi phục.
 *
 * Phiên chờ (AuthController::PENDING_KEY) chỉ sống 5 phút và chịu tối đa 5 lần sai; quá thì
 * xoá, phải nhập lại mật khẩu. Route còn có throttle theo IP làm chốt thứ hai.
 */
class TwoFactorChallengeController extends Controller
{
    private const TTL_SECONDS = 300;

    private const MAX_FAILS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $this->pendingUser($request)) {
            return $this->restart($request, 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.');
        }

        return view('admin.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->restart($request, 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:40'],
        ]);

        $ok = false;
        $usedRecovery = false;

        if (filled($data['code'] ?? null)) {
            $step = Totp::verify($user->two_factor_secret, $data['code'], $user->two_factor_last_used_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_used_step' => $step])->save();
                $ok = true;
            }
        } elseif (filled($data['recovery_code'] ?? null)) {
            $ok = $usedRecovery = $user->consumeRecoveryCode($data['recovery_code']);
        }

        if (! $ok) {
            $pending = $request->session()->get(AuthController::PENDING_KEY);
            $pending['fails'] = ($pending['fails'] ?? 0) + 1;

            if ($pending['fails'] >= self::MAX_FAILS) {
                return $this->restart($request, 'Nhập sai mã quá nhiều lần, vui lòng đăng nhập lại.');
            }

            $request->session()->put(AuthController::PENDING_KEY, $pending);

            return back()->withErrors([
                filled($data['recovery_code'] ?? null) ? 'recovery_code' : 'code' => 'Mã không đúng hoặc đã dùng rồi.',
            ]);
        }

        $remember = (bool) $request->session()->get(AuthController::PENDING_KEY.'.remember');
        $request->session()->forget(AuthController::PENDING_KEY);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($usedRecovery) {
            $left = $user->recoveryCodesLeft();
            $request->session()->flash('warning', "Bạn vừa dùng một mã khôi phục, còn {$left} mã. "
                .'Nếu đã mất điện thoại, hãy vào Bảo mật để chuyển 2FA sang thiết bị mới.');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(AuthController::PENDING_KEY);

        if (! is_array($pending) || now()->getTimestamp() - (int) ($pending['at'] ?? 0) > self::TTL_SECONDS) {
            return null;
        }

        $user = User::find($pending['id'] ?? null);

        return $user && $user->hasTwoFactorEnabled() ? $user : null;
    }

    private function restart(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(AuthController::PENDING_KEY);

        return redirect()->route('admin.login')->withErrors(['email' => $message]);
    }
}
