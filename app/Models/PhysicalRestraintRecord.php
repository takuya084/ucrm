<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 身体拘束の記録（やむを得ず実施した場合の態様・時間・理由の記録義務）
 */
class PhysicalRestraintRecord extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'child_id',
        'staff_id',
        'occurred_at',
        'duration_minutes',
        'method',
        'reason',
        'guardian_notified_at',
    ];

    protected $casts = [
        'occurred_at'          => 'datetime',
        'guardian_notified_at' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
