<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register application-wide bindings here when needed.
    }

    public function boot(): void
    {
        if ($this->app->environment('production') && config('app.url')) {
            URL::forceScheme('https');
        }
    }
}
