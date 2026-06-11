<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'year_month',
        'status',
        'submitted_at',
        'confirmed_by',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public const STATUS_LABELS = [
        'draft'       => '下書き',
        'calculating' => '計算中',
        'confirmed'   => '確定済',
        'submitted'   => '提出済',
        'completed'   => '完了',
        'error'       => 'エラー',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function confirmedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'confirmed_by');
    }

    public function billingDetails(): HasMany
    {
        return $this->hasMany(BillingDetail::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * 確定後（確定済・提出済・完了）は再計算による明細の破壊を許さない
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['confirmed', 'submitted', 'completed'], true);
    }
}
