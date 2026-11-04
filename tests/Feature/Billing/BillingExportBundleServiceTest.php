<?php

namespace Tests\Feature\Billing;

use App\Models\AreaCategory;
use App\Models\BillingPeriod;
use App\Models\Facility;
use App\Services\Billing\BillingExportBundleService;
use App\Services\Billing\StaffingComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 地域区分コードと地域区分単価の整合性チェック（国保連CSV出力前バリデーション）。
 *
 * NOTE: StaffingComplianceService::checkPeriod() はSQL上 MySQL専用関数（CONCAT/DATE_FORMAT）を
 * 使用しており、SQLite（テスト環境）では実行できない既存の問題があるため、このテストでは
 * 無関係な人員配置チェックをモックして無効化し、地域区分チェックのみを検証する。
 */
class BillingExportBundleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bundleService(): BillingExportBundleService
    {
        $this->mock(StaffingComplianceService::class, function ($mock) {
            $mock->shouldReceive('checkPeriod')->andReturn([]);
        });

        return app(BillingExportBundleService::class);
    }

    public function test_warns_when_area_unit_price_does_not_match_registered_master_value(): void
    {
        AreaCategory::create(['code' => '06', 'label' => '六級地', 'unit_price' => 10.00, 'valid_from' => '2024-04-01']);

        $facility = Facility::create([
            'name'               => 'テスト事業所',
            'facility_code'      => '1310000001',
            'service_type'       => 'houday',
            'area_category_code' => '06',
            'area_unit_price'    => 11.40, // マスタ上の六級地の単価(10.00)と不一致
            'capacity_per_day'   => 10,
        ]);

        $period = BillingPeriod::create([
            'facility_id' => $facility->id,
            'year_month'  => '2026-04',
            'status'      => 'draft',
        ]);

        $warnings = $this->bundleService()->validate($period);

        $this->assertTrue(
            collect($warnings)->contains(fn ($w) => str_contains($w['message'], '地域区分単価') && str_contains($w['message'], '一致していません')),
            '地域区分マスタと単価が不一致の場合に警告が出るはず'
        );
    }

    public function test_no_mismatch_warning_when_price_matches_master(): void
    {
        AreaCategory::create(['code' => '06', 'label' => '六級地', 'unit_price' => 10.00, 'valid_from' => '2024-04-01']);

        $facility = Facility::create([
            'name'               => 'テスト事業所',
            'facility_code'      => '1310000001',
            'service_type'       => 'houday',
            'area_category_code' => '06',
            'area_unit_price'    => 10.00,
            'capacity_per_day'   => 10,
        ]);

        $period = BillingPeriod::create([
            'facility_id' => $facility->id,
            'year_month'  => '2026-04',
            'status'      => 'draft',
        ]);

        $warnings = $this->bundleService()->validate($period);

        $this->assertFalse(
            collect($warnings)->contains(fn ($w) => str_contains($w['message'], '地域区分単価')),
            'マスタと単価が一致している場合は警告が出ないはず'
        );
    }

    public function test_no_mismatch_warning_when_master_price_is_unverified(): void
    {
        // 単価が未登録（null）のマスタは、手入力値との照合をしない
        AreaCategory::create(['code' => '06', 'label' => '六級地', 'unit_price' => null, 'valid_from' => '2024-04-01']);

        $facility = Facility::create([
            'name'               => 'テスト事業所',
            'facility_code'      => '1310000001',
            'service_type'       => 'houday',
            'area_category_code' => '06',
            'area_unit_price'    => 11.40,
            'capacity_per_day'   => 10,
        ]);

        $period = BillingPeriod::create([
            'facility_id' => $facility->id,
            'year_month'  => '2026-04',
            'status'      => 'draft',
        ]);

        $warnings = $this->bundleService()->validate($period);

        $this->assertFalse(
            collect($warnings)->contains(fn ($w) => str_contains($w['message'], '地域区分単価')),
            '未確認（null）のマスタ値では不一致警告を出さないはず'
        );
    }
}
