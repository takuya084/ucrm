<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipientCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'certificate_number',
        'municipality',
        'valid_from',
        'valid_to',
        'monthly_limit',
        'copayment_rate',
        'copayment_cap_monthly',
        'is_cap_management_target',
        'cap_managing_facility_id',
        'service_type',
        'municipality_code',
        'disability_support_category',
        'issue_date',
        'status',
        'document_path',
    ];

    protected $casts = [
        'valid_from'               => 'date',
        'valid_to'                 => 'date',
        'issue_date'               => 'date',
        'is_cap_management_target' => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function capManagingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'cap_managing_facility_id');
    }

    public function billingDetails(): HasMany
    {
        return $this->hasMany(BillingDetail::class);
    }

    // 有効期限まで残り何日か
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->valid_to?->diffInDays(now(), false) * -1;
    }

    // 当月の残り利用可能回数
    public function getRemainingCountAttribute(): int
    {
        $usedCount = $this->child->monthlyUsageCount();
        return max(0, $this->monthly_limit - $usedCount);
    }

    // 有効期限切れかどうか
    public function isExpired(): bool
    {
        return $this->valid_to && $this->valid_to->isPast();
    }

    // 有効期限が近い（30日以内）かどうか
    public function isExpiringSoon(): bool
    {
        return $this->valid_to && $this->valid_to->diffInDays(now(), false) >= -30 && !$this->isExpired();
    }
}
