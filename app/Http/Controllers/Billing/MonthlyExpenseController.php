<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\MonthlyExpense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonthlyExpenseController extends Controller
{
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $month      = $request->input('month', now()->format('Y-m'));

        $existing = MonthlyExpense::where('facility_id', $facilityId)
            ->where('year_month', $month)
            ->get()
            ->keyBy('category');

        $rows = [];
        foreach (MonthlyExpense::CATEGORY_LABELS as $key => $label) {
            $rec = $existing->get($key);
            $rows[] = [
                'category' => $key,
                'label'    => $label,
                'amount'   => $rec ? (int) $rec->amount : 0,
                'note'     => $rec?->note,
            ];
        }

        // 6ヶ月推移
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("{$month}-01 -{$i} months"));
            $sum = (int) MonthlyExpense::where('facility_id', $facilityId)
                ->where('year_month', $ym)
                ->sum('amount');
            $trend[] = ['year_month' => $ym, 'total' => $sum];
        }

        return Inertia::render('Billing/Expenses/Index', [
            'yearMonth' => $month,
            'rows'      => $rows,
            'trend'     => $trend,
        ]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'year_month'            => 'required|date_format:Y-m',
            'items'                 => 'required|array',
            'items.*.category'      => 'required|string',
            'items.*.amount'        => 'required|numeric|min:0',
            'items.*.note'          => 'nullable|string|max:200',
        ]);

        $facilityId = $this->facilityId();
        $allowed    = array_keys(MonthlyExpense::CATEGORY_LABELS);

        foreach ($data['items'] as $item) {
            if (!in_array($item['category'], $allowed, true)) continue;

            MonthlyExpense::updateOrCreate(
                [
                    'facility_id' => $facilityId,
                    'year_month'  => $data['year_month'],
                    'category'    => $item['category'],
                ],
                [
                    'amount' => $item['amount'],
                    'note'   => $item['note'] ?? null,
                ]
            );
        }

        return back()->with(['message' => '経費を保存しました。', 'status' => 'success']);
    }
}
