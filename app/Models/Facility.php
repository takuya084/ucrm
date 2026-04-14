<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'facility_code',
        'service_type',
        'area_unit_price',
        'designated_date',
        'administrator_name',
        'address',
        'tel',
        'fax',
        'capacity_per_day',
        'yoyaku_business_id',
        'yoyaku_api_token',
        'yoyaku_webhook_secret',
        'billing_type',
        'is_active',
        'subscription_status',
        'subscription_ended_at',
        'stripe_checkout_session_id',
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'subscription_ended_at'  => 'datetime',
        'designated_date'        => 'date',
        'area_unit_price'        => 'decimal:2',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationMessage::class);
    }

    public function availableSlots(): HasMany
    {
        return $this->hasMany(AvailableSlot::class);
    }

    public function billingPeriods(): HasMany
    {
        return $this->hasMany(BillingPeriod::class);
    }

    public function facilityServiceSettings(): HasMany
    {
        return $this->hasMany(FacilityServiceSetting::class);
    }

    public function guardianInvoices(): HasMany
    {
        return $this->hasMany(GuardianInvoice::class);
    }

    /**
     * 無料プランの事業所かどうか
     */
    public function isFree(): bool
    {
        return $this->billing_type === 'free';
    }
}
