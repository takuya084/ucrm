<?php

namespace App\Services\Billing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

/** Font metrics belong in writable runtime storage, including read-only demo images. */
final class RuntimePdf
{
    public static function loadView(string $view, array $data)
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        return Pdf::setOptions([
            'fontDir' => $cache, 'fontCache' => $cache,
            'isFontSubsettingEnabled' => true,
        ], true)
            ->loadView($view, $data);
    }
}
