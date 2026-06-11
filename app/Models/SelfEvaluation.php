<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 自己評価結果の公表記録（未公表は減算対象）
 */
class SelfEvaluation extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'facility_id',
        'fiscal_year',
        'guardian_survey_at',
        'published_at',
        'published_url',
        'document_path',
    ];

    protected $casts = [
        'guardian_survey_at' => 'date',
        'published_at'       => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
