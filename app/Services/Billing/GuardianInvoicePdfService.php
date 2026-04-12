<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use App\Models\GuardianInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 利用者向けPDF請求書生成
 */
class GuardianInvoicePdfService
{
    /**
     * 単一の請求書PDFを生成
     */
    public function generateInvoice(GuardianInvoice $invoice): string
    {
        $invoice->load([
            'billingDetail.billingDetailLines',
            'billingDetail.billingPeriod',
            'child',
            'guardian',
            'facility',
        ]);

        $pdf = Pdf::loadView('billing.invoice-pdf', [
            'invoice'  => $invoice,
            'detail'   => $invoice->billingDetail,
            'child'    => $invoice->child,
            'guardian'  => $invoice->guardian,
            'facility' => $invoice->facility,
            'lines'    => $invoice->billingDetail->billingDetailLines,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = "invoice_{$invoice->facility_id}_{$invoice->year_month}_{$invoice->child_id}.pdf";
        $filePath = "invoices/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        $invoice->update(['pdf_path' => $filePath]);

        return $filePath;
    }

    /**
     * 月次一括PDF生成
     */
    public function generateBulkInvoices(BillingPeriod $period): array
    {
        $invoices = GuardianInvoice::where('facility_id', $period->facility_id)
            ->where('year_month', $period->year_month)
            ->get();

        $paths = [];
        foreach ($invoices as $invoice) {
            $paths[] = $this->generateInvoice($invoice);
        }

        return $paths;
    }

    /**
     * 請求明細からGuardianInvoiceレコードを生成
     */
    public function createInvoicesFromBillingPeriod(BillingPeriod $period): array
    {
        $period->load('billingDetails.child.guardians');

        $invoices = [];

        foreach ($period->billingDetails as $detail) {
            $child = $detail->child;
            $primaryGuardian = $child->guardians()
                ->wherePivot('is_primary', true)
                ->first();

            if (!$primaryGuardian) {
                $primaryGuardian = $child->guardians()->first();
            }

            if (!$primaryGuardian) {
                continue;
            }

            $invoice = GuardianInvoice::updateOrCreate(
                [
                    'billing_detail_id' => $detail->id,
                    'child_id'          => $child->id,
                ],
                [
                    'guardian_id'       => $primaryGuardian->id,
                    'facility_id'      => $period->facility_id,
                    'year_month'       => $period->year_month,
                    'copayment_amount' => $detail->copayment_cap_applied,
                    'total_amount'     => $detail->copayment_cap_applied,
                    'payment_status'   => 'unpaid',
                    'due_date'         => now()->addMonth()->startOfMonth()->addDays(14),
                ]
            );

            $invoices[] = $invoice;
        }

        return $invoices;
    }
}
