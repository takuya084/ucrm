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
        'address',
        'tel',
        'capacity_per_day',
        'yoyaku_business_id',
        'billing_type',
        'is_active',
        'subscription_status',
        'subscription_ended_at',
        'stripe_checkout_session_id',
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscription_ended_at' => 'datetime',
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
}
