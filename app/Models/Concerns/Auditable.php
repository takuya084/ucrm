<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * モデルの作成・更新・削除を audit_logs に自動記録する。
 *
 * 注意: Eloquent イベント経由のため、クエリビルダの一括 update/delete は
 * 記録されない。一括操作を行う箇所では AuditLog::record() を直接呼ぶこと。
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            AuditLog::record(
                'updated',
                $model,
                array_intersect_key($model->getOriginal(), $changes),
                $changes,
            );
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model);
        });
    }
}
