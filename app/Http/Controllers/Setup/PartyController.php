<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\PartyRequest;
use App\Models\Party;
use App\Services\Setup\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartyController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $parties = Party::query()
            ->with(['partyType', 'linkedLedger'])
            ->orderBy('party_code')
            ->get();

        return view('setup.parties', [
            'parties' => $parties,
        ]);
    }

    public function store(
        PartyRequest $request,
        PartyService $service
    ): JsonResponse {
        $party = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Party saved successfully.',
            'data' => $party->load(['partyType', 'linkedLedger']),
            'redirect' => route('setup.parties'),
        ], 201);
    }

    public function update(
        PartyRequest $request,
        Party $party,
        PartyService $service
    ): JsonResponse {
        $party = $service->update(
            $party,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Party updated successfully.',
            'data' => $party,
            'redirect' => route('setup.parties'),
        ]);
    }

    public function destroy(
        Request $request,
        Party $party,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        try {
            $deleteService->deleteParty($party);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.parties',
                'This party could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.parties',
            'Party deleted successfully.'
        );
    }
}
