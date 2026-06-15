<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateTransactionRequest;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionDeletionService;
use App\Services\Accounting\TransactionUpdateService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionRegisterController extends Controller
{
    public function __construct(
        private readonly TransactionUpdateService $transactionUpdateService,
        private readonly TransactionDeletionService $transactionDeletionService,
    ) {}

    public function index(Request $request): View
    {
        $transactions = $this->filteredQuery($request)
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return view('transactions.index', [
            'transactions' => $transactions,
            'search' => $request->string('search')->toString(),
            'category' => $request->string('category')->toString(),
        ]);
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->ensureCompany($request, $transaction);

        $transaction->load(['transactionHead.accountingRule', 'moneyAccount', 'party']);
        $companyId = $request->user()->company_id;

        return view('transactions.create', [
            'transaction' => $transaction,
            'category' => $transaction->category,
            'transactionHeads' => TransactionHead::query()
                ->with('accountingRule')
                ->where('company_id', $companyId)
                ->where('category', $transaction->category)
                ->where('is_active', true)
                ->whereHas('accountingRule', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
            'moneyAccounts' => MoneyAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'parties' => Party::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
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

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->ensureCompany($request, $transaction);
        $voucherNo = $transaction->voucher_no;

        $this->transactionDeletionService->delete($transaction, $request->user());

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction '.$voucherNo.' and its journal lines were deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $transactions = $this->filteredQuery($request)
            ->oldest('transaction_date')
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
        $category = $request->string('category')->toString();

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

    private function ensureCompany(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);
    }
}
