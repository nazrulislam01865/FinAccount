<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountingAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isAccountActive') && ! $user->isAccountActive()) {
            $status = method_exists($user, 'accountStatusLabel') ? $user->accountStatusLabel() : 'Inactive';
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => "Your account is {$status}. Please contact the System Admin.",
            ]);
        }

        return $next($request);
    }
}
