<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * ログイン時の 2FA チャレンジ（認証コード / リカバリコードの検証）。
 * パスワード検証済みのユーザー ID はセッション 'login.id' に保持されている。
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request)
    {
        $user = User::find($request->session()->get('login.id'));

        if (! $user) {
            return redirect()->route('login');
        }

        if ($request->filled('recovery_code')) {
            $request->validate(['recovery_code' => ['required', 'string']]);

            $code = collect($user->recoveryCodes())
                ->first(fn ($c) => hash_equals($c, $request->input('recovery_code')));

            if (! $code) {
                throw ValidationException::withMessages([
                    'recovery_code' => 'リカバリコードが正しくありません。',
                ]);
            }

            // 使用済みリカバリコードは新しいコードに置き換える（再利用不可）
            $user->replaceRecoveryCode($code);
        } else {
            $request->validate(['code' => ['required', 'string']]);

            $valid = app(TwoFactorAuthenticationProvider::class)->verify(
                decrypt($user->two_factor_secret),
                $request->input('code'),
            );

            if (! $valid) {
                throw ValidationException::withMessages([
                    'code' => '認証コードが正しくありません。',
                ]);
            }
        }

        Auth::guard('web')->login($user, $request->session()->pull('login.remember', false));

        $request->session()->forget('login.id');
        $request->session()->regenerate();

        if ($request->filled('recovery_code')) {
            AuditLog::record('two_factor.recovery_code_used', $user);
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
