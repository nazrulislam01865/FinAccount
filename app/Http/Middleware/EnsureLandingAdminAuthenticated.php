<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLandingAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('landing_admin')->user();

        if (! $admin) {
            return redirect()->route('landing-admin.login');
        }

        if (method_exists($admin, 'isActive') && ! $admin->isActive()) {
            Auth::guard('landing_admin')->logout();
            $request->session()->forget('landing_admin_auth');

            return redirect()
                ->route('landing-admin.login')
                ->withErrors(['username' => 'Landing Admin account is inactive.']);
        }

        return $next($request);
    }
}
