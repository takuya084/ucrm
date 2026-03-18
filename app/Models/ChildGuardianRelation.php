<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildGuardianRelation extends Model
{
    use HasFactory;

    protected $table = 'child_guardian_relations';

    protected $fillable = [
        'child_id',
        'guardian_id',
        'is_primary',
        'is_emergency_contact',
        'priority_order',
        'memo',
    ];

    protected $casts = [
        'is_primary'           => 'boolean',
        'is_emergency_contact' => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
