<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreFormDraftRequest;
use App\Services\Accounting\FormDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormDraftController extends Controller
{
    public function __construct(private readonly FormDraftService $drafts)
    {
    }

    public function show(Request $request, string $draftKey): JsonResponse
    {
        $draft = $this->drafts->find($request->user(), $draftKey);

        if (! $draft) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'draft' => [
                'key' => $draft->draft_key,
                'title' => $draft->title,
                'payload' => $draft->payload,
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'updated_at_label' => $draft->updated_at?->diffForHumans(),
            ],
        ]);
    }

    public function store(StoreFormDraftRequest $request, string $draftKey): JsonResponse
    {
        $draft = $this->drafts->save(
            $request->user(),
            $draftKey,
            (array) $request->validated('payload'),
            $request->validated('title'),
            $request->route()?->getName(),
        );

        return response()->json([
            'message' => 'Draft saved.',
            'draft' => [
                'key' => $draft->draft_key,
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'updated_at_label' => $draft->updated_at?->diffForHumans(),
            ],
        ]);
    }

    public function destroy(Request $request, string $draftKey): JsonResponse|RedirectResponse
    {
        $this->drafts->delete($request->user(), $draftKey);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Draft discarded.']);
        }

        return back()->with('success', 'Draft discarded.');
    }
}
