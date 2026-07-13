<?php

use App\Http\Middleware\ApplyCompanyTimeZone;
use App\Http\Middleware\CaptureAccountingActivityNotifications;
use App\Http\Middleware\ClearSavedFormDraft;
use App\Http\Middleware\EnsureAccountingAccountActive;
use App\Http\Middleware\EnsureAccountingPermission;
use App\Http\Middleware\EnsureLandingAdminAuthenticated;
use App\Http\Middleware\EnsureMasterDataPermission;
use App\Http\Middleware\EnsureSystemAdmin;
use App\Http\Middleware\SessionTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$basePath = dirname(__DIR__);

foreach ([
    $basePath.'/bootstrap/cache',
    $basePath.'/storage/framework/cache/data',
    $basePath.'/storage/framework/sessions',
    $basePath.'/storage/framework/testing',
    $basePath.'/storage/framework/views',
] as $runtimePath) {
    if (! is_dir($runtimePath)) {
        @mkdir($runtimePath, 0775, true);
    }
}

return Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.active' => EnsureAccountingAccountActive::class,
            'accounting.activity.notifications' => CaptureAccountingActivityNotifications::class,
            'company.context' => ApplyCompanyTimeZone::class,
            'form.draft.cleanup' => ClearSavedFormDraft::class,
            'accounting.permission' => EnsureAccountingPermission::class,
            'master.permission' => EnsureMasterDataPermission::class,
            'landing.admin.auth' => EnsureLandingAdminAuthenticated::class,
            'session.timeout' => SessionTimeout::class,
            'system.admin' => EnsureSystemAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
