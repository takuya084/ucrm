<?php

namespace App\Services\Billing;

use App\Exceptions\BaseCodeResolutionException;
use App\Models\BillingDetail;
use App\Models\BillingPeriod;
use App\Models\Facility;
use App\Models\ServiceCodeMaster;
use App\Models\UsageRecord;
use Illuminate\Validation\ValidationException;

/** Read-only gate shared by calculation, confirmation and output (including legacy data). */
class BillingCompletenessGuard
{
    public function __construct(private ServiceCodeResolver $resolver) {}

    public function resolve(UsageRecord $record, Facility $facility, string $serviceType, string $month): ServiceCodeMaster
    {
        $date = $record->date->format('Y-m-d');
        $plan = $record->child->supportPlans()
            ->where('plan_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->orderByDesc('plan_date')->orderByDesc('id')->first();
        try {
            return $this->resolver->resolveBaseCode(
                $serviceType, $record->is_school_day ?? true, $record->check_in_time,
                $record->check_out_time, $month, $facility->capacity_per_day,
                $plan?->planned_duration_minutes
            );
        } catch (BaseCodeResolutionException $e) {
            $this->fail($record, $e->reason, $e->getMessage());
        }
    }

    public function assertInvoice(\App\Models\GuardianInvoice $invoice): void
    {
        $detail = $invoice->billingDetail;
        if (!$detail || $detail->child_id !== $invoice->child_id
            || $detail->billingPeriod->facility_id !== $invoice->facility_id
            || $detail->billingPeriod->year_month !== $invoice->year_month) {
            throw ValidationException::withMessages(['billing' => $invoice->year_month . '：請求書の元明細を確認できないため出力できません。']);
        }
        $this->assertDetail($detail);
        if ((int) $invoice->copayment_amount !== (int) $detail->copayment_cap_applied
            || (int) $invoice->total_amount !== (int) $invoice->copayment_amount + (int) $invoice->other_charges) {
            throw ValidationException::withMessages(['billing' => $invoice->year_month . '：請求書の金額が元明細と一致しません。確認後に請求書を再生成してください。']);
        }
    }

    public function assertPeriod(BillingPeriod $period): void
    {
        // Detect a completely omitted child's detail as well as incomplete existing details.
        foreach ($this->attendances($period)->get() as $record) {
            if (!$period->billingDetails()->where('child_id', $record->child_id)->exists()) {
                $this->fail($record, 'billing_detail_missing', '請求対象の出席に対応する明細がありません。');
            }
        }
        foreach ($period->billingDetails()->get() as $detail) {
            $this->assertDetail($detail);
        }
    }

    public function assertDetail(BillingDetail $detail): void
    {
        $period = $detail->billingPeriod;
        $records = $this->attendances($period)->where('child_id', $detail->child_id)->get();
        $dailyBase = $detail->dailyServiceRecords()->whereHas('serviceCodeMaster', fn ($q) => $q->where('category', 'base'))->get();
        foreach ($records as $record) {
            $master = $this->resolve($record, $period->facility, $detail->service_type, $period->year_month);
            $daily = $dailyBase->where('usage_record_id', $record->id);
            if ($daily->count() !== 1 || (int) $daily->first()->service_code_master_id !== $master->id
                || (int) $daily->first()->units !== (int) $master->unit_count) {
                $this->fail($record, 'base_record_incomplete', '保存済み明細の基本報酬実績が不足または不一致です。条件を修正後、対象月の再計算が必要です。');
            }
            $sameCode = $dailyBase->where('service_code_master_id', $master->id);
            $lines = $detail->billingDetailLines()->where('service_code_master_id', $master->id)->get();
            if ($lines->count() !== 1 || (int) $lines->first()->count !== $sameCode->count()
                || (int) $lines->first()->total_units !== (int) $sameCode->sum('units')
                || (int) $detail->total_units !== (int) $detail->billingDetailLines()->sum('total_units')) {
                $this->fail($record, 'base_line_incomplete', '保存済み基本報酬明細・合計が日別実績と一致しません。対象月の再計算が必要です。');
            }
        }
        if ($records->count() !== (int) $detail->total_days || $dailyBase->count() !== $records->count()) {
            throw ValidationException::withMessages(['billing' => $period->year_month . '：保存済み出席日数・基本報酬実績が現在の請求対象実績と一致しません。対象月の再計算が必要です。']);
        }
    }

    private function attendances(BillingPeriod $period)
    {
        return UsageRecord::where('facility_id', $period->facility_id)->where('billing_target', true)
            ->where('status', 'attended')->where('date', 'like', $period->year_month . '%')->orderBy('date')->orderBy('id');
    }

    private function fail(UsageRecord $record, string $reason, string $message): never
    {
        throw ValidationException::withMessages(['billing' => $record->date->format('Y-m-d')
            . '（児童ID ' . $record->child_id . '） [' . $reason . '] ' . $message
            . ' 処理を中止しました。明細の保存・確定・請求書出力はできません。']);
    }
}
