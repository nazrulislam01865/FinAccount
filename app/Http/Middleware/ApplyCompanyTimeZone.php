<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplyCompanyTimeZone
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;
        $timezone = $company?->timeZone?->php_timezone ?: $company?->timezone;

        if (filled($timezone)) {
            try {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            } catch (Throwable) {
                // Keep the application default when a legacy timezone value is invalid.
            }
        }

        return $next($request);
    }
}
