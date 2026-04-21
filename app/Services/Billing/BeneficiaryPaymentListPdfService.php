<?php

namespace App\Services\Billing;

use App\Models\BillingPeriod;
use App\Models\CopaymentCapManagement;
use App\Models\Facility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 利用者負担額一覧表PDFを生成（事業所が管理事業所や市町村へ提出する一覧）
 */
class BeneficiaryPaymentListPdfService
{
    public function generate(int $facilityId, string $yearMonth): string
    {
        $facility = Facility::findOrFail($facilityId);

        $period = BillingPeriod::where('facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with([
                'billingDetails.child.guardians',
                'billingDetails.recipientCertificate',
            ])
            ->first();

        $capByChild = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->get()
            ->keyBy('child_id');

        $rows = collect();
        if ($period) {
            $rows = $period->billingDetails
                ->sortBy(fn ($d) => $d->child->name_kana ?? $d->child->name)
                ->values()
                ->map(function ($d) use ($capByChild) {
                    $primaryGuardian = $d->child->guardians
                        ->first(fn ($g) => (bool) ($g->pivot->is_primary ?? false))
                        ?? $d->child->guardians->first();

                    $cap = $capByChild->get($d->child_id);

                    return [
                        'certificate_number' => $d->recipientCertificate?->certificate_number ?? '',
                        'municipality_code'  => $d->recipientCertificate?->municipality_code ?? '',
                        'guardian_name'      => $primaryGuardian?->name ?? '',
                        'child_name'         => $d->child->name,
                        'total_amount'       => (int) $d->total_amount,
                        'copayment_amount'   => (int) $d->copayment_amount,
                        'adjusted_amount'    => $cap ? (int) $cap->adjusted_copayment : (int) $d->copayment_amount,
                        'is_cap_target'      => (bool) ($d->recipientCertificate?->is_cap_management_target ?? false),
                        'cap_result'         => $cap?->management_result,
                    ];
                });
        }

        $ymJa = \Carbon\Carbon::parse($yearMonth . '-01')->format('Y年n月');

        $pdf = Pdf::loadView('billing.beneficiary-payment-list-pdf', [
            'facility' => $facility,
            'rows'     => $rows,
            'yearMonth' => $yearMonth,
            'ymJa'     => $ymJa,
            'totalFee'     => $rows->sum('total_amount'),
            'totalCopay'   => $rows->sum('copayment_amount'),
            'totalAdjusted' => $rows->sum('adjusted_amount'),
        ])->setPaper('A4', 'landscape');

        $ym = str_replace('-', '', $yearMonth);
        $filename = "beneficiary_payment_list_{$facilityId}_{$ym}.pdf";
        $path = "cap-management/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
