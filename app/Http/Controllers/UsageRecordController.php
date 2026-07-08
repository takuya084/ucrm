<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkStoreUsageRecordRequest;
use App\Models\Child;
use App\Models\ChildSchedule;
use App\Models\ContactNote;
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

        // 連絡帳の状態（児童ID => 連絡帳）。バッジ表示用
        $noteMap = ContactNote::where('facility_id', $facilityId)
            ->whereDate('date', $date)
            ->get(['id', 'child_id', 'status', 'read_at', 'guardian_submitted_at'])
            ->keyBy('child_id');

        if ($hasRecords) {
            // ── 保存済みモード: DBにあるものだけを表示する（勝手な補充はしない） ──
            $rows = $savedRecords->map(fn($rec) => $this->rowFromRecord($rec, $noteMap->get($rec->child_id)));
            $dataSource = 'records';
        } else {
            // ── テンプレートモード: 初めて開くときは予定を表示 ──
            $yoyakuBusinessId = $this->getYoyakuBusinessId($facilityId);
            $yoyakuSchedules  = $yoyakuBusinessId ? $this->yoyakuApi->getDailySchedule($date, (int) $yoyakuBusinessId, $facilityId) : null;

            if ($yoyakuSchedules !== null) {
                $dataSource    = 'yoyaku';
                $yoyakuMap     = collect($yoyakuSchedules)->keyBy('user_id');
                $templateChildren = Child::with('school')
                    ->whereIn('yoyaku_user_id', $yoyakuMap->keys()->toArray())
                    ->where('facility_id', $facilityId)
                    ->where('contract_status', 'active')
                    ->orderBy('name_kana')
                    ->get();

                $rows = $templateChildren->map(fn($c) => $this->rowFromChild($c, $yoyakuMap->get($c->yoyaku_user_id), $noteMap->get($c->id)));
            } else {
                $dataSource = 'schedule';
                $scheduled = ChildSchedule::with('child.school')
                    ->where('day_of_week', $dayOfWeek)
                    ->where('start_date', '<=', $date)
                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
                    ->whereHas('child', fn($q) => $q->where('facility_id', $facilityId)->where('contract_status', 'active'))
                    ->get();
                $rows = $scheduled->map(fn($s) => $this->rowFromChild($s->child, null, $noteMap->get($s->child_id)));
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
            UsageRecord::where('date', $dateStr)
                ->where('facility_id', $facilityId)
                ->whereNotIn('child_id', $sentChildIds)
                ->get()
                ->each(fn ($record) => $record->delete());

            // 2. 残りの児童を更新または作成（過去にソフトデリートした記録があれば復活させる）
            foreach ($request->records as $rec) {
                $ur = UsageRecord::withTrashed()->updateOrCreate(
                    ['child_id' => $rec['child_id'], 'date' => $dateStr],
                    [
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

    // ── private helpers ──────────────────────────────────────────────────

    /** usage_record モデルから行データを生成 */
    private function rowFromRecord(UsageRecord $rec, ?ContactNote $note = null): array
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
            'check_in_time'           => $rec->check_in_time ? substr($rec->check_in_time, 0, 5) : '',
            'check_out_time'          => $rec->check_out_time ? substr($rec->check_out_time, 0, 5) : '',
            'is_school_day'           => $rec->is_school_day ?? true,
            'service_type'            => $rec->service_type ?? '',
            'has_support_record'      => $rec->supportRecord !== null,
            'support_record_id'       => $rec->supportRecord?->id,
            'contact_note_status'     => $note?->status,
            'contact_note_read'       => (bool) $note?->read_at,
            'contact_note_home_entry' => (bool) $note?->guardian_submitted_at,
        ];
    }

    /** Child モデルからテンプレート行データを生成（未保存） */
    private function rowFromChild(Child $child, ?array $yoyaku = null, ?ContactNote $note = null): array
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
            'check_in_time'           => '',
            'check_out_time'          => '',
            'is_school_day'           => true,
            'service_type'            => '',
            'has_support_record'      => false,
            'support_record_id'       => null,
            'contact_note_status'     => $note?->status,
            'contact_note_read'       => (bool) $note?->read_at,
            'contact_note_home_entry' => (bool) $note?->guardian_submitted_at,
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
