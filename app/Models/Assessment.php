<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * アセスメント記録（個別支援計画作成プロセスの起点）
 */
class Assessment extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'child_id',
        'staff_id',
        'assessed_at',
        'physical_condition',
        'living_environment',
        'child_intention',
        'guardian_intention',
        'five_domains',
        'notes',
    ];

    protected $casts = [
        'assessed_at'  => 'date',
        'five_domains' => 'array',
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
