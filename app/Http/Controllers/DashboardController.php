<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        foreach (config('access.features', []) as $feature) {
            $route = $feature['route'] ?? null;

            if (!$route || !Route::has($route)) {
                continue;
            }

            if ($user->hasAnyPermission([$feature['view'] ?? null, $feature['manage'] ?? null])) {
                return redirect()->route($route);
            }
        }

        throw new AccessDeniedHttpException('Your account does not have any assigned feature permission. Please contact the system administrator.');
    }
}
