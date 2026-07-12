<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Facility;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 主要画面のスモークテスト。
 * フレームワーク更新等でクエリ生成が変わった際の退行（例: latestOfMany の
 * JOIN で未修飾カラムが ambiguous になる）を検知するため、認証済みで
 * 各一覧・詳細ページが 200 を返すことだけを確認する。
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;
    private Child $child;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::create([
            'name'             => 'テスト事業所',
            'facility_code'    => '1310000001',
            'service_type'     => 'houday',
            'area_unit_price'  => 10.00,
            'capacity_per_day' => 10,
        ]);

        $this->child = Child::create([
            'facility_id'     => $this->facility->id,
            'name'            => 'テスト児童',
            'name_kana'       => 'テストジドウ',
            'contract_status' => 'active',
        ]);

        // activeRecipientCertificate（latestOfMany）の JOIN を実際に通す
        DB::table('recipient_certificates')->insert([
            'child_id'           => $this->child->id,
            'certificate_number' => '2600010001',
            'municipality'       => '渋谷区',
            'municipality_code'  => '131130',
            'valid_from'         => now()->subMonths(3)->toDateString(),
            'valid_to'           => now()->addMonths(9)->toDateString(),
            'monthly_limit'      => 23,
            'issue_date'         => now()->subMonths(3)->toDateString(),
            'status'             => 'active',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->user = User::factory()->create();
        Staff::create([
            'user_id'     => $this->user->id,
            'facility_id' => $this->facility->id,
            'name'        => 'テスト職員',
            'role'        => 'admin',
            'is_active'   => true,
        ]);
    }

    public function test_主要ページが認証済みで200を返す(): void
    {
        $pages = [
            '/dashboard',
            '/children',
            '/children/' . $this->child->id,
            '/usage-records',
            '/contact-notes',
            '/billing',
            '/shifts',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->user)
                ->get($page)
                ->assertOk();
        }
    }

    public function test_未ログインでは主要ページからログインへリダイレクトされる(): void
    {
        $this->get('/children')->assertRedirect('/login');
    }
}
