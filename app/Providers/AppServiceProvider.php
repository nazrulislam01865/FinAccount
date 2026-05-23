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
        $this->app->bind(AccountingEngineContract::class, AccountingEngine::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') && config('app.url')) {
            URL::forceScheme('https');
        }
    }
}
