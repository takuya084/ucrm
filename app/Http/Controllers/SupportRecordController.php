<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRecordRequest;
use App\Http\Requests\UpdateSupportRecordRequest;
use App\Models\Child;
use App\Models\Program;
use App\Models\Staff;
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
            return Inertia::render('SupportRecords/Create', [
                'child'          => $usageRecord->child->only('id', 'name', 'care_note'),
                'date'           => $usageRecord->date,
                'usageRecordId'  => $usageRecord->id,
                'programs'       => $programs,
                'staffList'      => $this->staffList(),
                'defaultStaffId' => $defaultStaffId,
            ]);
        }

        $child = Child::findOrFail($request->child_id);
        return Inertia::render('SupportRecords/Create', [
            'child'          => $child->only('id', 'name', 'care_note'),
            'date'           => $request->date ?? date('Y-m-d'),
            'usageRecordId'  => null,
            'programs'       => $programs,
            'staffList'      => $this->staffList(),
            'defaultStaffId' => $defaultStaffId,
        ]);
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
        });

        return to_route('usage-records.index', ['date' => $request->date])
            ->with(['message' => '支援記録を保存しました。', 'status' => 'success']);
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
            'record' => $supportRecord,
        ]);
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
            'programs'         => $programs,
            'selectedPrograms' => $selectedPrograms,
            'selectedItems'    => $selectedItems,
            'staffList'        => $this->staffList(),
        ]);
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
        });

        return to_route('support-records.show', $supportRecord)
            ->with(['message' => '支援記録を更新しました。', 'status' => 'success']);
    }
}
