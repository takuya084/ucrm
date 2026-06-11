<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 安全計画（児童福祉法施行規則・令和5年4月義務化）
 */
class SafetyPlan extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'facility_id',
        'fiscal_year',
        'document_path',
        'established_at',
        'last_reviewed_at',
    ];

    protected $casts = [
        'established_at'   => 'date',
        'last_reviewed_at' => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
