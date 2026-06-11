<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個別支援計画への保護者同意・交付記録（電子署名対応）
 *
 * 電子同意の場合は signature_data（署名画像）・signed_ip・document_hash
 * （同意時点の計画書PDFのSHA-256）を保存し、改ざん検知と本人性の証跡とする。
 */
class SupportPlanConsent extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'support_plan_id',
        'guardian_id',
        'consented_at',
        'method',
        'signature_data',
        'signed_ip',
        'document_hash',
        'delivered_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $hidden = [
        'signature_data',
    ];

    public function supportPlan(): BelongsTo
    {
        return $this->belongsTo(SupportPlan::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
