<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsByAccountingAccess
{
    /**
     * Keep Fleet-style View and Manage access independent. A user who can
     * create/manage but cannot view the list returns to the add screen.
     *
     * @param array<string, mixed> $routeParameters
     */
    protected function redirectAfterAccountingSave(
        Request $request,
        string $viewPermission,
        string $routeName,
        string $successMessage,
        array $routeParameters = [],
    ): RedirectResponse {
        if ($request->user()?->canAccounting($viewPermission)) {
            return redirect()->route($routeName, $routeParameters)->with('success', $successMessage);
        }

        return redirect()
            ->route($routeName, [...$routeParameters, 'action' => 'add'])
            ->with('success', $successMessage)
            ->with('warning', 'The record was saved, but your role is not allowed to view this list. You have been returned to the add screen.');
    }
}
