<?php

namespace App\Services;

use App\Exceptions\BillingPeriodLockedException;
use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\Facility;
use App\Models\UsageRecord;
use App\Observers\UsageRecordObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class YoyakuSyncService
{
    public function __construct(private YoyakuApiService $api) {}

    /**
     * Import transport flags into existing attended records, the source for billing.
     * Never create attendance, restore cancellations, or directly modify billing rows.
     *
     * @return array{imported:int, skipped:int}
     */
    public function importActuals(int $facilityId, string $date): array
    {
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $facility = Facility::findOrFail($facilityId);
        if (!$facility->yoyaku_business_id) {
            return ['imported' => 0, 'skipped' => 0];
        }
        $this->assertUniqueFacility($facility);

        // Do not hold database locks during the HTTP request.
        $actuals = $this->api->getActuals($date, (int) $facility->yoyaku_business_id, $facilityId);
        if ($actuals === null) {
            throw new \RuntimeException('Transport actuals could not be retrieved');
        }
        if (!array_is_list($actuals)) {
            throw new \RuntimeException('Invalid transport actuals response');
        }
        $rows = [];
        foreach ($actuals as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Invalid transport actuals response');
            }
            Validator::make($row, [
                'user_id' => ['required', 'integer', 'min:1'],
                'date' => ['required', 'in:'.$date],
                'actual_pickup_at' => ['present', 'nullable', 'date'],
                'actual_dropoff_at' => ['present', 'nullable', 'date'],
            ])->validate();
            foreach (['actual_pickup_at', 'actual_dropoff_at'] as $field) {
                if ($row[$field] !== null && (!is_string($row[$field]) || trim($row[$field]) === '')) {
                    throw new \RuntimeException('Invalid transport actuals response');
                }
            }
            $userId = (int) $row['user_id'];
            if (isset($rows[$userId])) {
                throw new \RuntimeException('Ambiguous transport actuals');
            }
            $rows[$userId] = $row;
        }

        return DB::transaction(function () use ($facility, $facilityId, $date, $rows) {
            $current = Facility::whereKey($facilityId)->lockForUpdate()->firstOrFail();
            if ((string) $current->yoyaku_business_id !== (string) $facility->yoyaku_business_id
                || $current->yoyaku_api_token !== $facility->yoyaku_api_token) {
                throw new \RuntimeException('Facility integration changed during import');
            }
            $this->assertUniqueFacility($current);
            $period = BillingPeriod::where('facility_id', $facilityId)
                ->where('year_month', substr($date, 0, 7))->lockForUpdate()->first();
            if ($period?->isLocked() || $period?->status === 'calculating') {
                throw new BillingPeriodLockedException('Transport actuals cannot modify a locked or calculating billing period');
            }

            $children = Child::where('facility_id', $facilityId)
                ->whereIn('yoyaku_user_id', array_keys($rows))
                ->lockForUpdate()->get()->groupBy('yoyaku_user_id');
            foreach ($children as $matches) {
                if ($matches->count() !== 1) {
                    throw new \RuntimeException('Ambiguous child integration mapping');
                }
            }

            $imported = 0;
            $skipped = 0;
            return UsageRecordObserver::withoutPush(function () use ($rows, $children, $facilityId, $date, &$imported, &$skipped) {
                foreach ($rows as $userId => $row) {
                    $child = $children->get($userId)?->first();
                    $record = $child ? UsageRecord::where('facility_id', $facilityId)
                        ->where('child_id', $child->id)->whereDate('date', $date)
                        ->lockForUpdate()->first() : null;
                    // Missing, deleted, absent and cancelled attendance stays untouched.
                    if (!$record || $record->status !== 'attended') {
                        $skipped++;
                        continue;
                    }
                    $cancelled = DB::table('yoyaku_booking_states')->where([
                        'facility_id' => $facilityId, 'yoyaku_user_id' => $userId, 'date' => $date,
                    ])->value('cancelled');
                    if ($cancelled) {
                        $skipped++;
                        continue;
                    }
                    $record->update([
                        'pickup_done' => $row['actual_pickup_at'] !== null,
                        'dropoff_done' => $row['actual_dropoff_at'] !== null,
                    ]);
                    $imported++;
                }
                return compact('imported', 'skipped');
            });
        });
    }

    private function assertUniqueFacility(Facility $facility): void
    {
        if (Facility::where('yoyaku_business_id', $facility->yoyaku_business_id)->count() !== 1) {
            throw new \RuntimeException('Ambiguous facility integration mapping');
        }
    }
}
