<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'billing_detail_id',
        'year_month',
        'child_id',
        'return_code',
        'return_reason',
        'original_amount',
        'status',
        'resubmitted_billing_detail_id',
        'received_at',
        'remarks',
        'resubmitted_at',
        'resolved_at',
    ];

    protected $casts = [
        'received_at'    => 'date',
        'resubmitted_at' => 'date',
        'resolved_at'    => 'date',
    ];

    public const RETURN_CODE_PRESETS = [
        'E001' => '受給者証の有効期間外',
        'E005' => '支給量超過',
        'E010' => '重複請求',
        'E012' => '事業所番号不一致',
        'E015' => 'サービス提供実績なし',
        'E020' => '単価・単位数の誤り',
        'E030' => '上限管理結果票の不整合',
        'E050' => '受給者番号の誤り',
        'OTHER' => 'その他',
    ];

    public const STATUS_LABELS = [
        'returned'     => '返戻',
        'resubmitting' => '再請求準備中',
        'resubmitted'  => '再請求済',
        'resolved'     => '解決済',
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

    public function resubmittedBillingDetail(): BelongsTo
    {
        return $this->belongsTo(BillingDetail::class, 'resubmitted_billing_detail_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
