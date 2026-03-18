<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        // ─── ロール定義 ───────────────────────────────────────
        // admin: 全操作可能
        Gate::define('admin', fn ($user) => $user->isAdmin());

        // leader-or-above: admin + 児童発達支援管理責任者
        Gate::define('leader-or-above', fn ($user) => $user->isLeaderOrAbove());

        // active-staff: 有効な職員（全ロール共通の基本チェック）
        Gate::define('active-staff', fn ($user) => $user->isActiveStaff());

        // ─── 機能別ゲート ─────────────────────────────────────
        // 児童・保護者の登録・編集
        Gate::define('manage-children', fn ($user) => $user->isLeaderOrAbove());

        // 支援記録の入力（staff以上）
        Gate::define('write-support-record', fn ($user) => $user->isActiveStaff());

        // モニタリング・支援計画（leader以上）
        Gate::define('manage-support-plan', fn ($user) => $user->isLeaderOrAbove());

        // 通知の送信（leader以上）
        Gate::define('send-notification', fn ($user) => $user->isLeaderOrAbove());

        // 新規受け入れ判断（admin のみ）
        Gate::define('manage-intake', fn ($user) => $user->isAdmin());

        // マスタ管理（プログラム・職員・事業所）
        Gate::define('manage-masters', fn ($user) => $user->isAdmin());
    }
}
