<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\ChildProgramProgress;
use App\Models\Program;
use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgramProgressController extends Controller
{
    public function index(Request $request)
    {
        $facilityId     = $this->facilityId();
        $categoryFilter = $request->input('category', 'physical');
        $programId      = $request->input('program_id');

        // 項目が登録されているプログラムのみ対象
        $programs = Program::where('facility_id', $facilityId)
            ->where('category', $categoryFilter)
            ->where('is_active', true)
            ->whereHas('items')
            ->with(['items' => fn($q) => $q->orderBy('difficulty_order')->orderBy('id')])
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        // カテゴリに合わせて初期プログラムを選択
        if (!$programId && $programs->isNotEmpty()) {
            $programId = $programs->first()->id;
        }
        $selectedProgram = $programs->firstWhere('id', (int)$programId);

        // 契約中の児童
        $children = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana', 'grade']);

        // 進度データ: { child_id: { item_id: status } }
        $progress = [];
        if ($selectedProgram) {
            $itemIds = $selectedProgram->items->pluck('id');
            $records = ChildProgramProgress::whereIn('program_item_id', $itemIds)
                ->whereIn('child_id', $children->pluck('id'))
                ->get();

            foreach ($children as $child) {
                $childRecords = $records->where('child_id', $child->id)->keyBy('program_item_id');
                $progress[$child->id] = [];
                foreach ($itemIds as $itemId) {
                    $progress[$child->id][$itemId] = $childRecords->get($itemId)?->status ?? null;
                }
            }
        }

        // 項目ありのカテゴリ一覧（タブ用）
        $availableCategories = Program::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->whereHas('items')
            ->distinct()
            ->pluck('category');

        // 今日出席の児童ID（status = attended）
        $todayChildIds = UsageRecord::where('date', date('Y-m-d'))
            ->where('status', 'attended')
            ->where('facility_id', $facilityId)
            ->pluck('child_id')
            ->toArray();

        return Inertia::render('ProgramProgress/Index', [
            'programs'            => $programs,
            'selectedProgram'     => $selectedProgram,
            'children'            => $children,
            'progress'            => $progress,
            'categoryFilter'      => $categoryFilter,
            'availableCategories' => $availableCategories,
            'todayChildIds'       => $todayChildIds,
        ]);
    }

    /** セル1つの進度を更新（なし→練習中→達成→なし） */
    public function update(Request $request)
    {
        $request->validate([
            'child_id'        => 'required|exists:children,id',
            'program_item_id' => 'required|exists:program_items,id',
            'status'          => 'required|in:practicing,mastered,none',
        ]);

        if ($request->status === 'none') {
            ChildProgramProgress::where('child_id', $request->child_id)
                ->where('program_item_id', $request->program_item_id)
                ->delete();
        } else {
            ChildProgramProgress::updateOrCreate(
                [
                    'child_id'        => $request->child_id,
                    'program_item_id' => $request->program_item_id,
                ],
                [
                    'status'      => $request->status,
                    'achieved_at' => $request->status === 'mastered' ? now()->toDateString() : null,
                ]
            );
        }

        return back()->with(['status' => 'success']);
    }
}
