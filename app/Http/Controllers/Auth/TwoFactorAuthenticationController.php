<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * アカウントの 2FA 設定（有効化 → QR 読み取り → コード確認 → リカバリコード表示）。
 * ルート全体に password.confirm を掛けている（セキュリティ設定のため再認証必須）。
 */
class TwoFactorAuthenticationController extends Controller
{
    /** 設定ページ */
    public function show(Request $request)
    {
        $user = $request->user();

        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = ! is_null($user->two_factor_secret) && ! $enabled;

        return Inertia::render('Account/Security', [
            'twoFactor' => [
                'enabled'       => $enabled,
                'pending'       => $pending,
                // QR とシークレットは確認前（pending）のみ表示する
                'qrCodeSvg'     => $pending ? $user->twoFactorQrCodeSvg() : null,
                'secretKey'     => $pending ? decrypt($user->two_factor_secret) : null,
                'recoveryCodes' => $enabled ? $user->recoveryCodes() : [],
            ],
        ]);
    }

    /** 2FA を有効化（シークレット生成。この時点ではまだ未確認） */
    public function store(Request $request, EnableTwoFactorAuthentication $enable)
    {
        $enable($request->user());

        return redirect()->route('account.security');
    }

    /** 認証アプリのコードで有効化を確定 */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm)
    {
        $request->validate(['code' => ['required', 'string']]);

        $confirm($request->user(), $request->input('code'));

        AuditLog::record('two_factor.enabled', $request->user());

        return redirect()->route('account.security');
    }

    /** リカバリコードを再生成 */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate)
    {
        $generate($request->user());

        AuditLog::record('two_factor.recovery_codes_regenerated', $request->user());

        return redirect()->route('account.security');
    }

    /** 2FA を無効化 */
    public function destroy(Request $request, DisableTwoFactorAuthentication $disable)
    {
        $disable($request->user());

        AuditLog::record('two_factor.disabled', $request->user());

        return redirect()->route('account.security');
    }
}
