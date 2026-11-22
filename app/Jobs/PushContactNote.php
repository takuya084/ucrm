<?php

namespace App\Jobs;

use App\Models\ContactNote;
use App\Models\Facility;
use App\Services\YoyakuApiService;
use App\Services\YoyakuOutboundTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 公開済みの連絡帳を p-yoyaku へ配信する。
 * external_ref に "ucrm:{contact_note_id}" を入れて冪等性を担保。
 *
 * 送るのは保護者に公開する内容のみ。支援記録の内部の見立て
 * （課題・配慮・申し送り）は絶対にペイロードへ含めないこと。
 */
class PushContactNote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public ?array $target = null;
    public ?string $requestId = null;
    private ?int $attemptRevision = null;
    private string $failureReason = 'delivery_failed';

    public function __construct(public int $contactNoteId)
    {
        $note = ContactNote::find($contactNoteId);
        if ($note) {
            $this->requestId = $note->delivery_request_id;
            $this->target = YoyakuOutboundTarget::capture((int) $note->facility_id, (int) $note->child_id, $note->date->format('Y-m-d'));
        }
    }

    public function handle(YoyakuApiService $api): void
    {
        $this->attemptRevision = null;
        $this->failureReason = 'delivery_failed';
        try { $this->deliver($api); }
        catch (\Throwable $e) { $this->recordFailure($this->failureReason); throw $e; }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->recordFailure('delivery_failed', false);
    }

    private function recordFailure(string $code, bool $overwrite = true): void
    {
        $query = \Illuminate\Support\Facades\DB::table('contact_notes')->where('id', $this->contactNoteId)
            ->whereNull('deleted_at')->where('delivery_request_id', $this->requestId)->whereNull('yoyaku_synced_at');
        if ($this->target) $query->where('facility_id', $this->target['facility_id'])->where('child_id', $this->target['child_id']);
        if ($this->attemptRevision !== null) $query->where('delivery_revision', $this->attemptRevision);
        if (!$overwrite) $query->whereNull('delivery_error_code');
        $query->update(['delivery_error_code'=>$code]);
    }

    private function deliver(YoyakuApiService $api): void
    {
        $delivery = \Illuminate\Support\Facades\DB::transaction(function () {
            $note = ContactNote::with(['child', 'supportRecord.programs', 'publishedByStaff'])
                ->lockForUpdate()->find($this->contactNoteId);
            if (!$note || $note->delivery_request_id !== $this->requestId) return;
            $withdrawn=!$note->isPublished() && $note->withdrawn_at!==null;
            if (!$note->isPublished() && !$withdrawn) return;

            \Illuminate\Support\Facades\DB::table('contact_notes')->where('id', $note->id)
                ->update(['delivery_attempted_at'=>now(), 'delivery_error_code'=>null]);
            $note->refresh();
            try {
                if (!YoyakuOutboundTarget::assertCurrent($this->target, (int) $note->facility_id, (int) $note->child_id, $note->date->format('Y-m-d'))) {
                    $this->recordFailure(\App\Services\ContactNoteDelivery::blockedReason($note) ?? 'not_configured');
                    return;
                }
            } catch (\RuntimeException $e) { $this->failureReason = 'mapping_changed'; throw $e; }
            $facility = Facility::findOrFail($note->facility_id);

            if (!$withdrawn) {
                // A valid recipient must never receive another child's related content.
                $support = $note->supportRecord;
                if ($note->support_record_id && (!$support || (int) $support->child_id !== (int) $note->child_id
                    || $support->date->format('Y-m-d') !== $note->date->format('Y-m-d'))) {
                    throw new \RuntimeException('Contact note support record does not match its recipient');
                }
                if ($support?->usage_record_id) {
                    $usage = $support->usageRecord;
                    if (!$usage || (int) $usage->facility_id !== (int) $note->facility_id
                        || (int) $usage->child_id !== (int) $note->child_id
                        || $usage->date->format('Y-m-d') !== $note->date->format('Y-m-d')) {
                        throw new \RuntimeException('Contact note attendance does not match its recipient');
                    }
                }
                if (($note->published_by && (!$note->publishedByStaff
                        || (int) $note->publishedByStaff->facility_id !== (int) $note->facility_id))
                    || ($support && $support->programs->contains(fn ($program) => (int) $program->facility_id !== (int) $note->facility_id))) {
                    throw new \RuntimeException('Contact note related content belongs to another facility');
                }

                // 実施プログラム（活動名と時間のみ。詳細メモは内部情報のため送らない）
                $activities = $note->supportRecord
                    ? $note->supportRecord->programs->sortBy('id')->map(fn ($p) => [
                        'name'             => $p->name,
                        'duration_minutes' => $p->pivot->duration_minutes,
                    ])->values()->all()
                    : [];

            }

            // 本文のスナップショットと世代の採番を同じ行ロック内で確定する。
            $revision=(int)$note->delivery_revision+1;
            $this->attemptRevision = $revision;
            if ($revision>9007199254740991) throw new \RuntimeException('Contact delivery revision exhausted');
            // 配信管理情報だけの更新なので個人情報の監査イベントは発火させない。
            \Illuminate\Support\Facades\DB::table('contact_notes')->where('id',$note->id)->update(['delivery_revision'=>$revision]);
            $note->delivery_revision=$revision;
            $note->syncOriginalAttribute('delivery_revision');
            $payload=[
                'revision' => $revision,
                'external_ref'     => "ucrm:{$note->id}",
                'user_id'          => $this->target['user_id'],
                'date'             => $note->date->format('Y-m-d'),
                'withdrawn' => $withdrawn,
                'published_at' => $withdrawn ? null : $note->published_at?->toIso8601String(),
            ];
            if (!$withdrawn) $payload += [
                'condition'        => $note->supportRecord?->condition,
                'activities'       => $activities,
                'meal_note'        => $note->meal_note,
                'health_note'      => $note->health_note,
                'guardian_message' => $note->guardian_message,
                'staff_name'       => $note->publishedByStaff?->name,
            ];
            $hash=$note->currentPublicationHash();
            if ($note->publication_hash!==$hash) {
                $note->guardian_confirmed_at=null;
                $note->guardian_confirmed_hash=null;
            }
            $note->publication_hash=$hash;
            $note->saveQuietly();
            return compact('note','facility','payload');
        });
        if (!$delivery) return;
        ['note'=>$note,'facility'=>$facility,'payload'=>$payload]=$delivery;
        // 通信中はDBロックを保持しない。遅れて到着した本文は受信側の世代で拒否する。
        try { $result=$api->upsertContactNote($payload,$facility->id,$this->target['token_hash']); }
        catch (\RuntimeException $e) { $this->failureReason = 'mapping_changed'; throw $e; }

        if ($result !== null) {
            // 同期時刻の記録のみ（個人情報の変更ではないため監査ログは発火させない）
            try { YoyakuOutboundTarget::assertCurrent($this->target, (int) $note->facility_id, (int) $note->child_id, $note->date->format('Y-m-d')); }
            catch (\RuntimeException $e) { $this->failureReason = 'mapping_changed'; throw $e; }
            // 編集・公開状態変更・移動・削除があったスナップショットは同期済みにしない。
            ContactNote::whereKey($note->id)->where($note->getRawOriginal())
                ->update(['yoyaku_synced_at' => now(), 'delivery_error_code'=>null]);
        } else {
            $this->recordFailure($api->contactNoteDeliveryError() ?? 'delivery_failed');
            Log::warning('PushContactNote: 配信失敗', ['contact_note_id' => $note->id]);
            $this->release(60);
        }
    }
}
