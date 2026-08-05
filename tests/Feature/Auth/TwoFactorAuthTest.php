<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    /** 2FA を有効化済みのユーザーを作る */
    private function userWithTwoFactor(): array
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret'         => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode([
                'AAAA-1111-recovery',
                'BBBB-2222-recovery',
            ])),
            'two_factor_confirmed_at'   => now(),
        ])->save();

        return [$user, $secret, $google2fa];
    }

    /** password.confirm を通過済みのセッションで振る舞う */
    private function actingAsWithConfirmedPassword(User $user): static
    {
        return $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_2fa未設定ユーザーは従来どおりログインできる(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_2fa有効ユーザーはログイン後チャレンジへリダイレクトされ未認証のまま(): void
    {
        [$user] = $this->userWithTwoFactor();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_正しい認証コードでログインが完了する(): void
    {
        [$user, $secret, $google2fa] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post('/two-factor-challenge', [
            'code' => $google2fa->getCurrentOtp($secret),
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.id'));
    }

    public function test_誤った認証コードでは拒否され未認証のまま(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post('/two-factor-challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_リカバリコードでログインでき使用済みコードは無効になる(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post('/two-factor-challenge', [
            'recovery_code' => 'AAAA-1111-recovery',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // 使用済みコードは置き換えられ再利用できない
        $this->assertNotContains('AAAA-1111-recovery', $user->fresh()->recoveryCodes());
        $this->assertContains('BBBB-2222-recovery', $user->fresh()->recoveryCodes());
    }

    public function test_チャレンジ画面はログイン手続き外では開けない(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }

    public function test_有効化から確認までのフロー(): void
    {
        $user = User::factory()->create();

        // 有効化（シークレット生成・この時点では未確認）
        $this->actingAsWithConfirmedPassword($user)
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('account.security'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        // 認証アプリのコードで確認して確定
        $code = (new Google2FA())->getCurrentOtp(decrypt($user->two_factor_secret));

        $this->actingAsWithConfirmedPassword($user)
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('account.security'));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertNotEmpty($user->recoveryCodes());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'two_factor.enabled',
        ]);
    }

    public function test_無効化すると通常ログインに戻る(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->actingAsWithConfirmedPassword($user)
            ->delete(route('two-factor.disable'))
            ->assertRedirect(route('account.security'));

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'two_factor.disabled',
        ]);
    }

    public function test_設定ページはパスワード再確認が必要(): void
    {
        $user = User::factory()->create();

        // 再確認前は confirm-password へ誘導される
        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertRedirect(route('password.confirm'));

        // 再確認済みなら表示できる
        $this->actingAsWithConfirmedPassword($user)
            ->get(route('account.security'))
            ->assertOk();
    }
}
