<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Fortify は 2FA（TOTP・リカバリコード）のエンジンとしてのみ使う。
     * ログイン・登録・パスワード管理は Breeze 側の既存実装のままにするため、
     * Fortify が提供するルートはすべて無効化し、必要なエンドポイントは
     * routes/web.php に自前で定義している（TwoFactor*Controller）。
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        // 2FA チャレンジの総当たり対策（セッション上のログイン候補ID + IP 単位）
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->session()->get('login.id') . '|' . $request->ip()
            );
        });
    }
}
