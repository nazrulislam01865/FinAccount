<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        $cache = 'ok';

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = 'fail';
        }

        try {
            Cache::put('health_check', now()->timestamp, 10);
            Cache::get('health_check');
        } catch (Throwable) {
            $cache = 'fail';
        }

        $healthy = $database === 'ok' && $cache === 'ok';

        return response()->json([
            'app' => 'ok',
            'environment' => app()->environment(),
            'debug' => config('app.debug') ? 'enabled' : 'disabled',
            'database' => $database,
            'cache' => $cache,
            'cache_store' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue' => config('queue.default'),
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => method_exists(app(), 'routesAreCached') ? app()->routesAreCached() : false,
            'storage_writable' => is_writable(storage_path()) && is_writable(storage_path('app')),
            'time' => now()->toDateTimeString(),
        ], $healthy ? 200 : 503);
    }
}
