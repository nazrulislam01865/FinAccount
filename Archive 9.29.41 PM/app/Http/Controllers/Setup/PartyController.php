<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartyRequest;
use App\Models\Party;
use App\Services\Setup\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PartyController extends Controller
{
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
}
