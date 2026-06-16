<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Requests\Accounting\UpdateTransactionRequest;
use App\Models\AccountingOption;
use App\Models\Transaction;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\TransactionDeletionService;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Accounting\TransactionUpdateService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionRegisterController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly TransactionUpdateService $transactionUpdateService,
        private readonly TransactionDeletionService $transactionDeletionService,
        private readonly AccountingOptionService $optionService,
        private readonly SafeDeleteService $safeDeleteService,
        private readonly TransactionEntryOptionService $entryOptionService,
    ) {}

    public function index(Request $request): View
    {
        $transactions = $this->filteredQuery($request)
            ->latest('id')
            ->get();

        return view('transactions.index', [
            'transactions' => $transactions,
            'search' => $request->string('search')->toString(),
            'category' => $this->validatedCategoryFilter($request),
            'transactionCategories' => $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
        ]);
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->ensureCompany($request, $transaction);

        $transaction->load(['transactionHead.accountingRule', 'moneyAccount', 'party']);
        $companyId = $request->user()->company_id;

        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $storedCategoryOption = $transactionCategories->firstWhere('value', $transaction->category);
        $requestedCategoryOption = $transactionCategories->firstWhere('value', $request->string('category')->toString());
        $categoryOption = $storedCategoryOption ?? $requestedCategoryOption ?? $transactionCategories->first();
        abort_if($categoryOption === null, 422, 'Add an active Transaction Category before repairing this transaction.');
        $category = $categoryOption->value;
        $categoryRepairRequired = $storedCategoryOption === null;

        return view('transactions.create', [
            'transaction' => $transaction,
            'category' => $category,
            'categoryOption' => $categoryOption,
            'categoryRepairRequired' => $categoryRepairRequired,
            'transactionCategories' => $transactionCategories,
            'transactionHeads' => $this->entryOptionService->transactionHeads($companyId, $category),
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'parties' => $this->entryOptionService->parties($companyId),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
        ]);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
    ): RedirectResponse {
        $this->ensureCompany($request, $transaction);

        $updated = $this->transactionUpdateService->update(
            $transaction,
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction '.$updated->voucher_no.' updated successfully.');
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $transaction);
        $voucherNo = $transaction->voucher_no;
        $plan = $this->safeDeleteService->inspectTransaction($transaction);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->transactionDeletionService->delete($transaction, $request->user()),
            'transactions.index',
            'Transaction '.$voucherNo.' and its generated journal records were deleted permanently.',
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $transactions = $this->filteredQuery($request)
            ->oldest('id')
            ->get();

        return response()->streamDownload(function () use ($transactions): void {
            $stream = fopen('php://output', 'wb');

            fputcsv($stream, [
                'Date',
                'Voucher',
                'Category',
                'Head',
                'Money',
                'Party',
                'Reference',
                'Description',
                'Amount',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($stream, [
                    $transaction->transaction_date->format('Y-m-d'),
                    $transaction->voucher_no,
                    $transaction->category,
                    $transaction->transactionHead?->name,
                    $transaction->moneyAccount?->name,
                    $transaction->party?->name,
                    $transaction->reference,
                    $transaction->description,
                    $transaction->amount,
                ]);
            }

            fclose($stream);
        }, 'hisebghor_transactions.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $companyId = $request->user()->company_id;
        $search = trim($request->string('search')->toString());
        $category = $this->validatedCategoryFilter($request);

        return Transaction::query()
            ->with(['transactionHead', 'moneyAccount', 'party'])
            ->where('company_id', $companyId)
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('voucher_no', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('transactionHead', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('party', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('moneyAccount', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            });
    }

    private function validatedCategoryFilter(Request $request): string
    {
        $category = $request->string('category')->toString();

        return $this->optionService->isActiveValue(
            AccountingOption::GROUP_TRANSACTION_CATEGORY,
            $category,
        ) ? $category : '';
    }

    private function ensureCompany(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);
    }
}
