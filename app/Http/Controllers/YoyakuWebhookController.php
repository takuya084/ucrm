<?php

namespace App\Http\Controllers;

use App\Models\BillingPeriod;
use App\Models\Child;
use App\Models\ContactNote;
use App\Models\Facility;
use App\Models\UsageRecord;
use App\Observers\UsageRecordObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * p-yoyaku からの booking.{created,updated,deleted} を受信し、
 * UsageRecord と同期する。
 * contact_note.{read,guardian_entry} も受信し、連絡帳の既読・家庭側記入を反映する。
 *
 * 署名: X-Yoyaku-Signature (HMAC-SHA256, body x webhook_secret)
 * 認証は HMAC のみ（Sanctum を介さない）
 */
class YoyakuWebhookController extends Controller
{
    /** sent_at の許容ずれ（秒）。これより古いペイロードはリプレイとして拒否 */
    private const REPLAY_TOLERANCE_SECONDS = 600;

    public function __invoke(Request $request)
    {
        $body      = $request->getContent();
        $data      = json_decode($body, true);
        if (!is_array($data) || array_is_list($data)) {
            return response()->json(['error' => 'invalid payload'], 400);
        }
        $event     = $data['event']       ?? null;
        $businessId= $this->positiveId($data['business_id'] ?? null);
        $payload   = $data['data']        ?? [];

        if (!is_string($event) || !$businessId || !is_array($payload) || array_is_list($payload)
            || !$this->positiveId($payload['user_id'] ?? null)) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $facility = Facility::where('yoyaku_business_id', $businessId)->first();
        if (!$facility) {
            Log::info('YoyakuWebhook: facility not found', ['business_id' => $businessId]);
            return response()->json(['ignored' => true]);
        }

        // HMAC 署名検証（secret 未設定の施設はフェイルクローズで拒否。
        // 出欠記録は請求の元データのため、未認証の書き込みは受け付けない）
        if (!$facility->yoyaku_webhook_secret) {
            Log::warning('YoyakuWebhook: webhook secret 未設定のため拒否', ['facility_id' => $facility->id]);
            return response()->json(['error' => 'webhook secret not configured'], 403);
        }

        $expected = hash_hmac('sha256', $body, $facility->yoyaku_webhook_secret);
        $received = $request->header('X-Yoyaku-Signature', '');
        if (!hash_equals($expected, $received)) {
            Log::warning('YoyakuWebhook: 署名不一致', ['facility_id' => $facility->id, 'ip' => $request->ip()]);
            return response()->json(['error' => 'invalid signature'], 401);
        }

        // Only supported event types may change records. Unknown types are not bookings.
        if (!in_array($event, ['booking.created', 'booking.updated', 'booking.deleted',
            'contact_note.read', 'contact_note.guardian_entry'], true)) {
            return response()->json(['error' => 'unsupported event'], 400);
        }

        // Carbon::parse(null/'') means now; require an explicit absolute timestamp first.
        $sentAt = $this->absoluteTimestamp($data['sent_at'] ?? null);
        if (!$sentAt || $sentAt->diffInSeconds(now(), true) > self::REPLAY_TOLERANCE_SECONDS) {
            return response()->json(['error' => 'stale or missing sent_at'], 401);
        }

        $child = Child::where('facility_id', $facility->id)
            ->where('yoyaku_user_id', $payload['user_id'] ?? 0)
            ->first();
        if (!$child) {
            return response()->json(['ignored' => 'child not linked']);
        }

        $date = $payload['date'] ?? null;
        if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return response()->json(['error' => 'date missing or invalid'], 400);
        }

        // Reject wrong scalar types before casts or database writes.
        foreach (['pickup_time', 'dropoff_time'] as $key) {
            $time = $payload[$key] ?? null;
            if ($time !== null && (!is_string($time)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time))) {
                return response()->json(['error' => 'invalid time'], 400);
            }
        }
        foreach (['actual_pickup_at', 'actual_dropoff_at'] as $key) {
            if (isset($payload[$key]) && !$this->absoluteTimestamp($payload[$key])) {
                return response()->json(['error' => 'invalid actual timestamp'], 400);
            }
        }
        foreach (['home_temperature', 'home_sleep', 'home_medication', 'home_condition', 'guardian_comment'] as $key) {
            if (isset($payload[$key]) && !is_string($payload[$key]) && !is_numeric($payload[$key])) {
                return response()->json(['error' => 'invalid contact field'], 400);
            }
        }

        // 連絡帳イベント（出欠と違い請求に影響しないため、請求ロックの対象外）
        if (str_starts_with($event, 'contact_note.')) {
            return $this->handleContactNoteEvent($event, $facility, $child, $date, $payload);
        }

        // 請求確定済みの月の出欠は変更不可（請求の根拠データ保護）。
        // 恒久的な状態のため 200 で応答し、p-yoyaku 側の無限リトライを避ける
        $yearMonth = substr($date, 0, 7);
        $period = BillingPeriod::where('facility_id', $facility->id)
            ->where('year_month', $yearMonth)
            ->first();
        if ($period?->isLocked()) {
            Log::warning('YoyakuWebhook: 請求確定済み期間のため変更を無視', [
                'facility_id' => $facility->id,
                'year_month'  => $yearMonth,
                'event'       => $event,
            ]);
            return response()->json(['ignored' => 'billing period locked']);
        }

        if ($event === 'booking.deleted') {
            // モデル経由で更新する（Auditable の監査ログを発火させるため。
            // クエリビルダの一括 update はイベントが発火しない）
            $record = UsageRecord::where('facility_id', $facility->id)
                ->where('child_id', $child->id)
                ->whereDate('date', $date)
                ->first();
            if ($record) {
                UsageRecordObserver::withoutPush(fn () => $record->update([
                    'pickup_done'  => false,
                    'dropoff_done' => false,
                ]));
            }
            return response()->json(['ok' => true]);
        }

        // created / updated（ソフトデリート済みの記録がある日はユニーク制約衝突を避けるため復活させる）
        // withoutPush: 受信内容を p-yoyaku へ送り返すエコーループを防ぐ
        UsageRecordObserver::withoutPush(function () use ($facility, $child, $date, $payload) {
            $record = UsageRecord::withTrashed()->whereDate('date', $date)->updateOrCreate(
                [
                    'facility_id' => $facility->id,
                    'child_id'    => $child->id,
                ],
                [
                    'date'           => $date,
                    'pickup_done'    => !empty($payload['pickup_time']) || !empty($payload['actual_pickup_at']),
                    'dropoff_done'   => !empty($payload['dropoff_time']) || !empty($payload['actual_dropoff_at']),
                    'check_in_time'  => $payload['pickup_time']  ?? null,
                    'check_out_time' => $payload['dropoff_time'] ?? null,
                ],
            );
            if ($record->trashed()) {
                $record->restore();
            }
        });

        return response()->json(['ok' => true]);
    }

    private function positiveId(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string) $value)) return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function absoluteTimestamp(mixed $value): ?Carbon
    {
        if (!is_string($value) || !preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/D',
            $value, $parts
        )) return null;
        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59) return null;
        if ($parts[7] !== 'Z' && ((int) substr($parts[7], 1, 2) > 23 || (int) substr($parts[7], 4, 2) > 59)) return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    /**
     * 連絡帳イベントの処理。
     * - contact_note.read: 保護者が公開済み連絡帳を開いた（既読）
     * - contact_note.guardian_entry: 保護者が家庭側記入を送信した
     *   （施設側が未作成でも下書きとして作成する＝朝の家庭記入が先行するケース）
     */
    private function handleContactNoteEvent(string $event, Facility $facility, Child $child, string $date, array $payload)
    {
        // ソフトデリート済みがあると unique(child_id, date) に当たるため withTrashed で引く
        $note = ContactNote::withTrashed()
            ->where('child_id', $child->id)
            ->whereDate('date', $date)
            ->first();

        if ($event === 'contact_note.read') {
            if (!$note || $note->trashed() || !$note->isPublished()) {
                return response()->json(['ignored' => 'note not published']);
            }
            if (!$note->read_at) {
                $note->update(['read_at' => now()]);
            }
            return response()->json(['ok' => true]);
        }

        if ($event === 'contact_note.guardian_entry') {
            if (!$note) {
                $note = new ContactNote([
                    'child_id' => $child->id,
                    'date'     => $date,
                ]);
                $note->facility_id = $facility->id;
            }
            if ($note->trashed()) {
                $note->deleted_at = null;
            }

            // ペイロードに含まれる項目だけを反映する（部分送信で既存の家庭側記入を消さない）。
            // null が明示的に送られた場合はクリア扱い
            $fields = ['guardian_submitted_at' => now()];
            $maxLengths = [
                'home_temperature' => 10,
                'home_sleep'       => 100,
                'home_medication'  => 100,
                'home_condition'   => 100,
                'guardian_comment' => 2000,
            ];
            foreach ($maxLengths as $key => $len) {
                if (array_key_exists($key, $payload)) {
                    $fields[$key] = $payload[$key] === null ? null : mb_substr((string) $payload[$key], 0, $len);
                }
            }
            $note->fill($fields);
            $note->save();

            return response()->json(['ok' => true]);
        }

        return response()->json(['ignored' => 'unknown contact_note event']);
    }
}
