<?php

namespace App\Providers;

use App\Support\ActiveLoginSession;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLandingRateLimiter();
        $this->releaseActiveSessionOnLogout();
    }


    /**
     * Keep active-session markers clean when users manually log out.
     */
    protected function releaseActiveSessionOnLogout(): void
    {
        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;

            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                app(ActiveLoginSession::class)->release(request(), $user);
            }
        });
    }

    /**
     * Protect the public landing-page inquiry form from repeated spam.
     */
    protected function configureLandingRateLimiter(): void
    {
        RateLimiter::for('landing-inquiry', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limits.landing_inquiry_per_minute', 5))
                ->by($request->ip() ?: 'unknown');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
