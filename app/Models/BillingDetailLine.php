<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDetailLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_detail_id',
        'service_code_master_id',
        'service_code',
        'service_name',
        'count',
        'units_per_count',
        'total_units',
    ];

    public function billingDetail(): BelongsTo
    {
        return $this->belongsTo(BillingDetail::class);
    }

    public function serviceCodeMaster(): BelongsTo
    {
        return $this->belongsTo(ServiceCodeMaster::class);
    }
}
