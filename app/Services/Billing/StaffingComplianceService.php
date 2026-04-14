<?php

namespace App\Services\Billing;

use App\Models\BillingPeriod;
use App\Models\ShiftEntry;
use App\Models\ShiftLabel;
use App\Models\UsageRecord;
use Illuminate\Support\Carbon;

/**
 * 人員配置基準とシフトの整合性をチェック
 *
 * 放デイ/児発の基準（簡易版）:
 *   - 児童10名以下       → 児童指導員等 2名以上
 *   - 11〜15名           → 3名以上
 *   - 16〜20名           → 4名以上
 *   - 21名以上           → ceil((n-10)/5)+2 名以上
 *   - 児発管(role=leader) が営業日に1名以上勤務していること
 */
class StaffingComplianceService
{
    /**
     * 請求期間の人員配置違反を検出
     *
     * @return array<int, array{level:string, message:string}>
     */
    public function checkPeriod(BillingPeriod $period): array
    {
        $facilityId = $period->facility_id;
        $yearMonth  = $period->year_month;

        // 「休み/有給」扱いのラベル名を取得
        $offLabels = ShiftLabel::where('facility_id', $facilityId)
            ->where('is_off', true)
            ->pluck('name')
            ->all();

        // 当月シフトエントリを取得
        $entries = ShiftEntry::whereHas('monthlyShift', fn($q) =>
                $q->where('facility_id', $facilityId)
                  ->whereRaw("DATE_FORMAT(CONCAT(year,'-',LPAD(month,2,'0'),'-01'), '%Y-%m') = ?", [$yearMonth]))
            ->with('staff:id,role')
            ->get(['id', 'staff_id', 'date', 'work_type']);

        // 日別：出勤スタッフ + 児発管有無
        $byDate = [];
        foreach ($entries as $e) {
            $isWorking = $e->work_type !== '' && !in_array($e->work_type, $offLabels, true);
            if (!$isWorking) continue;

            $d = $e->date instanceof \Carbon\Carbon ? $e->date->format('Y-m-d') : (string) $e->date;
            if (!isset($byDate[$d])) {
                $byDate[$d] = ['staff_count' => 0, 'has_leader' => false];
            }
            $byDate[$d]['staff_count']++;
            if ($e->staff?->role === 'leader') $byDate[$d]['has_leader'] = true;
        }

        // 日別：利用児童数（出席のみ）
        $childCountByDate = UsageRecord::where('facility_id', $facilityId)
            ->where('date', 'like', $yearMonth . '%')
            ->where('status', 'attended')
            ->selectRaw('date, COUNT(DISTINCT child_id) AS cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date')
            ->all();

        $warnings = [];
        ksort($childCountByDate);

        foreach ($childCountByDate as $date => $childCount) {
            $required = $this->requiredStaff($childCount);
            $actual   = $byDate[$date]['staff_count'] ?? 0;
            $hasLeader = $byDate[$date]['has_leader'] ?? false;
            $label = Carbon::parse($date)->format('n/j');

            if ($actual < $required) {
                $warnings[] = [
                    'level'   => 'error',
                    'message' => "{$label}: 利用児童 {$childCount}名 に対して出勤スタッフ {$actual}名（基準 {$required}名）— 人員配置基準未達（減算対象の可能性）",
                ];
            }
            if (!$hasLeader) {
                $warnings[] = [
                    'level'   => 'warning',
                    'message' => "{$label}: 児童発達支援管理責任者（leader）の勤務が確認できません",
                ];
            }
        }

        // 利用実績はあるがシフトが全く登録されていない日
        $noShiftDates = array_diff(array_keys($childCountByDate), array_keys($byDate));
        foreach ($noShiftDates as $date) {
            $warnings[] = [
                'level'   => 'warning',
                'message' => Carbon::parse($date)->format('n/j') . ': 利用実績があるがシフトが未登録です',
            ];
        }

        return $warnings;
    }

    /**
     * 児童数から必要スタッフ数を算出
     */
    public function requiredStaff(int $childCount): int
    {
        if ($childCount <= 0) return 0;
        if ($childCount <= 10) return 2;
        return (int) (ceil(($childCount - 10) / 5) + 2);
    }
}
