<?php

namespace App\Services;

use App\Models\MonitoringRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * モニタリング記録PDF（保護者配布・署名用）
 */
class MonitoringRecordPdfService
{
    public function generate(MonitoringRecord $record): string
    {
        $record->load(['child.facility', 'staff:id,name']);

        $pdf = Pdf::loadView('monitoring-records.pdf', [
            'record'   => $record,
            'child'    => $record->child,
            'facility' => $record->child->facility,
        ])->setPaper('A4', 'portrait');

        $date     = optional($record->monitoring_date)->format('Ymd') ?? 'nodate';
        $filename = "monitoring_{$record->child_id}_{$record->id}_{$date}.pdf";
        $path     = "monitoring-records/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
