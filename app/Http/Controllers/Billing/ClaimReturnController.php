<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ClaimReturn;
use App\Services\Billing\ErrorClaimService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClaimReturnController extends Controller
{
    public function __construct(
        private ErrorClaimService $errorClaimService,
    ) {}

    /**
     * 返戻一覧
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();

        $query = ClaimReturn::where('facility_id', $facilityId)
            ->with(['child:id,name,name_kana', 'billingDetail:id,total_amount,billing_period_id']);

        if ($month = $request->input('month')) {
            $query->where('year_month', $month);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($childId = $request->input('child_id')) {
            $query->where('child_id', $childId);
        }
        if ($code = $request->input('return_code')) {
            $query->where('return_code', $code);
        }

        $returns = $query->orderByDesc('received_at')->paginate(20)->withQueryString();

        // サマリー
        $summary = ClaimReturn::where('facility_id', $facilityId)
            ->selectRaw('status, COUNT(*) AS cnt, SUM(original_amount) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $stats = [
            'returned'     => (int) ($summary['returned']->cnt     ?? 0),
            'resubmitting' => (int) ($summary['resubmitting']->cnt ?? 0),
            'resubmitted'  => (int) ($summary['resubmitted']->cnt  ?? 0),
            'resolved'     => (int) ($summary['resolved']->cnt     ?? 0),
            'total_amount' => (int) $summary->sum('total'),
        ];

        $children = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana']);

        return Inertia::render('Billing/Returns/Index', [
            'returns'      => $returns,
            'children'     => $children,
            'stats'        => $stats,
            'filters'      => $request->only(['month', 'status', 'child_id', 'return_code']),
            'statusLabels' => ClaimReturn::STATUS_LABELS,
            'codePresets'  => ClaimReturn::RETURN_CODE_PRESETS,
        ]);
    }

    /**
     * 返戻を登録
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'year_month'      => 'required|date_format:Y-m',
            'child_id'        => 'required|exists:children,id',
            'return_code'     => 'nullable|string|max:10',
            'return_reason'   => 'nullable|string|max:2000',
            'original_amount' => 'required|integer|min:0',
            'received_at'     => 'required|date',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $facilityId = $this->facilityId();

        $this->errorClaimService->registerReturn(
            $facilityId,
            null,
            $validated['year_month'],
            $validated['child_id'],
            $validated['return_code'] ?? '',
            $validated['return_reason'] ?? '',
            $validated['original_amount'],
            $validated['received_at'],
        );

        if (!empty($validated['remarks'])) {
            ClaimReturn::where('facility_id', $facilityId)
                ->where('child_id', $validated['child_id'])
                ->where('year_month', $validated['year_month'])
                ->latest('id')->first()?->update(['remarks' => $validated['remarks']]);
        }

        return back()->with(['message' => '返戻を登録しました。', 'status' => 'success']);
    }

    /**
     * 返戻レコードの編集
     */
    public function update(Request $request, ClaimReturn $claimReturn)
    {
        abort_if($claimReturn->facility_id !== $this->facilityId(), 403);

        $data = $request->validate([
            'return_code'   => ['nullable', 'string', 'max:10'],
            'return_reason' => ['nullable', 'string', 'max:2000'],
            'remarks'       => ['nullable', 'string', 'max:1000'],
            'received_at'   => ['nullable', 'date'],
        ]);

        $claimReturn->update($data);

        return back()->with(['message' => '返戻情報を更新しました。', 'status' => 'success']);
    }

    /**
     * ステータス遷移（returned → resubmitting → resubmitted → resolved）
     */
    public function transition(Request $request, ClaimReturn $claimReturn)
    {
        abort_if($claimReturn->facility_id !== $this->facilityId(), 403);

        $data = $request->validate([
            'action' => ['required', 'in:start_resubmit,mark_resubmitted,mark_resolved,revert'],
        ]);

        $today = now()->toDateString();
        $updates = match ($data['action']) {
            'start_resubmit'   => ['status' => 'resubmitting'],
            'mark_resubmitted' => ['status' => 'resubmitted', 'resubmitted_at' => $today],
            'mark_resolved'    => ['status' => 'resolved',    'resolved_at' => $today],
            'revert'           => $this->revertStatus($claimReturn),
        };

        if (!empty($updates)) {
            $claimReturn->update($updates);
        }

        return back()->with(['message' => '状態を更新しました。', 'status' => 'success']);
    }

    private function revertStatus(ClaimReturn $r): array
    {
        return match ($r->status) {
            'resolved'     => ['status' => 'resubmitted',  'resolved_at' => null],
            'resubmitted'  => ['status' => 'resubmitting', 'resubmitted_at' => null],
            'resubmitting' => ['status' => 'returned'],
            default        => [],
        };
    }

    /**
     * 旧エンドポイント（互換用）
     */
    public function resubmit(ClaimReturn $claimReturn)
    {
        abort_if($claimReturn->facility_id !== $this->facilityId(), 403);

        if ($claimReturn->status === 'returned') {
            $claimReturn->update(['status' => 'resubmitting']);
        }

        return back()->with(['message' => '再請求準備を開始しました。', 'status' => 'success']);
    }
}
