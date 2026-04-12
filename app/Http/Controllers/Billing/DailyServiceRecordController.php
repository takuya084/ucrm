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
                        'check_in_time'        => $record->check_in_time,
                        'check_out_time'       => $record->check_out_time,
                        'is_school_day'        => $record->is_school_day,
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

        return Inertia::render('Billing/DailyRecords/Index', [
            'grouped'   => $grouped,
            'yearMonth' => $yearMonth,
            'childId'   => $childId,
            'children'  => $children,
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

        foreach ($validated['records'] as $rec) {
            UsageRecord::where('id', $rec['usage_record_id'])
                ->where('facility_id', $this->facilityId())
                ->update([
                    'check_in_time'  => $rec['check_in_time'],
                    'check_out_time' => $rec['check_out_time'],
                    'is_school_day'  => $rec['is_school_day'],
                    'service_type'   => $rec['service_type'] ?? null,
                ]);
        }

        session()->flash('message', '実績記録を更新しました。');
        session()->flash('status', 'success');

        return back();
    }
}
