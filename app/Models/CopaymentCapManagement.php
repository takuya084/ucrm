<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopaymentCapManagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'year_month',
        'managing_facility_id',
        'cap_amount',
        'total_copayment',
        'adjusted_copayment',
        'management_result',
        'status',
    ];

    public const RESULT_LABELS = [
        '1' => '管理結果なし',
        '2' => '管理結果あり',
        '3' => '管理結果あり（按分）',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function managingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'managing_facility_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CopaymentCapDetail::class);
    }

    public function getResultLabelAttribute(): string
    {
        return self::RESULT_LABELS[$this->management_result] ?? $this->management_result;
    }
}
