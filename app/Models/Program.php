<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'name',
        'category',
        'description',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // カテゴリの日本語ラベル
    public const CATEGORY_LABELS = [
        'physical'    => '運動',
        'cognitive'   => '認知・学習',
        'social'      => '社会性・SST',
        'life_skills' => '生活スキル',
        'other'       => 'その他',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProgramItem::class)->orderBy('difficulty_order')->orderBy('id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function supportRecords(): BelongsToMany
    {
        return $this->belongsToMany(SupportRecord::class, 'support_record_programs')
            ->withPivot(['duration_minutes', 'memo'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }
}
