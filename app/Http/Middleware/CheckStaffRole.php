<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CheckStaffRole
{
    /**
     * ロールベースのアクセス制御ミドルウェア
     *
     * 使い方（routes/web.php）:
     *   ->middleware('role:admin')
     *   ->middleware('role:leader-or-above')
     *   ->middleware('role:active-staff')
     */
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->isActiveStaff()) {
            abort(403, 'アカウントが無効です。管理者にお問い合わせください。');
        }

        if (!Gate::allows($role)) {
            abort(403, 'この操作を行う権限がありません。');
        }

        return $next($request);
    }
}
