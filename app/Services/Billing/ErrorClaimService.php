<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\ClaimReturn;
use App\Models\ErrorClaim;
use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use League\Csv\CharsetConverter;
use League\Csv\Writer;

/**
 * 過誤申立処理
 */
class ErrorClaimService
{
    /**
     * 過誤申立を作成
     */
    public function createErrorClaim(
        BillingDetail $detail,
        string $claimType,
        string $reason
    ): ErrorClaim {
        return ErrorClaim::create([
            'facility_id'        => $detail->billingPeriod->facility_id,
            'billing_detail_id'  => $detail->id,
            'original_year_month' => $detail->billingPeriod->year_month,
            'child_id'           => $detail->child_id,
            'claim_type'         => $claimType,
            'reason'             => $reason,
            'status'             => 'draft',
        ]);
    }

    /**
     * 過誤申立CSVを生成
     */
    public function generateErrorClaimCsv(int $facilityId, Collection $claims): string
    {
        $facility = Facility::findOrFail($facilityId);
        $filename = "error_claim_{$facility->facility_code}_" . now()->format('Ymd') . ".csv";
        $filePath = "billing_csv/{$filename}";

        $csv = Writer::createFromString();

        $encoder = (new CharsetConverter())
            ->inputEncoding('UTF-8')
            ->outputEncoding('SJIS-win');
        $csv->addFormatter($encoder);

        foreach ($claims as $claim) {
            $claim->load(['child', 'billingDetail.recipientCertificate']);
            $cert = $claim->billingDetail->recipientCertificate;

            $csv->insertOne([
                $facility->facility_code ?? '',
                $cert?->certificate_number ?? '',
                $cert?->municipality_code ?? '',
                $claim->child->name,
                $claim->original_year_month,
                $claim->claim_type === 'full_cancel' ? '1' : '2',
                $claim->billingDetail->total_amount,
                $claim->reason,
            ]);
        }

        Storage::disk('local')->put($filePath, $csv->toString());

        return $filePath;
    }

    /**
     * 返戻を登録
     */
    public function registerReturn(
        int $facilityId,
        ?int $billingDetailId,
        string $yearMonth,
        int $childId,
        string $returnCode,
        string $returnReason,
        int $originalAmount,
        string $receivedAt
    ): ClaimReturn {
        return ClaimReturn::create([
            'facility_id'        => $facilityId,
            'billing_detail_id'  => $billingDetailId,
            'year_month'         => $yearMonth,
            'child_id'           => $childId,
            'return_code'        => $returnCode,
            'return_reason'      => $returnReason,
            'original_amount'    => $originalAmount,
            'status'             => 'returned',
            'received_at'        => $receivedAt,
        ]);
    }

    /**
     * 国保連からの返戻CSVを一括インポート
     *
     * CSVフォーマット（ヘッダ行必須）:
     *   受給者証番号,サービス提供年月,返戻コード,返戻理由,費用額,受領日
     *   (YYYYMM もしくは YYYY-MM、受領日は YYYY-MM-DD もしくは YYYYMMDD)
     *
     * @return array{imported:int, skipped:int, errors:array<int,string>}
     */
    public function importReturnsCsv(int $facilityId, string $filePath): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['ファイルを読み込めませんでした']];
        }

        // 文字コード自動判定（国保連は Shift_JIS が多い）
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'SJIS', 'CP932', 'EUC-JP'], true) ?: 'UTF-8';
        if ($encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM除去

        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (count($lines) <= 1) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['データ行がありません']];
        }
        array_shift($lines); // ヘッダ行を捨てる

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') continue;
            $lineNo = $i + 2; // ヘッダ込みの実際の行番号
            $cols   = str_getcsv($line);

            if (count($cols) < 6) {
                $errors[] = "行{$lineNo}: 列数不足";
                $skipped++;
                continue;
            }

            [$certNo, $ymRaw, $code, $reason, $amount, $receivedRaw] = array_map('trim', array_slice($cols, 0, 6));

            $yearMonth = $this->normalizeYearMonth($ymRaw);
            $receivedAt = $this->normalizeDate($receivedRaw);

            if (!$yearMonth) { $errors[] = "行{$lineNo}: 年月形式が不正 ({$ymRaw})"; $skipped++; continue; }
            if (!$receivedAt) { $errors[] = "行{$lineNo}: 受領日形式が不正 ({$receivedRaw})"; $skipped++; continue; }

            // 受給者証番号から child_id を逆引き（当該事業所の児童に限る）
            $cert = \App\Models\RecipientCertificate::where('certificate_number', $certNo)
                ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId))
                ->first();

            if (!$cert) {
                $errors[] = "行{$lineNo}: 受給者証番号 {$certNo} に該当する児童が見つかりません";
                $skipped++;
                continue;
            }

            // billing_detail の紐付け（同月の明細があれば）
            $detail = \App\Models\BillingDetail::whereHas('billingPeriod', fn($q) =>
                    $q->where('facility_id', $facilityId)->where('year_month', $yearMonth))
                ->where('child_id', $cert->child_id)
                ->first();

            // 重複チェック（同一 facility + child + year_month + return_code は1件とする）
            $exists = ClaimReturn::where('facility_id', $facilityId)
                ->where('child_id', $cert->child_id)
                ->where('year_month', $yearMonth)
                ->where('return_code', $code)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $this->registerReturn(
                $facilityId,
                $detail?->id,
                $yearMonth,
                $cert->child_id,
                $code,
                $reason,
                (int) preg_replace('/[^0-9-]/', '', $amount),
                $receivedAt,
            );
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function normalizeYearMonth(string $s): ?string
    {
        if (preg_match('/^(\d{4})-?(\d{2})$/', $s, $m)) {
            return "{$m[1]}-{$m[2]}";
        }
        return null;
    }

    private function normalizeDate(string $s): ?string
    {
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $s, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }
}
