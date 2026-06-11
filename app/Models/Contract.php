<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 契約情報（国保連明細書の契約欄: 契約支給量・契約日・事業者記入欄番号）
 */
class Contract extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'child_id',
        'facility_id',
        'recipient_certificate_id',
        'contracted_amount',
        'contract_start_date',
        'contract_end_date',
        'record_number',
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date'   => 'date',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function recipientCertificate(): BelongsTo
    {
        return $this->belongsTo(RecipientCertificate::class);
    }
}
