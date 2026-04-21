<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingExport;
use App\Models\BillingPeriod;
use App\Models\BillingDetail;
use App\Models\CopaymentCapManagement;
use App\Models\DailyServiceRecord;
use App\Models\MonthlyExpense;
use App\Models\ShiftEntry;
use App\Models\ShiftLabel;
use App\Models\Staff;
use App\Models\UsageRecord;
use App\Services\Billing\BillingCalculationService;
use App\Services\Billing\BillingExportBundleService;
use App\Services\Billing\NhifCsvExportService;
use App\Services\Billing\PerformanceRecordPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BillingPeriodController extends Controller
{
    public function __construct(
        private BillingCalculationService $calculationService,
        private NhifCsvExportService $csvExportService,
        private BillingExportBundleService $bundleService,
        private PerformanceRecordPdfService $performancePdfService,
    ) {}

    /**
     * 実績記録票PDF（1児童分）
     */
    public function performancePdf(BillingDetail $billingDetail)
    {
        abort_if($billingDetail->billingPeriod->facility_id !== $this->facilityId(), 403);
        $path = $this->performancePdfService->generate($billingDetail);
        return Storage::disk('local')->download($path);
    }

    /**
     * 実績記録票PDF（月次一括ZIP）
     */
    public function performancePdfBundle(BillingPeriod $billingPeriod)
    {
        $this->authorizeFacility($billingPeriod);
        $path = $this->performancePdfService->generateBundle($billingPeriod);
        return Storage::disk('local')->download($path);
    }

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
            'facility:id,name,capacity_per_day',
            'billingDetails' => fn($q) => $q->orderBy('child_id'),
            'billingDetails.child:id,name,name_kana',
            'billingDetails.recipientCertificate:id,certificate_number,copayment_cap_monthly,monthly_limit,valid_from,valid_to,is_cap_management_target,cap_managing_facility_id',
            'billingDetails.billingDetailLines.serviceCodeMaster:id,category,service_name',
            'confirmedByStaff:id,name',
        ]);

        $exports = BillingExport::where('billing_period_id', $billingPeriod->id)
            ->orderByDesc('created_at')
            ->with('createdBy:id,name')
            ->get();

        $this->attachReviewMetrics($billingPeriod);
        $kpi   = $this->buildFacilityKpi($billingPeriod);
        $trend = $this->buildRevenueTrend($billingPeriod, 6);

        return Inertia::render('Billing/Show', [
            'period'  => $billingPeriod,
            'exports' => $exports,
            'kpi'     => $kpi,
            'trend'   => $trend,
        ]);
    }

    /**
     * 前月の請求詳細へショートカット
     */
    public function previousMonth()
    {
        $prev = now()->subMonthNoOverflow()->format('Y-m');

        $period = BillingPeriod::where('facility_id', $this->facilityId())
            ->where('year_month', $prev)
            ->first();

        if (!$period) {
            session()->flash('message', "{$prev}の請求データがありません。計算を実行してください。");
            session()->flash('status', 'info');
            return to_route('billing.index', ['month' => $prev]);
        }

        return to_route('billing.show', $period);
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

    /**
     * 月初確認用の集計値を各 billingDetail に付加
     *   - absent_days: 欠席（連絡あり／無断／キャンセル）日数
     *   - attended_days: 実績上の出席日数（請求の total_days と突合用）
     *   - pickup_count / dropoff_count: 実績側の送迎回数
     *   - pickup_billed / dropoff_billed: 請求側の送迎回数
     *   - addition_lines: 加算カテゴリの明細行
     *   - is_cap_management_target: 上限管理対象フラグ
     *   - cap_management_status: 上限管理票の状態（未作成/draft/created/...）
     *   - review_issues: 月初レビューで検知した不整合の配列
     *   - review_level: 'ok' | 'warning' | 'error'
     */
    private function attachReviewMetrics(BillingPeriod $period): void
    {
        $childIds = $period->billingDetails->pluck('child_id')->all();
        if (empty($childIds)) return;

        $from = "{$period->year_month}-01";
        $to   = date('Y-m-t', strtotime($from));

        $usageStats = UsageRecord::where('facility_id', $period->facility_id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('child_id', $childIds)
            ->selectRaw("
                child_id,
                SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END)                       AS attended_days,
                SUM(CASE WHEN status IN ('absent','absent_notice','cancel') THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN pickup_done = 1 THEN 1 ELSE 0 END)  AS pickup_count,
                SUM(CASE WHEN dropoff_done = 1 THEN 1 ELSE 0 END) AS dropoff_count
            ")
            ->groupBy('child_id')
            ->get()
            ->keyBy('child_id');

        $detailIds = $period->billingDetails->pluck('id')->all();
        $dsrStats = DailyServiceRecord::whereIn('billing_detail_id', $detailIds)
            ->selectRaw("
                billing_detail_id,
                SUM(CASE WHEN is_pickup  = 1 THEN 1 ELSE 0 END) AS pickup_billed,
                SUM(CASE WHEN is_dropoff = 1 THEN 1 ELSE 0 END) AS dropoff_billed
            ")
            ->groupBy('billing_detail_id')
            ->get()
            ->keyBy('billing_detail_id');

        $capManagements = CopaymentCapManagement::where('year_month', $period->year_month)
            ->whereIn('child_id', $childIds)
            ->get()
            ->keyBy('child_id');

        foreach ($period->billingDetails as $detail) {
            $u = $usageStats->get($detail->child_id);
            $detail->attended_days = (int) ($u->attended_days ?? 0);
            $detail->absent_days   = (int) ($u->absent_days   ?? 0);
            $detail->pickup_count  = (int) ($u->pickup_count  ?? 0);
            $detail->dropoff_count = (int) ($u->dropoff_count ?? 0);

            $d = $dsrStats->get($detail->id);
            $detail->pickup_billed  = (int) ($d->pickup_billed  ?? 0);
            $detail->dropoff_billed = (int) ($d->dropoff_billed ?? 0);

            $additions = $detail->billingDetailLines
                ->filter(fn($l) => optional($l->serviceCodeMaster)->category === 'addition')
                ->map(fn($l) => [
                    'service_code' => $l->service_code,
                    'service_name' => $l->service_name,
                    'count'        => $l->count,
                ])->values();
            $detail->addition_lines = $additions;
            $detail->addition_count = $additions->count();

            $cert = $detail->recipientCertificate;
            $detail->is_cap_management_target = (bool) optional($cert)->is_cap_management_target;
            $cap = $capManagements->get($detail->child_id);
            $detail->cap_management_status = $cap?->status ?? ($detail->is_cap_management_target ? 'missing' : null);

            $detail->review_issues = $this->detectReviewIssues($detail, $additions);
            $detail->review_level  = $this->summarizeLevel($detail->review_issues);
        }
    }

    /**
     * 各明細の不整合検知
     */
    private function detectReviewIssues(BillingDetail $detail, $additions): array
    {
        $issues = [];

        // ① 実績記録票と請求データの整合
        if ($detail->attended_days !== (int) $detail->total_days) {
            $issues[] = [
                'level'   => 'error',
                'message' => "実績の出席 {$detail->attended_days}日 ≠ 請求の利用日数 {$detail->total_days}日",
            ];
        }

        // ② 送迎の実績と請求の回数突合
        if ($detail->pickup_count !== $detail->pickup_billed) {
            $issues[] = [
                'level'   => 'warning',
                'message' => "送迎(迎) 実績{$detail->pickup_count}回 ≠ 請求{$detail->pickup_billed}回",
            ];
        }
        if ($detail->dropoff_count !== $detail->dropoff_billed) {
            $issues[] = [
                'level'   => 'warning',
                'message' => "送迎(送) 実績{$detail->dropoff_count}回 ≠ 請求{$detail->dropoff_billed}回",
            ];
        }

        // ③ 加算要件の簡易チェック（名称ベース）
        $additionNames = $additions->pluck('service_name')->implode(' ');
        if (preg_match('/送迎/u', $additionNames) && $detail->pickup_count === 0 && $detail->dropoff_count === 0) {
            $issues[] = ['level' => 'error', 'message' => '送迎加算あり、しかし実績に送迎記録なし'];
        }
        if (preg_match('/欠席時対応/u', $additionNames) && $detail->absent_days === 0) {
            $issues[] = ['level' => 'warning', 'message' => '欠席時対応加算あり、しかし欠席記録なし'];
        }

        // ④ 支給量超過（既存の警告と二重になるがここにも集約）
        $limit = optional($detail->recipientCertificate)->monthly_limit;
        if ($limit && $detail->total_days > $limit) {
            $issues[] = ['level' => 'error', 'message' => "利用日数 {$detail->total_days}日 が支給量 {$limit}日 を超過"];
        }

        // ⑤ 上限管理対象だが管理票が未作成
        if ($detail->is_cap_management_target && $detail->cap_management_status === 'missing') {
            $issues[] = ['level' => 'warning', 'message' => '上限管理対象だが管理票が未作成'];
        }

        return $issues;
    }

    private function summarizeLevel(array $issues): string
    {
        foreach ($issues as $i) {
            if ($i['level'] === 'error') return 'error';
        }
        return count($issues) > 0 ? 'warning' : 'ok';
    }

    /**
     * 事業所KPI（月次・対象 BillingPeriod 単位）
     *   - 利用率 = 出席日数合計 ÷ (定員 × 営業日数)
     *   - キャンセル率 = 欠席合計 ÷ 予定合計
     *   - 送迎実施率 = (迎 + 送) ÷ (出席 × 2)
     *   - 加算サマリ = service_name 別の合計 count（件数上位）
     *   - 金額合計 = total_amount / insurance_amount / copayment_cap_applied
     */
    private function buildFacilityKpi(BillingPeriod $period): array
    {
        $from = "{$period->year_month}-01";
        $to   = date('Y-m-t', strtotime($from));

        $usage = UsageRecord::where('facility_id', $period->facility_id)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("
                COUNT(*)                                                                          AS scheduled,
                SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END)                              AS attended,
                SUM(CASE WHEN status IN ('absent','absent_notice','cancel') THEN 1 ELSE 0 END)    AS absent,
                SUM(CASE WHEN pickup_done  = 1 THEN 1 ELSE 0 END)                                 AS pickups,
                SUM(CASE WHEN dropoff_done = 1 THEN 1 ELSE 0 END)                                 AS dropoffs,
                COUNT(DISTINCT date)                                                              AS business_days
            ")
            ->first();

        $capacity = (int) optional($period->facility)->capacity_per_day;
        $businessDays = (int) ($usage->business_days ?? 0);
        $attended = (int) ($usage->attended ?? 0);
        $scheduled = (int) ($usage->scheduled ?? 0);
        $absent = (int) ($usage->absent ?? 0);
        $pickups = (int) ($usage->pickups ?? 0);
        $dropoffs = (int) ($usage->dropoffs ?? 0);

        $additionSummary = $period->billingDetails
            ->flatMap(fn($d) => $d->addition_lines ?? collect())
            ->groupBy('service_name')
            ->map(fn($rows, $name) => [
                'service_name' => $name,
                'total_count'  => $rows->sum('count'),
                'child_count'  => $rows->count(),
            ])
            ->sortByDesc('total_count')
            ->values()
            ->all();

        $totalAmount  = $period->billingDetails->sum('total_amount');
        $insurance    = $period->billingDetails->sum('insurance_amount');
        $copayApplied = $period->billingDetails->sum('copayment_cap_applied');

        [$laborCost, $laborBreakdown] = $this->calcLaborCost($period);
        $expenses     = $this->calcExpenses($period);
        $revenue      = (int) ($insurance + $copayApplied);
        $totalCost    = $laborCost + $expenses;
        $netProfit    = $revenue - $totalCost;

        return [
            'capacity_per_day'  => $capacity,
            'business_days'     => $businessDays,
            'scheduled'         => $scheduled,
            'attended'          => $attended,
            'absent'            => $absent,
            'pickups'           => $pickups,
            'dropoffs'          => $dropoffs,
            'utilization_rate'  => ($capacity > 0 && $businessDays > 0)
                ? round($attended / ($capacity * $businessDays) * 100, 1) : null,
            'cancellation_rate' => $scheduled > 0
                ? round($absent / $scheduled * 100, 1) : null,
            'pickup_rate'       => $attended > 0
                ? round(($pickups + $dropoffs) / ($attended * 2) * 100, 1) : null,
            'addition_summary'  => $additionSummary,
            'total_amount'      => (int) $totalAmount,
            'insurance_amount'  => (int) $insurance,
            'copayment_applied' => (int) $copayApplied,
            'children_count'    => $period->billingDetails->count(),
            // 損益
            'revenue'           => $revenue,
            'labor_cost'        => $laborCost,
            'labor_breakdown'   => $laborBreakdown,
            'expenses'          => $expenses,
            'total_cost'        => $totalCost,
            'net_profit'        => $netProfit,
            'labor_ratio'       => $revenue > 0 ? round($laborCost / $revenue * 100, 1) : null,
            'profit_ratio'      => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : null,
        ];
    }

    /**
     * 月次人件費
     *   - 常勤: monthly_salary の合計（その月に在職）
     *   - パート/契約: sum(work_hours of matched shift_label) × hourly_wage
     */
    private function calcLaborCost(BillingPeriod $period): array
    {
        $facilityId = $period->facility_id;
        $from = "{$period->year_month}-01";
        $to   = date('Y-m-t', strtotime($from));

        $staff = Staff::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->where(function ($q) use ($to) {
                $q->whereNull('joined_at')->orWhere('joined_at', '<=', $to);
            })
            ->get(['id', 'name', 'employment_type', 'monthly_salary', 'hourly_wage']);

        $fulltimeCost = (int) $staff->where('employment_type', 'full_time')->sum('monthly_salary');

        // パート・契約の時給精算
        $labels = ShiftLabel::where('facility_id', $facilityId)
            ->whereNotNull('work_hours')
            ->pluck('work_hours', 'name');

        $hourlyStaff = $staff->whereIn('employment_type', ['part_time', 'contract']);
        $partCost = 0;

        if ($hourlyStaff->isNotEmpty() && $labels->isNotEmpty()) {
            $entries = ShiftEntry::whereIn('staff_id', $hourlyStaff->pluck('id'))
                ->whereBetween('date', [$from, $to])
                ->get(['staff_id', 'work_type']);

            foreach ($hourlyStaff as $s) {
                $wage = (float) $s->hourly_wage;
                if ($wage <= 0) continue;
                $hours = 0.0;
                foreach ($entries->where('staff_id', $s->id) as $e) {
                    $hours += (float) ($labels[$e->work_type] ?? 0);
                }
                $partCost += (int) round($wage * $hours);
            }
        }

        return [
            $fulltimeCost + $partCost,
            [
                'full_time' => $fulltimeCost,
                'part_time' => $partCost,
                'staff_count' => $staff->count(),
            ],
        ];
    }

    /**
     * 月次経費合計
     */
    private function calcExpenses(BillingPeriod $period): int
    {
        return (int) MonthlyExpense::where('facility_id', $period->facility_id)
            ->where('year_month', $period->year_month)
            ->sum('amount');
    }

    /**
     * 直近 N ヶ月の売上トレンド（対象期間を末尾に含む）
     */
    private function buildRevenueTrend(BillingPeriod $period, int $months): array
    {
        $end   = $period->year_month . '-01';
        $start = date('Y-m-01', strtotime("$end -" . ($months - 1) . " months"));

        $rows = BillingPeriod::where('facility_id', $period->facility_id)
            ->whereBetween('year_month', [substr($start, 0, 7), $period->year_month])
            ->with(['billingDetails:id,billing_period_id,total_amount,insurance_amount,copayment_cap_applied,total_days'])
            ->get()
            ->keyBy('year_month');

        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("$end -$i months"));
            $p  = $rows->get($ym);
            $result[] = [
                'year_month' => $ym,
                'total'      => $p ? (int) $p->billingDetails->sum('total_amount') : 0,
                'insurance'  => $p ? (int) $p->billingDetails->sum('insurance_amount') : 0,
                'copayment'  => $p ? (int) $p->billingDetails->sum('copayment_cap_applied') : 0,
                'days'       => $p ? (int) $p->billingDetails->sum('total_days') : 0,
                'exists'     => (bool) $p,
            ];
        }
        return $result;
    }
}
