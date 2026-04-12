<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyServiceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'usage_record_id',
        'billing_detail_id',
        'service_code_master_id',
        'service_code',
        'units',
        'start_time',
        'end_time',
        'is_pickup',
        'is_dropoff',
        'is_extension',
        'memo',
    ];

    protected $casts = [
        'is_pickup'    => 'boolean',
        'is_dropoff'   => 'boolean',
        'is_extension' => 'boolean',
    ];

    public function usageRecord(): BelongsTo
    {
        return $this->belongsTo(UsageRecord::class);
    }

    public function billingDetail(): BelongsTo
    {
        return $this->belongsTo(BillingDetail::class);
    }

    public function serviceCodeMaster(): BelongsTo
    {
        return $this->belongsTo(ServiceCodeMaster::class);
    }
}
