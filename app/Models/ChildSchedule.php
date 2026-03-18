<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'day_of_week',
        'start_date',
        'end_date',
        'status',
        'pickup_required',
        'memo',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'end_date'        => 'date',
        'pickup_required' => 'boolean',
    ];

    // 曜日の日本語表示
    public const DAY_LABELS = [
        'mon' => '月',
        'tue' => '火',
        'wed' => '水',
        'thu' => '木',
        'fri' => '金',
        'sat' => '土',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    // 現在有効なスケジュールのみ
    public function scopeActive($query)
    {
        return $query->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function getDayLabelAttribute(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? $this->day_of_week;
    }
}
