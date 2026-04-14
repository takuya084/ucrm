<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * サービス提供実績記録票（保護者確認印用）PDFを生成
 */
class PerformanceRecordPdfService
{
    /**
     * 単一児童分の実績記録票PDFを生成
     */
    public function generate(BillingDetail $detail): string
    {
        $detail->load([
            'child',
            'billingPeriod.facility',
            'recipientCertificate',
            'dailyServiceRecords.usageRecord',
            'dailyServiceRecords.serviceCodeMaster',
        ]);

        $yearMonth = $detail->billingPeriod->year_month;
        $rows = $this->buildCalendarRows($detail, $yearMonth);

        $pdf = Pdf::loadView('billing.performance-record-pdf', [
            'detail'      => $detail,
            'child'       => $detail->child,
            'facility'    => $detail->billingPeriod->facility,
            'certificate' => $detail->recipientCertificate,
            'yearMonth'   => $yearMonth,
            'rows'        => $rows,
        ])->setPaper('A4', 'portrait');

        $ym       = str_replace('-', '', $yearMonth);
        $filename = "performance_{$detail->billing_period_id}_{$detail->child_id}_{$ym}.pdf";
        $path     = "performance-records/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * 請求期間全員分をZIPにまとめる
     */
    public function generateBundle(BillingPeriod $period): string
    {
        $period->load('billingDetails.child');

        $tmpDir = storage_path('app/performance-records/tmp_' . $period->id . '_' . time());
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);

        $zipPath = "performance-records/performance_{$period->facility_id}_" . str_replace('-', '', $period->year_month) . '.zip';
        $zipAbs  = Storage::disk('local')->path($zipPath);
        if (!is_dir(dirname($zipAbs))) mkdir(dirname($zipAbs), 0775, true);

        $zip = new ZipArchive();
        $zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($period->billingDetails as $detail) {
            $path    = $this->generate($detail);
            $absPath = Storage::disk('local')->path($path);
            $childName = preg_replace('/[\/\\\\:\*\?"<>\|]/', '_', $detail->child->name ?? "child_{$detail->child_id}");
            $zip->addFile($absPath, "{$childName}.pdf");
        }
        $zip->close();

        return $zipPath;
    }

    /**
     * 月内の日付ごとの行データを組み立て
     *
     * @return array<int, array{date:string, weekday:string, record:?\App\Models\DailyServiceRecord}>
     */
    private function buildCalendarRows(BillingDetail $detail, string $yearMonth): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $yearMonth . '-01');
        $days  = $start->daysInMonth;

        // usage_record_id ごとに先頭1件を採用（同日複数行は集約）
        $recordsByDate = $detail->dailyServiceRecords
            ->groupBy(fn($r) => $r->usageRecord?->date)
            ->map(fn($group) => $group->first());

        $wdays = ['日', '月', '火', '水', '木', '金', '土'];
        $rows  = [];
        for ($d = 1; $d <= $days; $d++) {
            $date = $start->copy()->day($d);
            $key  = $date->toDateString();
            $rows[] = [
                'date'    => $date->format('n/j'),
                'weekday' => $wdays[$date->dayOfWeek],
                'is_weekend' => in_array($date->dayOfWeek, [0, 6], true),
                'record'  => $recordsByDate[$key] ?? null,
            ];
        }

        return $rows;
    }
}
