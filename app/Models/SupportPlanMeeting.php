<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個別支援計画の担当者会議記録
 */
class SupportPlanMeeting extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'support_plan_id',
        'held_at',
        'attendees',
        'minutes',
    ];

    protected $casts = [
        'held_at'   => 'datetime',
        'attendees' => 'array',
    ];

    public function supportPlan(): BelongsTo
    {
        return $this->belongsTo(SupportPlan::class);
    }
}
