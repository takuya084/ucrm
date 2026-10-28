<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureFacilityIsActive
{
    public function handle(Request $request, Closure $next)
    {
        // Account recovery/security remain available; this is a business-data gate.
        if ($request->routeIs('profile.*', 'password.*', 'verification.*', 'two-factor.*', 'account.security')) {
            return $next($request);
        }

        if (
            $request->is('/') ||
            $request->is('subscribe*') ||
            $request->is('success') ||
            $request->is('cancel') ||
            $request->is('create-checkout-session') ||
            $request->is('stripe/*') ||
            $request->is('login') ||
            $request->is('logout') ||
            $request->is('forgot-password') ||
            $request->is('reset-password/*')
        ) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Read current membership rather than a relation cached earlier in a session/worker.
        $staff = $user->staff()->with('facility')->first();
        $user->setRelation('staff', $staff);
        abort_unless($staff && $staff->is_active && $staff->facility, 403,
            '有効な職員として事業所に所属していません。管理者にお問い合わせください。');
        $facility = $staff->facility;

        if ($facility && $facility->isFree()) {
            return $next($request);
        }

        if ($facility && !$facility->is_active) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'サブスクリプションが停止中です。お支払い状況をご確認ください。');
        }

        return $next($request);
    }
}

