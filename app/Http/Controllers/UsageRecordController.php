<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreUsageRecordRequest;
use App\Models\Child;
use App\Models\UsageRecord;
use App\Services\DailyAttendanceRoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UsageRecordController extends Controller
{
    public function index(Request $request, DailyAttendanceRoster $roster)
    {
        $date       = \Carbon\Carbon::parse($request->input('date', date('Y-m-d')))->toDateString();
        $dayOfWeek  = $this->getDayOfWeek($date);
        $facilityId = $this->facilityId();

        ['rows' => $rows, 'dataSource' => $dataSource, 'hasRecords' => $hasRecords] =
            $roster->forDate($facilityId, $date);

        // 追加候補の児童（契約中の全児童。フロント側で表示中の児童を除外）
        $availableChildren = Child::where('contract_status', 'active')
            ->where('facility_id', $facilityId)
            ->with('school:id,name')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana', 'grade', 'pickup_required', 'school_id']);

        return Inertia::render('UsageRecords/Index', [
            'date'              => $date,
            'dayName'           => $this->dayName($dayOfWeek),
            'rows'              => $rows,
            'dataSource'        => $dataSource,
            'hasRecords'        => $hasRecords,
            'availableChildren' => $availableChildren,
            'serverTs'          => microtime(true), // SPA遷移でのprop変更検知用
        ]);
    }

    public function bulkStore(BulkStoreUsageRecordRequest $request)
    {
        $date       = $request->date;
        $facilityId = $this->facilityId();
        $staffId    = auth()->user()->staff?->id;
        $dateStr    = \Carbon\Carbon::parse($date)->toDateString();

        // 請求確定済み・提出済みの月の出欠記録は変更不可（請求の根拠データ保護）
        $yearMonth = \Carbon\Carbon::parse($dateStr)->format('Y-m');
        $lockedPeriod = \App\Models\BillingPeriod::where('facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->first();

        if ($lockedPeriod?->isLocked()) {
            $message = "{$yearMonth}の請求は{$lockedPeriod->status_label}のため、出欠記録を変更できません。過誤申立・返戻処理を行ってください。";
            if (! $request->header('X-Inertia')) {
                return response()->json(['error' => $message], 423);
            }
            session()->flash('message', $message);
            session()->flash('status', 'error');
            return back();
        }

        $savedIds = [];

        DB::transaction(function () use ($request, $dateStr, $facilityId, $staffId, &$savedIds) {
            $sentChildIds = collect($request->records)->pluck('child_id')->toArray();

            // 1. リストから削除（バツ印）された児童の既存レコードをソフトデリート
            //    （出欠記録は保存義務対象のため物理削除しない）
            UsageRecord::whereDate('date', $dateStr)
                ->where('facility_id', $facilityId)
                ->whereNotIn('child_id', $sentChildIds)
                ->get()
                ->each(fn ($record) => $record->delete());

            // 2. 残りの児童を更新または作成（過去にソフトデリートした記録があれば復活させる）
            foreach ($request->records as $rec) {
                $ur = UsageRecord::withTrashed()->whereDate('date', $dateStr)->updateOrCreate(
                    ['child_id' => $rec['child_id'], 'facility_id' => $facilityId],
                    [
                        'date'           => $dateStr,
                        'facility_id'    => $facilityId,
                        'staff_id'       => $staffId,
                        'status'         => $rec['status'],
                        'absent_reason'  => $rec['absent_reason'] ?? null,
                        'pickup_done'    => $rec['pickup_done'] ?? false,
                        'dropoff_done'   => $rec['dropoff_done'] ?? false,
                        'billing_target'  => $rec['billing_target'] ?? true,
                        'memo'            => $rec['memo'] ?? null,
                        'check_in_time'   => $rec['check_in_time'] ?? null,
                        'check_out_time'  => $rec['check_out_time'] ?? null,
                        'is_school_day'   => $rec['is_school_day'] ?? true,
                        'service_type'    => $rec['service_type'] ?? null,
                    ]
                );
                if ($ur->trashed()) {
                    $ur->restore();
                }
                $savedIds[$rec['child_id']] = $ur->id;
            }
        });

        // axios（自動保存）からの場合は JSON で usage_record_id を返す
        if (! $request->header('X-Inertia')) {
            return response()->json(['ids' => $savedIds]);
        }

        session()->flash('message', '出席記録を保存しました。');
        session()->flash('status', 'success');
        return to_route('usage-records.index', ['date' => $dateStr]);
    }

    private function getDayOfWeek(string $date): string
    {
        $map = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue',
                'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
        return $map[date('D', strtotime($date))] ?? 'mon';
    }

    private function dayName(string $day): string
    {
        $names = ['mon' => '月', 'tue' => '火', 'wed' => '水',
                  'thu' => '木', 'fri' => '金', 'sat' => '土', 'sun' => '日'];
        return $names[$day] ?? '';
    }
}
