<?php

namespace App\Jobs;

use App\Models\Child;
use App\Models\Facility;
use App\Models\UsageRecord;
use App\Services\YoyakuApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * UsageRecord を p-yoyaku の予約として push する。
 * external_ref に "ucrm:{usage_record_id}" を入れて冪等性を担保。
 */
class PushYoyakuBooking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $usageRecordId, public bool $delete = false) {}

    public function handle(YoyakuApiService $api): void
    {
        // deleted observer から dispatch された時点でソフトデリート済みのため withTrashed で引く
        $ur = UsageRecord::withTrashed()->find($this->usageRecordId);
        if (!$ur) return;

        $facility = Facility::find($ur->facility_id);
        if (!$facility || !$facility->yoyaku_business_id) return;

        $child = Child::find($ur->child_id);
        if (!$child || !$child->yoyaku_user_id) {
            Log::info('PushYoyakuBooking: child not linked to yoyaku_user_id', [
                'usage_record_id' => $ur->id,
            ]);
            return;
        }

        $payload = [
            'external_ref' => "ucrm:{$ur->id}",
            'user_id'      => (int) $child->yoyaku_user_id,
            'date'         => (string) $ur->date->format('Y-m-d'),
            'pickup_time'  => $ur->pickup_done ? ($ur->check_in_time ?: null) : null,
            'dropoff_time' => $ur->dropoff_done ? ($ur->check_out_time ?: null) : null,
        ];

        if ($this->delete || $ur->trashed() || in_array($ur->status, ['absent', 'absent_notice', 'cancel'])) {
            // 欠席/キャンセルは予約を消す扱い（external_ref 経由で再 upsert を潰す運用も可）
            $api->createBooking($payload + ['pickup_time' => null, 'dropoff_time' => null], $facility->id);
            return;
        }

        $api->createBooking($payload, $facility->id);
    }
}
