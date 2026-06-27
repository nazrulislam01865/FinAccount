<?php

namespace App\Http\Middleware;

use App\Support\AccountingRbac;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountingPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $requiredPermission = $permission;
        $action = strtolower(trim((string) $request->query('action', '')));

        $sharedAddScreens = [
            'chart_of_accounts.view', 'opening_balances.view', 'accounting_rules.view', 'transaction_heads.view',
            'voucher_numbering.view', 'parties.view', 'money_accounts.view',
            'users.view', 'role_matrix.view',
            'business_types.view', 'currencies.view', 'time_zones.view', 'financial_years.view',
        ];
        if (in_array($permission, $sharedAddScreens, true) && in_array($action, ['add', 'create'], true)) {
            $requiredPermission = AccountingRbac::pairedPermission($permission, 'manage') ?? $permission;
        }

        if (! method_exists($user, 'canAccounting') || ! $user->canAccounting($requiredPermission)) {
            $message = str_ends_with($requiredPermission, '.view')
                ? 'You are not allowed to view this list.'
                : 'You do not have permission to create or manage records in this module.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'permission' => $requiredPermission], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
