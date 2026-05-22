<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\ReleaseItemRequest;
use App\Models\ReleaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ReleaseNoteController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $releaseItems = ReleaseItem::query()
            ->with(['creator', 'updater'])
            ->orderByDesc('release_date')
            ->orderBy('module')
            ->orderBy('task')
            ->get();

        return view('release-notes.index', [
            'releaseItems' => $releaseItems,
            'modules' => ReleaseItem::MODULES,
            'uiFunctions' => ReleaseItem::UI_FUNCTIONS,
            'itemTypes' => ReleaseItem::ITEM_TYPES,
            'releaseVersions' => ReleaseItem::RELEASE_VERSIONS,
            'statuses' => ReleaseItem::STATUSES,
        ]);
    }

    public function store(ReleaseItemRequest $request): JsonResponse
    {
        $releaseItem = ReleaseItem::query()->create([
            ...$request->validated(),
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release item saved successfully.',
            'data' => $releaseItem,
            'redirect' => route('release-notes.index'),
        ], 201);
    }

    public function update(ReleaseItemRequest $request, ReleaseItem $releaseItem): JsonResponse
    {
        $releaseItem->update([
            ...$request->validated(),
            'updated_by_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release item updated successfully.',
            'data' => $releaseItem->fresh(),
            'redirect' => route('release-notes.index'),
        ]);
    }

    public function destroy(Request $request, ReleaseItem $releaseItem): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('release-notes.manage'), 403, 'You are not allowed to delete release items.');

        try {
            $releaseItem->delete();
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'release-notes.index',
                'This release item could not be deleted. Please try again.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'release-notes.index',
            'Release item deleted successfully.'
        );
    }
}
