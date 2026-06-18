<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\AccountingLoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, AccountingLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function () {
            $request = request();
            $logoutNotice = '';

            if (! $request->expectsJson() && ! $request->ajax()) {
                $logoutNotice = trim((string) $request->session()->pull('hisebghor.logout_notice', ''));

                if ($logoutNotice === '' && $request->query('reason') === 'session-replaced') {
                    $logoutNotice = 'You were logged out because this account was signed in on another device or browser. Only one active login is allowed per user. If this was not you, change your password immediately.';
                }
            }

            return view('livewire.auth.login', [
                'logoutNotice' => $logoutNotice,
            ]);
        });
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            if (! (bool) config('security.rate_limits.system_login.enabled', true)) {
                return Limit::none();
            }

            return Limit::perMinutes(
                max(1, (int) config('security.rate_limits.system_login.lock_minutes', 120)),
                max(1, (int) config('security.rate_limits.system_login.max_attempts', 5))
            )->by($this->systemLoginThrottleKey($request));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }

    private function systemLoginThrottleKey(Request $request): string
    {
        $strategy = (string) config('security.rate_limits.system_login.key_strategy', 'email_ip');
        $email = Str::lower(trim((string) $request->input(Fortify::username(), 'guest')));
        $ip = (string) ($request->ip() ?: 'unknown');

        return Str::transliterate(match ($strategy) {
            'email' => 'system-login|email|'.$email,
            'ip' => 'system-login|ip|'.$ip,
            'global' => 'system-login|global',
            default => 'system-login|email-ip|'.$email.'|'.$ip,
        });
    }
}
