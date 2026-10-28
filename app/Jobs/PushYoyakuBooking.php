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
        // Ignore a queued deletion flag: a later explicit restore may already exist.
        // Allocate/persist the revision before HTTP so concurrent, out-of-order deliveries
        // are comparable at the receiver. Never hold the row lock during HTTP.
        $snapshot=\Illuminate\Support\Facades\DB::transaction(function () {
            $ur=UsageRecord::withTrashed()->whereKey($this->usageRecordId)->lockForUpdate()->first();
            if (!$ur) return null;
            $facility=Facility::find($ur->facility_id);
            $child=Child::where('facility_id',$ur->facility_id)->find($ur->child_id);
            if (!$facility?->yoyaku_business_id || !$child?->yoyaku_user_id) return null;
            $received=\Illuminate\Support\Facades\DB::table('yoyaku_booking_states')->where([
                'facility_id'=>$facility->id,'yoyaku_user_id'=>(int)$child->yoyaku_user_id,'date'=>$ur->date->format('Y-m-d'),
            ])->first();
            $cancelled=($received?->cancelled && !$ur->pickup_done && !$ur->dropoff_done) || $ur->trashed() || in_array($ur->status,['absent','absent_notice','cancel'],true);
            $revision=(int)$ur->yoyaku_sync_revision+1;
            UsageRecord::withTrashed()->whereKey($ur->id)->update(['yoyaku_sync_revision'=>$revision]);
            $time=static function ($value): ?string {
                if ($value===null || $value==='') return null;
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D',(string)$value)) {
                    throw new \RuntimeException('Invalid booking time');
                }
                return substr((string)$value,0,5);
            };
            return [$facility->id,[
                'external_ref'=>"ucrm:{$ur->id}", 'revision'=>$revision,
                'user_id'=>(int)$child->yoyaku_user_id,'date'=>$ur->date->format('Y-m-d'),
                'cancelled'=>$cancelled,
                'expected_cancellation_token'=>$ur->yoyaku_cancellation_token ?? $received?->cancellation_token,
                'pickup_time'=>!$cancelled && $ur->pickup_done ? $time($ur->check_in_time):null,
                'dropoff_time'=>!$cancelled && $ur->dropoff_done ? $time($ur->check_out_time):null,
            ]];
        });
        if ($snapshot) {
            $token=$api->syncBooking($snapshot[1],$snapshot[0]);
            UsageRecord::withTrashed()->whereKey($this->usageRecordId)
                ->where('yoyaku_sync_revision',$snapshot[1]['revision'])
                ->update(['yoyaku_cancellation_token'=>$token]);
        }
    }
}
