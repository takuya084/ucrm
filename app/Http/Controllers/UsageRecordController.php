<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreUsageRecordRequest;
use App\Models\Child;
use App\Models\ChildSchedule;
use App\Models\Facility;
use App\Models\UsageRecord;
use App\Services\YoyakuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UsageRecordController extends Controller
{
    public function __construct(private YoyakuApiService $yoyakuApi) {}

    public function index(Request $request)
    {
        $date       = $request->input('date', date('Y-m-d'));
        $dayOfWeek  = $this->getDayOfWeek($date);
        $facilityId = auth()->user()->staff?->facility_id;

        // その日すでに出席記録が保存されているか
        $hasRecords = UsageRecord::where('date', $date)
            ->when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->exists();

        $currentChildIds = [];
        $dataSource      = 'schedule';

        if ($hasRecords) {
            // ── 保存済みモード: usage_records をソースにする ──────────────
            $savedRecords = UsageRecord::where('date', $date)
                ->when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
                ->with(['child.school', 'supportRecord'])
                ->get();

            $rows = $savedRecords->map(fn($rec) => $this->rowFromRecord($rec));
            $currentChildIds = $savedRecords->pluck('child_id')->toArray();
            $dataSource = 'records';

        } else {
            // ── テンプレートモード: yoyaku API or child_schedules ─────────
            $yoyakuBusinessId = $this->getYoyakuBusinessId($facilityId);
            $yoyakuSchedules  = null;

            if ($yoyakuBusinessId) {
                $yoyakuSchedules = $this->yoyakuApi->getDailySchedule($date, (int) $yoyakuBusinessId);
            }

            if ($yoyakuSchedules !== null) {
                $dataSource    = 'yoyaku';
                $yoyakuMap     = collect($yoyakuSchedules)->keyBy('user_id');
                $yoyakuUserIds = $yoyakuMap->keys()->toArray();

                $templateChildren = Child::with('school')
                    ->whereIn('yoyaku_user_id', $yoyakuUserIds)
                    ->when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
                    ->where('contract_status', 'active')
                    ->orderBy('name_kana')
                    ->get();

                $rows = $templateChildren->map(function ($child) use ($yoyakuMap) {
                    $yoyaku = $child->yoyaku_user_id ? $yoyakuMap->get($child->yoyaku_user_id) : null;
                    return $this->rowFromChild($child, $yoyaku);
                });

            } else {
                $dataSource = 'schedule';

                $scheduled = ChildSchedule::with('child.school')
                    ->where('day_of_week', $dayOfWeek)
                    ->where('start_date', '<=', $date)
                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
                    ->whereHas('child', fn($q) => $q
                        ->when($facilityId, fn($q2) => $q2->where('facility_id', $facilityId))
                        ->where('contract_status', 'active'))
                    ->get();

                $rows = $scheduled->map(fn($s) => $this->rowFromChild($s->child));
            }

            $currentChildIds = $rows->pluck('child_id')->toArray();
        }

        // 追加候補の児童（現在リストにいない・契約中）
        $availableChildren = Child::where('contract_status', 'active')
            ->when($facilityId, fn($q) => $q->where('facility_id', $facilityId))
            ->whereNotIn('id', $currentChildIds)
            ->with('school:id,name')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana', 'grade', 'pickup_required', 'school_id']);

        return Inertia::render('UsageRecords/Index', [
            'date'              => $date,
            'dayName'           => $this->dayName($dayOfWeek),
            'rows'              => $rows->values(),
            'dataSource'        => $dataSource,
            'hasRecords'        => $hasRecords,
            'availableChildren' => $availableChildren,
        ]);
    }

    public function bulkStore(BulkStoreUsageRecordRequest $request)
    {
        $date       = $request->date;
        $facilityId = $this->facilityId();
        $staffId    = auth()->user()->staff?->id;

        DB::transaction(function () use ($request, $date, $facilityId, $staffId) {
            foreach ($request->records as $rec) {
                UsageRecord::updateOrCreate(
                    ['child_id' => $rec['child_id'], 'date' => $date],
                    [
                        'facility_id'    => $facilityId,
                        'staff_id'       => $staffId,
                        'status'         => $rec['status'],
                        'absent_reason'  => $rec['absent_reason'] ?? null,
                        'pickup_done'    => $rec['pickup_done'] ?? false,
                        'dropoff_done'   => $rec['dropoff_done'] ?? false,
                        'billing_target' => $rec['billing_target'] ?? true,
                        'memo'           => $rec['memo'] ?? null,
                    ]
                );
            }
        });

        return back()->with(['message' => '出席記録を保存しました。', 'status' => 'success']);
    }

    // ── private helpers ──────────────────────────────────────────────────

    /** usage_record モデルから行データを生成 */
    private function rowFromRecord(UsageRecord $rec): array
    {
        return [
            'child_id'                => $rec->child_id,
            'child_name'              => $rec->child?->name,
            'child_name_kana'         => $rec->child?->name_kana,
            'school_name'             => $rec->child?->school?->name,
            'pickup_required'         => $rec->child?->pickup_required ?? false,
            'yoyaku_pickup_time'      => null,
            'yoyaku_dropoff_time'     => null,
            'yoyaku_pickup_location'  => null,
            'yoyaku_dropoff_location' => null,
            'usage_record_id'         => $rec->id,
            'status'                  => $rec->status,
            'absent_reason'           => $rec->absent_reason ?? '',
            'pickup_done'             => $rec->pickup_done,
            'dropoff_done'            => $rec->dropoff_done,
            'billing_target'          => $rec->billing_target,
            'memo'                    => $rec->memo ?? '',
            'has_support_record'      => $rec->supportRecord !== null,
            'support_record_id'       => $rec->supportRecord?->id,
        ];
    }

    /** Child モデルからテンプレート行データを生成（未保存） */
    private function rowFromChild(Child $child, ?array $yoyaku = null): array
    {
        return [
            'child_id'                => $child->id,
            'child_name'              => $child->name,
            'child_name_kana'         => $child->name_kana,
            'school_name'             => $child->school?->name,
            'pickup_required'         => $child->pickup_required,
            'yoyaku_pickup_time'      => $yoyaku['pickup_time'] ?? null,
            'yoyaku_dropoff_time'     => $yoyaku['dropoff_time'] ?? null,
            'yoyaku_pickup_location'  => $yoyaku['pickup_location'] ?? null,
            'yoyaku_dropoff_location' => $yoyaku['dropoff_location'] ?? null,
            'usage_record_id'         => null, // 未保存
            'status'                  => 'attended',
            'absent_reason'           => '',
            'pickup_done'             => $child->pickup_required,
            'dropoff_done'            => $child->pickup_required,
            'billing_target'          => true,
            'memo'                    => '',
            'has_support_record'      => false,
            'support_record_id'       => null,
        ];
    }

    private function getYoyakuBusinessId(?int $facilityId): ?int
    {
        $val = $facilityId
            ? Facility::where('id', $facilityId)->value('yoyaku_business_id')
            : Facility::value('yoyaku_business_id');
        return $val ? (int) $val : null;
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
