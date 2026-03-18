<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Models\Child;
use App\Models\School;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ChildrenController extends Controller
{
    /** 利用児童一覧 */
    public function index(Request $request)
    {
        $facilityId = auth()->user()->staff?->facility_id;

        $query = Child::with(['school'])
            ->search($request->search)
            ->when($request->status, fn ($q, $s) => $q->where('contract_status', $s))
            ->orderBy('name_kana')
            ->select('id', 'name', 'name_kana', 'gender', 'grade', 'school_id', 'contract_status', 'pickup_required');

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        $children = $query->paginate(20)->withQueryString();

        return Inertia::render('Children/Index', [
            'children' => $children,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    /** 登録フォーム */
    public function create()
    {
        return Inertia::render('Children/Create', [
            'schools' => School::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 登録処理 */
    public function store(StoreChildRequest $request)
    {
        Child::create(array_merge(
            $request->validated(),
            ['facility_id' => $this->facilityId()]
        ));

        return to_route('children.index')
            ->with(['message' => '児童を登録しました。', 'status' => 'success']);
    }

    /** 詳細表示 */
    public function show(Child $child)
    {
        $child->load([
            'school',
            'guardians',
            'schedules' => fn ($q) => $q->active()->orderBy('day_of_week'),
            'activeRecipientCertificate',
            'usageRecords' => fn ($q) => $q->orderByDesc('date')->limit(5),
            'monitoringRecords' => fn ($q) => $q->orderByDesc('monitoring_date')->limit(10),
            'supportPlans' => fn ($q) => $q->orderByDesc('plan_date')->limit(10),
        ]);

        return Inertia::render('Children/Show', [
            'child' => $child,
        ]);
    }

    /** 編集フォーム */
    public function edit(Child $child)
    {
        return Inertia::render('Children/Edit', [
            'child'   => $child->load('school'),
            'schools' => School::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 更新処理 */
    public function update(UpdateChildRequest $request, Child $child)
    {
        $child->update($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '情報を更新しました。', 'status' => 'success']);
    }

    /** 削除（ソフトデリート） */
    public function destroy(Child $child)
    {
        $child->delete();

        return to_route('children.index')
            ->with(['message' => '児童を削除しました。', 'status' => 'success']);
    }
}
