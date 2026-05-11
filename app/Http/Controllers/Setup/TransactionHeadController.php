<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionHeadRequest;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Services\Setup\TransactionHeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TransactionHeadController extends Controller
{
    public function index(): View
    {
        $transactionHeads = TransactionHead::query()
            ->with(['defaultPartyType', 'settlementTypes'])
            ->orderBy('name')
            ->get();

        $settlementTypes = SettlementType::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('setup.transaction-heads', [
            'transactionHeads' => $transactionHeads,
            'settlementTypes' => $settlementTypes,
        ]);
    }

    public function store(
        TransactionHeadRequest $request,
        TransactionHeadService $service
    ): JsonResponse {
        $head = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction head saved successfully.',
            'data' => $head,
            'redirect' => route('setup.transaction-heads'),
        ], 201);
    }
}
