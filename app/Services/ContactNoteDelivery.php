<?php

namespace App\Services;

use App\Jobs\PushContactNote;
use App\Models\{Child, ContactNote, Facility};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactNoteDelivery
{
    public const REASONS = [
        'facility_unlinked' => '事業所とp-yoyakuの連携が未設定です。管理者が施設設定を確認してください。',
        'child_unlinked' => '児童とp-yoyakuの利用者が紐付いていません。管理者が連携設定を確認してください。',
        'not_configured' => '連携先または認証情報が未設定です。管理者が連携設定を確認してください。',
        'ambiguous_target' => '連携先の紐付けが重複しています。管理者が連携設定を確認してください。',
        'mapping_changed' => '送信予約後に連携設定が変わりました。管理者が現在の紐付け先を確認してから再試行してください。',
        'authentication' => 'p-yoyakuで認証されませんでした。管理者が認証情報・アクセス権限を確認してください。',
        'rejected' => 'p-yoyakuが送信内容を受け付けませんでした。管理者が児童の紐付け・送信内容を確認してください。',
        'unreachable' => 'p-yoyakuに接続できませんでした。接続の復旧後に再試行してください。',
        'remote_error' => 'p-yoyaku側でエラーが発生しました。復旧後に再試行してください。',
        'invalid_response' => 'p-yoyakuから送信完了の応答を確認できませんでした。再試行してください。',
        'delivery_failed' => '送信処理を完了できませんでした。再試行しても続く場合は管理者に確認してください。',
    ];

    public static function blockedReason(ContactNote $note): ?string
    {
        $facility = Facility::find($note->facility_id);
        if (!$facility?->yoyaku_business_id) return 'facility_unlinked';
        $child = Child::where('facility_id', $note->facility_id)->find($note->child_id);
        if (!$child?->yoyaku_user_id) return 'child_unlinked';
        if (!filled($facility->yoyaku_api_token) || !filled(config('services.houkago_plus.base_url'))) return 'not_configured';
        if (Facility::where('yoyaku_business_id', $facility->yoyaku_business_id)->count() !== 1
            || Child::where('facility_id', $note->facility_id)->where('yoyaku_user_id', $child->yoyaku_user_id)->count() !== 1) return 'ambiguous_target';
        return null;
    }

    public static function details(?ContactNote $note): ?array
    {
        if (!$note || (!$note->isPublished() && !$note->withdrawn_at)) return null;
        $pending = str_contains(ContactNoteStatus::for($note)['delivery'] ?? '', '要確認');
        if (!$pending) return ['pending'=>false, 'canRetry'=>false, 'reason'=>null, 'attemptedAt'=>self::attemptedAt($note)];
        $blocked = self::blockedReason($note);
        $code = $blocked ?? $note->delivery_error_code;
        $waiting = !$code && $note->delivery_requested_at?->gt(now()->subMinute());
        return [
            'pending'=>true, 'canRetry'=>!$blocked && !$waiting,
            'reason'=>self::REASONS[$code] ?? ($waiting
                ? '送信処理の完了待ちです。少し待って「最新の状態に更新」で確認してください。'
                : '送信完了を確認できていません。再試行しても続く場合は、管理者が送信処理の稼働状況を確認してください。'),
            'attemptedAt'=>self::attemptedAt($note),
        ];
    }

    private static function attemptedAt(ContactNote $note): ?string
    {
        return $note->delivery_attempted_at?->copy()->timezone('Asia/Tokyo')->format('Y/m/d H:i:s');
    }

    /** Caller holds the note row lock inside a transaction. No public content is changed. */
    public static function enqueue(ContactNote $note): void
    {
        DB::table('contact_notes')->where('id', $note->id)->update([
            'delivery_request_id'=>(string) Str::uuid(), 'delivery_requested_at'=>now(),
            'delivery_attempted_at'=>null, 'delivery_error_code'=>null, 'yoyaku_synced_at'=>null,
        ]);
        PushContactNote::dispatch($note->id)->afterCommit();
    }
}
