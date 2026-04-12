<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingDetailController extends Controller
{
    /**
     * 児童別明細の表示
     */
    public function show(BillingDetail $billingDetail)
    {
        $this->authorizeFacility($billingDetail);

        $billingDetail->load([
            'child',
            'recipientCertificate',
            'billingDetailLines.serviceCodeMaster',
            'dailyServiceRecords.usageRecord',
            'billingPeriod',
        ]);

        return Inertia::render('Billing/Detail/Show', [
            'detail' => $billingDetail,
        ]);
    }

    /**
     * 手動調整フォーム
     */
    public function edit(BillingDetail $billingDetail)
    {
        $this->authorizeFacility($billingDetail);

        if ($billingDetail->status !== 'draft') {
            session()->flash('message', '下書き状態の明細のみ編集できます。');
            session()->flash('status', 'error');
            return back();
        }

        $billingDetail->load([
            'child',
            'recipientCertificate',
            'billingDetailLines.serviceCodeMaster',
            'billingPeriod',
        ]);

        return Inertia::render('Billing/Detail/Edit', [
            'detail' => $billingDetail,
        ]);
    }

    /**
     * 手動調整の保存
     */
    public function update(Request $request, BillingDetail $billingDetail)
    {
        $this->authorizeFacility($billingDetail);

        if ($billingDetail->status !== 'draft') {
            session()->flash('message', '下書き状態の明細のみ編集できます。');
            session()->flash('status', 'error');
            return back();
        }

        $validated = $request->validate([
            'lines'                  => 'required|array',
            'lines.*.id'             => 'required|exists:billing_detail_lines,id',
            'lines.*.count'          => 'required|integer|min:0',
            'lines.*.units_per_count' => 'required|integer|min:0',
            'lines.*.total_units'    => 'required|integer|min:0',
        ]);

        foreach ($validated['lines'] as $lineData) {
            $billingDetail->billingDetailLines()
                ->where('id', $lineData['id'])
                ->update([
                    'count'          => $lineData['count'],
                    'units_per_count' => $lineData['units_per_count'],
                    'total_units'    => $lineData['total_units'],
                ]);
        }

        // 合計再計算
        $totalUnits = $billingDetail->billingDetailLines()->sum('total_units');
        $totalAmount = (int) floor($totalUnits * $billingDetail->unit_price_yen);
        $copaymentRate = $billingDetail->recipientCertificate?->copayment_rate ?? 10;
        $copaymentAmount = (int) floor($totalAmount * $copaymentRate / 100);
        $cap = $billingDetail->copayment_cap;
        $capApplied = $cap > 0 ? min($copaymentAmount, $cap) : $copaymentAmount;

        $billingDetail->update([
            'total_units'           => $totalUnits,
            'total_amount'          => $totalAmount,
            'copayment_amount'      => $copaymentAmount,
            'copayment_cap_applied' => $capApplied,
            'insurance_amount'      => $totalAmount - $capApplied,
        ]);

        session()->flash('message', '明細を更新しました。');
        session()->flash('status', 'success');

        return to_route('billing.details.show', $billingDetail);
    }

    private function authorizeFacility(BillingDetail $detail): void
    {
        abort_if($detail->billingPeriod->facility_id !== $this->facilityId(), 403);
    }
}
