<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // 新規登録はStripeサブスクフロー経由のみ（直接登録を無効化）
    Route::get('register', fn () => redirect('/'))->name('register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.update');

    // 2FA チャレンジ（パスワード検証済み・ログイン確定前）
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
                ->name('two-factor.login');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                ->middleware('throttle:two-factor');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [EmailVerificationPromptController::class, '__invoke'])
                ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
});

// アカウントの 2FA 設定（セキュリティ設定のため再認証必須）
Route::middleware(['auth', 'verified', 'password.confirm'])->group(function () {
    Route::get('account/security', [TwoFactorAuthenticationController::class, 'show'])
                ->name('account.security');

    Route::post('account/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
                ->name('two-factor.enable');

    Route::post('account/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])
                ->name('two-factor.confirm');

    Route::post('account/two-factor-recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])
                ->name('two-factor.recovery-codes');

    Route::delete('account/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
                ->name('two-factor.disable');
});
