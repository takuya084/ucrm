<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ContactNote;
use App\Models\Facility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 連絡帳の年間PDF（年末に1年分を出力して保存する運用向け）
 *
 * 保存義務対象の記録のため、公開済み・下書き・家庭側記入のみの行も
 * すべて出力する（下書きは「未公開」と明示）。
 */
class ContactNotePdfService
{
    /**
     * 児童×年の連絡帳PDFを生成して保存先パスを返す。対象が0件なら null。
     */
    public function generateYearly(Child $child, int $year): ?string
    {
        $notes = ContactNote::where('child_id', $child->id)
            ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
            ->with(['staff:id,name', 'supportRecord.programs'])
            ->orderBy('date')
            ->get();

        if ($notes->isEmpty()) {
            return null;
        }

        $child->load('facility');

        $pdf = Pdf::loadView('contact-notes.pdf-yearly', [
            'child'    => $child,
            'facility' => $child->facility,
            'year'     => $year,
            'notes'    => $notes,
        ])->setPaper('A4', 'portrait');

        $path = "contact-notes/{$year}/contact_notes_{$child->id}_{$year}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * 施設の全児童分をまとめた ZIP を生成して保存先パスを返す。対象が0件なら null。
     */
    public function generateYearlyZip(Facility $facility, int $year): ?string
    {
        // 児童数分の dompdf 生成を1リクエストで行うため、実行時間制限を緩める
        set_time_limit(600);

        $childIds = ContactNote::where('facility_id', $facility->id)
            ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
            ->distinct()
            ->pluck('child_id');

        if ($childIds->isEmpty()) {
            return null;
        }

        $children = Child::whereIn('id', $childIds)->orderBy('name_kana')->get();

        $zipPath = "contact-notes/{$year}/contact_notes_{$facility->id}_{$year}.zip";
        Storage::disk('local')->makeDirectory("contact-notes/{$year}");
        $zipFullPath = Storage::disk('local')->path($zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipFullPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルを作成できませんでした。');
        }

        foreach ($children as $child) {
            $pdfPath = $this->generateYearly($child, $year);
            if ($pdfPath) {
                // ZIP 内のファイル名は人が読める形式にする
                $zip->addFile(
                    Storage::disk('local')->path($pdfPath),
                    "連絡帳_{$year}年_{$child->name}.pdf"
                );
            }
        }

        $zip->close();

        return $zipPath;
    }
}
