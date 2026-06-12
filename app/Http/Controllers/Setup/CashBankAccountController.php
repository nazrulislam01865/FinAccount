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

    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $accounts = CashBankAccount::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->with(['linkedLedger.accountType', 'bank'])
            ->orderBy('cash_bank_code')
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
            $request->user()?->id,
            (int) ($request->user()?->company_id ?? 0) ?: null
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash / Bank account saved successfully. ID: ' . $account->cash_bank_code,
            'data' => $account->load(['linkedLedger.accountType', 'bank']),
            'redirect' => route('setup.cash-bank-accounts'),
        ], 201);
    }

    public function update(
        CashBankAccountRequest $request,
        CashBankAccount $cashBankAccount,
        CashBankAccountService $service
    ): JsonResponse {
        $this->ensureAccountBelongsToCurrentCompany($request, $cashBankAccount);

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
        $this->ensureAccountBelongsToCurrentCompany($request, $cashBankAccount);

        try {
            $deleteService->deleteCashBankAccount($cashBankAccount);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.cash-bank-accounts',
                $exception->getMessage(),
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.cash-bank-accounts',
            'Cash / Bank account deleted successfully.'
        );
    }

    private function ensureAccountBelongsToCurrentCompany(Request $request, CashBankAccount $account): void
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        abort_if(
            $companyId > 0 && (int) $account->company_id !== $companyId,
            404
        );
    }
}
