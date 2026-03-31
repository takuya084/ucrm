<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffQualification extends Model
{
    const TYPES = [
        'hoikushi'       => ['name' => '保育士',       'color' => 'blue'],
        'jidou_shidouin' => ['name' => '児童指導員',   'color' => 'green'],
        'pt'             => ['name' => 'PT',           'color' => 'purple'],
        'ot'             => ['name' => 'OT',           'color' => 'purple'],
        'st'             => ['name' => 'ST',           'color' => 'purple'],
        'shinrishi'      => ['name' => '心理士',       'color' => 'purple'],
        'kyoudo'         => ['name' => '強度行動障害', 'color' => 'orange'],
        'sougeikanou'    => ['name' => '送迎可能',     'color' => 'gray'],
    ];

    protected $fillable = ['staff_id', 'qualification'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
