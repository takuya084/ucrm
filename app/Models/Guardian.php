<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_kana',
        'relationship',
        'tel_primary',
        'tel_secondary',
        'email',
        'line_id',
        'address',
        'postcode',
        'preferred_contact',
        'memo',
    ];

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_guardian_relations')
            ->withPivot(['is_primary', 'is_emergency_contact', 'priority_order', 'memo'])
            ->orderByPivot('priority_order');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function notificationTargets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class);
    }
}
