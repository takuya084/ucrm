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
        'is_active',
        'joined_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'date',
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
