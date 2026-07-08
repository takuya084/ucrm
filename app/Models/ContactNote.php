<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 連絡帳（児童×日付で1件）
 *
 * 支援記録（SupportRecord）が「正」の内部記録で、連絡帳はそこから派生する
 * 保護者向けの表現層。保護者に見せる内容だけをこのモデルに保持する。
 * 内部の見立て（課題・配慮・申し送り）は support_records 側にのみ書くこと。
 */
class ContactNote extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'facility_id',
        'child_id',
        'support_record_id',
        'staff_id',
        'date',
        'meal_note',
        'health_note',
        'guardian_message',
        'five_domain_tags',
        'goal_progress',
        'status',
        'published_at',
        'published_by',
        'yoyaku_synced_at',
        'read_at',
        'home_temperature',
        'home_sleep',
        'home_medication',
        'home_condition',
        'guardian_comment',
        'guardian_submitted_at',
    ];

    protected $casts = [
        'date'                  => 'date',
        'five_domain_tags'      => 'array',
        'published_at'          => 'datetime',
        'yoyaku_synced_at'      => 'datetime',
        'read_at'               => 'datetime',
        'guardian_submitted_at' => 'datetime',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT     => '下書き',
        self::STATUS_PUBLISHED => '公開済み',
    ];

    // 短期目標への手応え（モニタリング集計用の軽い評価）
    public const GOAL_PROGRESS_LABELS = [
        'achieved'  => '◎ 手応えあり',
        'partial'   => '○ まずまず',
        'difficult' => '△ 難しかった',
    ];

    // R6基準の5領域（SupportPlan.five_domains のキーと揃える）
    public const FIVE_DOMAIN_LABELS = [
        'health_life'            => '健康・生活',
        'motor_sensory'          => '運動・感覚',
        'cognition_behavior'     => '認知・行動',
        'language_communication' => '言語・コミュニケーション',
        'social_relations'       => '人間関係・社会性',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function supportRecord(): BelongsTo
    {
        return $this->belongsTo(SupportRecord::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function publishedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // 家庭側の記入があるもの
    public function scopeHasGuardianEntry($query)
    {
        return $query->whereNotNull('guardian_submitted_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getGoalProgressLabelAttribute(): ?string
    {
        return $this->goal_progress ? (self::GOAL_PROGRESS_LABELS[$this->goal_progress] ?? $this->goal_progress) : null;
    }

    /**
     * 施設側記入欄に何か書かれているか（空の下書きを公開させないためのガード）
     */
    public function hasFacilityContent(): bool
    {
        return filled($this->guardian_message) || filled($this->meal_note) || filled($this->health_note);
    }
}
