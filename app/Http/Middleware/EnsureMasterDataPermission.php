<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterDataPermission
{
    private const MAP = [
        'party-types' => 'party_types',
        'money-account-types' => 'money_account_types',
        'transaction-categories' => 'transaction_categories',
    ];

    public function handle(Request $request, Closure $next, string $action = 'view'): Response
    {
        $section = (string) $request->route('section');
        $module = self::MAP[$section] ?? null;
        abort_unless($module, 404);

        $requestedAction = strtolower(trim((string) $request->query('action', '')));
        $effectiveAction = $action === 'view' && in_array($requestedAction, ['add', 'create'], true)
            ? 'manage'
            : $action;
        $permission = $module.'.'.($effectiveAction === 'manage' ? 'manage' : 'view');
        $user = $request->user();

        abort_unless($user && method_exists($user, 'canAccounting') && $user->canAccounting($permission), 403,
            $effectiveAction === 'manage'
                ? 'You do not have permission to manage this master-data section.'
                : 'You are not allowed to view this master-data section.'
        );

        return $next($request);
    }
}
