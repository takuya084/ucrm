<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BusinessContinuityPlan;
use App\Models\Child;
use App\Models\PhysicalRestraintRecord;
use App\Models\PreventionCommittee;
use App\Models\SafetyPlan;
use App\Models\SelfEvaluation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 運営記録（委員会・研修 / 身体拘束 / BCP・安全計画 / 自己評価公表）
 * 未実施・未策定・未公表は減算対象のため、実施状況を一元管理する。
 */
class OperationRecordController extends Controller
{
    public function index()
    {
        $facilityId = $this->facilityId();

        return Inertia::render('OperationRecords/Index', [
            'committees' => PreventionCommittee::where('facility_id', $facilityId)
                ->orderByDesc('held_at')->limit(30)->get(),
            'restraints' => PhysicalRestraintRecord::whereHas('child', fn ($q) => $q->where('facility_id', $facilityId))
                ->with(['child:id,name', 'staff:id,name'])
                ->orderByDesc('occurred_at')->limit(30)->get(),
            'bcps' => BusinessContinuityPlan::where('facility_id', $facilityId)->get()->keyBy('type'),
            'safetyPlans' => SafetyPlan::where('facility_id', $facilityId)
                ->orderByDesc('fiscal_year')->get(),
            'selfEvaluations' => SelfEvaluation::where('facility_id', $facilityId)
                ->orderByDesc('fiscal_year')->get(),
            'children' => Child::where('facility_id', $facilityId)
                ->where('contract_status', 'active')
                ->orderBy('name_kana')->get(['id', 'name']),
        ]);
    }

    /** 虐待防止・身体拘束適正化の委員会/研修記録 */
    public function storeCommittee(Request $request)
    {
        $validated = $request->validate([
            'type'      => 'required|in:abuse_prevention,restraint',
            'category'  => 'required|in:committee,training',
            'held_at'   => 'required|date',
            'attendees' => 'nullable|string|max:500',
            'minutes'   => 'nullable|string|max:5000',
        ]);

        PreventionCommittee::create([
            'facility_id' => $this->facilityId(),
            'type'        => $validated['type'],
            'category'    => $validated['category'],
            'held_at'     => $validated['held_at'],
            'attendees'   => $validated['attendees']
                ? array_values(array_filter(array_map('trim', preg_split('/[、,]/u', $validated['attendees']))))
                : null,
            'minutes'     => $validated['minutes'] ?? null,
        ]);

        return back()->with(['message' => '委員会・研修の記録を登録しました。', 'status' => 'success']);
    }

    /** 身体拘束の記録 */
    public function storeRestraint(Request $request)
    {
        $validated = $request->validate([
            'child_id'             => [
                'required',
                \Illuminate\Validation\Rule::exists('children', 'id')->where('facility_id', $this->facilityId()),
            ],
            'occurred_at'          => 'required|date',
            'duration_minutes'     => 'nullable|integer|min:1|max:1440',
            'method'               => 'required|string|max:2000',
            'reason'               => 'required|string|max:2000',
            'guardian_notified_at' => 'nullable|date',
        ]);

        $validated['staff_id'] = auth()->user()->staff?->id;

        PhysicalRestraintRecord::create($validated);

        return back()->with(['message' => '身体拘束の記録を登録しました。保護者への報告を忘れずに行ってください。', 'status' => 'success']);
    }

    /** 身体拘束記録の保護者報告日時を記録 */
    public function markRestraintNotified(Request $request, PhysicalRestraintRecord $restraint)
    {
        abort_if($restraint->child->facility_id !== $this->facilityId(), 403);

        $restraint->update(['guardian_notified_at' => now()]);

        return back()->with(['message' => '保護者への報告を記録しました。', 'status' => 'success']);
    }

    /** BCP（感染症・災害）の策定・見直し・訓練日 */
    public function upsertBcp(Request $request)
    {
        $validated = $request->validate([
            'type'             => 'required|in:infection,disaster',
            'established_at'   => 'nullable|date',
            'last_reviewed_at' => 'nullable|date',
            'last_training_at' => 'nullable|date',
        ]);

        $bcp = BusinessContinuityPlan::updateOrCreate(
            ['facility_id' => $this->facilityId(), 'type' => $validated['type']],
            $validated,
        );
        AuditLog::record('updated', $bcp, null, $validated);

        return back()->with(['message' => 'BCPの記録を更新しました。', 'status' => 'success']);
    }

    /** 安全計画（年度別） */
    public function upsertSafetyPlan(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year'      => 'required|digits:4',
            'established_at'   => 'nullable|date',
            'last_reviewed_at' => 'nullable|date',
        ]);

        $plan = SafetyPlan::updateOrCreate(
            ['facility_id' => $this->facilityId(), 'fiscal_year' => $validated['fiscal_year']],
            $validated,
        );
        AuditLog::record('updated', $plan, null, $validated);

        return back()->with(['message' => '安全計画の記録を更新しました。', 'status' => 'success']);
    }

    /** 自己評価結果の公表（年度別） */
    public function upsertSelfEvaluation(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year'        => 'required|digits:4',
            'guardian_survey_at' => 'nullable|date',
            'published_at'       => 'nullable|date',
            'published_url'      => 'nullable|url|max:255',
        ]);

        $ev = SelfEvaluation::updateOrCreate(
            ['facility_id' => $this->facilityId(), 'fiscal_year' => $validated['fiscal_year']],
            $validated,
        );
        AuditLog::record('updated', $ev, null, $validated);

        return back()->with(['message' => '自己評価の記録を更新しました。', 'status' => 'success']);
    }
}
