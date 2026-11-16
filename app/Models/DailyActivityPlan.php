<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class DailyActivityPlan extends Model
{
    public const CATEGORIES = [
        'event' => '行事',
        'support' => '支援プログラム',
        'exercise' => '運動',
        'learning' => '学習',
    ];

    protected $fillable = ['program_id', 'staff_id', 'activity_date', 'category', 'title', 'starts_at', 'materials'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public static function forDate(int $facilityId, string $date): Collection
    {
        return self::where('facility_id', $facilityId)
            ->where('activity_date', $date)
            ->with('staff:id,name')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }
}
