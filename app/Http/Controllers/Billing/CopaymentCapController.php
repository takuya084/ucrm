<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Child;
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

        // 上限管理対象の利用者（受給者証ベース）
        $children = Child::where('facility_id', $facilityId)
            ->whereHas('recipientCertificates', function ($q) use ($yearMonth) {
                $q->where('is_cap_management_target', true)
                  ->where('status', 'active')
                  ->where('valid_from', '<=', $yearMonth . '-01')
                  ->where(function ($q2) use ($yearMonth) {
                      $q2->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $yearMonth . '-01');
                  });
            })
            ->with(['recipientCertificates' => function ($q) {
                $q->where('status', 'active')->latest('valid_from');
            }])
            ->orderBy('name_kana')->orderBy('name')
            ->get();

        $managementsByChild = CopaymentCapManagement::where('managing_facility_id', $facilityId)
            ->where('year_month', $yearMonth)
            ->with('details')
            ->get()
            ->keyBy('child_id');

        $rows = $children->map(function ($child) use ($managementsByChild) {
            $m = $managementsByChild->get($child->id);
            $cert = $child->recipientCertificates->first();
            return [
                'child_id'         => $child->id,
                'child_name'       => $child->name,
                'child_name_kana'  => $child->name_kana,
                'contract_status'  => $m?->contract_status ?? 'contracted',
                'form_type'        => $m?->form_type ?? 'paper',
                'status'           => $m?->status ?? 'draft',
                'actual_confirmed_at' => $m?->actual_confirmed_at,
                'sent_at'          => $m?->sent_at,
                'received_at'      => $m?->received_at,
                'confirmed_at'     => $m?->confirmed_at,
                'result_amount'    => $m?->adjusted_copayment,
                'management_result' => $m?->management_result,
                'remarks'          => $m?->remarks,
                'management_id'    => $m?->id,
                'related_count'    => $m ? $m->details->count() : 0,
                'cap_amount'       => $cert?->copayment_cap_monthly,
            ];
        });

        return Inertia::render('Billing/CapManagement/Index', [
            'rows'      => $rows,
            'yearMonth' => $yearMonth,
            'labels'    => [
                'status'          => CopaymentCapManagement::STATUS_LABELS,
                'formType'        => CopaymentCapManagement::FORM_TYPE_LABELS,
                'contractStatus'  => CopaymentCapManagement::CONTRACT_STATUS_LABELS,
                'result'          => CopaymentCapManagement::RESULT_LABELS,
            ],
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
            'labels'     => [
                'status'         => CopaymentCapManagement::STATUS_LABELS,
                'formType'       => CopaymentCapManagement::FORM_TYPE_LABELS,
                'contractStatus' => CopaymentCapManagement::CONTRACT_STATUS_LABELS,
                'result'         => CopaymentCapManagement::RESULT_LABELS,
            ],
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

    /**
     * ワークフロー状態の遷移
     */
    public function transition(Request $request, CopaymentCapManagement $copaymentCapManagement)
    {
        abort_if($copaymentCapManagement->managing_facility_id !== $this->facilityId(), 403);

        $data = $request->validate([
            'action' => ['required', 'in:send,receive,confirm,revert,confirm_actual'],
        ]);

        $now = now();
        $updates = match ($data['action']) {
            'send'           => ['status' => 'sent',      'sent_at' => $now],
            'receive'        => ['status' => 'received',  'received_at' => $now],
            'confirm'        => ['status' => 'confirmed', 'confirmed_at' => $now],
            'confirm_actual' => ['actual_confirmed_at' => $now],
            'revert'         => $this->revertStatus($copaymentCapManagement),
        };

        if (!empty($updates)) {
            $copaymentCapManagement->update($updates);
        }

        return back()->with(['message' => '状態を更新しました。', 'status' => 'success']);
    }

    private function revertStatus(CopaymentCapManagement $m): array
    {
        return match ($m->status) {
            'confirmed' => ['status' => 'received', 'confirmed_at' => null],
            'received'  => ['status' => 'sent',     'received_at' => null],
            'sent'      => ['status' => 'created',  'sent_at' => null],
            default     => [],
        };
    }

    /**
     * 備考・様式・契約状況の更新
     */
    public function updateAttributes(Request $request, CopaymentCapManagement $copaymentCapManagement)
    {
        abort_if($copaymentCapManagement->managing_facility_id !== $this->facilityId(), 403);

        $data = $request->validate([
            'form_type'       => ['nullable', 'in:paper,electronic'],
            'contract_status' => ['nullable', 'in:contracted,pending,terminated'],
            'remarks'         => ['nullable', 'string', 'max:1000'],
        ]);

        $copaymentCapManagement->update(array_filter($data, fn($v) => $v !== null));

        return back()->with(['message' => '属性を更新しました。', 'status' => 'success']);
    }
}
