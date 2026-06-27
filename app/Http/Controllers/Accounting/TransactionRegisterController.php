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
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Accounting\TransactionUpdateService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Support\TransactionTypes;
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
        private readonly TransactionAttachmentService $transactionAttachmentService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
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
            'settlementLabels' => $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE),
        ]);
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->ensureCompany($request, $transaction);

        $transaction->load(['transactionHead.postingAccount', 'moneyAccount', 'party', 'attachments.uploader']);
        $company = $request->user()->company;
        abort_unless($company, 404);
        $companyId = $company->id;

        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $storedCategoryOption = $transactionCategories->firstWhere('value', $transaction->category);
        $requestedCategoryOption = $transactionCategories->firstWhere('value', $request->string('category')->toString());
        $categoryOption = $storedCategoryOption ?? $requestedCategoryOption ?? $transactionCategories->first();
        abort_if($categoryOption === null, 422, 'Add an active Transaction Type before repairing this transaction.');
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
            'transactionTypeDefinition' => TransactionTypes::configuredDefinition(
                $category,
                is_array($categoryOption->metadata) ? $categoryOption->metadata : [],
                $categoryOption->label,
            ),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext(
                $company,
                $transaction->transaction_date?->toDateString(),
            ),
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
        $this->transactionAttachmentService->storeUploaded(
            $updated,
            $request->file('transaction_attachments'),
            $request->user(),
        );

        $updated->loadMissing('salesInvoice');

        $message = 'Transaction '.$updated->voucher_no.' updated successfully.';
        if ($updated->salesInvoice) {
            $message .= ' Sales invoice '.$updated->salesInvoice->invoice_no.' updated and download started.';
        }

        if ($request->user()->canAccounting('transactions.view')) {
            $redirect = redirect()->route('transactions.index')->with('success', $message);

            if ($updated->salesInvoice) {
                $redirect
                    ->with('invoice_download_url', route('sales-invoices.download', $updated->salesInvoice))
                    ->with('invoice_show_url', route('sales-invoices.show', $updated->salesInvoice));
            }

            return $redirect;
        }

        return redirect()
            ->route('transactions.create', ['category' => $updated->category])
            ->with('success', $message)
            ->with('warning', 'The transaction was updated, but your role is not allowed to view the register. You have been returned to Transaction Entry.');
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
                'Settlement Type',
                'Paid Amount',
                'Due Amount',
                'Due Date',
                'Invoice No',
                'Invoice Status',
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
                    $transaction->settlement_type ?? TransactionTypes::CASH,
                    $transaction->paid_amount,
                    $transaction->due_amount,
                    $transaction->due_date?->format('Y-m-d'),
                    $transaction->salesInvoice?->invoice_no,
                    $transaction->salesInvoice?->status,
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
            ->with(['transactionHead.postingAccount', 'moneyAccount', 'party', 'attachments', 'salesInvoice'])
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
