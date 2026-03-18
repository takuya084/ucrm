<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    // ログインユーザーのロールを取得（staffが未登録の場合は null）
    public function getStaffRoleAttribute(): ?string
    {
        return $this->staff?->role;
    }

    // 権限チェックヘルパー
    public function isAdmin(): bool
    {
        return $this->staff?->role === 'admin';
    }

    public function isLeaderOrAbove(): bool
    {
        return in_array($this->staff?->role, ['admin', 'leader']);
    }

    public function isActiveStaff(): bool
    {
        return $this->staff?->is_active === true;
    }
}
