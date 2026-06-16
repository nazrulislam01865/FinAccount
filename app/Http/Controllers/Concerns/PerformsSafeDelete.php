<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Accounting\SafeDelete\DeletionPlan;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait PerformsSafeDelete
{
    protected function performSafeDelete(
        Request $request,
        DeletionPlan $plan,
        Closure $delete,
        string $redirectRoute,
        string $successMessage,
    ): JsonResponse|RedirectResponse {
        if ($request->boolean('preview')) {
            return response()->json([
                'success' => true,
                'preview' => true,
                'plan' => $plan,
            ]);
        }

        if (! $request->boolean('confirmed')) {
            $message = 'Deletion was not completed because explicit confirmation is required.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'plan' => $plan,
                ], 409);
            }

            return redirect()->route($redirectRoute)->with('error', $message);
        }

        $delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect_url' => route($redirectRoute),
            ]);
        }

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }
}
