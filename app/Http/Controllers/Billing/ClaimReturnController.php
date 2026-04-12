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

        $returns = ClaimReturn::where('facility_id', $facilityId)
            ->with(['child:id,name,name_kana', 'billingDetail'])
            ->orderByDesc('received_at')
            ->paginate(20);

        $children = Child::where('facility_id', $facilityId)
            ->where('contract_status', 'active')
            ->orderBy('name_kana')
            ->get(['id', 'name', 'name_kana']);

        return Inertia::render('Billing/Returns/Index', [
            'returns'  => $returns,
            'children' => $children,
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

        session()->flash('message', '返戻を登録しました。');
        session()->flash('status', 'success');

        return back();
    }

    /**
     * 再請求処理
     */
    public function resubmit(ClaimReturn $claimReturn)
    {
        abort_if($claimReturn->facility_id !== $this->facilityId(), 403);

        if ($claimReturn->status !== 'returned') {
            session()->flash('message', '返戻ステータスが不正です。');
            session()->flash('status', 'error');
            return back();
        }

        $claimReturn->update(['status' => 'resubmitting']);

        session()->flash('message', '再請求準備を開始しました。');
        session()->flash('status', 'success');

        return back();
    }
}
