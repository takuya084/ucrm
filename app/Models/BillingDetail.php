<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillingDetail extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'billing_period_id',
        'child_id',
        'recipient_certificate_id',
        'service_type',
        'total_days',
        'total_units',
        'unit_price_yen',
        'total_amount',
        'insurance_amount',
        'copayment_amount',
        'copayment_cap',
        'copayment_cap_applied',
        'cap_management_result_code',
        'cap_managing_facility_code',
        'cap_result_amount',
        'status',
        'adjustment_note',
        'adjusted_by',
        'adjusted_at',
    ];

    protected $casts = [
        'unit_price_yen' => 'decimal:2',
        'adjusted_at'    => 'datetime',
    ];

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function recipientCertificate(): BelongsTo
    {
        return $this->belongsTo(RecipientCertificate::class);
    }

    public function billingDetailLines(): HasMany
    {
        return $this->hasMany(BillingDetailLine::class);
    }

    public function dailyServiceRecords(): HasMany
    {
        return $this->hasMany(DailyServiceRecord::class);
    }

    public function guardianInvoice(): HasOne
    {
        return $this->hasOne(GuardianInvoice::class);
    }

    public function errorClaims(): HasMany
    {
        return $this->hasMany(ErrorClaim::class);
    }

    public function claimReturns(): HasMany
    {
        return $this->hasMany(ClaimReturn::class);
    }
}
