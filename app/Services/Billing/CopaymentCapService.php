<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\CopaymentCapDetail;
use App\Models\CopaymentCapManagement;
use App\Models\Child;
use App\Models\ExternalFacility;
use App\Models\Facility;
use Illuminate\Support\Facades\DB;

/**
 * 上限額管理・他事業所按分計算
 */
class CopaymentCapService
{
    /**
     * 児童の月間上限管理を計算
     */
    public function calculateCap(Child $child, string $yearMonth, int $facilityId): ?CopaymentCapManagement
    {
        $certificate = $child->recipientCertificates()
            ->where('status', 'active')
            ->where('valid_from', '<=', $yearMonth . '-01')
            ->where(function ($q) use ($yearMonth) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', $yearMonth . '-01');
            })
            ->latest('valid_from')
            ->first();

        if (!$certificate || !$certificate->is_cap_management_target) {
            return null;
        }

        $capAmount = $certificate->copayment_cap_monthly ?? 0;
        if ($capAmount <= 0) {
            return null;
        }

        $managingFacilityId = $certificate->cap_managing_facility_id ?? $facilityId;

        return DB::transaction(function () use ($child, $yearMonth, $managingFacilityId, $capAmount) {
            $management = CopaymentCapManagement::updateOrCreate(
                [
                    'child_id'              => $child->id,
                    'year_month'            => $yearMonth,
                    'managing_facility_id'  => $managingFacilityId,
                ],
                [
                    'cap_amount'          => $capAmount,
                    'status'              => 'draft',
                ]
            );

            // 既存の明細を金額情報を保持しながら再生成する準備
            $previousExternalAmounts = $management->details()
                ->where('billable_facility_type', ExternalFacility::class)
                ->get()
                ->keyBy('billable_facility_id');

            $management->details()->delete();

            // この児童の自社事業所の請求明細
            $billingDetails = BillingDetail::where('child_id', $child->id)
                ->whereHas('billingPeriod', function ($q) use ($yearMonth) {
                    $q->where('year_month', $yearMonth);
                })
                ->with('billingPeriod.facility')
                ->get();

            $totalCopayment = 0;

            foreach ($billingDetails as $detail) {
                $facility = $detail->billingPeriod->facility;
                $copayment = $detail->copayment_amount;
                $totalCopayment += $copayment;

                CopaymentCapDetail::create([
                    'copayment_cap_management_id' => $management->id,
                    'facility_id'                 => $facility->id,
                    'billable_facility_type'      => Facility::class,
                    'billable_facility_id'        => $facility->id,
                    'facility_name'               => $facility->name,
                    'total_amount'                => $detail->total_amount,
                    'copayment_amount'            => $copayment,
                    'adjusted_amount'             => $copayment,
                    'is_managing_facility'        => $facility->id === $managingFacilityId,
                ]);
            }

            // 受給者証に紐付く他社事業所の枠を作成（金額は手入力）
            foreach ($certificate->externalFacilities as $externalFacility) {
                $prev = $previousExternalAmounts->get($externalFacility->id);
                $copayment = $prev?->copayment_amount ?? 0;
                $totalCopayment += $copayment;

                CopaymentCapDetail::create([
                    'copayment_cap_management_id' => $management->id,
                    'facility_id'                 => null,
                    'billable_facility_type'      => ExternalFacility::class,
                    'billable_facility_id'        => $externalFacility->id,
                    'facility_name'               => $externalFacility->name,
                    'total_amount'                => $prev?->total_amount ?? 0,
                    'copayment_amount'            => $copayment,
                    'adjusted_amount'             => $copayment,
                    'is_managing_facility'        => false,
                ]);
            }

            // 按分計算
            $detailCount = $management->details()->count();

            if ($totalCopayment <= $capAmount) {
                // 上限以下：按分不要
                $management->update([
                    'total_copayment'     => $totalCopayment,
                    'adjusted_copayment'  => $totalCopayment,
                    'management_result'   => $detailCount > 1 ? '2' : '1',
                ]);
            } else {
                // 上限超過：按分が必要
                $this->allocateAmongFacilities($management, $capAmount, $totalCopayment, $managingFacilityId);
            }

            return $management->load('details');
        });
    }

    /**
     * 複数事業所間での上限額按分
     */
    private function allocateAmongFacilities(
        CopaymentCapManagement $management,
        int $capAmount,
        int $totalCopayment,
        int $managingFacilityId
    ): void {
        $details = $management->details;
        $allocatedTotal = 0;

        foreach ($details as $detail) {
            if ($detail->facility_id === $managingFacilityId) {
                continue; // 管理事業所は最後に計算
            }

            // 按分: 上限額 × (当該事業所負担額 / 全事業所合計)
            $adjusted = (int) floor($capAmount * $detail->copayment_amount / $totalCopayment);
            $detail->update(['adjusted_amount' => $adjusted]);
            $allocatedTotal += $adjusted;
        }

        // 管理事業所は端数を引き受け
        $managingDetail = $details->firstWhere('facility_id', $managingFacilityId);
        if ($managingDetail) {
            $managingDetail->update(['adjusted_amount' => $capAmount - $allocatedTotal]);
        }

        $management->update([
            'total_copayment'     => $totalCopayment,
            'adjusted_copayment'  => $capAmount,
            'management_result'   => '3',
        ]);
    }

    /**
     * 既存明細から合計と按分を再計算（他社金額更新後などに呼ぶ）
     */
    public function recomputeAllocation(CopaymentCapManagement $management): void
    {
        $details = $management->details;
        $totalCopayment = (int) $details->sum('copayment_amount');
        $capAmount = $management->cap_amount;
        $managingFacilityId = $management->managing_facility_id;

        if ($totalCopayment <= $capAmount) {
            foreach ($details as $d) {
                $d->update(['adjusted_amount' => $d->copayment_amount]);
            }
            $management->update([
                'total_copayment'    => $totalCopayment,
                'adjusted_copayment' => $totalCopayment,
                'management_result'  => $details->count() > 1 ? '2' : '1',
            ]);
            return;
        }

        $this->allocateAmongFacilities($management, $capAmount, $totalCopayment, $managingFacilityId);
    }

    /**
     * 月次上限管理の一括計算
     */
    public function calculateMonthlyCapManagement(int $facilityId, string $yearMonth): array
    {
        $results = [];

        // 上限管理対象の児童を取得
        $children = Child::where('facility_id', $facilityId)
            ->whereHas('recipientCertificates', function ($q) use ($yearMonth) {
                $q->where('is_cap_management_target', true)
                  ->where('status', 'active')
                  ->where('valid_from', '<=', $yearMonth . '-01')
                  ->where(function ($q2) use ($yearMonth) {
                      $q2->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $yearMonth . '-01');
                  });
            })
            ->get();

        foreach ($children as $child) {
            $result = $this->calculateCap($child, $yearMonth, $facilityId);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
