<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRecordRequest;
use App\Http\Requests\UpdateSupportRecordRequest;
use App\Jobs\PushContactNote;
use App\Models\Child;
use App\Models\ContactNote;
use App\Models\Program;
use App\Models\Staff;
use App\Models\SupportPlan;
use App\Models\SupportRecord;
use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupportRecordController extends Controller
{
    private function staffList(): \Illuminate\Support\Collection
    {
        $facilityId = $this->facilityId();
        return Staff::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /**
     * 連絡帳ゾーン用の共通 props（既存の連絡帳・家庭側記入・有効な支援計画の短期目標）
     */
    private function contactNoteProps(int $childId, string $date): array
    {
        $note = ContactNote::where('child_id', $childId)
            ->whereDate('date', $date)
            ->first();

        $activePlan = SupportPlan::where('child_id', $childId)
            ->where('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->orderBy('valid_from', 'desc')
            ->first(['id', 'short_term_goal']);

        return [
            'contactNote'        => $note,
            'shortTermGoal'      => $activePlan?->short_term_goal,
            'fiveDomainLabels'   => ContactNote::FIVE_DOMAIN_LABELS,
            'goalProgressLabels' => ContactNote::GOAL_PROGRESS_LABELS,
        ];
    }

    /**
     * 支援記録の保存に合わせて連絡帳を upsert する。
     * 連絡帳ゾーンに何も書かれておらず既存もなければ作らない。
     * publish_now が立っていれば公開し p-yoyaku へ配信する。
     */
    private function upsertContactNote(Request $request, SupportRecord $record): void
    {
        $data = $request->input('contact_note');
        if (!is_array($data)) {
            return;
        }

        $fields = [
            'meal_note'        => $data['meal_note'] ?? null,
            'health_note'      => $data['health_note'] ?? null,
            'guardian_message' => $data['guardian_message'] ?? null,
            'five_domain_tags' => array_values($data['five_domain_tags'] ?? []) ?: null,
            'goal_progress'    => $data['goal_progress'] ?? null,
        ];

        // ソフトデリート済みがあると unique(child_id, date) に当たるため withTrashed で引く
        $note = ContactNote::withTrashed()
            ->where('child_id', $record->child_id)
            ->whereDate('date', $record->date->toDateString())
            ->first()
            ?? new ContactNote([
                'child_id' => $record->child_id,
                'date'     => $record->date->toDateString(),
            ]);

        if (!$note->exists && !array_filter($fields, fn ($v) => filled($v))) {
            return;
        }

        $note->fill($fields);
        $note->facility_id       = $record->child->facility_id;
        $note->support_record_id = $record->id;
        $note->staff_id          = $record->staff_id;
        if ($note->trashed()) {
            $note->deleted_at = null;
        }

        $publishNow = (bool) ($data['publish_now'] ?? false);
        if ($publishNow && !$note->isPublished() && $note->hasFacilityContent()) {
            $note->status       = ContactNote::STATUS_PUBLISHED;
            $note->published_at = now();
            $note->published_by = auth()->user()->staff?->id;
        }

        $note->save();

        // 支援記録側の保護者共有フラグは「連絡帳あり」の意味に統一
        if ($note->hasFacilityContent() && !$record->is_shared_with_guardian) {
            $record->is_shared_with_guardian = true;
            $record->saveQuietly();
        }

        if ($note->isPublished()) {
            PushContactNote::dispatch($note->id)->afterCommit();
        }
    }

    /**
     * 支援記録入力フォーム
     */
    public function create(Request $request)
    {
        $facilityId = $this->facilityId();
        $programs   = Program::where('facility_id', $facilityId)
            ->active()
            ->with('items')
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'duration_minutes']);

        $defaultStaffId = auth()->user()->staff?->id;

        if ($request->usage_record_id) {
            $usageRecord = UsageRecord::with('child')->findOrFail($request->usage_record_id);
            abort_if($usageRecord->child->facility_id !== $facilityId, 403);
            $date = $usageRecord->date->format('Y-m-d');
            return Inertia::render('SupportRecords/Create', [
                'child'          => $usageRecord->child->only('id', 'name', 'care_note'),
                'date'           => $date,
                'usageRecordId'  => $usageRecord->id,
                'programs'       => $programs,
                'staffList'      => $this->staffList(),
                'defaultStaffId' => $defaultStaffId,
            ] + $this->contactNoteProps($usageRecord->child->id, $date));
        }

        $child = Child::findOrFail($request->child_id);
        abort_if($child->facility_id !== $facilityId, 403);
        $date = \Carbon\Carbon::parse($request->date ?? today())->format('Y-m-d');
        return Inertia::render('SupportRecords/Create', [
            'child'          => $child->only('id', 'name', 'care_note'),
            'date'           => $date,
            'usageRecordId'  => null,
            'programs'       => $programs,
            'staffList'      => $this->staffList(),
            'defaultStaffId' => $defaultStaffId,
        ] + $this->contactNoteProps($child->id, $date));
    }

    /** 支援記録保存 */
    public function store(StoreSupportRecordRequest $request)
    {
        DB::transaction(function () use ($request) {
            $record = SupportRecord::create([
                'child_id'                => $request->child_id,
                'usage_record_id'         => $request->usage_record_id,
                'staff_id'                => $request->staff_id ?: auth()->user()->staff?->id,
                'date'                    => $request->date,
                'condition'               => $request->condition,
                'behavior_note'           => $request->behavior_note,
                'achievement_note'        => $request->achievement_note,
                'challenge_note'          => $request->challenge_note,
                'care_note'               => $request->care_note,
                'next_action'             => $request->next_action,
                'is_shared_with_guardian' => $request->is_shared_with_guardian ?? false,
            ]);

            if ($request->program_ids) {
                $sync = [];
                foreach ($request->program_ids as $programId) {
                    $items = $request->program_items[$programId] ?? [];
                    $sync[$programId] = [
                        'duration_minutes'  => $request->program_durations[$programId] ?? null,
                        'selected_item_ids' => empty($items) ? null : json_encode(array_map('intval', $items)),
                    ];
                }
                $record->programs()->sync($sync);
            }

            $this->upsertContactNote($request, $record);
        });

        $dateStr = \Carbon\Carbon::parse($request->date)->toDateString();
        session()->flash('message', '支援記録を保存しました。');
        session()->flash('status', 'success');
        // axios から呼ばれた場合は JSON を返す（Inertia リダイレクト追従によるキャッシュ問題を回避）
        if (! $request->header('X-Inertia')) {
            return response()->json(['date' => $dateStr]);
        }
        return to_route('usage-records.index', ['date' => $dateStr]);
    }

    /** 支援記録詳細 */
    public function show(SupportRecord $supportRecord)
    {
        abort_if($supportRecord->child->facility_id !== $this->facilityId(), 403);
        $supportRecord->load(['child', 'staff', 'programs.items']);

        // pivot の selected_item_ids を各プログラムに付与
        $supportRecord->programs->each(function ($program) {
            $ids = json_decode($program->pivot->selected_item_ids ?? '[]', true);
            $program->selected_items = $program->items->whereIn('id', $ids)->values();
        });

        return Inertia::render('SupportRecords/Show', [
            'record'     => $supportRecord,
            'recordDate' => $supportRecord->date->format('Y-m-d'),
        ] + $this->contactNoteProps($supportRecord->child_id, $supportRecord->date->format('Y-m-d')));
    }

    /** 編集フォーム */
    public function edit(SupportRecord $supportRecord)
    {
        abort_if($supportRecord->child->facility_id !== $this->facilityId(), 403);
        $facilityId = $this->facilityId();
        $programs   = Program::where('facility_id', $facilityId)
            ->active()
            ->with('items')
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'duration_minutes']);

        $supportRecord->load(['child', 'programs']);

        $selectedPrograms = $supportRecord->programs->mapWithKeys(fn ($p) => [
            $p->id => $p->pivot->duration_minutes,
        ])->toArray();

        $selectedItems = $supportRecord->programs->mapWithKeys(fn ($p) => [
            $p->id => json_decode($p->pivot->selected_item_ids ?? '[]', true),
        ])->toArray();

        return Inertia::render('SupportRecords/Edit', [
            'record'           => $supportRecord,
            'recordDate'       => $supportRecord->date->format('Y-m-d'),
            'programs'         => $programs,
            'selectedPrograms' => $selectedPrograms,
            'selectedItems'    => $selectedItems,
            'staffList'        => $this->staffList(),
        ] + $this->contactNoteProps($supportRecord->child_id, $supportRecord->date->format('Y-m-d')));
    }

    /** 更新処理 */
    public function update(UpdateSupportRecordRequest $request, SupportRecord $supportRecord)
    {
        abort_if($supportRecord->child->facility_id !== $this->facilityId(), 403);
        DB::transaction(function () use ($request, $supportRecord) {
            $supportRecord->update([
                'staff_id'                => $request->staff_id ?: $supportRecord->staff_id,
                'condition'               => $request->condition,
                'behavior_note'           => $request->behavior_note,
                'achievement_note'        => $request->achievement_note,
                'challenge_note'          => $request->challenge_note,
                'care_note'               => $request->care_note,
                'next_action'             => $request->next_action,
                'is_shared_with_guardian' => $request->is_shared_with_guardian ?? false,
            ]);

            $sync = [];
            foreach ($request->program_ids ?? [] as $programId) {
                $items = $request->program_items[$programId] ?? [];
                $sync[$programId] = [
                    'duration_minutes'  => $request->program_durations[$programId] ?? null,
                    'selected_item_ids' => empty($items) ? null : json_encode(array_map('intval', $items)),
                ];
            }
            $supportRecord->programs()->sync($sync);

            $this->upsertContactNote($request, $supportRecord);
        });

        $dateStr = \Carbon\Carbon::parse($supportRecord->date)->toDateString();
        session()->flash('message', '支援記録を更新しました。');
        session()->flash('status', 'success');
        // axios から呼ばれた場合は JSON を返す（Inertia リダイレクト追従によるキャッシュ問題を回避）
        if (! $request->header('X-Inertia')) {
            return response()->json(['date' => $dateStr]);
        }
        return to_route('usage-records.index', ['date' => $dateStr]);
    }
}
