<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    const TYPE_LABELS = [
        'elementary'   => '小学校',
        'junior_high'  => '中学校',
        'special_needs'=> '特別支援学校',
        'other'        => 'その他',
    ];

    protected $fillable = [
        'facility_id',
        'name',
        'name_kana',
        'address',
        'school_type',
        'end_time_regular',
        'end_time_early',
        'memo',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }
}
