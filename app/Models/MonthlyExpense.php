<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'year_month',
        'category',
        'amount',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const CATEGORY_LABELS = [
        'rent'           => '家賃',
        'utilities'      => '光熱費',
        'communications' => '通信費',
        'supplies'       => '消耗品費',
        'vehicle'        => '車両・送迎費',
        'training'       => '研修費',
        'welfare'        => '福利厚生',
        'others'         => 'その他',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }
}
