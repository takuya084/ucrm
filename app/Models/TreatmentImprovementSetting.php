<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 処遇改善加算等の率ベース加算の設定（施設×期間×加算率）
 */
class TreatmentImprovementSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'service_code_master_id',
        'rate',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rate'           => 'decimal:2',
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
}
