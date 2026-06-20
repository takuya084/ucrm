<?php

namespace Tests\Feature;

use App\Jobs\PushYoyakuBooking;
use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\Facility;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class YoyakuWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET      = 'test-webhook-secret';
    private const BUSINESS_ID = 77;

    private Facility $facility;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::create([
            'name'                  => 'テスト事業所',
            'facility_code'         => '1310000001',
            'service_type'          => 'houday',
            'area_unit_price'       => 10.00,
            'capacity_per_day'      => 10,
            'yoyaku_business_id'    => self::BUSINESS_ID,
            'yoyaku_webhook_secret' => self::SECRET,
        ]);

        $this->child = Child::create([
            'facility_id'     => $this->facility->id,
            'name'            => 'テスト児童',
            'name_kana'       => 'テストジドウ',
            'contract_status' => 'active',
            'yoyaku_user_id'  => 501,
        ]);
    }

    private function postWebhook(array $body, ?string $secret = self::SECRET, array $headers = [])
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($secret !== null) {
            $headers['X-Yoyaku-Signature'] = hash_hmac('sha256', $json, $secret);
        }

        return $this->call(
            'POST',
            route('api.webhooks.yoyaku'),
            server: $this->transformHeadersToServerVars($headers + ['Content-Type' => 'application/json']),
            content: $json,
        );
    }

    private function bookingBody(array $overrides = [], array $dataOverrides = []): array
    {
        return $overrides + [
            'event'       => 'booking.created',
            'business_id' => self::BUSINESS_ID,
            'sent_at'     => now()->toIso8601String(),
            'data'        => $dataOverrides + [
                'user_id'           => 501,
                'date'              => '2026-06-10',
                'pickup_time'       => '15:00',
                'dropoff_time'      => '17:30',
                'actual_pickup_at'  => null,
                'actual_dropoff_at' => null,
            ],
        ];
    }

    public function test_有効な署名で出欠記録が作成される(): void
    {
        $this->postWebhook($this->bookingBody())->assertOk();

        $this->assertDatabaseHas('usage_records', [
            'facility_id'  => $this->facility->id,
            'child_id'     => $this->child->id,
            'pickup_done'  => true,
            'dropoff_done' => true,
        ]);
    }

    public function test_webhook起点の保存ではp_yoyakuへpushしない(): void
    {
        Bus::fake();

        $this->postWebhook($this->bookingBody())->assertOk();

        Bus::assertNotDispatched(PushYoyakuBooking::class);
    }

    public function test_署名不一致は拒否される(): void
    {
        $this->postWebhook($this->bookingBody(), secret: 'wrong-secret')->assertStatus(401);
        $this->assertDatabaseCount('usage_records', 0);
    }

    public function test_secret未設定の施設は拒否される(): void
    {
        $this->facility->update(['yoyaku_webhook_secret' => null]);

        $this->postWebhook($this->bookingBody(), secret: 'anything')->assertStatus(403);
    }

    public function test_古いsent_atはリプレイとして拒否される(): void
    {
        $body = $this->bookingBody(['sent_at' => now()->subMinutes(30)->toIso8601String()]);

        $this->postWebhook($body)->assertStatus(401);
        $this->assertDatabaseCount('usage_records', 0);
    }

    public function test_請求確定済みの月は変更されない(): void
    {
        BillingPeriod::create([
            'facility_id' => $this->facility->id,
            'year_month'  => '2026-06',
            'status'      => 'confirmed',
        ]);

        $this->postWebhook($this->bookingBody())
            ->assertOk()
            ->assertJson(['ignored' => 'billing period locked']);

        $this->assertDatabaseCount('usage_records', 0);
    }

    public function test_booking_deletedで送迎フラグが下ろされる(): void
    {
        UsageRecord::create([
            'facility_id'  => $this->facility->id,
            'child_id'     => $this->child->id,
            'date'         => '2026-06-10',
            'status'       => 'attended',
            'pickup_done'  => true,
            'dropoff_done' => true,
        ]);

        $this->postWebhook($this->bookingBody(['event' => 'booking.deleted']))->assertOk();

        $this->assertDatabaseHas('usage_records', [
            'child_id'     => $this->child->id,
            'pickup_done'  => false,
            'dropoff_done' => false,
        ]);
    }

    public function test_未連携の児童は無視される(): void
    {
        $this->postWebhook($this->bookingBody(dataOverrides: ['user_id' => 999]))
            ->assertOk()
            ->assertJson(['ignored' => 'child not linked']);

        $this->assertDatabaseCount('usage_records', 0);
    }

    public function test_不正な日付は拒否される(): void
    {
        $this->postWebhook($this->bookingBody(dataOverrides: ['date' => '2026-13-99']))
            ->assertStatus(400);
    }
}
