<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureFacilityIsActive
{
    public function handle(Request $request, Closure $next)
    {
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

        $facility = $user->staff->facility ?? null;

        if ($facility && !$facility->is_active) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'サブスクリプションが停止中です。お支払い状況をご確認ください。');
        }

        return $next($request);
    }
}
