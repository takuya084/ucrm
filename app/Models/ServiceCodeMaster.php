<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCodeMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'revision_date',
        'service_type',
        'service_code',
        'service_name',
        'unit_count',
        'unit_type',
        'category',
        'conditions',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'revision_date' => 'date',
        'valid_from'    => 'date',
        'valid_to'      => 'date',
        'conditions'    => 'array',
    ];

    public function facilityServiceSettings(): HasMany
    {
        return $this->hasMany(FacilityServiceSetting::class);
    }

    public function dailyServiceRecords(): HasMany
    {
        return $this->hasMany(DailyServiceRecord::class);
    }

    public function billingDetailLines(): HasMany
    {
        return $this->hasMany(BillingDetailLine::class);
    }

    /**
     * 指定年月に有効なサービスコードを取得
     */
    public function scopeValidAt($query, string $yearMonth)
    {
        $date = $yearMonth . '-01';
        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }

    /**
     * サービス種別で絞り込み
     */
    public function scopeForServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * カテゴリで絞り込み
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
