<?php

namespace App\Services;

use App\Models\SupportPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * 個別支援計画書PDF（保護者配布・署名用）
 */
class SupportPlanPdfService
{
    public function generate(SupportPlan $plan): string
    {
        $plan->load(['child.facility', 'staff:id,name']);

        $pdf = Pdf::loadView('support-plans.pdf', [
            'plan'     => $plan,
            'child'    => $plan->child,
            'facility' => $plan->child->facility,
        ])->setPaper('A4', 'portrait');

        $date     = optional($plan->plan_date)->format('Ymd') ?? 'nodate';
        $filename = "support_plan_{$plan->child_id}_{$plan->id}_{$date}.pdf";
        $path     = "support-plans/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
