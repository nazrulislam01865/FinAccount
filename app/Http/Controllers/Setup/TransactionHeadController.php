<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionHeadRequest;
use App\Models\ChartOfAccount;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionHeadConfigurationService;
use App\Services\Setup\EntityDeleteService;
use App\Services\Setup\TransactionHeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TransactionHeadController extends Controller
{
    use RespondsToDelete;

    public function index(
        Request $request,
        TransactionHeadConfigurationService $configuration
    ): View {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $transactionHeads = TransactionHead::query()
            ->when($companyId > 0, fn ($query) => $query->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)
                    ->orWhere(function ($global) {
                        $global->whereNull('company_id')->where('is_system_default', true);
                    });
            }))
            ->with([
                'defaultPrimaryLedger.accountType',
                'accountingRules.lines',
                'accountingRules.settlementType',
                'accountingRules.partyType',
                'ledgerMappingRules.settlementType',
                'settlementTypes',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $profiles = $transactionHeads->mapWithKeys(
            fn (TransactionHead $head) => [$head->id => $configuration->summarize($head)]
        );

        $postingLedgers = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('posting_allowed', true)
            ->where(function ($query) {
                $query->where('coa_level', 4)
                    ->orWhere('account_level', 'Ledger');
            })
            ->when($companyId > 0, fn ($query) => $query->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->orderBy('account_code')
            ->get();

        return view('setup.transaction-heads', [
            'transactionHeads' => $transactionHeads,
            'transactionHeadProfiles' => $profiles,
            'postingLedgers' => $postingLedgers,
        ]);
    }

    public function store(
        TransactionHeadRequest $request,
        TransactionHeadService $service
    ): JsonResponse {
        $head = $service->create(
            $request->validated(),
            $request->user()?->id,
            (int) ($request->user()?->company_id ?? 0)
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction Head saved. Configure its Accounting Rule before using it in Transaction Entry.',
            'data' => $head,
            'redirect' => route('setup.transaction-heads'),
        ], 201);
    }

    public function update(
        TransactionHeadRequest $request,
        TransactionHead $transactionHead,
        TransactionHeadService $service
    ): JsonResponse {
        $this->assertCompanyAccess($request, $transactionHead);

        $head = $service->update(
            $transactionHead,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction Head updated successfully.',
            'data' => $head,
            'redirect' => route('setup.transaction-heads'),
        ]);
    }

    public function destroy(
        Request $request,
        TransactionHead $transactionHead,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        $this->assertCompanyAccess($request, $transactionHead);

        try {
            $deleteService->deleteTransactionHead($transactionHead);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.transaction-heads',
                $exception->getMessage() ?: 'This Transaction Head could not be deleted. Deactivate it instead.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.transaction-heads',
            'Transaction Head deleted successfully.'
        );
    }

    private function assertCompanyAccess(Request $request, TransactionHead $head): void
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        abort_if(
            $companyId > 0 && $head->company_id !== null && (int) $head->company_id !== $companyId,
            404
        );
    }
}
