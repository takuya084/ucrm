<?php

namespace App\Services\Billing;

use App\Models\BillingDetail;
use App\Models\BillingDetailLine;
use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\DailyServiceRecord;
use App\Models\Facility;
use App\Models\RecipientCertificate;
use App\Models\UsageRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 月次請求計算のメインエンジン
 */
class BillingCalculationService
{
    public function __construct(
        private ServiceCodeResolver $codeResolver,
        private CopaymentCapService $capService,
    ) {}

    /**
     * 月次請求を計算
     */
    public function calculateMonthlyBilling(int $facilityId, string $yearMonth): BillingPeriod
    {
        $facility = Facility::findOrFail($facilityId);

        return DB::transaction(function () use ($facility, $yearMonth) {
            // 請求期間を取得または作成
            $period = BillingPeriod::updateOrCreate(
                ['facility_id' => $facility->id, 'year_month' => $yearMonth],
                ['status' => 'calculating']
            );

            // 既存の明細をクリア（再計算）
            $period->billingDetails()->delete();

            // 対象の児童を取得（当月に請求対象の利用記録がある児童）
            $children = $this->getTargetChildren($facility->id, $yearMonth);

            foreach ($children as $child) {
                $this->calculateChildBilling($period, $child, $facility, $yearMonth);
            }

            $period->update(['status' => 'draft']);

            return $period->load('billingDetails.child', 'billingDetails.billingDetailLines');
        });
    }

    /**
     * 児童別の請求計算
     */
    private function calculateChildBilling(
        BillingPeriod $period,
        Child $child,
        Facility $facility,
        string $yearMonth
    ): ?BillingDetail {
        // 有効な受給者証を取得
        $certificate = $child->recipientCertificates()
            ->where('status', 'active')
            ->where('valid_from', '<=', $yearMonth . '-01')
            ->where(function ($q) use ($yearMonth) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', $yearMonth . '-01');
            })
            ->latest('valid_from')
            ->first();

        if (!$certificate) {
            return null;
        }

        // 当月の請求対象利用記録を取得
        $usageRecords = UsageRecord::where('child_id', $child->id)
            ->where('facility_id', $facility->id)
            ->where('billing_target', true)
            ->where('date', 'like', $yearMonth . '%')
            ->whereIn('status', ['attended', 'absent_notice'])
            ->orderBy('date')
            ->get();

        if ($usageRecords->isEmpty()) {
            return null;
        }

        $serviceType = $certificate->service_type ?? $facility->service_type;

        // 請求明細を作成
        $detail = BillingDetail::create([
            'billing_period_id'        => $period->id,
            'child_id'                 => $child->id,
            'recipient_certificate_id' => $certificate->id,
            'service_type'             => $serviceType,
            'total_days'               => 0,
            'total_units'              => 0,
            'unit_price_yen'           => $facility->area_unit_price,
            'total_amount'             => 0,
            'insurance_amount'         => 0,
            'copayment_amount'         => 0,
            'copayment_cap'            => $certificate->copayment_cap_monthly ?? 0,
            'copayment_cap_applied'    => 0,
            'status'                   => 'draft',
        ]);

        // 日別サービス実績を生成
        $totalDays = 0;
        foreach ($usageRecords as $record) {
            $this->processUsageRecord($record, $detail, $facility, $serviceType, $yearMonth);
            if ($record->status === 'attended') {
                $totalDays++;
            }
        }

        // 明細行を集計（サービスコード別）
        $this->aggregateDetailLines($detail);

        // 合計計算
        $totalUnits = $detail->billingDetailLines()->sum('total_units');
        $totalAmount = (int) floor($totalUnits * $facility->area_unit_price);
        $copaymentRate = $certificate->copayment_rate ?? 10;
        $copaymentAmount = (int) floor($totalAmount * $copaymentRate / 100);
        $copaymentCap = $certificate->copayment_cap_monthly ?? 0;
        $copaymentCapApplied = $copaymentCap > 0 ? min($copaymentAmount, $copaymentCap) : $copaymentAmount;
        $insuranceAmount = $totalAmount - $copaymentCapApplied;

        $detail->update([
            'total_days'            => $totalDays,
            'total_units'           => $totalUnits,
            'total_amount'          => $totalAmount,
            'insurance_amount'      => $insuranceAmount,
            'copayment_amount'      => $copaymentAmount,
            'copayment_cap_applied' => $copaymentCapApplied,
        ]);

        return $detail;
    }

    /**
     * 利用記録に対するサービスコードの適用
     */
    private function processUsageRecord(
        UsageRecord $record,
        BillingDetail $detail,
        Facility $facility,
        string $serviceType,
        string $yearMonth
    ): void {
        // 基本サービスコードの特定
        $baseCode = $this->codeResolver->resolveBaseCode(
            $serviceType,
            $record->is_school_day ?? true,
            $record->check_in_time,
            $record->check_out_time,
            $yearMonth
        );

        if ($baseCode) {
            DailyServiceRecord::create([
                'usage_record_id'       => $record->id,
                'billing_detail_id'     => $detail->id,
                'service_code_master_id' => $baseCode->id,
                'service_code'          => $baseCode->service_code,
                'units'                 => $baseCode->unit_count,
                'start_time'            => $record->check_in_time,
                'end_time'              => $record->check_out_time,
                'is_pickup'             => $record->pickup_done,
                'is_dropoff'            => $record->dropoff_done,
            ]);
        }

        // 加算コードの適用
        $additions = $this->codeResolver->resolveAdditions($record, $facility, $yearMonth);
        foreach ($additions as $addition) {
            DailyServiceRecord::create([
                'usage_record_id'       => $record->id,
                'billing_detail_id'     => $detail->id,
                'service_code_master_id' => $addition->id,
                'service_code'          => $addition->service_code,
                'units'                 => $addition->unit_count,
                'start_time'            => $record->check_in_time,
                'end_time'              => $record->check_out_time,
                'is_pickup'             => isset($addition->conditions['requires_pickup']),
                'is_dropoff'            => isset($addition->conditions['requires_dropoff']),
                'is_extension'          => isset($addition->conditions['extension_after']),
            ]);
        }
    }

    /**
     * 日別サービス実績をサービスコード別に集計して明細行を作成
     */
    private function aggregateDetailLines(BillingDetail $detail): void
    {
        $grouped = DailyServiceRecord::where('billing_detail_id', $detail->id)
            ->selectRaw('service_code_master_id, service_code, COUNT(*) as count, SUM(units) as total_units')
            ->groupBy('service_code_master_id', 'service_code')
            ->get();

        foreach ($grouped as $row) {
            $master = \App\Models\ServiceCodeMaster::find($row->service_code_master_id);
            BillingDetailLine::create([
                'billing_detail_id'     => $detail->id,
                'service_code_master_id' => $row->service_code_master_id,
                'service_code'          => $row->service_code,
                'service_name'          => $master?->service_name ?? '',
                'count'                 => $row->count,
                'units_per_count'       => $master?->unit_count ?? 0,
                'total_units'           => $row->total_units,
            ]);
        }
    }

    /**
     * 当月に請求対象の利用記録がある児童を取得
     */
    private function getTargetChildren(int $facilityId, string $yearMonth): Collection
    {
        return Child::where('facility_id', $facilityId)
            ->whereHas('usageRecords', function ($q) use ($yearMonth, $facilityId) {
                $q->where('facility_id', $facilityId)
                  ->where('billing_target', true)
                  ->where('date', 'like', $yearMonth . '%')
                  ->whereIn('status', ['attended', 'absent_notice']);
            })
            ->with('recipientCertificates')
            ->orderBy('name_kana')
            ->get();
    }
}
