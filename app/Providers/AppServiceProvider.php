<?php

namespace App\Providers;

use App\AccountingEngine\AccountingEngine;
use App\AccountingEngine\Contracts\AccountingEngineContract;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (
            interface_exists(AccountingEngineContract::class)
            && class_exists(AccountingEngine::class)
        ) {
            $this->app->bind(AccountingEngineContract::class, AccountingEngine::class);
        }
    }

    public function boot(): void
    {
        /*
         * Cloud-safe HTTPS handling.
         * Do not force HTTPS unless SSL/443 is configured and APP_FORCE_HTTPS=true.
         * This prevents the Droplet browser issue where HTTP redirects to HTTPS
         * while Nginx is only listening on port 80.
         */
        if (filter_var(config('app.force_https', false), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }
    }
}
