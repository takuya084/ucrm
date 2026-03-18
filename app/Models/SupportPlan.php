<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'staff_id',
        'previous_plan_id',
        'plan_date',
        'valid_from',
        'valid_to',
        'long_term_goal',
        'short_term_goal',
        'support_policy',
        'program_content',
        'guardian_agreement',
        'guardian_agreement_date',
        'document_path',
    ];

    protected $casts = [
        'plan_date'               => 'date',
        'valid_from'              => 'date',
        'valid_to'                => 'date',
        'guardian_agreement'      => 'boolean',
        'guardian_agreement_date' => 'date',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(SupportPlan::class, 'previous_plan_id');
    }

    public function nextPlans(): HasMany
    {
        return $this->hasMany(SupportPlan::class, 'previous_plan_id');
    }

    // 有効期限切れ
    public function isExpired(): bool
    {
        return $this->valid_to && $this->valid_to->isPast();
    }

    // 更新期限が近い（90日以内）
    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->whereNotNull('valid_to')
            ->whereBetween('valid_to', [now(), now()->addDays($days)]);
    }

    // 保護者同意未取得
    public function scopePendingAgreement($query)
    {
        return $query->where('guardian_agreement', false);
    }
}
