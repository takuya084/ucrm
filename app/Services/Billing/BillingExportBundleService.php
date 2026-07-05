<?php

namespace App\Services\Billing;

use App\Models\BillingExport;
use App\Models\BillingPeriod;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * 国保連提出用 複式CSV（請求明細・実績記録票・上限管理結果票）を
 * ZIP にまとめて出力し、履歴を残す。
 */
class BillingExportBundleService
{
    public function __construct(
        private NhifCsvExportService $csv,
        private StaffingComplianceService $staffing,
    ) {}

    /**
     * 事前バリデーション
     */
    public function validate(BillingPeriod $period): array
    {
        $period->loadMissing([
            'facility',
            'billingDetails.child',
            'billingDetails.recipientCertificate',
        ]);

        $warnings = [];
        $facility = $period->facility;

        if (!$facility->facility_code || !preg_match('/^\d{10}$/', $facility->facility_code)) {
            $warnings[] = ['level' => 'error', 'message' => "事業所番号が10桁の数字ではありません（現在値: {$facility->facility_code}）"];
        }

        if (!$facility->area_category_code) {
            $warnings[] = ['level' => 'error', 'message' => '地域区分コードが未設定です（施設情報で設定してください。明細書の必須項目です）'];
        }

        if ($period->billingDetails->isEmpty()) {
            $warnings[] = ['level' => 'error', 'message' => '請求明細が0件です。計算を実行してください。'];
        }

        foreach ($period->billingDetails as $detail) {
            $child = $detail->child;
            $cert  = $detail->recipientCertificate;
            $label = $child->name ?? "ID:{$detail->child_id}";

            if (!$cert) {
                $warnings[] = ['level' => 'error', 'message' => "{$label}: 受給者証が紐付いていません"];
                continue;
            }
            if (!$cert->certificate_number) {
                $warnings[] = ['level' => 'error', 'message' => "{$label}: 受給者証番号が未登録です"];
            }
            if (!$cert->municipality_code || !preg_match('/^\d{6}$/', $cert->municipality_code)) {
                $warnings[] = ['level' => 'warning', 'message' => "{$label}: 市区町村コードが6桁数字ではありません"];
            }
            if ($detail->total_days <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => "{$label}: 利用日数が0日です"];
            }
            if (!$child?->name_kana) {
                $warnings[] = ['level' => 'warning', 'message' => "{$label}: カナ氏名が未登録です（明細書・実績記録票のカナ欄が空欄になります）"];
            }

            // 支給量（月間支給日数）超過チェック
            if ($cert->monthly_limit && $detail->total_days > $cert->monthly_limit) {
                $warnings[] = [
                    'level'   => 'error',
                    'message' => "{$label}: 利用日数 {$detail->total_days}日 が受給者証の支給量 {$cert->monthly_limit}日 を超過しています（返戻対象）",
                ];
            } elseif ($cert->monthly_limit && $detail->total_days === (int) $cert->monthly_limit) {
                $warnings[] = [
                    'level'   => 'warning',
                    'message' => "{$label}: 利用日数が支給量の上限 {$cert->monthly_limit}日 に達しています",
                ];
            }

            // 受給者証の有効期間チェック
            $periodDate = $period->year_month . '-01';
            if ($cert->valid_from && $cert->valid_from->format('Y-m-d') > $periodDate) {
                $warnings[] = ['level' => 'error', 'message' => "{$label}: 受給者証の有効開始日（{$cert->valid_from->format('Y-m-d')}）が請求対象月より後です"];
            }
            if ($cert->valid_to && $cert->valid_to->format('Y-m') < $period->year_month) {
                $warnings[] = ['level' => 'error', 'message' => "{$label}: 受給者証の有効期限（{$cert->valid_to->format('Y-m-d')}）が請求対象月より前に切れています"];
            }
        }

        // 人員配置基準チェック（シフト連動）
        foreach ($this->staffing->checkPeriod($period) as $w) {
            $warnings[] = $w;
        }

        return $warnings;
    }

    /**
     * 複式ZIPを生成（請求・実績・上限管理を同梱）
     */
    public function generateBundle(BillingPeriod $period, ?int $userId = null): BillingExport
    {
        $warnings = $this->validate($period);

        $facility   = $period->facility;
        $yearMonth  = str_replace('-', '', $period->year_month);
        $facilityNo = $facility->facility_code ?: '0000000000';
        $baseName   = "JIG{$facilityNo}_{$yearMonth}";

        // 各CSVを生成（NhifCsvExportService が storage/app/billing_csv/ に出力）
        $billingCsvPath     = $this->csv->generateBillingCsv($period);
        $performanceCsvPath = $this->csv->generatePerformanceRecordCsv($period);
        $capCsvPath         = $this->csv->generateCapManagementCsv($facility->id, $period->year_month);

        // ZIP にまとめる
        $zipRelative = "billing_csv/{$baseName}.zip";
        $zipAbsolute = Storage::disk('local')->path($zipRelative);

        @mkdir(dirname($zipAbsolute), 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP ファイルを作成できませんでした。');
        }

        // ZIP 内のファイル名は電子請求受付システムの規定（英字始まり8桁以内+.CSV）のまま同梱する
        $included = [
            basename($billingCsvPath)     => $billingCsvPath,
            basename($performanceCsvPath) => $performanceCsvPath,
            basename($capCsvPath)         => $capCsvPath,
        ];

        foreach ($included as $localName => $srcRelative) {
            $srcAbsolute = Storage::disk('local')->path($srcRelative);
            if (file_exists($srcAbsolute)) {
                $zip->addFile($srcAbsolute, $localName);
            }
        }
        $zip->close();

        return BillingExport::create([
            'facility_id'       => $facility->id,
            'billing_period_id' => $period->id,
            'kind'              => 'bundle',
            'file_path'         => $zipRelative,
            'file_name'         => "{$baseName}.zip",
            'file_size'         => filesize($zipAbsolute) ?: 0,
            'included_files'    => array_keys($included),
            'warnings'          => $warnings,
            'created_by'        => $userId,
        ]);
    }
}
