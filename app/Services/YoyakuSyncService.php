<?php

namespace App\Services;

use App\Models\Child;
use App\Models\DailyServiceRecord;
use App\Models\Facility;
use Illuminate\Support\Facades\DB;

/**
 * p-yoyaku との双方向同期ロジック
 *  - 実績取り込み（actuals → DailyServiceRecord の送迎フラグ更新）
 *  - 利用予定 push（UsageRecord → p-yoyaku 予約）
 */
class YoyakuSyncService
{
    public function __construct(private YoyakuApiService $api) {}

    /**
     * 実績取り込み
     * 指定日の actuals を取得し、Child.yoyaku_user_id で紐付けて
     * DailyServiceRecord.is_pickup / is_dropoff を実績ベースで更新する。
     *
     * @return array{imported:int, skipped:int} 処理結果
     */
    public function importActuals(int $facilityId, string $date): array
    {
        $facility = Facility::findOrFail($facilityId);
        if (!$facility->yoyaku_business_id) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $actuals = $this->api->getActuals($date, (int) $facility->yoyaku_business_id, $facilityId);
        if (!is_array($actuals)) {
            return ['imported' => 0, 'skipped' => 0];
        }

        // yoyaku_user_id → child_id のマップを作る
        $userIds = array_filter(array_column($actuals, 'user_id'));
        $childMap = Child::where('facility_id', $facilityId)
            ->whereIn('yoyaku_user_id', $userIds)
            ->pluck('id', 'yoyaku_user_id');

        $imported = 0;
        $skipped  = 0;

        DB::transaction(function () use ($actuals, $childMap, $facilityId, $date, &$imported, &$skipped) {
            foreach ($actuals as $row) {
                $childId = $childMap->get($row['user_id'] ?? null);
                if (!$childId) { $skipped++; continue; }

                DailyServiceRecord::updateOrCreate(
                    [
                        'facility_id' => $facilityId,
                        'child_id'    => $childId,
                        'service_date' => $date,
                    ],
                    [
                        'is_pickup'   => !empty($row['actual_pickup_at']),
                        'is_dropoff'  => !empty($row['actual_dropoff_at']),
                    ],
                );
                $imported++;
            }
        });

        return compact('imported', 'skipped');
    }
}
