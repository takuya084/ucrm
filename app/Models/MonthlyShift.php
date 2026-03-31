<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'year',
        'month',
        'status',
        'created_by',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ShiftEntry::class);
    }

    public function dayNotes(): HasMany
    {
        return $this->hasMany(ShiftDayNote::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
