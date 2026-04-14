<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Facility;
use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * p-yoyaku からの booking.{created,updated,deleted} を受信し、
 * UsageRecord と同期する。
 *
 * 署名: X-Yoyaku-Signature (HMAC-SHA256, body x webhook_secret)
 * 認証は HMAC のみ（Sanctum を介さない）
 */
class YoyakuWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $body      = $request->getContent();
        $data      = json_decode($body, true);
        $event     = $data['event']       ?? null;
        $businessId= (int) ($data['business_id'] ?? 0);
        $payload   = $data['data']        ?? [];

        if (!$event || !$businessId) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $facility = Facility::where('yoyaku_business_id', $businessId)->first();
        if (!$facility) {
            Log::info('YoyakuWebhook: facility not found', ['business_id' => $businessId]);
            return response()->json(['ignored' => true]);
        }

        // HMAC 署名検証
        if ($facility->yoyaku_webhook_secret) {
            $expected = hash_hmac('sha256', $body, $facility->yoyaku_webhook_secret);
            $received = $request->header('X-Yoyaku-Signature', '');
            if (!hash_equals($expected, $received)) {
                return response()->json(['error' => 'invalid signature'], 401);
            }
        }

        $child = Child::where('facility_id', $facility->id)
            ->where('yoyaku_user_id', $payload['user_id'] ?? 0)
            ->first();
        if (!$child) {
            return response()->json(['ignored' => 'child not linked']);
        }

        $date = $payload['date'] ?? null;
        if (!$date) {
            return response()->json(['error' => 'date missing'], 400);
        }

        if ($event === 'booking.deleted') {
            UsageRecord::where('facility_id', $facility->id)
                ->where('child_id', $child->id)
                ->whereDate('date', $date)
                ->update(['pickup_done' => false, 'dropoff_done' => false]);
            return response()->json(['ok' => true]);
        }

        // created / updated
        UsageRecord::updateOrCreate(
            [
                'facility_id' => $facility->id,
                'child_id'    => $child->id,
                'date'        => $date,
            ],
            [
                'pickup_done'    => !empty($payload['pickup_time']) || !empty($payload['actual_pickup_at']),
                'dropoff_done'   => !empty($payload['dropoff_time']) || !empty($payload['actual_dropoff_at']),
                'check_in_time'  => $payload['pickup_time']  ?? null,
                'check_out_time' => $payload['dropoff_time'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }
}
