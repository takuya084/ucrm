<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingDetail;
use App\Models\ErrorClaim;
use App\Services\Billing\ErrorClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ErrorClaimController extends Controller
{
    public function __construct(
        private ErrorClaimService $errorClaimService,
    ) {}

    /**
     * 過誤申立一覧
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();

        $claims = ErrorClaim::where('facility_id', $facilityId)
            ->with(['child:id,name,name_kana', 'billingDetail'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Billing/ErrorClaims/Index', [
            'claims' => $claims,
        ]);
    }

    /**
     * 過誤申立作成フォーム
     */
    public function create(BillingDetail $billingDetail)
    {
        abort_if($billingDetail->billingPeriod->facility_id !== $this->facilityId(), 403);

        $billingDetail->load(['child', 'billingPeriod', 'billingDetailLines']);

        return Inertia::render('Billing/ErrorClaims/Create', [
            'billingDetail' => $billingDetail,
        ]);
    }

    /**
     * 過誤申立を保存
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'billing_detail_id' => 'required|exists:billing_details,id',
            'claim_type'        => 'required|in:full_cancel,partial_correction',
            'reason'            => 'required|string|max:2000',
        ]);

        $detail = BillingDetail::findOrFail($validated['billing_detail_id']);
        abort_if($detail->billingPeriod->facility_id !== $this->facilityId(), 403);

        $this->errorClaimService->createErrorClaim(
            $detail,
            $validated['claim_type'],
            $validated['reason']
        );

        session()->flash('message', '過誤申立を作成しました。');
        session()->flash('status', 'success');

        return to_route('billing.error-claims.index');
    }

    /**
     * 過誤申立CSV出力
     */
    public function export(Request $request)
    {
        $facilityId = $this->facilityId();

        $claims = ErrorClaim::where('facility_id', $facilityId)
            ->where('status', 'draft')
            ->get();

        if ($claims->isEmpty()) {
            session()->flash('message', '出力対象の過誤申立がありません。');
            session()->flash('status', 'error');
            return back();
        }

        $path = $this->errorClaimService->generateErrorClaimCsv($facilityId, $claims);

        // ステータスを更新
        ErrorClaim::whereIn('id', $claims->pluck('id'))
            ->update(['status' => 'submitted', 'submitted_at' => now()]);

        return Storage::disk('local')->download($path);
    }
}
