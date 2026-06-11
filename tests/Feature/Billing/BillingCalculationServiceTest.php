<?php

namespace Tests\Feature\Billing;

use App\Exceptions\BillingPeriodLockedException;
use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\Facility;
use App\Models\FacilityServiceSetting;
use App\Models\ServiceCodeMaster;
use App\Models\UsageRecord;
use App\Services\Billing\BillingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR_MONTH = '2026-05';

    private Facility $facility;
    private Child $child;

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

        $this->child->recipientCertificates()->create([
            'certificate_number'    => '1234567890',
            'status'                => 'active',
            'valid_from'            => '2026-04-01',
            'monthly_limit'         => 23,
            'copayment_rate'        => 10,
            'copayment_cap_monthly' => 4600,
            'service_type'          => 'houday',
        ]);

        // 基本報酬（授業日・定員10人以下）
        $this->createCode('631000', 604, 'base', ['day_type' => 'school_day', 'max_capacity' => 10]);
        // 送迎加算（迎え）
        $this->createCode('636100', 54, 'addition', ['requires_pickup' => true], enable: true);
        // 欠席時対応加算
        $this->createCode('636300', 94, 'addition', ['absent_with_notice' => true], enable: true);
    }

    private function createCode(string $code, int $units, string $category, array $conditions, bool $enable = false): ServiceCodeMaster
    {
        $master = ServiceCodeMaster::create([
            'revision_date' => '2024-04-01',
            'service_type'  => 'houday',
            'service_code'  => $code,
            'service_name'  => "テストコード{$code}",
            'unit_count'    => $units,
            'unit_type'     => 'per_day',
            'category'      => $category,
            'conditions'    => $conditions,
            'valid_from'    => '2024-04-01',
        ]);

        if ($enable) {
            FacilityServiceSetting::create([
                'facility_id'            => $this->facility->id,
                'service_code_master_id' => $master->id,
                'is_enabled'             => true,
                'effective_from'         => '2024-04-01',
            ]);
        }

        return $master;
    }

    private function createUsageRecord(string $date, string $status = 'attended', bool $pickup = false): UsageRecord
    {
        return UsageRecord::create([
            'child_id'       => $this->child->id,
            'facility_id'    => $this->facility->id,
            'date'           => $date,
            'status'         => $status,
            'is_school_day'  => true,
            'pickup_done'    => $pickup,
            'billing_target' => true,
        ]);
    }

    private function service(): BillingCalculationService
    {
        return app(BillingCalculationService::class);
    }

    public function test_出席日には基本報酬と送迎加算が算定される(): void
    {
        $this->createUsageRecord('2026-05-07', 'attended', pickup: true);
        $this->createUsageRecord('2026-05-08', 'attended');

        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);

        $detail = $period->billingDetails->firstWhere('child_id', $this->child->id);
        $this->assertNotNull($detail);
        $this->assertSame(2, $detail->total_days);

        // 基本報酬 604×2日 + 送迎加算 54×1回 = 1262単位
        $this->assertSame(1262, (int) $detail->total_units);
        // 総費用 = 単位数 × 10.00円
        $this->assertSame(12620, (int) $detail->total_amount);
        // 利用者負担 = 1割（上限4600円未満なのでそのまま）
        $this->assertSame(1262, (int) $detail->copayment_amount);
        $this->assertSame(1262, (int) $detail->copayment_cap_applied);
        $this->assertSame(12620 - 1262, (int) $detail->insurance_amount);
    }

    public function test_欠席日には基本報酬を算定せず欠席時対応加算のみ算定する(): void
    {
        $this->createUsageRecord('2026-05-07', 'absent_notice');

        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);

        $detail = $period->billingDetails->firstWhere('child_id', $this->child->id);
        $this->assertNotNull($detail);

        // 出席日数は0日
        $this->assertSame(0, $detail->total_days);

        // 欠席時対応加算 94単位のみ。基本報酬604単位が含まれていれば過請求
        $this->assertSame(94, (int) $detail->total_units);

        $codes = $detail->billingDetailLines()->pluck('service_code')->all();
        $this->assertContains('636300', $codes);
        $this->assertNotContains('631000', $codes);
    }

    public function test_利用者負担は月額上限でカットされる(): void
    {
        // 8日出席 → 基本報酬 604×8 = 4832単位 = 48320円 → 1割 4832円 > 上限4600円
        foreach (range(11, 18) as $day) {
            $this->createUsageRecord(sprintf('2026-05-%02d', $day));
        }

        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
        $detail = $period->billingDetails->firstWhere('child_id', $this->child->id);

        $this->assertSame(4832, (int) $detail->copayment_amount);
        $this->assertSame(4600, (int) $detail->copayment_cap_applied);
        $this->assertSame(48320 - 4600, (int) $detail->insurance_amount);
    }

    public function test_請求対象外の利用記録は算定されない(): void
    {
        $record = $this->createUsageRecord('2026-05-07');
        $record->update(['billing_target' => false]);

        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);

        $this->assertCount(0, $period->billingDetails);
    }

    public function test_有効な受給者証がない児童は請求対象にならない(): void
    {
        $this->child->recipientCertificates()->update(['status' => 'expired']);
        $this->createUsageRecord('2026-05-07');

        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);

        $this->assertCount(0, $period->billingDetails);
    }

    public function test_確定済みの請求期間は再計算できない(): void
    {
        $this->createUsageRecord('2026-05-07');
        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
        $period->update(['status' => 'confirmed']);

        $this->expectException(BillingPeriodLockedException::class);

        $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
    }

    public function test_提出済みの請求期間も再計算できない(): void
    {
        $this->createUsageRecord('2026-05-07');
        $period = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
        $period->update(['status' => 'submitted']);

        $this->expectException(BillingPeriodLockedException::class);

        $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
    }

    public function test_下書きの請求期間は再計算で明細が再生成される(): void
    {
        $this->createUsageRecord('2026-05-07');
        $first = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
        $this->assertSame('draft', $first->status);

        $this->createUsageRecord('2026-05-08');
        $second = $this->service()->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);

        $this->assertSame($first->id, $second->id);
        $detail = $second->billingDetails->firstWhere('child_id', $this->child->id);
        $this->assertSame(2, $detail->total_days);
    }
}
