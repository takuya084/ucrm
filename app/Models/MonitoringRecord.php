<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'staff_id',
        'monitoring_date',
        'period_from',
        'period_to',
        'support_summary',
        'strengths',
        'challenges',
        'guardian_needs',
        'environmental_notes',
        'next_review_date',
    ];

    protected $casts = [
        'monitoring_date'  => 'date',
        'period_from'      => 'date',
        'period_to'        => 'date',
        'next_review_date' => 'date',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // 次回モニタリングが近い（30日以内）
    public function scopeDueSoon($query, int $days = 30)
    {
        return $query->whereNotNull('next_review_date')
            ->whereBetween('next_review_date', [now(), now()->addDays($days)]);
    }

    // 次回モニタリングが期限切れ
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('next_review_date')
            ->where('next_review_date', '<', now());
    }
}
