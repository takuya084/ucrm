<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SupportPlan;
use App\Models\Child;
use App\Http\Requests\StoreSupportPlanRequest;
use App\Http\Requests\UpdateSupportPlanRequest;
use App\Services\SupportPlanPdfService;
use Illuminate\Support\Facades\Storage;

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

        $supportPlan->load([
            'staff:id,name',
            'previousPlan:id,plan_date,valid_from,valid_to',
            'meetings' => fn ($q) => $q->orderByDesc('held_at'),
            'consents' => fn ($q) => $q->orderByDesc('consented_at'),
            'consents.guardian:id,name',
            'approvedByStaff:id,name',
        ]);

        return Inertia::render('SupportPlans/Show', [
            'child'     => $child->only(['id', 'name']),
            'plan'      => $supportPlan,
            'guardians' => $child->guardians()->get(['guardians.id', 'guardians.name']),
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

    /** 児発管の承認（原案 → 承認済） */
    public function approve(Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        if ($supportPlan->status !== 'draft') {
            return back()->with(['message' => 'この計画はすでに承認済みです。', 'status' => 'error']);
        }

        $supportPlan->update([
            'status'      => 'approved',
            'approved_by' => auth()->user()->staff?->id,
            'approved_at' => now(),
        ]);

        return back()->with(['message' => '計画を承認しました。保護者への説明・同意・交付に進んでください。', 'status' => 'success']);
    }

    /** 担当者会議の記録 */
    public function storeMeeting(Request $request, Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $validated = $request->validate([
            'held_at'   => 'required|date',
            'attendees' => 'nullable|string|max:500',
            'minutes'   => 'nullable|string|max:5000',
        ]);

        $supportPlan->meetings()->create([
            'held_at'   => $validated['held_at'],
            'attendees' => $validated['attendees']
                ? array_values(array_filter(array_map('trim', preg_split('/[、,]/u', $validated['attendees']))))
                : null,
            'minutes'   => $validated['minutes'] ?? null,
        ]);

        return back()->with(['message' => '担当者会議の記録を登録しました。', 'status' => 'success']);
    }

    public function destroyMeeting(Child $child, SupportPlan $supportPlan, \App\Models\SupportPlanMeeting $meeting)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);
        abort_if($meeting->support_plan_id !== $supportPlan->id, 404);

        \App\Models\AuditLog::record('deleted', $meeting, $meeting->only(['held_at', 'minutes']), null);
        $meeting->delete();

        return back()->with(['message' => '会議記録を削除しました。', 'status' => 'success']);
    }

    /** 保護者の同意・計画書交付の記録 */
    public function storeConsent(Request $request, Child $child, SupportPlan $supportPlan)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $validated = $request->validate([
            'guardian_id'  => [
                'nullable',
                \Illuminate\Validation\Rule::exists('child_guardian_relations', 'guardian_id')
                    ->where('child_id', $child->id),
            ],
            'consented_at' => 'required|date',
            'method'       => 'required|in:paper,electronic',
            'delivered_at' => 'nullable|date|after_or_equal:consented_at',
        ]);

        $supportPlan->consents()->create($validated);

        // 従来の同意フラグ・承認フローの状態も同期する
        $updates = [
            'guardian_agreement'      => true,
            'guardian_agreement_date' => $validated['consented_at'],
        ];
        if (!empty($validated['delivered_at'])) {
            $updates['status'] = 'delivered';
        }
        $supportPlan->update($updates);

        return back()->with(['message' => '同意・交付の記録を登録しました。', 'status' => 'success']);
    }

    public function pdf(Child $child, SupportPlan $supportPlan, SupportPlanPdfService $pdfService)
    {
        $this->authorizeChild($child);
        abort_if($supportPlan->child_id !== $child->id, 404);

        $path = $pdfService->generate($supportPlan);
        return Storage::disk('local')->download($path);
    }

    private function authorizeChild(Child $child): void
    {
        abort_if($child->facility_id !== $this->facilityId(), 403);
    }
}
