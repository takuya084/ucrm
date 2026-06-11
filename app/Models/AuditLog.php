<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * 監査ログ（個人情報保護法の安全管理措置・実地指導対応）
 *
 * 個人情報を含むレコードの作成・変更・削除・閲覧・出力を記録する。
 * アプリケーションからは追記のみ行い、更新・削除はしない。
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /** 記録から除外する属性（秘匿情報・ノイズ） */
    private const EXCLUDED_ATTRIBUTES = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 監査ログを1件記録する。
     * ログ記録の失敗で業務処理を止めないため、例外は握りつぶしてエラーログに残す。
     */
    public static function record(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        try {
            static::create([
                'user_id'        => auth()->id(),
                'facility_id'    => auth()->user()?->staff?->facility_id,
                'action'         => $action,
                'auditable_type' => $model ? $model::class : null,
                'auditable_id'   => $model?->getKey(),
                'old_values'     => static::filterValues($oldValues),
                'new_values'     => static::filterValues($newValues),
                'ip_address'     => request()?->ip(),
                'user_agent'     => mb_substr((string) request()?->userAgent(), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditLog: 記録に失敗', ['action' => $action, 'message' => $e->getMessage()]);
        }
    }

    private static function filterValues(?array $values): ?array
    {
        if (!$values) {
            return null;
        }

        return array_diff_key($values, array_flip(self::EXCLUDED_ATTRIBUTES)) ?: null;
    }
}
