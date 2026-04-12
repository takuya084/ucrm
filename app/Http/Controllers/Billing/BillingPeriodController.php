<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingPeriod;
use App\Services\Billing\BillingCalculationService;
use App\Services\Billing\NhifCsvExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BillingPeriodController extends Controller
{
    public function __construct(
        private BillingCalculationService $calculationService,
        private NhifCsvExportService $csvExportService,
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
            'billingDetails.recipientCertificate:id,certificate_number,copayment_cap_monthly',
            'billingDetails.billingDetailLines',
            'confirmedByStaff:id,name',
        ]);

        return Inertia::render('Billing/Show', [
            'period' => $billingPeriod,
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

    private function authorizeFacility(BillingPeriod $period): void
    {
        abort_if($period->facility_id !== $this->facilityId(), 403);
    }
}
