<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopaymentCapManagement extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $table = 'copayment_cap_managements';

    protected $fillable = [
        'child_id',
        'year_month',
        'managing_facility_id',
        'cap_amount',
        'total_copayment',
        'adjusted_copayment',
        'management_result',
        'status',
        'form_type',
        'contract_status',
        'actual_confirmed_at',
        'sent_at',
        'received_at',
        'confirmed_at',
        'remarks',
    ];

    protected $casts = [
        'actual_confirmed_at' => 'datetime',
        'sent_at'             => 'datetime',
        'received_at'         => 'datetime',
        'confirmed_at'        => 'datetime',
    ];

    public const RESULT_LABELS = [
        '1' => '管理結果なし',
        '2' => '管理結果あり',
        '3' => '管理結果あり（按分）',
    ];

    public const STATUS_LABELS = [
        'draft'     => '未作成',
        'created'   => '作成済',
        'sent'      => '送付済',
        'received'  => '受領済',
        'confirmed' => '確定済',
    ];

    public const FORM_TYPE_LABELS = [
        'paper'      => '紙',
        'electronic' => '電子',
    ];

    public const CONTRACT_STATUS_LABELS = [
        'contracted' => '契約中',
        'pending'    => '未開始',
        'terminated' => '解約',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function managingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'managing_facility_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CopaymentCapDetail::class);
    }

    public function getResultLabelAttribute(): string
    {
        return self::RESULT_LABELS[$this->management_result] ?? $this->management_result;
    }
}
