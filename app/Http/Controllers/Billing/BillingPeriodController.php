<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingExport;
use App\Models\BillingPeriod;
use App\Services\Billing\BillingCalculationService;
use App\Services\Billing\BillingExportBundleService;
use App\Services\Billing\NhifCsvExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BillingPeriodController extends Controller
{
    public function __construct(
        private BillingCalculationService $calculationService,
        private NhifCsvExportService $csvExportService,
        private BillingExportBundleService $bundleService,
    ) {}

    /**
     * 月次請求一覧
     */
    public function index(Request $request)
    {
        $facilityId = $this->facilityId();

        $periods = BillingPeriod::where('facility_id', $facilityId)
            ->withCount('billingDetails')
            ->orderByDesc('year_month')
            ->paginate(12);

        return Inertia::render('Billing/Index', [
            'periods'      => $periods,
            'currentMonth' => $request->input('month', now()->format('Y-m')),
        ]);
    }

    /**
     * 請求期間詳細（児童一覧+金額）
     */
    public function show(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        $billingPeriod->load([
            'billingDetails' => fn($q) => $q->orderBy('child_id'),
            'billingDetails.child:id,name,name_kana',
            'billingDetails.recipientCertificate:id,certificate_number,copayment_cap_monthly,monthly_limit,valid_from,valid_to',
            'billingDetails.billingDetailLines',
            'confirmedByStaff:id,name',
        ]);

        $exports = BillingExport::where('billing_period_id', $billingPeriod->id)
            ->orderByDesc('created_at')
            ->with('createdBy:id,name')
            ->get();

        return Inertia::render('Billing/Show', [
            'period'  => $billingPeriod,
            'exports' => $exports,
        ]);
    }

    /**
     * 月次請求計算を実行
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'year_month' => 'required|date_format:Y-m',
        ]);

        $facilityId = $this->facilityId();
        $yearMonth  = $request->year_month;

        $period = $this->calculationService->calculateMonthlyBilling($facilityId, $yearMonth);

        session()->flash('message', "{$yearMonth}の請求計算が完了しました。");
        session()->flash('status', 'success');

        return to_route('billing.show', $period);
    }

    /**
     * 請求期間を確定
     */
    public function confirm(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        if (!$billingPeriod->isDraft()) {
            session()->flash('message', '下書き状態の請求のみ確定できます。');
            session()->flash('status', 'error');
            return back();
        }

        $billingPeriod->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->user()->staff?->id,
        ]);

        $billingPeriod->billingDetails()->update(['status' => 'confirmed']);

        session()->flash('message', '請求を確定しました。');
        session()->flash('status', 'success');

        return back();
    }

    /**
     * 国保連CSV出力
     */
    public function export(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        $billingCsvPath      = $this->csvExportService->generateBillingCsv($billingPeriod);
        $performanceCsvPath  = $this->csvExportService->generatePerformanceRecordCsv($billingPeriod);

        // 請求明細CSVをダウンロード（実績記録票は別途ダウンロードできるようにする）
        return Storage::disk('local')->download($billingCsvPath);
    }

    /**
     * 実績記録票CSV出力
     */
    public function exportPerformance(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        $path = $this->csvExportService->generatePerformanceRecordCsv($billingPeriod);
        return Storage::disk('local')->download($path);
    }

    /**
     * 事前バリデーション（ZIP出力前チェック）
     */
    public function validateExport(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        return response()->json([
            'warnings' => $this->bundleService->validate($billingPeriod),
        ]);
    }

    /**
     * 複式CSV（ZIP一括）出力
     */
    public function exportBundle(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);

        $export = $this->bundleService->generateBundle($billingPeriod, auth()->id());

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }

    /**
     * 過去の出力履歴から再ダウンロード
     */
    public function downloadExport(BillingExport $billingExport)
    {
        abort_if($billingExport->facility_id !== $this->facilityId(), 403);
        abort_unless(Storage::disk('local')->exists($billingExport->file_path), 404);

        return Storage::disk('local')->download($billingExport->file_path, $billingExport->file_name);
    }

    /**
     * 国保連送信済マーク
     */
    public function markSubmitted(BillingExport $billingExport)
    {
        abort_if($billingExport->facility_id !== $this->facilityId(), 403);

        $billingExport->update([
            'is_submitted' => true,
            'submitted_at' => now(),
        ]);

        return back()->with(['message' => '送信済にマークしました。', 'status' => 'success']);
    }

    private function authorizeFacility(BillingPeriod $period): void
    {
        abort_if($period->facility_id !== $this->facilityId(), 403);
    }
}
