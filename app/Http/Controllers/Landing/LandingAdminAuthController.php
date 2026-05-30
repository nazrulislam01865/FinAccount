<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LandingAdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('landing_admin')->check()) {
            return redirect()->route('landing-admin.dashboard');
        }

        return view('landing.admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('landing_admin')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These Landing Admin credentials do not match our records.',
            ]);
        }

        $admin = Auth::guard('landing_admin')->user();

        if (method_exists($admin, 'isActive') && ! $admin->isActive()) {
            Auth::guard('landing_admin')->logout();

            throw ValidationException::withMessages([
                'email' => 'Landing Admin account is inactive.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('landing-admin.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('landing_admin')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('landing-admin.login');
    }
}
