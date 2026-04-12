<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_detail_id',
        'guardian_id',
        'child_id',
        'facility_id',
        'year_month',
        'copayment_amount',
        'other_charges',
        'total_amount',
        'payment_status',
        'payment_method',
        'paid_at',
        'paid_amount',
        'due_date',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'paid_at'  => 'datetime',
        'due_date' => 'date',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'unpaid'  => '未入金',
        'paid'    => '入金済',
        'partial' => '一部入金',
        'overdue' => '滞納',
    ];

    public function billingDetail(): BelongsTo
    {
        return $this->belongsTo(BillingDetail::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
