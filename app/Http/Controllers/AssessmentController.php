<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\Child;
use Inertia\Inertia;

class AssessmentController extends Controller
{
    public function create(Child $child)
    {
        $this->authorizeChild($child);

        return Inertia::render('Assessments/Create', [
            'child' => $child->only(['id', 'name']),
        ]);
    }

    public function store(StoreAssessmentRequest $request, Child $child)
    {
        $this->authorizeChild($child);

        $data = $request->validated();
        $data['child_id'] = $child->id;
        $data['staff_id'] = auth()->user()->staff?->id;

        Assessment::create($data);

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'アセスメントを登録しました。', 'status' => 'success']);
    }

    public function show(Child $child, Assessment $assessment)
    {
        $this->authorizeChild($child);
        abort_if($assessment->child_id !== $child->id, 404);

        $assessment->load('staff:id,name');

        return Inertia::render('Assessments/Show', [
            'child'      => $child->only(['id', 'name']),
            'assessment' => $assessment,
        ]);
    }

    public function edit(Child $child, Assessment $assessment)
    {
        $this->authorizeChild($child);
        abort_if($assessment->child_id !== $child->id, 404);

        return Inertia::render('Assessments/Edit', [
            'child'      => $child->only(['id', 'name']),
            'assessment' => $assessment,
        ]);
    }

    public function update(StoreAssessmentRequest $request, Child $child, Assessment $assessment)
    {
        $this->authorizeChild($child);
        abort_if($assessment->child_id !== $child->id, 404);

        $assessment->update($request->validated());

        return redirect()->route('children.assessments.show', [$child->id, $assessment->id])
            ->with(['message' => 'アセスメントを更新しました。', 'status' => 'success']);
    }

    public function destroy(Child $child, Assessment $assessment)
    {
        $this->authorizeChild($child);
        abort_if($assessment->child_id !== $child->id, 404);

        // 記録の保存義務対象のため SoftDeletes（物理削除禁止）
        $assessment->delete();

        return redirect()->route('children.show', $child->id)
            ->with(['message' => 'アセスメントを削除しました。', 'status' => 'success']);
    }

    private function authorizeChild(Child $child): void
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
    }
}
