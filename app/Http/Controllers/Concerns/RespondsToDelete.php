<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

trait RespondsToDelete
{
    protected function deleteSuccess(Request $request, string $routeName, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route($routeName)
            ->with('success', $message);
    }

    protected function deleteFailure(Request $request, string $routeName, string $message, Throwable $exception): JsonResponse|RedirectResponse
    {
        report($exception);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()
            ->route($routeName)
            ->with('error', $message);
    }
}
