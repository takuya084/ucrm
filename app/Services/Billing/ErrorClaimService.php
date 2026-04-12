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
}
