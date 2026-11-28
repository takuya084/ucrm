<?php

namespace App\Services\Billing;

use App\Models\CopaymentCapManagement;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Validation\ValidationException;

/**
 * 利用者負担上限額管理結果票PDFを生成
 */
class CopaymentCapPdfService
{
    /**
     * 単一児童分のPDFを生成
     */
    public function generate(CopaymentCapManagement $management): string
    {
        $management->load([
            'child.guardians',
            'child.activeRecipientCertificate',
            'managingFacility',
            'details.billableFacility',
        ]);

        $certificate = $management->child->activeRecipientCertificate;
        $primaryGuardian = $management->child->guardians
            ->first(fn ($g) => (bool) ($g->pivot->is_primary ?? false))
            ?? $management->child->guardians->first();

        $pdf = RuntimePdf::loadView('billing.copayment-cap-pdf', [
            'management'     => $management,
            'child'          => $management->child,
            'facility'       => $management->managingFacility,
            'certificate'    => $certificate,
            'guardian'       => $primaryGuardian,
            'details'        => $management->details->sortByDesc('is_managing_facility')->values(),
        ])->setPaper('A4', 'portrait');

        $ym       = str_replace('-', '', $management->year_month);
        $filename = "cap_{$management->managing_facility_id}_{$management->child_id}_{$ym}.pdf";
        $path     = "cap-management/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * 対象月の管理票をZIPにまとめる
     */
    public function generateBundle(int $facilityId, string $yearMonth): string
    {
        $managements = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with('child')
            ->get();

        if ($managements->isEmpty()) {
            throw ValidationException::withMessages(['billing' => '対象月の上限管理記録がありません。対象月と管理記録を確認してください。']);
        }

        $zipPath = "cap-management/cap_{$facilityId}_" . str_replace('-', '', $yearMonth) . '.zip';
        $zipAbs  = Storage::disk('local')->path($zipPath);
        if (!is_dir(dirname($zipAbs))) mkdir(dirname($zipAbs), 0775, true);

        $zip = new ZipArchive();
        if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('管理結果票ZIPの保存先を開けません。');
        }

        foreach ($managements as $m) {
            $path      = $this->generate($m);
            $absPath   = Storage::disk('local')->path($path);
            $childName = preg_replace('/[\/\\\\:\*\?"<>\|]/', '_', $m->child->name ?? "child_{$m->child_id}");
            $zip->addFile($absPath, "{$childName}_{$m->child_id}.pdf");
        }
        $zip->close();

        return $zipPath;
    }
}
