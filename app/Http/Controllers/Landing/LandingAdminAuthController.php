<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Support\ActiveLoginSession;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LandingAdminAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::guard('landing_admin')->check()) {
            return redirect()->route('landing-admin.dashboard');
        }

        $logoutNotice = '';
        if (! $request->expectsJson() && ! $request->ajax()) {
            $logoutNotice = trim((string) $request->session()->pull('landing_admin_logout_notice', ''));

            if ($logoutNotice === '' && app(ActiveLoginSession::class)->consumeReplacement($request)) {
                $logoutNotice = 'You were logged out because a newer login used the same Landing Admin account. The newer user is now signed in.';
            }

            if ($logoutNotice === '' && $request->query('reason') === 'session-replaced') {
                $logoutNotice = 'You were logged out because this Landing Admin account was signed in on another device or browser. Only one active login is allowed per user.';
            }
        }

        return view('landing.admin.login', [
            'logoutNotice' => $logoutNotice,
        ]);
    }

    public function store(Request $request, ActiveLoginSession $activeLoginSession): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $credentials['username'] = Str::lower(trim($credentials['username']));

        $this->ensureIsNotRateLimited($request);

        if (! Auth::guard('landing_admin')->attempt($credentials, $request->boolean('remember'))) {
            $this->hitLoginLimiter($request);

            throw ValidationException::withMessages([
                'username' => 'These Landing Admin credentials do not match our records.',
            ]);
        }

        $admin = Auth::guard('landing_admin')->user();

        if (method_exists($admin, 'isActive') && ! $admin->isActive()) {
            Auth::guard('landing_admin')->logout();
            $this->hitLoginLimiter($request);

            throw ValidationException::withMessages([
                'username' => 'Landing Admin account is inactive.',
            ]);
        }

        if ($this->loginRateLimitEnabled()) {
            RateLimiter::clear($this->throttleKey($request));
        }

        $request->session()->regenerate();
        $request->session()->put('landing_admin_last_activity_at', time());

        $replacedAnotherSession = $activeLoginSession->claim($request, $admin);
        $redirect = redirect()->intended(route('landing-admin.dashboard', absolute: false));

        if ($replacedAnotherSession) {
            $redirect->with('login_notice', 'Login successful. Another active session for this Landing Admin account was logged out. You are now the only active user for this account.');
        }

        return $redirect;
    }

    public function destroy(Request $request, ActiveLoginSession $activeLoginSession): RedirectResponse
    {
        $activeLoginSession->release($request, Auth::guard('landing_admin')->user());
        Auth::guard('landing_admin')->logout();

        $request->session()->forget('landing_admin_last_activity_at');
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        return redirect()
            ->route('landing-admin.login')
            ->with('status', 'You have been logged out successfully.');
    }

    public function keepAlive(Request $request, ActiveLoginSession $activeLoginSession): JsonResponse
    {
        $admin = Auth::guard('landing_admin')->user();

        if ($admin && method_exists($admin, 'isActive') && ! $admin->isActive()) {
            $activeLoginSession->release($request, $admin);
            Auth::guard('landing_admin')->logout();
            $request->session()->migrate(true);
            $request->session()->regenerateToken();

            return response()->json([
                'active' => false,
                'reason' => 'account-inactive',
                'message' => 'Landing Admin account is inactive.',
                'redirect' => route('landing-admin.login'),
            ], 401);
        }

        if ($admin && ! $activeLoginSession->isCurrent($request, $admin)) {
            Auth::guard('landing_admin')->logout();
            $request->session()->migrate(true);
            $request->session()->regenerateToken();

            return response()->json([
                'active' => false,
                'reason' => 'session-replaced',
                'message' => 'You were logged out because this Landing Admin account was signed in on another device or browser.',
                'redirect' => route('landing-admin.login', ['reason' => 'session-replaced']),
            ], 401);
        }

        $request->session()->put('landing_admin_last_activity_at', time());

        return response()->json(['active' => true]);
    }

    public function timeout(Request $request, ActiveLoginSession $activeLoginSession): JsonResponse|RedirectResponse
    {
        $activeLoginSession->release($request, Auth::guard('landing_admin')->user());
        Auth::guard('landing_admin')->logout();

        $request->session()->forget('landing_admin_last_activity_at');
        $request->session()->migrate(true);
        $request->session()->regenerateToken();
        $request->session()->flash('status', 'Your Landing Admin session expired after 15 minutes of inactivity. Please log in again.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your Landing Admin session expired after 15 minutes of inactivity.',
                'redirect' => route('landing-admin.login'),
            ]);
        }

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
            'username' => 'Too many Landing Admin login attempts. Please try again in ' . $this->formatSeconds($seconds) . '.',
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
        $strategy = (string) config('security.rate_limits.landing_admin_login.key_strategy', 'username_ip');
        $username = Str::lower(trim((string) $request->input('username', 'guest')));
        $ip = (string) ($request->ip() ?: 'unknown');

        return Str::transliterate(match ($strategy) {
            'username' => 'landing-admin-login|username|' . $username,
            'ip' => 'landing-admin-login|ip|' . $ip,
            'global' => 'landing-admin-login|global',
            default => 'landing-admin-login|username-ip|' . $username . '|' . $ip,
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
