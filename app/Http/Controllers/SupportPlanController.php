<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SupportPlan;
use App\Models\Child;
use App\Http\Requests\StoreSupportPlanRequest;
use App\Http\Requests\UpdateSupportPlanRequest;

class SupportPlanController extends Controller
{
    public function create(Request $request, Child $child)
    {
        $this->authorizeChild($child);

        // 前回計画を取得（コピー元として使う）
        $previousPlan = SupportPlan::where('child_id', $child->id)
            ->orderBy('plan_date', 'desc')
            ->first();

        return Inertia::render('SupportPlans/Create', [
            'child'        => $child->only(['id', 'name']),
            'previousPlan' => $previousPlan,
        ]);
    }

    public function store(StoreSupportPlanRequest $request, Child $child)
    {
        $this->authorizeChild($child);

        $data = $request->validated();
        $data['child_id'] = $child->id;
        $data['staff_id'] = auth()->user()->staff?->id;

        SupportPlan::create($data);

        return redirect()->route('children.show', $child->id)
            ->with(['message' => '個別支援計画を登録しました。', 'status' => 'success']);
    }

    public function show(Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $supportPlan->load(['staff:id,name', 'previousPlan:id,plan_date,valid_from,valid_to']);

        return Inertia::render('SupportPlans/Show', [
            'child' => $child->only(['id', 'name']),
            'plan'  => $supportPlan,
        ]);
    }

    public function edit(Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        return Inertia::render('SupportPlans/Edit', [
            'child' => $child->only(['id', 'name']),
            'plan'  => $supportPlan,
        ]);
    }

    public function update(UpdateSupportPlanRequest $request, Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $supportPlan->update($request->validated());

        return redirect()->route('children.support-plans.show', [$child->id, $supportPlan->id])
            ->with(['message' => '個別支援計画を更新しました。', 'status' => 'success']);
    }

    public function destroy(Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $supportPlan->delete();

        return redirect()->route('children.show', $child->id)
            ->with(['message' => '個別支援計画を削除しました。', 'status' => 'success']);
    }

    private function authorizeChild(Child $child): void
    {
        $facilityId = auth()->user()->staff?->facility_id;
        if ($facilityId) {
            abort_if($child->facility_id !== $facilityId, 403);
        }
    }
}
