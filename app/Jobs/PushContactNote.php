<?php

namespace App\Jobs;

use App\Models\ContactNote;
use App\Models\Facility;
use App\Services\YoyakuApiService;
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

    public function __construct(public int $contactNoteId) {}

    public function handle(YoyakuApiService $api): void
    {
        $note = ContactNote::with(['child', 'supportRecord.programs', 'publishedByStaff'])
            ->find($this->contactNoteId);
        if (!$note || !$note->isPublished()) return;

        $facility = Facility::find($note->facility_id);
        if (!$facility || !$facility->yoyaku_business_id) return;

        if (!$note->child?->yoyaku_user_id) {
            Log::info('PushContactNote: child not linked to yoyaku_user_id', [
                'contact_note_id' => $note->id,
            ]);
            return;
        }

        // 実施プログラム（活動名と時間のみ。詳細メモは内部情報のため送らない）
        $activities = $note->supportRecord
            ? $note->supportRecord->programs->map(fn ($p) => [
                'name'             => $p->name,
                'duration_minutes' => $p->pivot->duration_minutes,
            ])->values()->all()
            : [];

        $result = $api->upsertContactNote([
            'external_ref'     => "ucrm:{$note->id}",
            'user_id'          => (int) $note->child->yoyaku_user_id,
            'date'             => $note->date->format('Y-m-d'),
            'condition'        => $note->supportRecord?->condition,
            'activities'       => $activities,
            'meal_note'        => $note->meal_note,
            'health_note'      => $note->health_note,
            'guardian_message' => $note->guardian_message,
            'staff_name'       => $note->publishedByStaff?->name,
            'published_at'     => $note->published_at?->toIso8601String(),
        ], $facility->id);

        if ($result !== null) {
            // 同期時刻の記録のみ（個人情報の変更ではないため監査ログは発火させない）
            $note->yoyaku_synced_at = now();
            $note->saveQuietly();
        } else {
            Log::warning('PushContactNote: 配信失敗', ['contact_note_id' => $note->id]);
            $this->release(60);
        }
    }
}
