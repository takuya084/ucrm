<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 欠席時対応の記録（欠席時対応加算の算定要件となる相談援助の記録）
 */
class AbsenceSupport extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'usage_record_id',
        'staff_id',
        'contacted_at',
        'contact_method',
        'support_content',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    public function usageRecord(): BelongsTo
    {
        return $this->belongsTo(UsageRecord::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
