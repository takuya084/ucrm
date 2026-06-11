<?php

namespace App\Services\Billing;

use App\Models\Facility;
use App\Models\FacilityServiceSetting;
use App\Models\ServiceCodeMaster;
use App\Models\UsageRecord;
use Illuminate\Support\Collection;

/**
 * サービス種別・学校日/休業日・時間帯からサービスコードを特定
 */
class ServiceCodeResolver
{
    /**
     * 基本サービスコードを特定
     */
    public function resolveBaseCode(
        string $serviceType,
        bool $isSchoolDay,
        ?string $checkInTime,
        ?string $checkOutTime,
        string $yearMonth,
        ?int $capacityPerDay = null,
        ?int $plannedMinutes = null
    ): ?ServiceCodeMaster {
        $query = ServiceCodeMaster::forServiceType($serviceType)
            ->ofCategory('base')
            ->validAt($yearMonth);

        // 学校日/休業日の条件で絞り込み
        $dayType = $isSchoolDay ? 'school_day' : 'holiday';

        $codes = $query->get();

        // R6改定: 個別支援計画に定めた支援時間から時間区分を算出
        $timeCategory = $this->resolveTimeCategory($plannedMinutes);

        // 条件JSONからマッチするものを探す
        return $codes->first(function ($code) use ($dayType, $checkInTime, $checkOutTime, $capacityPerDay, $timeCategory) {
            $conditions = $code->conditions ?? [];

            // day_type条件のチェック
            if (isset($conditions['day_type']) && $conditions['day_type'] !== $dayType) {
                return false;
            }

            // 時間区分条件のチェック（R6改定: 計画上の支援時間で判定）
            if (isset($conditions['time_category'])) {
                if ($timeCategory === null || (int) $conditions['time_category'] !== $timeCategory) {
                    return false;
                }
            }

            // 定員条件のチェック
            if ($capacityPerDay !== null) {
                if (isset($conditions['max_capacity']) && $capacityPerDay > $conditions['max_capacity']) {
                    return false;
                }
                if (isset($conditions['min_capacity']) && $capacityPerDay < $conditions['min_capacity']) {
                    return false;
                }
            }

            // 時間帯条件のチェック（設定があれば）
            if (isset($conditions['min_hours']) && $checkInTime && $checkOutTime) {
                $duration = $this->calculateDurationHours($checkInTime, $checkOutTime);
                if ($duration < $conditions['min_hours']) {
                    return false;
                }
            }

            if (isset($conditions['max_hours']) && $checkInTime && $checkOutTime) {
                $duration = $this->calculateDurationHours($checkInTime, $checkOutTime);
                if ($duration > $conditions['max_hours']) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * 利用記録に適用可能な加算コードを特定
     */
    public function resolveAdditions(
        UsageRecord $record,
        Facility $facility,
        string $yearMonth
    ): Collection {
        $additions = collect();

        // 事業所で有効な加算コードを取得
        $enabledCodes = $this->getActiveCodesForFacility($facility->id, $yearMonth)
            ->where('category', 'addition');

        foreach ($enabledCodes as $code) {
            if ($this->isAdditionApplicable($code, $record)) {
                $additions->push($code);
            }
        }

        return $additions;
    }

    /**
     * 事業所に有効なサービスコード一覧を取得
     */
    public function getActiveCodesForFacility(int $facilityId, string $yearMonth): Collection
    {
        return ServiceCodeMaster::validAt($yearMonth)
            ->whereHas('facilityServiceSettings', function ($q) use ($facilityId, $yearMonth) {
                $q->where('facility_id', $facilityId)->activeAt($yearMonth);
            })
            ->get();
    }

    /**
     * 減算コードを特定
     */
    public function resolveSubtractions(
        Facility $facility,
        string $yearMonth
    ): Collection {
        return $this->getActiveCodesForFacility($facility->id, $yearMonth)
            ->where('category', 'subtraction');
    }

    /**
     * 加算の適用判定
     */
    private function isAdditionApplicable(ServiceCodeMaster $code, UsageRecord $record): bool
    {
        $conditions = $code->conditions ?? [];

        // 率ベース加算（処遇改善加算等）は日次ではなく月次で別計算
        if (!empty($conditions['rate_based'])) {
            return false;
        }

        // 欠席時対応加算: 連絡ありの欠席のみ
        if (isset($conditions['absent_with_notice']) && $conditions['absent_with_notice']) {
            return $record->status === 'absent_notice';
        }

        // 欠席時対応加算以外の加算は出席日のみ算定（欠席日への算定は過請求となる）
        if ($record->status !== 'attended') {
            return false;
        }

        // 送迎加算: 送迎実施の場合に適用
        if (isset($conditions['requires_pickup']) && $conditions['requires_pickup']) {
            return $record->pickup_done;
        }
        if (isset($conditions['requires_dropoff']) && $conditions['requires_dropoff']) {
            return $record->dropoff_done;
        }

        // 延長支援加算: 退所時間が基準時間を超えた場合
        if (isset($conditions['extension_after'])) {
            return $record->check_out_time && $record->check_out_time > $conditions['extension_after'];
        }

        // 条件が無い、または一般的な加算はデフォルト適用
        return true;
    }

    /**
     * 計画上の支援時間（分）から R6 の時間区分を算出
     *
     *   区分1: 30分以上1時間30分以下
     *   区分2: 1時間30分超3時間以下
     *   区分3: 3時間超5時間以下（休業日・児童発達支援）
     *   30分未満は原則算定不可（市町村が認めた場合を除く）→ null
     */
    private function resolveTimeCategory(?int $plannedMinutes): ?int
    {
        if ($plannedMinutes === null) {
            return null;
        }

        return match (true) {
            $plannedMinutes < 30   => null,
            $plannedMinutes <= 90  => 1,
            $plannedMinutes <= 180 => 2,
            $plannedMinutes <= 300 => 3,
            default                => 3, // 5時間超は区分3＋延長支援加算で対応
        };
    }

    /**
     * 来所・退所時間から利用時間（時間単位）を計算
     */
    private function calculateDurationHours(string $checkIn, string $checkOut): float
    {
        $in  = strtotime($checkIn);
        $out = strtotime($checkOut);

        if ($out <= $in) {
            return 0;
        }

        return ($out - $in) / 3600;
    }
}
