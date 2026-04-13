<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CopaymentCapDetail;
use App\Models\CopaymentCapManagement;
use App\Models\ExternalFacility;
use App\Services\Billing\CopaymentCapService;
use App\Services\Billing\NhifCsvExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CopaymentCapController extends Controller
{
    public function __construct(
        private CopaymentCapService $capService,
        private NhifCsvExportService $csvExportService,
    ) {}

    /**
     * 上限管理一覧
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();
        $yearMonth  = $request->input('month', now()->format('Y-m'));

        $managements = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with(['child:id,name,name_kana', 'details'])
            ->orderBy('child_id')
            ->get();

        return Inertia::render('Billing/CapManagement/Index', [
            'managements' => $managements,
            'yearMonth'   => $yearMonth,
        ]);
    }

    /**
     * 上限管理詳細
     */
    public function show(CopaymentCapManagement $copaymentCapManagement)
    {
        abort_if($copaymentCapManagement->managing_facility_id !== $this->facilityId(), 403);

        $copaymentCapManagement->load(['child', 'details.billableFacility']);

        return Inertia::render('Billing/CapManagement/Show', [
            'management' => $copaymentCapManagement,
        ]);
    }

    /**
     * 他社事業所の利用者負担額を更新し、按分再計算
     */
    public function updateExternalDetail(
        Request $request,
        CopaymentCapManagement $copaymentCapManagement,
        CopaymentCapDetail $copaymentCapDetail,
    ) {
        $detail = $copaymentCapDetail;
        abort_if($copaymentCapManagement->managing_facility_id !== $this->facilityId(), 403);
        abort_if($detail->copayment_cap_management_id !== $copaymentCapManagement->id, 404);
        abort_unless($detail->billable_facility_type === ExternalFacility::class, 422, '他社事業所の明細ではありません。');

        $data = $request->validate([
            'total_amount'     => ['required', 'integer', 'min:0'],
            'copayment_amount' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($copaymentCapManagement, $detail, $data) {
            $detail->update([
                'total_amount'     => $data['total_amount'],
                'copayment_amount' => $data['copayment_amount'],
                'adjusted_amount'  => $data['copayment_amount'],
            ]);

            $this->capService->recomputeAllocation($copaymentCapManagement->fresh('details'));
        });

        return back()->with(['message' => '他社事業所の金額を更新しました。', 'status' => 'success']);
    }

    /**
     * 上限管理を計算
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'year_month' => 'required|date_format:Y-m',
        ]);

        $facilityId = $this->facilityId();
        $results = $this->capService->calculateMonthlyCapManagement($facilityId, $request->year_month);

        session()->flash('message', count($results) . '件の上限管理を計算しました。');
        session()->flash('status', 'success');

        return to_route('billing.cap-management.index', ['month' => $request->year_month]);
    }

    /**
     * 上限管理結果票CSV出力
     */
    public function export(Request $request)
    {
        $request->validate([
            'year_month' => 'required|date_format:Y-m',
        ]);

        $facilityId = $this->facilityId();
        $path = $this->csvExportService->generateCapManagementCsv($facilityId, $request->year_month);

        return Storage::disk('local')->download($path);
    }
}
