<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 本システムは職員アカウントを管理者が発行する運用のため、
 * 自己登録（サインアップ）は無効化されている。
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_登録画面は無効化されておりトップへリダイレクトされる()
    {
        $response = $this->get('/register');

        $response->assertRedirect('/');
    }

    public function test_登録処理は受け付けない()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        // POST /register ルートは存在しない（405 Method Not Allowed）
        $response->assertStatus(405);
    }
}
