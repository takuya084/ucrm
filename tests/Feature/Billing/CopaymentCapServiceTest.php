<?php

namespace Tests\Feature\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\CopaymentCapDetail;
use App\Models\ExternalFacility;
use App\Models\Facility;
use App\Models\RecipientCertificate;
use App\Services\Billing\CopaymentCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopaymentCapServiceTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR_MONTH = '2026-05';

    private Facility $facility;
    private Child $child;
    private RecipientCertificate $certificate;
    private BillingDetail $billingDetail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::create([
            'name'             => '管理事業所',
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

        $this->certificate = $this->child->recipientCertificates()->create([
            'certificate_number'       => '1234567890',
            'status'                   => 'active',
            'valid_from'               => '2026-04-01',
            'monthly_limit'            => 23,
            'copayment_rate'           => 10,
            'copayment_cap_monthly'    => 4600,
            'is_cap_management_target' => true,
            'cap_managing_facility_id' => $this->facility->id,
            'service_type'             => 'houday',
        ]);

        $period = BillingPeriod::create([
            'facility_id' => $this->facility->id,
            'year_month'  => self::YEAR_MONTH,
            'status'      => 'draft',
        ]);

        $this->billingDetail = BillingDetail::create([
            'billing_period_id'        => $period->id,
            'child_id'                 => $this->child->id,
            'recipient_certificate_id' => $this->certificate->id,
            'service_type'             => 'houday',
            'total_days'               => 10,
            'total_units'              => 4000,
            'unit_price_yen'           => 10.00,
            'total_amount'             => 40000,
            'insurance_amount'         => 36000,
            'copayment_amount'         => 4000,
            'copayment_cap'            => 4600,
            'copayment_cap_applied'    => 4000,
            'status'                   => 'draft',
        ]);
    }

    private function service(): CopaymentCapService
    {
        return app(CopaymentCapService::class);
    }

    public function test_上限超過時は管理事業所優先充当方式で調整される(): void
    {
        // 他社事業所を受給者証に紐付け
        $external = ExternalFacility::create([
            'facility_id'     => $this->facility->id,
            'service_type'    => 'houday',
            'facility_number' => '1310000002',
            'name'            => '協力事業所A',
        ]);
        $this->certificate->externalFacilities()->sync([$external->id]);

        // 1回目の計算で枠を作成し、他社の負担額を手入力（2000円）
        $management = $this->service()->calculateCap($this->child, self::YEAR_MONTH, $this->facility->id);
        $management->details()
            ->where('billable_facility_type', ExternalFacility::class)
            ->update(['copayment_amount' => 2000, 'total_amount' => 20000]);

        // 再計算: 合計6000円 > 上限4600円
        $management = $this->service()->calculateCap($this->child, self::YEAR_MONTH, $this->facility->id);
        $management->details()
            ->where('billable_facility_type', ExternalFacility::class)
            ->update(['copayment_amount' => 2000, 'total_amount' => 20000]);
        $this->service()->recomputeAllocation($management->fresh('details'));
        $management = $management->fresh('details');

        $this->assertSame('3', $management->management_result);
        $this->assertSame(4600, (int) $management->adjusted_copayment);

        // 管理事業所が自社分4000円を優先充当、残額600円が協力事業所
        $own = $management->details->firstWhere('facility_id', $this->facility->id);
        $ext = $management->details->firstWhere('billable_facility_type', ExternalFacility::class);
        $this->assertSame(4000, (int) $own->adjusted_amount);
        $this->assertSame(600, (int) $ext->adjusted_amount);
    }

    public function test_上限以内なら調整なしで管理結果コードが設定される(): void
    {
        $management = $this->service()->calculateCap($this->child, self::YEAR_MONTH, $this->facility->id);

        $this->assertSame('1', $management->management_result);
        $this->assertSame(4000, (int) $management->adjusted_copayment);
    }

    public function test_上限管理結果が請求明細に反映される(): void
    {
        $this->service()->calculateCap($this->child, self::YEAR_MONTH, $this->facility->id);

        $this->billingDetail->refresh();
        $this->assertSame('1', $this->billingDetail->cap_management_result_code);
        $this->assertSame('1310000001', $this->billingDetail->cap_managing_facility_code);
        $this->assertSame(4000, (int) $this->billingDetail->cap_result_amount);
        $this->assertSame(4000, (int) $this->billingDetail->copayment_cap_applied);
        $this->assertSame(36000, (int) $this->billingDetail->insurance_amount);
    }

    public function test_確定済みの請求明細には上限管理結果を上書きしない(): void
    {
        $this->billingDetail->update(['status' => 'confirmed']);

        $this->service()->calculateCap($this->child, self::YEAR_MONTH, $this->facility->id);

        $this->billingDetail->refresh();
        $this->assertNull($this->billingDetail->cap_management_result_code);
    }
}
