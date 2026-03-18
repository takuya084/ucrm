<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailableSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'date',
        'day_of_week',
        'total_capacity',
        'booked_count',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    // 空き枠数を算出
    public function getAvailableCountAttribute(): int
    {
        return max(0, $this->total_capacity - $this->booked_count);
    }

    // 稼働率（%）を算出
    public function getOccupancyRateAttribute(): float
    {
        if ($this->total_capacity === 0) return 0;
        return round($this->booked_count / $this->total_capacity * 100, 1);
    }

    // 空き枠があるもの
    public function scopeHasAvailability($query)
    {
        return $query->whereRaw('total_capacity > booked_count');
    }

    // 特定曜日
    public function scopeByDay($query, string $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
