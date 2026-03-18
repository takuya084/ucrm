<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'usage_record_id',
        'staff_id',
        'date',
        'condition',
        'behavior_note',
        'achievement_note',
        'challenge_note',
        'care_note',
        'next_action',
        'is_shared_with_guardian',
    ];

    protected $casts = [
        'date'                    => 'date',
        'is_shared_with_guardian' => 'boolean',
    ];

    // コンディションの日本語ラベル
    public const CONDITION_LABELS = [
        'good'   => '良好',
        'normal' => '普通',
        'poor'   => '不調',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function usageRecord(): BelongsTo
    {
        return $this->belongsTo(UsageRecord::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'support_record_programs')
            ->withPivot(['duration_minutes', 'memo', 'selected_item_ids'])
            ->withTimestamps();
    }

    // 保護者共有分のみ
    public function scopeSharedWithGuardian($query)
    {
        return $query->where('is_shared_with_guardian', true);
    }

    // 課題記録があるもの
    public function scopeHasChallenge($query)
    {
        return $query->whereNotNull('challenge_note')->where('challenge_note', '!=', '');
    }

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITION_LABELS[$this->condition] ?? $this->condition;
    }
}
