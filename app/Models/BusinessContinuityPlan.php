<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 業務継続計画（BCP: 感染症・災害。未策定は減算対象）
 */
class BusinessContinuityPlan extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'facility_id',
        'type',
        'document_path',
        'established_at',
        'last_reviewed_at',
        'last_training_at',
    ];

    protected $casts = [
        'established_at'   => 'date',
        'last_reviewed_at' => 'date',
        'last_training_at' => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
