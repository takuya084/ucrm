<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'facility_id',
        'staff_id',
        'date',
        'status',
        'absent_reason',
        'pickup_done',
        'dropoff_done',
        'billing_target',
        'memo',
    ];

    protected $casts = [
        'date'           => 'date',
        'pickup_done'    => 'boolean',
        'dropoff_done'   => 'boolean',
        'billing_target' => 'boolean',
    ];

    // ステータスの日本語ラベル
    public const STATUS_LABELS = [
        'attended'      => '出席',
        'absent'        => '無断欠席',
        'absent_notice' => '欠席（連絡あり）',
        'cancel'        => 'キャンセル',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function supportRecord(): HasOne
    {
        return $this->hasOne(SupportRecord::class);
    }

    // 出席のみ
    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    // 指定期間内
    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
