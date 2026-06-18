<?php

namespace App\Http\Middleware;

use App\Services\Accounting\FormDraftService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ClearSavedFormDraft
{
    public function __construct(private readonly FormDraftService $drafts)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldClear($request, $response)) {
            return $response;
        }

        try {
            $this->drafts->delete($request->user(), (string) $request->input('_draft_key'));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldClear(Request $request, Response $response): bool
    {
        if (! $request->user() || $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        $routeName = (string) $request->route()?->getName();

        return filled($request->input('_draft_key'))
            && ! str_starts_with($routeName, 'accounting.form-drafts.')
            && (! $request->hasSession() || ! $request->session()->has('errors'));
    }
}
