<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        $this->ensureIsNotRateLimited($request);

        if (! Auth::guard('landing_admin')->attempt($credentials, $request->boolean('remember'))) {
            $this->hitLoginLimiter($request);

            throw ValidationException::withMessages([
                'email' => 'These Landing Admin credentials do not match our records.',
            ]);
        }

        $admin = Auth::guard('landing_admin')->user();

        if (method_exists($admin, 'isActive') && ! $admin->isActive()) {
            Auth::guard('landing_admin')->logout();
            $this->hitLoginLimiter($request);

            throw ValidationException::withMessages([
                'email' => 'Landing Admin account is inactive.',
            ]);
        }

        if ($this->loginRateLimitEnabled()) {
            RateLimiter::clear($this->throttleKey($request));
        }

        $request->session()->regenerate();
        $request->session()->put('landing_admin_last_activity_at', time());

        return redirect()->intended(route('landing-admin.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('landing_admin')->logout();

        $request->session()->forget('landing_admin_last_activity_at');
        $request->session()->regenerateToken();

        return redirect()->route('landing-admin.login');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! $this->loginRateLimitEnabled()) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts())) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));
        $this->flashLockoutCountdown($request, $seconds);

        throw ValidationException::withMessages([
            'email' => 'Too many Landing Admin login attempts. Please try again in ' . $this->formatSeconds($seconds) . '.',
        ]);
    }

    private function hitLoginLimiter(Request $request): void
    {
        if (! $this->loginRateLimitEnabled()) {
            return;
        }

        RateLimiter::hit($this->throttleKey($request), $this->lockSeconds());

        if (RateLimiter::retriesLeft($this->throttleKey($request), $this->maxAttempts()) <= 0) {
            $this->flashLockoutCountdown($request, RateLimiter::availableIn($this->throttleKey($request)));
        }
    }

    private function throttleKey(Request $request): string
    {
        $strategy = (string) config('security.rate_limits.landing_admin_login.key_strategy', 'email_ip');
        $email = Str::lower((string) $request->input('email', 'guest'));
        $ip = (string) ($request->ip() ?: 'unknown');

        return Str::transliterate(match ($strategy) {
            'email' => 'landing-admin-login|email|' . $email,
            'ip' => 'landing-admin-login|ip|' . $ip,
            'global' => 'landing-admin-login|global',
            default => 'landing-admin-login|email-ip|' . $email . '|' . $ip,
        });
    }

    private function loginRateLimitEnabled(): bool
    {
        return (bool) config('security.rate_limits.landing_admin_login.enabled', true);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('security.rate_limits.landing_admin_login.max_attempts', 5));
    }

    private function lockSeconds(): int
    {
        return max(60, (int) config('security.rate_limits.landing_admin_login.lock_minutes', 120) * 60);
    }

    private function flashLockoutCountdown(Request $request, int $seconds): void
    {
        $request->session()->flash('landing_admin_lockout_seconds', max(1, $seconds));
        $request->session()->flash('landing_admin_lockout_until', now()->addSeconds(max(1, $seconds))->timestamp);
    }

    private function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d hour%s %d minute%s', $hours, $hours === 1 ? '' : 's', $minutes, $minutes === 1 ? '' : 's');
        }

        if ($minutes > 0) {
            return sprintf('%d minute%s %d second%s', $minutes, $minutes === 1 ? '' : 's', $remainingSeconds, $remainingSeconds === 1 ? '' : 's');
        }

        return sprintf('%d second%s', $remainingSeconds, $remainingSeconds === 1 ? '' : 's');
    }
}
