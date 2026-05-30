<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->hitLoginLimiter();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user && method_exists($user, 'isActive') && ! $user->isActive()) {
            Auth::logout();
            $this->hitLoginLimiter();

            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact a Super Admin or Admin.',
            ]);
        }

        if ($this->loginRateLimitEnabled()) {
            RateLimiter::clear($this->throttleKey());
        }
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! $this->loginRateLimitEnabled()) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $this->flashLockoutCountdown($seconds);

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Please try again in ' . $this->formatSeconds($seconds) . '.',
        ]);
    }

    private function hitLoginLimiter(): void
    {
        if (! $this->loginRateLimitEnabled()) {
            return;
        }

        RateLimiter::hit($this->throttleKey(), $this->lockSeconds());

        $remaining = RateLimiter::retriesLeft($this->throttleKey(), $this->maxAttempts());

        if ($remaining <= 0) {
            $this->flashLockoutCountdown(RateLimiter::availableIn($this->throttleKey()));
        }
    }

    public function throttleKey(): string
    {
        $strategy = (string) config('security.rate_limits.system_login.key_strategy', 'email_ip');
        $email = Str::lower((string) $this->input('email', 'guest'));
        $ip = (string) ($this->ip() ?: 'unknown');

        return Str::transliterate(match ($strategy) {
            'email' => 'system-login|email|' . $email,
            'ip' => 'system-login|ip|' . $ip,
            'global' => 'system-login|global',
            default => 'system-login|email-ip|' . $email . '|' . $ip,
        });
    }

    private function loginRateLimitEnabled(): bool
    {
        return (bool) config('security.rate_limits.system_login.enabled', true);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('security.rate_limits.system_login.max_attempts', 5));
    }

    private function lockSeconds(): int
    {
        return max(60, (int) config('security.rate_limits.system_login.lock_minutes', 120) * 60);
    }

    private function flashLockoutCountdown(int $seconds): void
    {
        $seconds = max(1, $seconds);

        $this->session()->flash('login_lockout_seconds', $seconds);
        $this->session()->flash('login_lockout_until', now()->addSeconds($seconds)->timestamp);
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
