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
        $date       = \Carbon\Carbon::parse($request->input('date', date('Y-m-d')))->toDateString();
        $dayOfWeek  = $this->getDayOfWeek($date);
        $facilityId = $this->facilityId();

        // 1. その日すでに出席記録が保存されているか確認
        $savedRecords = UsageRecord::where('date', $date)
            ->where('facility_id', $facilityId)
            ->with(['child.school', 'supportRecord'])
            ->get();

        $hasRecords = $savedRecords->isNotEmpty();

        if ($hasRecords) {
            // ── 保存済みモード: DBにあるものだけを表示する（勝手な補充はしない） ──
            $rows = $savedRecords->map(fn($rec) => $this->rowFromRecord($rec));
            $dataSource = 'records';
        } else {
            // ── テンプレートモード: 初めて開くときは予定を表示 ──
            $yoyakuBusinessId = $this->getYoyakuBusinessId($facilityId);
            $yoyakuSchedules  = $yoyakuBusinessId ? $this->yoyakuApi->getDailySchedule($date, (int) $yoyakuBusinessId) : null;

            if ($yoyakuSchedules !== null) {
                $dataSource    = 'yoyaku';
                $yoyakuMap     = collect($yoyakuSchedules)->keyBy('user_id');
                $templateChildren = Child::with('school')
                    ->whereIn('yoyaku_user_id', $yoyakuMap->keys()->toArray())
                    ->where('facility_id', $facilityId)
                    ->where('contract_status', 'active')
                    ->orderBy('name_kana')
                    ->get();

                $rows = $templateChildren->map(fn($c) => $this->rowFromChild($c, $yoyakuMap->get($c->yoyaku_user_id)));
            } else {
                $dataSource = 'schedule';
                $scheduled = ChildSchedule::with('child.school')
                    ->where('day_of_week', $dayOfWeek)
                    ->where('start_date', '<=', $date)
                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
                    ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId)->where('contract_status', 'active'))
                    ->get();
                $rows = $scheduled->map(fn($s) => $this->rowFromChild($s->child));
            }
        }

        // 表示順をカナ順で安定させる
        $rows = $rows->sortBy('child_name_kana')->values();
        $currentChildIds = $rows->pluck('child_id')->toArray();

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

        $savedIds = [];

        DB::transaction(function () use ($request, $dateStr, $facilityId, $staffId, &$savedIds) {
            $sentChildIds = collect($request->records)->pluck('child_id')->toArray();

            // 1. リストから削除（バツ印）された児童の既存レコードをDBから物理削除
            UsageRecord::where('date', $dateStr)
                ->where('facility_id', $facilityId)
                ->whereNotIn('child_id', $sentChildIds)
                ->delete();

            // 2. 残りの児童を更新または作成
            foreach ($request->records as $rec) {
                $ur = UsageRecord::updateOrCreate(
                    ['child_id' => $rec['child_id'], 'date' => $dateStr],
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

    // ── private helpers ──────────────────────────────────────────────────

    /** usage_record モデルから行データを生成 */
    private function rowFromRecord(UsageRecord $rec): array
    {
        return [
            'child_id'                => $rec->child_id,
            'child_name'              => $rec->child?->name,
            'child_name_kana'         => $rec->child?->name_kana,
            'school_name'             => $rec->child?->school?->name,
            'allergy_note'            => $rec->child?->allergy_note,
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
            'allergy_note'            => $child->allergy_note,
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

    private function getYoyakuBusinessId(int $facilityId): ?int
    {
        $val = Facility::where('id', $facilityId)->value('yoyaku_business_id');
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
