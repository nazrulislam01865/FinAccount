<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('landing-admin*')) {
            return $this->handleLandingAdminSession($request, $next);
        }

        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.inactive_timeout', env('SESSION_INACTIVE_TIMEOUT', 15));

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('last_activity_at', time());

        if ((time() - $lastActivity) > ($timeoutMinutes * 60)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your session expired due to inactivity. Please log in again.',
            ]);
        }

        $request->session()->put('last_activity_at', time());

        return $next($request);
    }

    private function handleLandingAdminSession(Request $request, Closure $next): Response
    {
        if (! Auth::guard('landing_admin')->check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.landing_admin_inactive_timeout', env('LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT', 15));

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('landing_admin_last_activity_at', time());

        if ((time() - $lastActivity) > ($timeoutMinutes * 60)) {
            Auth::guard('landing_admin')->logout();
            $request->session()->forget('landing_admin_last_activity_at');
            $request->session()->regenerateToken();

            return redirect()->route('landing-admin.login')->withErrors([
                'username' => 'Your Landing Admin session expired due to inactivity. Please log in again.',
            ]);
        }

        $request->session()->put('landing_admin_last_activity_at', time());

        return $next($request);
    }
}
