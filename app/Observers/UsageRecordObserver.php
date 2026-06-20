<?php

namespace App\Observers;

use App\Jobs\PushYoyakuBooking;
use App\Models\UsageRecord;

class UsageRecordObserver
{
    /**
     * p-yoyaku の webhook を起点とする保存では push を抑制する
     * （受信データをそのまま送り返すエコーループ・重複予約の防止）
     */
    private static bool $suppressPush = false;

    public static function withoutPush(callable $callback): mixed
    {
        static::$suppressPush = true;
        try {
            return $callback();
        } finally {
            static::$suppressPush = false;
        }
    }

    public function saved(UsageRecord $record): void
    {
        if (static::$suppressPush) {
            return;
        }
        PushYoyakuBooking::dispatch($record->id);
    }

    public function deleted(UsageRecord $record): void
    {
        if (static::$suppressPush) {
            return;
        }
        PushYoyakuBooking::dispatch($record->id, delete: true);
    }
}
