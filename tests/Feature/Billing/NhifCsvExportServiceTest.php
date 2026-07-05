<?php

namespace Tests\Feature\Billing;

use App\Models\Child;
use App\Models\Contract;
use App\Models\CopaymentCapDetail;
use App\Models\CopaymentCapManagement;
use App\Models\Facility;
use App\Models\FacilityServiceSetting;
use App\Models\Guardian;
use App\Models\ServiceCodeMaster;
use App\Models\UsageRecord;
use App\Services\Billing\BillingCalculationService;
use App\Services\Billing\NhifCsvExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 国保連 交換情報CSV（インタフェース仕様書 令和7年4月版 準拠）の出力テスト
 */
class NhifCsvExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR_MONTH = '2026-05';

    private Facility $facility;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->facility = Facility::create([
            'name'               => 'テスト事業所',
            'facility_code'      => '1310000001',
            'service_type'       => 'houday',
            'area_unit_price'    => 10.00,
            'area_category_code' => '20',
            'capacity_per_day'   => 10,
        ]);

        $this->child = Child::create([
            'facility_id'     => $this->facility->id,
            'name'            => 'テスト児童',
            'name_kana'       => 'テストジドウ',
            'contract_status' => 'active',
        ]);

        $guardian = Guardian::create([
            'name'      => 'テスト保護者',
            'name_kana' => 'テストホゴシャ',
        ]);
        $this->child->guardians()->attach($guardian->id, ['is_primary' => true, 'priority_order' => 1]);

        $this->child->recipientCertificates()->create([
            'certificate_number'    => '1234567890',
            'status'                => 'active',
            'valid_from'            => '2026-04-01',
            'monthly_limit'         => 23,
            'copayment_rate'        => 10,
            'copayment_cap_monthly' => 4600,
            'service_type'          => 'houday',
            'municipality_code'     => '131059',
        ]);

        Contract::create([
            'child_id'                 => $this->child->id,
            'facility_id'              => $this->facility->id,
            'contracted_amount'        => 23,
            'contract_start_date'      => '2026-04-01',
            'record_number'            => '01',
        ]);

        // 基本報酬（授業日・定員10人以下）
        $this->createCode('631000', 604, 'base', ['day_type' => 'school_day', 'max_capacity' => 10]);
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

    /**
     * @return array<int, array<int, string>> 行ごとのフィールド配列（UTF-8変換済み）
     */
    private function readCsv(string $path): array
    {
        $sjis = Storage::disk('local')->get($path);
        $utf8 = mb_convert_encoding($sjis, 'UTF-8', 'SJIS-win');

        $this->assertStringEndsWith("\r\n", $utf8, 'レコード終端はCRLF');

        return array_map(
            fn ($line) => str_getcsv($line),
            array_filter(explode("\r\n", $utf8), fn ($l) => $l !== '')
        );
    }

    private function calculatePeriod(): \App\Models\BillingPeriod
    {
        $this->createUsage('2026-05-07', pickup: true, dropoff: true);
        $this->createUsage('2026-05-08');
        $this->createUsage('2026-05-09', status: 'absent_notice');

        return app(BillingCalculationService::class)
            ->calculateMonthlyBilling($this->facility->id, self::YEAR_MONTH);
    }

    private function createUsage(string $date, string $status = 'attended', bool $pickup = false, bool $dropoff = false): UsageRecord
    {
        return UsageRecord::create([
            'child_id'       => $this->child->id,
            'facility_id'    => $this->facility->id,
            'date'           => $date,
            'status'         => $status,
            'is_school_day'  => true,
            'check_in_time'  => $status === 'attended' ? '15:30' : null,
            'check_out_time' => $status === 'attended' ? '17:30' : null,
            'pickup_done'    => $pickup,
            'dropoff_done'   => $dropoff,
            'billing_target' => true,
        ]);
    }

    public function test_請求書明細書CSVがファイル構造仕様に準拠している(): void
    {
        $period = $this->calculatePeriod();
        $path   = app(NhifCsvExportService::class)->generateBillingCsv($period);

        $this->assertSame('billing_csv/1310000001/K112605.CSV', $path);

        $rows = $this->readCsv($path);

        // コントロールレコード: 種別1, 連番1, ボリューム0, 件数, データ種別K11, 市町村0, 事業所番号, 都道府県0, 媒体1, 処理対象年月=翌月
        $control = $rows[0];
        $this->assertSame(['1', '1', '0'], array_slice($control, 0, 3));
        $this->assertSame('K11', $control[4]);
        $this->assertSame('1310000001', $control[6]);
        $this->assertSame('1', $control[8]);
        $this->assertSame('202606', $control[9]);
        $this->assertSame((string) (count($rows) - 2), $control[3], 'レコード件数=データレコード数');

        // エンドレコード: 種別3, 通番=総レコード数
        $end = end($rows);
        $this->assertSame('3', $end[0]);
        $this->assertSame((string) count($rows), $end[1]);

        // データレコードは種別2・連番が2から通番
        foreach (array_slice($rows, 1, -1) as $i => $row) {
            $this->assertSame('2', $row[0]);
            $this->assertSame((string) ($i + 2), $row[1]);
        }
    }

    public function test_請求書と明細書のレコード内容(): void
    {
        $period = $this->calculatePeriod();
        $detail = $period->billingDetails->first();
        $rows   = $this->readCsv(app(NhifCsvExportService::class)->generateBillingCsv($period));

        $find = fn (string $id, string $recordType) => collect($rows)
            ->first(fn ($r) => ($r[2] ?? null) === $id && ($r[3] ?? null) === $recordType);

        // K112 請求書 基本情報レコード（データ部23項目）
        $invoice = $find('K112', '01');
        $this->assertNotNull($invoice);
        $data = array_slice($invoice, 2); // レコード種別・連番を除いたデータ部
        $this->assertCount(23, $data);
        $this->assertSame('202605', $data[2]);                        // サービス提供年月
        $this->assertSame('131059', $data[3]);                        // 都道府県等番号
        $this->assertSame((string) $detail->insurance_amount, $data[5]);  // 請求金額
        $this->assertSame('1', $data[6]);                             // 件数
        $this->assertSame((string) $detail->total_units, $data[7]);   // 単位数

        // K112 明細情報レコード: 給付種別1・サービス種類コード63（放デイ）
        $invoiceDetail = $find('K112', '02');
        $this->assertSame('1', $invoiceDetail[7]);
        $this->assertSame('63', $invoiceDetail[8]);

        // K122 明細書 基本情報レコード: カナ半角化・地域区分・上限月額
        $statement = $find('K122', '01');
        $data = array_slice($statement, 2);
        $this->assertCount(35, $data);
        $this->assertSame('1234567890', $data[5]);      // 受給者証番号
        $this->assertSame('ﾃｽﾄﾎｺﾞｼｬ', $data[7]);        // 保護者カナ（半角）
        $this->assertSame('ﾃｽﾄｼﾞﾄﾞｳ', $data[8]);        // 児童カナ（半角）
        $this->assertSame('20', $data[9]);              // 地域区分コード
        $this->assertSame('4600', $data[11]);           // 利用者負担上限月額①
        $this->assertSame((string) $detail->total_units, $data[19]);
        $this->assertSame((string) $detail->total_amount, $data[20]);

        // K122 日数情報レコード: サービス種類63・契約開始日・利用日数
        $days = $find('K122', '02');
        $data = array_slice($days, 2);
        $this->assertSame('63', $data[6]);
        $this->assertSame('20260401', $data[7]);
        $this->assertSame('2', $data[9]); // 出席2日（欠席日は利用日数に含めない）

        // K122 明細情報レコード: 基本報酬 631000 が 604単位×2回
        $line = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'K122' && ($r[3] ?? null) === '03' && $r[8] === '631000');
        $this->assertNotNull($line);
        $this->assertSame('604', $line[9]);
        $this->assertSame('2', $line[10]);

        // K122 集計情報レコード: 単位数単価10000（10円）・給付率0・1割相当額
        $summary = $find('K122', '04');
        $data = array_slice($summary, 2);
        $this->assertCount(33, $data);
        $this->assertSame('1', $data[7]);   // 集計欄分類番号
        $this->assertSame('10000', $data[10]);
        $this->assertSame('0', $data[11]);
        $this->assertSame((string) (int) floor($detail->total_amount / 10), $data[13]);

        // K122 契約情報レコード: 決定サービスコード631000・契約支給量23日→2300
        $contract = $find('K122', '05');
        $data = array_slice($contract, 2);
        $this->assertSame('631000', $data[6]);
        $this->assertSame('2300', $data[7]);
        $this->assertSame('20260401', $data[8]);
        $this->assertSame('1', $data[10]);
    }

    public function test_実績記録票CSVのレコード内容(): void
    {
        $period = $this->calculatePeriod();
        $rows   = $this->readCsv(app(NhifCsvExportService::class)->generatePerformanceRecordCsv($period));

        $this->assertSame('K61', $rows[0][4]);

        // 基本情報レコード: 様式0501（放デイ）・算定日数2・送迎2回・算定時間数計 2.0h×2日=400
        $basic = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'K611' && ($r[3] ?? null) === '01');
        $data  = array_slice($basic, 2);
        $this->assertCount(172, $data);
        $this->assertSame('0501', $data[6]);
        $this->assertSame('400', $data[18]);   // 算定時間数計
        $this->assertSame('2', $data[33]);     // 送迎加算（片道）
        $this->assertSame('2', $data[36]);     // 算定日数
        $this->assertSame('63', $data[110]);   // 施設種類

        // 明細情報レコード（出席日）: 日付・時刻HHMM・算定時間数・送迎・提供形態
        $daily = collect($rows)->filter(fn ($r) => ($r[2] ?? null) === 'K611' && ($r[3] ?? null) === '02')->values();
        $this->assertCount(3, $daily);

        $day7 = array_slice($daily[0], 2);
        $this->assertCount(113, $day7);
        $this->assertSame('7', $day7[8]);      // 日付
        $this->assertSame('1530', $day7[13]);  // 開始時間
        $this->assertSame('1730', $day7[14]);  // 終了時間
        $this->assertSame('200', $day7[15]);   // 算定時間数 2.00h
        $this->assertSame('1', $day7[20]);     // 送迎 往
        $this->assertSame('1', $day7[21]);     // 送迎 復
        $this->assertSame('1', $day7[33]);     // 提供形態: 授業終了後
        $this->assertSame('', $day7[35]);      // サービス提供の状況（通常日は空）

        // 欠席日: サービス提供の状況=8・時刻等は空
        $day9 = array_slice($daily[2], 2);
        $this->assertSame('9', $day9[8]);
        $this->assertSame('', $day9[13]);
        $this->assertSame('8', $day9[35]);
    }

    public function test_上限管理結果票CSVのレコード内容(): void
    {
        $mgmt = CopaymentCapManagement::create([
            'child_id'             => $this->child->id,
            'year_month'           => self::YEAR_MONTH,
            'managing_facility_id' => $this->facility->id,
            'cap_amount'           => 4600,
            'total_copayment'      => 6000,
            'adjusted_copayment'   => 4600,
            'management_result'    => '3',
            'status'               => 'confirmed',
        ]);
        CopaymentCapDetail::create([
            'copayment_cap_management_id' => $mgmt->id,
            'facility_id'                 => $this->facility->id,
            'billable_facility_type'      => Facility::class,
            'billable_facility_id'        => $this->facility->id,
            'facility_name'               => 'テスト事業所',
            'total_amount'                => 40000,
            'copayment_amount'            => 4000,
            'adjusted_amount'             => 4000,
            'is_managing_facility'        => true,
        ]);

        $rows = $this->readCsv(
            app(NhifCsvExportService::class)->generateCapManagementCsv($this->facility->id, self::YEAR_MONTH)
        );

        $this->assertSame('K41', $rows[0][4]);

        $basic = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'K411' && ($r[3] ?? null) === '01');
        $data  = array_slice($basic, 2);
        $this->assertCount(14, $data);
        $this->assertSame('1', $data[3]);        // 作成区分: 新規
        $this->assertSame('131059', $data[4]);   // 都道府県等番号
        $this->assertSame('1310000001', $data[5]);
        $this->assertSame('4600', $data[9]);     // 上限月額
        $this->assertSame('3', $data[10]);       // 管理結果

        $detail = collect($rows)->first(fn ($r) => ($r[2] ?? null) === 'K411' && ($r[3] ?? null) === '02');
        $data   = array_slice($detail, 2);
        $this->assertCount(11, $data);
        $this->assertSame('1', $data[6]);          // 項番
        $this->assertSame('1310000001', $data[7]); // 事業所番号
        $this->assertSame('40000', $data[8]);
    }
}
