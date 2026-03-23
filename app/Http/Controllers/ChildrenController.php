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
        $facilityId = $this->facilityId();

        $query = Child::with(['school'])
            ->where('facility_id', $facilityId)
            ->search($request->search)
            ->when($request->status, fn ($q, $s) => $q->where('contract_status', $s))
            ->orderBy('name_kana')
            ->select('id', 'name', 'name_kana', 'gender', 'grade', 'school_id', 'contract_status', 'pickup_required');

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
            'schools' => School::where('facility_id', $this->facilityId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 登録処理 */
    public function store(StoreChildRequest $request)
    {
        $data = $request->safe()->except('schedule_days');
        $child = Child::create(array_merge($data, ['facility_id' => $this->facilityId()]));

        // 利用曜日の一括登録
        $today = now()->toDateString();
        foreach ($request->input('schedule_days', []) as $day) {
            $child->schedules()->create([
                'day_of_week' => $day,
                'start_date'  => $request->contract_start_date ?? $today,
                'status'      => 'regular',
            ]);
        }

        return to_route('children.index')
            ->with(['message' => '児童を登録しました。', 'status' => 'success']);
    }

    /** 詳細表示 */
    public function show(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
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
        abort_if($child->facility_id !== $this->facilityId(), 403);
        return Inertia::render('Children/Edit', [
            'child'   => $child->load('school'),
            'schools' => School::where('facility_id', $this->facilityId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** 更新処理 */
    public function update(UpdateChildRequest $request, Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $child->update($request->validated());

        return to_route('children.show', $child)
            ->with(['message' => '情報を更新しました。', 'status' => 'success']);
    }

    /** 削除（ソフトデリート） */
    public function destroy(Child $child)
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
        $child->delete();

        return to_route('children.index')
            ->with(['message' => '児童を削除しました。', 'status' => 'success']);
    }
}
