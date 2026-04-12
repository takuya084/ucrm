<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use App\Models\CopaymentCapManagement;
use App\Models\Facility;
use Illuminate\Support\Facades\Storage;
use League\Csv\CharsetConverter;
use League\Csv\Writer;

/**
 * 国保連CSV出力（Shift_JIS対応）
 */
class NhifCsvExportService
{
    /**
     * 請求明細CSVを生成
     */
    public function generateBillingCsv(BillingPeriod $period): string
    {
        $period->load([
            'facility',
            'billingDetails.child',
            'billingDetails.recipientCertificate',
            'billingDetails.billingDetailLines',
        ]);

        $facility = $period->facility;
        $filename = "billing_{$facility->facility_code}_{$period->year_month}.csv";
        $filePath = "billing_csv/{$filename}";

        $csv = Writer::createFromString();

        // Shift_JIS変換
        $encoder = (new CharsetConverter())
            ->inputEncoding('UTF-8')
            ->outputEncoding('SJIS-win');
        $csv->addFormatter($encoder);

        // ヘッダーレコード
        $csv->insertOne($this->buildHeaderRecord($facility, $period));

        // 明細レコード
        foreach ($period->billingDetails as $detail) {
            $csv->insertOne($this->buildDetailRecord($detail, $facility));

            // サービスコード別明細
            foreach ($detail->billingDetailLines as $line) {
                $csv->insertOne($this->buildServiceCodeRecord($line, $detail));
            }
        }

        // エンドレコード
        $csv->insertOne($this->buildEndRecord($period));

        Storage::disk('local')->put($filePath, $csv->toString());

        return $filePath;
    }

    /**
     * サービス提供実績記録票CSVを生成
     */
    public function generatePerformanceRecordCsv(BillingPeriod $period): string
    {
        $period->load([
            'facility',
            'billingDetails.child',
            'billingDetails.recipientCertificate',
            'billingDetails.dailyServiceRecords.usageRecord',
        ]);

        $facility = $period->facility;
        $filename = "performance_{$facility->facility_code}_{$period->year_month}.csv";
        $filePath = "billing_csv/{$filename}";

        $csv = Writer::createFromString();

        $encoder = (new CharsetConverter())
            ->inputEncoding('UTF-8')
            ->outputEncoding('SJIS-win');
        $csv->addFormatter($encoder);

        // ヘッダー
        $csv->insertOne($this->buildPerformanceHeader($facility, $period));

        foreach ($period->billingDetails as $detail) {
            // 児童ヘッダー
            $csv->insertOne($this->buildChildPerformanceHeader($detail));

            // 日別実績
            foreach ($detail->dailyServiceRecords->sortBy('usageRecord.date') as $dailyRecord) {
                $csv->insertOne($this->buildDailyPerformanceRecord($dailyRecord, $detail));
            }
        }

        $csv->insertOne($this->buildEndRecord($period));

        Storage::disk('local')->put($filePath, $csv->toString());

        return $filePath;
    }

    /**
     * 上限管理結果票CSVを生成
     */
    public function generateCapManagementCsv(int $facilityId, string $yearMonth): string
    {
        $facility = Facility::findOrFail($facilityId);
        $managements = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with(['child', 'details'])
            ->get();

        $filename = "cap_mgmt_{$facility->facility_code}_{$yearMonth}.csv";
        $filePath = "billing_csv/{$filename}";

        $csv = Writer::createFromString();

        $encoder = (new CharsetConverter())
            ->inputEncoding('UTF-8')
            ->outputEncoding('SJIS-win');
        $csv->addFormatter($encoder);

        foreach ($managements as $mgmt) {
            $csv->insertOne([
                $mgmt->child->name,
                $mgmt->year_month,
                $mgmt->cap_amount,
                $mgmt->total_copayment,
                $mgmt->adjusted_copayment,
                $mgmt->management_result,
            ]);

            foreach ($mgmt->details as $capDetail) {
                $csv->insertOne([
                    '',
                    $capDetail->facility_name,
                    $capDetail->total_amount,
                    $capDetail->copayment_amount,
                    $capDetail->adjusted_amount,
                    $capDetail->is_managing_facility ? '1' : '0',
                ]);
            }
        }

        Storage::disk('local')->put($filePath, $csv->toString());

        return $filePath;
    }

    // ── private builders ──

    private function buildHeaderRecord(Facility $facility, BillingPeriod $period): array
    {
        return [
            '1',                                    // レコード種別: ヘッダー
            $facility->facility_code ?? '',         // 事業所番号
            $facility->name,                        // 事業所名
            $period->year_month,                    // 請求年月
            $facility->service_type,                // サービス種別
            $period->billingDetails->count(),       // 明細件数
        ];
    }

    private function buildDetailRecord(BillingDetail $detail, Facility $facility): array
    {
        $cert = $detail->recipientCertificate;
        $child = $detail->child;

        return [
            '2',                                        // レコード種別: 明細
            $cert?->certificate_number ?? '',            // 受給者証番号
            $cert?->municipality_code ?? '',             // 市区町村コード
            $child->name,                                // 児童氏名
            $child->birthdate?->format('Ymd') ?? '',     // 生年月日
            $detail->service_type,                       // サービス種別
            $detail->total_days,                         // 利用日数
            $detail->total_units,                        // 合計単位数
            $detail->total_amount,                       // 費用合計
            $detail->insurance_amount,                   // 給付費
            $detail->copayment_cap_applied,              // 利用者負担額
        ];
    }

    private function buildServiceCodeRecord($line, BillingDetail $detail): array
    {
        return [
            '3',                        // レコード種別: サービスコード明細
            $line->service_code,        // サービスコード
            $line->service_name,        // サービス名
            $line->count,               // 回数
            $line->units_per_count,     // 1回単位数
            $line->total_units,         // 合計単位数
        ];
    }

    private function buildPerformanceHeader(Facility $facility, BillingPeriod $period): array
    {
        return [
            '1',
            $facility->facility_code ?? '',
            $facility->name,
            $period->year_month,
            'performance_record',
        ];
    }

    private function buildChildPerformanceHeader(BillingDetail $detail): array
    {
        $cert = $detail->recipientCertificate;
        return [
            '2',
            $cert?->certificate_number ?? '',
            $detail->child->name,
            $detail->service_type,
            $detail->total_days,
        ];
    }

    private function buildDailyPerformanceRecord($dailyRecord, BillingDetail $detail): array
    {
        $usage = $dailyRecord->usageRecord;
        return [
            '3',
            $usage?->date?->format('Ymd') ?? '',
            $dailyRecord->service_code,
            $dailyRecord->units,
            $usage?->check_in_time ?? '',
            $usage?->check_out_time ?? '',
            $dailyRecord->is_pickup ? '1' : '0',
            $dailyRecord->is_dropoff ? '1' : '0',
        ];
    }

    private function buildEndRecord(BillingPeriod $period): array
    {
        return [
            '9',
            $period->billingDetails->sum('total_amount'),
            $period->billingDetails->sum('insurance_amount'),
            $period->billingDetails->sum('copayment_cap_applied'),
        ];
    }
}
