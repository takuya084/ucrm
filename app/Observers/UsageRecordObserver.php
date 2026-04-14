<?php

namespace App\Observers;

use App\Jobs\PushYoyakuBooking;
use App\Models\UsageRecord;

class UsageRecordObserver
{
    public function saved(UsageRecord $record): void
    {
        PushYoyakuBooking::dispatch($record->id);
    }

    public function deleted(UsageRecord $record): void
    {
        PushYoyakuBooking::dispatch($record->id, delete: true);
    }
}
