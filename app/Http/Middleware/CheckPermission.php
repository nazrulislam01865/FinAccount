<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = array_values(array_filter(array_map('trim', explode('|', $permission))));

        if (!$request->user() || !$request->user()->hasAnyPermission($permissions)) {
            abort(403, 'You do not have permission to access this feature.');
        }

        return $next($request);
    }
}
