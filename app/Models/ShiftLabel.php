<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'name',
        'is_off',
        'display_order',
    ];

    protected $casts = [
        'is_off' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function scopeOff($query)
    {
        return $query->where('is_off', true);
    }

    public function scopeWork($query)
    {
        return $query->where('is_off', false);
    }
}
