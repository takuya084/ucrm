<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopaymentCapDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'copayment_cap_management_id',
        'facility_id',
        'facility_name',
        'total_amount',
        'copayment_amount',
        'adjusted_amount',
        'is_managing_facility',
    ];

    protected $casts = [
        'is_managing_facility' => 'boolean',
    ];

    public function copaymentCapManagement(): BelongsTo
    {
        return $this->belongsTo(CopaymentCapManagement::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
