<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Laravelの組み込みNotificationクラスと名前が衝突しないようにNotificationMessageと命名
class NotificationMessage extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'facility_id',
        'staff_id',
        'title',
        'body',
        'type',
        'channel',
        'send_at',
        'status',
        'target_condition',
        'sent_count',
        'failed_count',
    ];

    protected $casts = [
        'send_at'          => 'datetime',
        'target_condition' => 'array',
    ];

    public const TYPE_LABELS = [
        'closure'    => '休所案内',
        'event'      => '行事案内',
        'emergency'  => '緊急連絡',
        'individual' => '個別連絡',
        'general'    => '一般連絡',
    ];

    public const CHANNEL_LABELS = [
        'email' => 'メール',
        'line'  => 'LINE',
        'both'  => 'メール＋LINE',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class, 'notification_id');
    }

    public function failedTargets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class, 'notification_id')->where('status', 'failed');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
