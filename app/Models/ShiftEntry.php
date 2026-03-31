<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_shift_id',
        'staff_id',
        'date',
        'start_time',
        'work_type',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function monthlyShift(): BelongsTo
    {
        return $this->belongsTo(MonthlyShift::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
