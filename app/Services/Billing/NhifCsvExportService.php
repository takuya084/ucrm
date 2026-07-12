<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use App\Models\Contract;
use App\Models\CopaymentCapManagement;
use App\Models\ExternalFacility;
use App\Models\Facility;
use App\Services\Billing\Nhif\NhifExchangeFileBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * 国保連 電子請求受付システム向け 交換情報CSV出力
 *
 * 準拠仕様: 障害者自立支援給付支払等システム インタフェース仕様書（令和7年4月版）
 * - 事業所編 2.1.3.1 障害児給付費等 請求書情報（K112）
 * - 事業所編 2.1.3.2 障害児給付費等 明細書情報（K122）
 * - 事業所編 2.1.3.4 利用者負担上限額管理結果票情報（K411）
 * - 事業所編 2.1.3.6 サービス提供実績記録票情報（K611。様式: 児発=0301/放デイ=0501）
 * - 共通編 1.2 交換情報の仕様（コントロール/データ/エンドレコード・Shift_JIS・CRLF）
 *
 * 注意: 提出前に国保中央会「取込送信システム」等でのフォーマット検証を推奨。
 */
class NhifCsvExportService
{
    /** サービス種類コード（共通編1.4 コード一覧: 61=児童発達支援, 63=放課後等デイサービス） */
    private const SERVICE_TYPE_CODES = ['jidou' => '61', 'houday' => '63'];

    /** 実績記録票 様式種別番号（事業所編 2.1.3.6(4): 児発=0301, 放デイ=0501） */
    private const FORM_TYPE_CODES = ['jidou' => '0301', 'houday' => '0501'];

    /** 基本決定サービスコード（共通編1.4: 611000=児発基本決定, 631000=放デイ基本決定） */
    private const DECISION_SERVICE_CODES = ['jidou' => '611000', 'houday' => '631000'];

    /**
     * 請求書・明細書情報（K112+K122）を生成
     */
    public function generateBillingCsv(BillingPeriod $period): string
    {
        $period->load([
            'facility',
            'billingDetails.child.guardians',
            'billingDetails.recipientCertificate',
            'billingDetails.billingDetailLines',
        ]);

        $facility  = $period->facility;
        $serviceYm = str_replace('-', '', $period->year_month);
        $builder   = new NhifExchangeFileBuilder('K11', $facility->facility_code ?? '', $this->processingYm($period->year_month));

        // 市町村（都道府県等番号）ごとに請求書を作成し、続けて明細書を編綴する
        $byMunicipality = $period->billingDetails
            ->sortBy(fn (BillingDetail $d) => $d->recipientCertificate?->certificate_number ?? '')
            ->groupBy(fn (BillingDetail $d) => $d->recipientCertificate?->municipality_code ?? '');

        foreach ($byMunicipality as $municipality => $details) {
            $this->addInvoiceRecords($builder, $details->values(), (string) $municipality, $facility, $serviceYm);
        }

        foreach ($byMunicipality as $municipality => $details) {
            foreach ($details->sortBy(fn ($d) => $d->recipientCertificate?->certificate_number ?? '') as $detail) {
                $this->addStatementRecords($builder, $detail, (string) $municipality, $facility, $serviceYm);
            }
        }

        return $this->store($facility, 'K11', $serviceYm, $builder);
    }

    /**
     * サービス提供実績記録票情報（K611）を生成
     */
    public function generatePerformanceRecordCsv(BillingPeriod $period): string
    {
        $period->load([
            'facility',
            'billingDetails.child.supportPlans',
            'billingDetails.recipientCertificate',
            'billingDetails.dailyServiceRecords.usageRecord',
            'billingDetails.dailyServiceRecords.serviceCodeMaster',
        ]);

        $facility  = $period->facility;
        $serviceYm = str_replace('-', '', $period->year_month);
        $builder   = new NhifExchangeFileBuilder('K61', $facility->facility_code ?? '', $this->processingYm($period->year_month));

        foreach ($period->billingDetails->sortBy(fn ($d) => $d->recipientCertificate?->certificate_number ?? '') as $detail) {
            $this->addPerformanceRecords($builder, $detail, $facility, $serviceYm, $period->year_month);
        }

        return $this->store($facility, 'K61', $serviceYm, $builder);
    }

    /**
     * 利用者負担上限額管理結果票情報（K411）を生成
     */
    public function generateCapManagementCsv(int $facilityId, string $yearMonth): string
    {
        $facility = Facility::findOrFail($facilityId);
        $managements = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with(['child.recipientCertificates', 'child.guardians', 'details'])
            ->get();

        $serviceYm = str_replace('-', '', $yearMonth);
        $builder   = new NhifExchangeFileBuilder('K41', $facility->facility_code ?? '', $this->processingYm($yearMonth));

        foreach ($managements as $mgmt) {
            $this->addCapManagementRecords($builder, $mgmt, $facility, $serviceYm, $yearMonth);
        }

        return $this->store($facility, 'K41', $serviceYm, $builder);
    }

    // ── K112 請求書 ──

    /**
     * @param \Illuminate\Support\Collection<int, BillingDetail> $details
     */
    private function addInvoiceRecords($builder, $details, string $municipality, Facility $facility, string $serviceYm): void
    {
        $count     = $details->count();
        $units     = (int) $details->sum('total_units');
        $amount    = (int) $details->sum('total_amount');
        $insurance = (int) $details->sum('insurance_amount');
        $copayment = (int) $details->sum(fn ($d) => $this->decidedCopayment($d));

        // 基本情報レコード（01・全23項目）
        $builder->addRecord($this->record(23, [
            1  => 'K112',
            2  => '01',
            3  => $serviceYm,
            4  => $municipality,
            5  => $facility->facility_code,
            6  => $insurance,          // 請求金額＝合計給付費請求額（特別対策費・自治体助成なし）
            7  => $count,              // 小計 障害児給付費
            8  => $units,
            9  => $amount,
            10 => $insurance,
            12 => $copayment,
            17 => $count,              // 合計
            18 => $units,
            19 => $amount,
            20 => $insurance,
            22 => $copayment,
        ]));

        // 明細情報レコード（02・全14項目）: サービス種類ごと
        foreach ($details->groupBy('service_type') as $serviceType => $group) {
            $builder->addRecord($this->record(14, [
                1  => 'K112',
                2  => '02',
                3  => $serviceYm,
                4  => $municipality,
                5  => $facility->facility_code,
                6  => '1', // 給付種別: 1=障害児通所給付費
                7  => self::SERVICE_TYPE_CODES[$serviceType] ?? '',
                8  => $group->count(),
                9  => (int) $group->sum('total_units'),
                10 => (int) $group->sum('total_amount'),
                11 => (int) $group->sum('insurance_amount'),
                13 => (int) $group->sum(fn ($d) => $this->decidedCopayment($d)),
            ]));
        }
    }

    // ── K122 明細書 ──

    private function addStatementRecords($builder, BillingDetail $detail, string $municipality, Facility $facility, string $serviceYm): void
    {
        $cert       = $detail->recipientCertificate;
        $child      = $detail->child;
        $typeCode   = self::SERVICE_TYPE_CODES[$detail->service_type] ?? '';
        $capManaged = $detail->cap_management_result_code !== null;
        $decided    = $this->decidedCopayment($detail);
        $guardian   = $child->guardians->firstWhere('pivot.is_primary', true) ?? $child->guardians->first();

        // 無償化対象で受給者証の負担上限月額欄に記載がない場合は 0（令和7年4月以降の規定）
        $capMonthly = $cert?->is_free_of_charge && !$cert?->copayment_cap_monthly
            ? 0
            : (int) $detail->copayment_cap;

        // 基本情報レコード（01・全35項目）
        $builder->addRecord($this->record(35, [
            1  => 'K122',
            2  => '01',
            3  => $serviceYm,
            4  => $municipality,
            5  => $facility->facility_code,
            6  => $cert?->certificate_number,
            8  => NhifExchangeFileBuilder::kana($guardian?->name_kana, 25),
            9  => NhifExchangeFileBuilder::kana($child->name_kana, 25),
            10 => $facility->area_category_code,
            12 => $capMonthly,
            15 => $capManaged ? $detail->cap_managing_facility_code : null,
            16 => $capManaged ? $detail->cap_management_result_code : null,
            17 => $capManaged ? (int) $detail->cap_result_amount : null,
            20 => (int) $detail->total_units,
            21 => (int) $detail->total_amount,
            22 => (int) $detail->copayment_cap_applied,   // 上限月額調整（①②の内少ない数）
            26 => $capManaged ? (int) $detail->cap_result_amount : null,
            27 => $decided,
            28 => (int) $detail->insurance_amount,
        ]));

        // 日数情報レコード（02・全12項目）
        $contract = Contract::where('child_id', $child->id)
            ->where('facility_id', $facility->id)
            ->orderByDesc('contract_start_date')
            ->first();

        $monthStart = Carbon::createFromFormat('Ym', $serviceYm)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $startDate  = $contract?->contract_start_date ?? $monthStart;
        $endDate    = $contract?->contract_end_date;

        $builder->addRecord($this->record(12, [
            1  => 'K122',
            2  => '02',
            3  => $serviceYm,
            4  => $municipality,
            5  => $facility->facility_code,
            6  => $cert?->certificate_number,
            7  => $typeCode,
            8  => $startDate->format('Ymd'),
            9  => ($endDate && $endDate->lte($monthEnd)) ? $endDate->format('Ymd') : null,
            10 => (int) $detail->total_days,
        ]));

        // 明細情報レコード（03・全11項目）: サービスコードごと
        foreach ($detail->billingDetailLines as $line) {
            $builder->addRecord($this->record(11, [
                1  => 'K122',
                2  => '03',
                3  => $serviceYm,
                4  => $municipality,
                5  => $facility->facility_code,
                6  => $cert?->certificate_number,
                7  => $line->service_code,
                8  => (int) $line->units_per_count,
                9  => (int) $line->count,
                10 => (int) $line->total_units,
            ]));
        }

        // 集計情報レコード（04・全33項目）
        $builder->addRecord($this->record(33, [
            1  => 'K122',
            2  => '04',
            3  => $serviceYm,
            4  => $municipality,
            5  => $facility->facility_code,
            6  => $cert?->certificate_number,
            7  => $typeCode,
            8  => '1', // 集計欄分類番号
            9  => (int) $detail->total_days,
            10 => (int) $detail->total_units,
            11 => (int) round(((float) $detail->unit_price_yen) * 1000), // 単位数単価: 整数2桁+小数3桁（10円→10000）
            12 => '0', // 給付率（平成24年4月以降は0）
            13 => (int) $detail->total_amount,
            14 => (int) floor($detail->total_amount / 10),  // 1割相当額
            15 => (int) $detail->copayment_amount,          // 利用者負担額②
            16 => (int) $detail->copayment_cap_applied,     // 上限月額調整
            20 => $capManaged ? (int) $detail->cap_result_amount : null,
            21 => $decided,
            22 => (int) $detail->insurance_amount,
        ]));

        // 契約情報レコード（05・全11項目）
        if ($contract) {
            $builder->addRecord($this->record(11, [
                1  => 'K122',
                2  => '05',
                3  => $serviceYm,
                4  => $municipality,
                5  => $facility->facility_code,
                6  => $cert?->certificate_number,
                // 決定サービスコードは基本決定を設定（重心・医ケア等の決定区分は受給者証を確認して要調整）
                7  => self::DECISION_SERVICE_CODES[$detail->service_type] ?? '',
                8  => (int) $contract->contracted_amount * 100, // 契約支給量: 整数3桁+小数2桁（12日→1200）
                9  => $contract->contract_start_date->format('Ymd'),
                10 => $contract->contract_end_date?->format('Ymd'),
                11 => $contract->record_number ? (int) $contract->record_number : null,
            ]));
        }
    }

    // ── K611 実績記録票 ──

    private function addPerformanceRecords($builder, BillingDetail $detail, Facility $facility, string $serviceYm, string $yearMonth): void
    {
        $cert     = $detail->recipientCertificate;
        $typeCode = self::SERVICE_TYPE_CODES[$detail->service_type] ?? '';
        $formType = self::FORM_TYPE_CODES[$detail->service_type] ?? '';

        // R6報酬改定: 算定時間数は個別支援計画に定めた支援時間に基づく
        $plannedMinutes = $detail->child->supportPlans
            ->filter(fn ($p) => $p->plan_date?->format('Y-m') <= $yearMonth
                && (!$p->valid_to || $p->valid_to->format('Y-m') >= $yearMonth))
            ->sortByDesc('plan_date')
            ->first()
            ?->planned_duration_minutes;

        // 日単位にまとめる（daily_service_records はサービスコード単位のため usage_record でグループ化）
        $byUsage = $detail->dailyServiceRecords
            ->filter(fn ($r) => $r->usageRecord !== null)
            ->groupBy('usage_record_id')
            ->sortBy(fn ($rows) => $rows->first()->usageRecord->date);

        $totalHours   = 0.0;
        $transport    = 0;
        $extensionDays = 0;
        $dailyRows    = [];

        foreach ($byUsage as $rows) {
            $usage    = $rows->first()->usageRecord;
            $isAbsent = $usage->status === 'absent_notice';
            $hours    = $isAbsent ? null : $this->calculatedHours($plannedMinutes, $usage);
            $extension = $rows->first(fn ($r) => $r->is_extension);

            if (!$isAbsent) {
                $totalHours += $hours ?? 0;
                $transport  += ($usage->pickup_done ? 1 : 0) + ($usage->dropoff_done ? 1 : 0);
                if ($extension) {
                    $extensionDays++;
                }
            }

            // 明細情報レコード（02・全113項目）
            $dailyRows[] = $this->record(113, [
                1   => 'K611',
                2   => '02',
                3   => $serviceYm,
                4   => $cert?->municipality_code,
                5   => $facility->facility_code,
                6   => $cert?->certificate_number,
                7   => $formType,
                9   => (int) $usage->date->format('j'),
                14  => $isAbsent ? null : NhifExchangeFileBuilder::hhmm($usage->check_in_time),
                15  => $isAbsent ? null : NhifExchangeFileBuilder::hhmm($usage->check_out_time),
                16  => $hours !== null ? NhifExchangeFileBuilder::scaled($hours, 2) : null,
                21  => (!$isAbsent && $usage->pickup_done) ? 1 : null,
                22  => (!$isAbsent && $usage->dropoff_done) ? 1 : null,
                34  => $isAbsent ? null : ($usage->is_school_day ? '1' : '2'), // 提供形態: 1=授業終了後, 2=休業日
                36  => $isAbsent ? '8' : null,                                 // サービス提供の状況: 8=欠席（欠席時対応加算）
                111 => $extension && !$isAbsent ? $this->extensionTier($extension) : null,
            ]);
        }

        // 基本情報レコード（01・全172項目）
        $builder->addRecord($this->record(172, [
            1   => 'K611',
            2   => '01',
            3   => $serviceYm,
            4   => $cert?->municipality_code,
            5   => $facility->facility_code,
            6   => $cert?->certificate_number,
            7   => $formType,
            19  => NhifExchangeFileBuilder::scaled($totalHours, 2), // 算定時間数計
            34  => $transport ?: null,                              // 送迎加算（片道回数）
            37  => (int) $detail->total_days,                       // 算定日数
            111 => $typeCode,                                       // 施設種類
            170 => $extensionDays ?: null,                          // 延長支援加算（回）
        ]));

        foreach ($dailyRows as $row) {
            $builder->addRecord($row);
        }
    }

    // ── K411 上限管理結果票 ──

    private function addCapManagementRecords($builder, CopaymentCapManagement $mgmt, Facility $facility, string $serviceYm, string $yearMonth): void
    {
        $child = $mgmt->child;
        $cert  = $child->recipientCertificates
            ->where('status', 'active')
            ->sortByDesc('valid_from')
            ->first();
        $guardian = $child->guardians->firstWhere('pivot.is_primary', true) ?? $child->guardians->first();

        // 基本情報レコード（01・全14項目）
        $builder->addRecord($this->record(14, [
            1  => 'K411',
            2  => '01',
            3  => $serviceYm,
            4  => '1', // 作成区分: 1=新規（修正・取消の再提出は運用で対応）
            5  => $cert?->municipality_code,
            6  => $facility->facility_code,
            7  => $cert?->certificate_number,
            8  => NhifExchangeFileBuilder::kana($guardian?->name_kana, 25),
            9  => NhifExchangeFileBuilder::kana($child->name_kana, 25),
            10 => (int) $mgmt->cap_amount,
            11 => $mgmt->management_result,
            12 => (int) $mgmt->details->sum('total_amount'),
            13 => (int) $mgmt->details->sum('copayment_amount'),
            14 => (int) $mgmt->details->sum('adjusted_amount'),
        ]));

        // 明細情報レコード（02・全11項目）: 事業所ごと
        $index = 0;
        foreach ($mgmt->details->sortByDesc('is_managing_facility') as $capDetail) {
            $index++;
            $builder->addRecord($this->record(11, [
                1  => 'K411',
                2  => '02',
                3  => $serviceYm,
                4  => $cert?->municipality_code,
                5  => $facility->facility_code,
                6  => $cert?->certificate_number,
                7  => $index,
                8  => $this->capDetailFacilityCode($capDetail, $facility),
                9  => (int) $capDetail->total_amount,
                10 => (int) $capDetail->copayment_amount,
                11 => (int) $capDetail->adjusted_amount,
            ]));
        }
    }

    // ── helpers ──

    /**
     * 項目番号→値のマップから固定項目数のレコード配列（1始まり→0始まり）を作る
     *
     * @param array<int, string|int|null> $values
     * @return array<int, string|null>
     */
    private function record(int $itemCount, array $values): array
    {
        $fields = array_fill(0, $itemCount, null);
        foreach ($values as $itemNo => $value) {
            $fields[$itemNo - 1] = $value === null ? null : (string) $value;
        }

        return $fields;
    }

    /**
     * 決定利用者負担額（上限額管理を行った場合は管理結果額）
     */
    private function decidedCopayment(BillingDetail $detail): int
    {
        return (int) ($detail->cap_result_amount ?? $detail->copayment_cap_applied);
    }

    /**
     * 算定時間数（時間）: 個別支援計画の計画時間を優先し、なければ実利用時間
     */
    private function calculatedHours(?int $plannedMinutes, $usage): ?float
    {
        if ($plannedMinutes) {
            return round($plannedMinutes / 60, 2);
        }

        if ($usage->check_in_time && $usage->check_out_time) {
            $minutes = Carbon::parse($usage->check_in_time)->diffInMinutes(Carbon::parse($usage->check_out_time), true);

            return round($minutes / 60, 2);
        }

        return null;
    }

    /**
     * 延長支援加算の区分（1: 30分〜1時間未満等 / 2: 1時間以上2時間未満 / 3: 2時間以上）
     */
    private function extensionTier($dailyRecord): string
    {
        $name = mb_convert_kana($dailyRecord->serviceCodeMaster?->service_name ?? '', 'n');

        if (str_contains($name, '2時間以上')) {
            return '3';
        }
        if (str_contains($name, '1時間以上')) {
            return '2';
        }

        return '1';
    }

    private function capDetailFacilityCode($capDetail, Facility $managingFacility): string
    {
        if ($capDetail->is_managing_facility) {
            return $managingFacility->facility_code ?? '';
        }

        if ($capDetail->billable_facility_type === ExternalFacility::class) {
            return ExternalFacility::find($capDetail->billable_facility_id)?->facility_number ?? '';
        }

        if ($capDetail->billable_facility_type === Facility::class) {
            return Facility::find($capDetail->billable_facility_id)?->facility_code ?? '';
        }

        return '';
    }

    /**
     * 処理対象年月＝サービス提供月の翌月（提出月）
     */
    private function processingYm(string $yearMonth): string
    {
        return Carbon::createFromFormat('Y-m', $yearMonth)->addMonth()->format('Ym');
    }

    /**
     * ファイル保存。ファイル名は仕様（英字始まり8桁以内+.CSV）に従い、
     * 事業所間の衝突を避けるため事業所番号のサブディレクトリに置く。
     */
    private function store(Facility $facility, string $dataType, string $serviceYm, NhifExchangeFileBuilder $builder): string
    {
        $fileName = $dataType . substr($serviceYm, 2) . '.CSV'; // 例: データ種別K11+提供年月202607 → K112607.CSV
        $dir      = 'billing_csv/' . ($facility->facility_code ?: 'F' . $facility->id);
        $filePath = "{$dir}/{$fileName}";

        Storage::disk('local')->put($filePath, $builder->render());

        return $filePath;
    }
}
