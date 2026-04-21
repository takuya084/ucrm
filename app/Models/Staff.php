<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'facility_id',
        'name',
        'role',
        'employment_type',
        'monthly_salary',
        'hourly_wage',
        'is_active',
        'display_order',
        'joined_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'joined_at'      => 'date',
        'monthly_salary' => 'decimal:2',
        'hourly_wage'    => 'decimal:2',
    ];

    public const EMPLOYMENT_TYPE_LABELS = [
        'full_time' => '常勤',
        'part_time' => 'パート',
        'contract'  => '契約',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function supportRecords(): HasMany
    {
        return $this->hasMany(SupportRecord::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function monitoringRecords(): HasMany
    {
        return $this->hasMany(MonitoringRecord::class);
    }

    public function supportPlans(): HasMany
    {
        return $this->hasMany(SupportPlan::class);
    }

    public function workPatterns(): HasMany
    {
        return $this->hasMany(StaffWorkPattern::class);
    }

    public function shiftEntries(): HasMany
    {
        return $this->hasMany(ShiftEntry::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(StaffQualification::class);
    }

    public function hasQualification(string $code): bool
    {
        return $this->qualifications->contains('qualification', $code);
    }

    // 管理者かどうか
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // 児童発達支援管理責任者かどうか
    public function isLeader(): bool
    {
        return in_array($this->role, ['admin', 'leader']);
    }
}
