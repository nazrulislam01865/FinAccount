<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Accounting\SafeDelete\DeletionPlan;
use Closure;
use Throwable;
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

        try {
            $delete();

            $redirectUrl = route($redirectRoute);
        } catch (Throwable $exception) {
            report($exception);

            $message = $this->safeDeleteFailureMessage($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'plan' => $plan,
                ], 422);
            }

            return redirect()->route($redirectRoute)->with('error', $message);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }

    private function safeDeleteFailureMessage(Throwable $exception): string
    {
        $message = trim((string) $exception->getMessage());

        if ($message === '') {
            return 'Deletion could not be completed. Please check the server log for details.';
        }

        if (str_contains($message, 'Integrity constraint violation') || str_contains($message, 'Cannot delete or update a parent row')) {
            return 'Deletion could not be completed because one or more database records still reference this item. The related links must be cleared first.';
        }

        return $message;
    }
}
