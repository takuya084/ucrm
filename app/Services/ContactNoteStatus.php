<?php

namespace App\Services;

use App\Models\ContactNote;

class ContactNoteStatus
{
    public static function for(?ContactNote $note): array
    {
        $homePending = $note?->guardian_submitted_at && (!$note->guardian_checked_at
            || (int) $note->guardian_checked_revision !== (int) $note->guardian_source_revision);
        $home = !$note?->guardian_submitted_at ? '家庭からの連絡なし' : ($homePending ? '家庭の連絡：未確認' : '家庭の連絡：確認済み');
        // A previous delivery timestamp must not imply the latest edit was delivered.
        $synced = $note?->yoyaku_synced_at && $note->yoyaku_synced_at->gte($note->updated_at);
        if (!$note) return ['home' => $home, 'homePending' => false, 'publication' => '連絡帳：未作成', 'delivery' => null, 'confirmation' => null];
        $published = $note->isPublished();
        $confirmation = $published && $note->publication_hash && $note->guardian_confirmed_at
            && hash_equals($note->publication_hash, (string) $note->guardian_confirmed_hash);
        return [
            'home' => $home,
            'homePending' => (bool) $homePending,
            'publication' => $published ? '公開済み' : ($note->withdrawn_at ? '公開取り下げ' : ($note->hasFacilityContent() ? '下書き' : '連絡帳：未作成')),
            'delivery' => $published ? ($synced && $note->publication_hash ? '連携完了' : '連携待ち・要確認') : ($note->withdrawn_at ? ($synced ? '取り下げ連携完了' : '取り下げ連携待ち・要確認') : null),
            'confirmation' => $published ? ($confirmation ? '保護者確認印済み' : '保護者の確認印待ち') : null,
        ];
    }
}
