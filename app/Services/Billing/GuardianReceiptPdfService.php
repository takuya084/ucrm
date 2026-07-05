<?php

namespace App\Services\Billing;

use App\Models\GuardianInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 利用者負担金の領収書PDFを生成
 */
class GuardianReceiptPdfService
{
    public function generate(GuardianInvoice $invoice): string
    {
        $invoice->load(['child', 'guardian', 'facility']);

        [$y, $m] = explode('-', $invoice->year_month);

        $pdf = Pdf::loadView('billing.receipt-pdf', [
            'invoice'        => $invoice,
            'child'          => $invoice->child,
            'guardian'       => $invoice->guardian,
            'facility'       => $invoice->facility,
            // 再発行でも同一番号になるよう請求書IDから採番する
            'receiptNumber'  => sprintf('R%s-%05d', str_replace('-', '', $invoice->year_month), $invoice->id),
            'amount'         => $invoice->paid_amount ?: $invoice->total_amount,
            'yearMonthLabel' => sprintf('%d年%d月', (int) $y, (int) $m),
            'paidAtLabel'    => $invoice->paid_at?->format('Y年n月j日') ?? '―',
        ])->setPaper('A4', 'portrait');

        $filename = "receipt_{$invoice->facility_id}_{$invoice->year_month}_{$invoice->child_id}.pdf";
        $path     = "receipts/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
