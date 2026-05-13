<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\TransactionHeadRequest;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Services\Setup\TransactionHeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionHeadController extends Controller
{
    use RespondsToDelete;

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

    public function update(
        TransactionHeadRequest $request,
        TransactionHead $transactionHead,
        TransactionHeadService $service
    ): JsonResponse {
        $head = $service->update(
            $transactionHead,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction head updated successfully.',
            'data' => $head,
            'redirect' => route('setup.transaction-heads'),
        ]);
    }

    public function destroy(
        Request $request,
        TransactionHead $transactionHead,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        try {
            $deleteService->deleteTransactionHead($transactionHead);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.transaction-heads',
                'This transaction head could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.transaction-heads',
            'Transaction head deleted successfully.'
        );
    }
}
