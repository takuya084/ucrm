<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 虐待防止・身体拘束適正化の委員会/研修記録
 */
class PreventionCommittee extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'facility_id',
        'type',
        'category',
        'held_at',
        'attendees',
        'minutes',
        'document_path',
    ];

    protected $casts = [
        'held_at'   => 'datetime',
        'attendees' => 'array',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
