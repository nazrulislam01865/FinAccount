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
}
