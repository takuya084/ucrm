<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalFacility extends Model
{
    use HasFactory;

    public const SERVICE_TYPE_LABELS = [
        'after_school'     => '放課後等デイサービス',
        'child_development' => '児童発達支援',
        'visit_support'    => '保育所等訪問支援',
        'home_visit'       => '居宅訪問型児童発達支援',
        'other'            => 'その他',
    ];

    public const SATELLITE_TYPE_LABELS = [
        'main'      => '本体',
        'satellite' => 'サテライト',
    ];

    protected $fillable = [
        'facility_id',
        'service_type',
        'facility_number',
        'name',
        'name_kana',
        'satellite_type',
        'phone',
        'fax',
        'postal_code',
        'address',
        'notes',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function getServiceTypeLabelAttribute(): string
    {
        return self::SERVICE_TYPE_LABELS[$this->service_type] ?? $this->service_type;
    }

    public function getSatelliteTypeLabelAttribute(): string
    {
        return self::SATELLITE_TYPE_LABELS[$this->satellite_type] ?? $this->satellite_type;
    }
}
