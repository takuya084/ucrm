<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_id',
        'guardian_id',
        'staff_id',
        'contact_method',
        'contacted_at',
        'category',
        'subject',
        'content',
        'response',
        'status',
        'is_escalated',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'is_escalated' => 'boolean',
    ];

    public const CONTACT_METHOD_LABELS = [
        'tel'       => '電話',
        'line'      => 'LINE',
        'email'     => 'メール',
        'in_person' => '口頭',
        'other'     => 'その他',
    ];

    public const CATEGORY_LABELS = [
        'schedule'  => '日程・利用調整',
        'support'   => '支援内容',
        'billing'   => '請求・費用',
        'complaint' => '苦情・クレーム',
        'other'     => 'その他',
    ];

    public const STATUS_LABELS = [
        'open'        => '未対応',
        'in_progress' => '対応中',
        'closed'      => '完了',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', 'closed');
    }

    public function scopeEscalated($query)
    {
        return $query->where('is_escalated', true);
    }
}
