<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftDayNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_shift_id',
        'date',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function monthlyShift(): BelongsTo
    {
        return $this->belongsTo(MonthlyShift::class);
    }
}
