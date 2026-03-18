<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Child extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id',
        'school_id',
        'name',
        'name_kana',
        'gender',
        'birthdate',
        'grade',
        'disability_type',
        'disability_note',
        'allergy_note',
        'care_note',
        'pickup_required',
        'pickup_address',
        'pickup_area',
        'contract_start_date',
        'contract_end_date',
        'contract_status',
        'memo',
        'yoyaku_user_id',
    ];

    protected $casts = [
        'birthdate'           => 'date',
        'pickup_required'     => 'boolean',
        'contract_start_date' => 'date',
        'contract_end_date'   => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'child_guardian_relations')
            ->withPivot(['is_primary', 'is_emergency_contact', 'priority_order', 'memo'])
            ->orderByPivot('priority_order');
    }

    public function primaryGuardian(): BelongsToMany
    {
        return $this->guardians()->wherePivot('is_primary', true);
    }

    public function recipientCertificates(): HasMany
    {
        return $this->hasMany(RecipientCertificate::class);
    }

    public function activeRecipientCertificate(): HasOne
    {
        return $this->hasOne(RecipientCertificate::class)->where('status', 'active')->latestOfMany('valid_from');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ChildSchedule::class);
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

    public function latestSupportPlan(): HasOne
    {
        return $this->hasOne(SupportPlan::class)->latestOfMany('plan_date');
    }

    // 年齢を計算
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate?->age;
    }

    // 当月の利用回数
    public function monthlyUsageCount(string $yearMonth = null): int
    {
        $yearMonth = $yearMonth ?? now()->format('Y-m');
        return $this->usageRecords()
            ->where('status', 'attended')
            ->where('date', 'like', $yearMonth . '%')
            ->count();
    }

    // かなまたは名前で検索
    public function scopeSearch($query, ?string $keyword)
    {
        if (!$keyword) return $query;
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('name_kana', 'like', "%{$keyword}%");
        });
    }

    // 契約中の児童のみ
    public function scopeActive($query)
    {
        return $query->where('contract_status', 'active');
    }
}
