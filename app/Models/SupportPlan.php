<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportPlan extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\Auditable;

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
        'planned_start_time',
        'planned_end_time',
        'planned_duration_minutes',
        'five_domains',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'plan_date'               => 'date',
        'valid_from'              => 'date',
        'valid_to'                => 'date',
        'guardian_agreement'      => 'boolean',
        'guardian_agreement_date' => 'date',
        'five_domains'            => 'array',
        'approved_at'             => 'datetime',
    ];

    public function meetings(): HasMany
    {
        return $this->hasMany(SupportPlanMeeting::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(SupportPlanConsent::class);
    }

    public function approvedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

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
