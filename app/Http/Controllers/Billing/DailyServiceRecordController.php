<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\DailyServiceRecord;
use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyServiceRecordController extends Controller
{
    /**
     * 実績記録票の表示（月×児童一覧）
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $yearMonth  = $request->input('month', now()->format('Y-m'));
        $childId    = $request->input('child_id');

        $query = UsageRecord::where('facility_id', $facilityId)
            ->where('date', 'like', $yearMonth . '%')
            ->where('billing_target', true)
            ->with(['child:id,name,name_kana', 'dailyServiceRecords.serviceCodeMaster'])
            ->orderBy('date');

        if ($childId) {
            $query->where('child_id', $childId);
        }

        $records = $query->get();

        // 児童別にグルーピング
        $grouped = $records->groupBy('child_id')->map(function ($childRecords) {
            $child = $childRecords->first()->child;
            return [
                'child'   => $child,
                'records' => $childRecords->map(function ($record) {
                    return [
                        'id'                   => $record->id,
                        'date'                 => $record->date->format('Y-m-d'),
                        'status'               => $record->status,
                        'check_in_time'        => $record->check_in_time ? substr($record->check_in_time, 0, 5) : null,
                        'check_out_time'       => $record->check_out_time ? substr($record->check_out_time, 0, 5) : null,
                        'is_school_day'        => (bool) $record->is_school_day,
                        'service_type'         => $record->service_type,
                        'pickup_done'          => $record->pickup_done,
                        'dropoff_done'         => $record->dropoff_done,
                        'daily_service_records' => $record->dailyServiceRecords->map(fn($dsr) => [
                            'id'            => $dsr->id,
                            'service_code'  => $dsr->service_code,
                            'service_name'  => $dsr->serviceCodeMaster?->service_name ?? '',
                            'units'         => $dsr->units,
                        ]),
                    ];
                })->values(),
            ];
        })->values();

        $children = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana']);

        // 請求確定済みの月は編集不可（UI 側で入力を無効化する）
        $locked = \App\Models\BillingPeriod::where('facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->whereIn('status', ['confirmed', 'submitted', 'completed'])
            ->exists();

        return Inertia::render('Billing/DailyRecords/Index', [
            'grouped'   => $grouped,
            'yearMonth' => $yearMonth,
            'childId'   => $childId,
            'children'  => $children,
            'locked'    => $locked,
        ]);
    }

    /**
     * 日別サービス実績の一括更新
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'records'                          => 'required|array',
            'records.*.usage_record_id'        => 'required|exists:usage_records,id',
            'records.*.check_in_time'          => 'nullable|date_format:H:i',
            'records.*.check_out_time'         => 'nullable|date_format:H:i',
            'records.*.is_school_day'          => 'required|boolean',
            'records.*.service_type'           => 'nullable|in:houday,jidou',
        ]);

        $facilityId = $this->facilityId();

        // 請求確定済み・提出済みの月の実績は変更不可（請求の根拠データ保護）
        $lockedMonths = \App\Models\BillingPeriod::where('facility_id', $facilityId)
            ->whereIn('status', ['confirmed', 'submitted', 'completed'])
            ->pluck('year_month')
            ->all();

        $skipped = 0;
        foreach ($validated['records'] as $rec) {
            $record = UsageRecord::where('id', $rec['usage_record_id'])
                ->where('facility_id', $facilityId)
                ->first();

            if (!$record) {
                continue;
            }

            if (in_array($record->date->format('Y-m'), $lockedMonths, true)) {
                $skipped++;
                continue;
            }

            $record->update([
                'check_in_time'  => $rec['check_in_time'],
                'check_out_time' => $rec['check_out_time'],
                'is_school_day'  => $rec['is_school_day'],
                'service_type'   => $rec['service_type'] ?? null,
            ]);
        }

        if ($skipped > 0) {
            session()->flash('message', "実績記録を更新しました（請求確定済みの {$skipped} 件はスキップ）。");
            session()->flash('status', 'warning');
        } else {
            session()->flash('message', '実績記録を更新しました。');
            session()->flash('status', 'success');
        }

        return back();
    }
}
