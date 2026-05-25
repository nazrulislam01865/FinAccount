<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = 'fail';
        }

        return response()->json([
            'app' => 'ok',
            'environment' => app()->environment(),
            'debug' => config('app.debug') ? 'enabled' : 'disabled',
            'database' => $database,
            'storage_writable' => is_writable(storage_path()) && is_writable(storage_path('app')),
            'queue' => config('queue.default'),
            'time' => now()->toDateTimeString(),
        ], $database === 'ok' ? 200 : 503);
    }
}
