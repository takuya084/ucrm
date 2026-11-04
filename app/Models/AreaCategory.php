<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 地域区分マスタ（級地コード → 1単位あたりの単価）。
 * 全事業所共通（こども家庭庁告示に基づく全国統一データ）。
 */
class AreaCategory extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'code',
        'label',
        'unit_price',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];
}
