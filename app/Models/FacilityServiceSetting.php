<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityServiceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'service_code_master_id',
        'is_enabled',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'is_enabled'     => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function serviceCodeMaster(): BelongsTo
    {
        return $this->belongsTo(ServiceCodeMaster::class);
    }

    /**
     * 指定年月に有効な設定のみ
     */
    public function scopeActiveAt($query, string $yearMonth)
    {
        $date = $yearMonth . '-01';
        return $query->where('is_enabled', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }
}
