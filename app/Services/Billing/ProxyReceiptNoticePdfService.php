<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * 法定代理受領額通知書PDFを生成
 * （児童福祉法第21条の5の7第11項: 代理受領した給付費の額を保護者に通知する義務）
 */
class ProxyReceiptNoticePdfService
{
    /**
     * 単一児童分の通知書PDFを生成
     */
    public function generate(BillingDetail $detail): string
    {
        $detail->load([
            'child.guardians',
            'billingPeriod.facility',
            'recipientCertificate',
        ]);

        $yearMonth = $detail->billingPeriod->year_month;
        [$y, $m]   = explode('-', $yearMonth);

        // 主連絡先の保護者（未設定なら先頭）
        $guardian = $detail->child->guardians->firstWhere('pivot.is_primary', true)
            ?? $detail->child->guardians->first();

        $pdf = Pdf::loadView('billing.proxy-receipt-notice-pdf', [
            'detail'         => $detail,
            'child'          => $detail->child,
            'guardian'       => $guardian,
            'facility'       => $detail->billingPeriod->facility,
            'certificate'    => $detail->recipientCertificate,
            'yearMonthLabel' => sprintf('%d年%d月', (int) $y, (int) $m),
        ])->setPaper('A4', 'portrait');

        $ym       = str_replace('-', '', $yearMonth);
        $filename = "proxy_receipt_{$detail->billing_period_id}_{$detail->child_id}_{$ym}.pdf";
        $path     = "proxy-receipts/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * 請求期間全員分をZIPにまとめる
     */
    public function generateBundle(BillingPeriod $period): string
    {
        $period->load('billingDetails.child');

        $zipPath = "proxy-receipts/proxy_receipts_{$period->facility_id}_" . str_replace('-', '', $period->year_month) . '.zip';
        $zipAbs  = Storage::disk('local')->path($zipPath);
        if (!is_dir(dirname($zipAbs))) {
            mkdir(dirname($zipAbs), 0775, true);
        }

        $zip = new ZipArchive();
        $zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($period->billingDetails as $detail) {
            $pdfPath   = $this->generate($detail);
            $absPath   = Storage::disk('local')->path($pdfPath);
            $childName = preg_replace('/[\/\\\\:*?"<>|]/u', '_', $detail->child->name ?? "child_{$detail->child_id}");
            $zip->addFile($absPath, "{$childName}.pdf");
        }

        $zip->close();

        return $zipPath;
    }
}
