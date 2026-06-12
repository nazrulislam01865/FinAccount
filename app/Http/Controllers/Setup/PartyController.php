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

    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $parties = Party::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->with([
                'partyType.defaultLedger.accountType',
                'linkedLedger.accountType',
                'ledgerMappings.ledger.accountType',
                'receivableLedgerMapping.ledger.accountType',
                'payableLedgerMapping.ledger.accountType',
                'capitalLedgerMapping.ledger.accountType',
            ])
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
            $request->user()?->id,
            (int) ($request->user()?->company_id ?? 0) ?: null
        );

        return response()->json([
            'success' => true,
            'message' => 'Party saved successfully.',
            'data' => $party->load(['partyType.defaultLedger.accountType', 'linkedLedger.accountType', 'ledgerMappings.ledger.accountType']),
            'redirect' => route('setup.parties'),
        ], 201);
    }

    public function update(
        PartyRequest $request,
        Party $party,
        PartyService $service
    ): JsonResponse {
        $this->ensurePartyBelongsToCurrentCompany($request, $party);

        $party = $service->update(
            $party,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Party updated successfully.',
            'data' => $party->load(['partyType.defaultLedger.accountType', 'linkedLedger.accountType', 'ledgerMappings.ledger.accountType']),
            'redirect' => route('setup.parties'),
        ]);
    }

    public function destroy(
        Request $request,
        Party $party,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        $this->ensurePartyBelongsToCurrentCompany($request, $party);

        try {
            $deleteService->deleteParty($party);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.parties',
                $exception->getMessage(),
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.parties',
            'Party deleted successfully.'
        );
    }

    private function ensurePartyBelongsToCurrentCompany(Request $request, Party $party): void
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        abort_if(
            $companyId > 0 && (int) $party->company_id !== $companyId,
            404
        );
    }

}
