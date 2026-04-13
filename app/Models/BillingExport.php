<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'billing_period_id',
        'kind',
        'file_path',
        'file_name',
        'file_size',
        'included_files',
        'warnings',
        'created_by',
        'is_submitted',
        'submitted_at',
    ];

    protected $casts = [
        'included_files' => 'array',
        'warnings'       => 'array',
        'is_submitted'   => 'boolean',
        'submitted_at'   => 'datetime',
    ];

    public const KIND_LABELS = [
        'bundle'      => '複式（ZIP）',
        'billing'     => '請求明細',
        'performance' => '実績記録票',
        'cap_mgmt'    => '上限管理結果票',
    ];

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
