<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'billing_detail_id',
        'original_year_month',
        'child_id',
        'claim_type',
        'reason',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public const TYPE_LABELS = [
        'full_cancel'        => '全額取消',
        'partial_correction' => '一部修正',
    ];

    public const STATUS_LABELS = [
        'draft'     => '下書き',
        'submitted' => '提出済',
        'accepted'  => '受理',
        'rejected'  => '却下',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function billingDetail(): BelongsTo
    {
        return $this->belongsTo(BillingDetail::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
