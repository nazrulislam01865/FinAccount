<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashBankAccountRequest;
use App\Models\CashBankAccount;
use App\Services\Setup\CashBankAccountService;
use App\Services\Setup\EntityDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CashBankAccountController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $accounts = CashBankAccount::query()
            ->with(['linkedLedger.accountType', 'bank'])
            ->orderBy('cash_bank_name')
            ->get();

        return view('setup.cash-bank-accounts', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        CashBankAccountRequest $request,
        CashBankAccountService $service
    ): JsonResponse {
        $account = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash / Bank account saved successfully.',
            'data' => $account->load(['linkedLedger.accountType', 'bank']),
            'redirect' => route('setup.cash-bank-accounts'),
        ], 201);
    }

    public function update(
        CashBankAccountRequest $request,
        CashBankAccount $cashBankAccount,
        CashBankAccountService $service
    ): JsonResponse {
        $account = $service->update(
            $cashBankAccount,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash / Bank account updated successfully.',
            'data' => $account->load(['linkedLedger.accountType', 'bank']),
            'redirect' => route('setup.cash-bank-accounts'),
        ]);
    }

    public function destroy(
        Request $request,
        CashBankAccount $cashBankAccount,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        try {
            $deleteService->deleteCashBankAccount($cashBankAccount);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.cash-bank-accounts',
                'This cash / bank account could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.cash-bank-accounts',
            'Cash / Bank account deleted successfully.'
        );
    }
}