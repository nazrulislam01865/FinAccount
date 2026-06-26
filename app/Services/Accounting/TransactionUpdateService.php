<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Support\TransactionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionUpdateService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly DecimalAmount $decimalAmount,
        private readonly TransactionSettlementService $settlementService,
        private readonly RuleMatcher $ruleMatcher,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
        private readonly TransactionPartyResolver $partyResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(Transaction $transaction, array $data, User $user): Transaction
    {
        if ($transaction->company_id !== $user->company_id) {
            abort(404);
        }

        if (
            $transaction->category !== null
            && $transaction->status !== 'incomplete'
            && $transaction->category !== $data['category']
        ) {
            throw ValidationException::withMessages([
                'category' => 'The transaction type cannot be changed while editing a complete posted transaction.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $data, $user): Transaction {
            $company = Company::query()
                ->with(['defaultFinancialYear', 'currency', 'timeZone'])
                ->lockForUpdate()
                ->findOrFail($user->company_id);

            $this->accountingPeriodService->assertPostingAllowed($company, (string) $data['transaction_date']);

            $lockedTransaction = Transaction::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $transactionType = TransactionTypes::normalize((string) $data['category']);

            $head = TransactionHead::query()
                ->with('postingAccount')
                ->where('company_id', $user->company_id)
                ->whereIn('category', TransactionTypes::databaseAliases($transactionType))
                ->where('is_active', true)
                ->whereNotNull('posting_account_id')
                ->whereHas('postingAccount', fn ($query) => $query
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true))
                ->findOrFail($data['transaction_head_id']);

            $this->validateHeadNature($head, $transactionType);

            $moneyAccount = filled($data['money_account_id'] ?? null)
                ? MoneyAccount::query()
                    ->with('chartOfAccount')
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')
                    ->whereHas('chartOfAccount', fn ($query) => $query
                        ->where('company_id', $user->company_id)
                        ->where('is_active', true))
                    ->findOrFail($data['money_account_id'])
                : null;

            $scale = (int) ($company->currency?->decimal_places ?? 2);
            $amount = $this->decimalAmount->normalize($data['amount'], $scale);
            $settlement = $this->settlementService->prepare($amount, $data, $scale);
            $settlementType = $settlement['settlement_type'];

            if (! $head->allowsSettlement($settlementType)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'The amount entered creates a payment type that is not allowed for this transaction head.',
                ]);
            }

            $rule = $this->ruleMatcher->match((int) $user->company_id, $transactionType, $settlementType);
            $requiresMoney = $this->settlementService->requiresMoney($rule);
            $requiresParty = $this->settlementService->requiresParty($rule);
            $party = $requiresParty
                ? $this->partyResolver->resolveRequired(
                    (int) $user->company_id,
                    $head,
                    $rule,
                    $data['party_id'] ?? null,
                )
                : null;

            if ($requiresMoney && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => TransactionTypes::moneyLabel($transactionType).' is required for this payment type.',
                ]);
            }

            $lines = $this->journalBuilder->buildFromRule(
                $head,
                $moneyAccount,
                $party,
                $amount,
                $settlement['paid_amount'],
                $settlement['due_amount'],
                $rule,
            );
            $narration = filled($data['description'] ?? null) ? $data['description'] : $head->name;

            $lockedTransaction->update([
                'category' => $transactionType,
                'transaction_head_id' => $head->id,
                'money_account_id' => $requiresMoney ? $moneyAccount?->id : null,
                'party_id' => $requiresParty ? $party?->id : null,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'settlement_type' => $settlement['settlement_type'],
                'paid_amount' => $settlement['paid_amount'],
                'due_amount' => $settlement['due_amount'],
                'due_date' => $settlement['due_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $journalEntry = $lockedTransaction->journalEntry()->firstOrFail();
            $journalEntry->update([
                'entry_date' => $data['transaction_date'],
                'narration' => $narration,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $journalEntry->lines()->delete();

            foreach ($lines as $index => $line) {
                $journalEntry->lines()->create([
                    'company_id' => $user->company_id,
                    'chart_of_account_id' => $line['account']->id,
                    'money_account_id' => $line['source'] === AccountingRule::SOURCE_SELECTED_MONEY
                        ? $moneyAccount?->id
                        : null,
                    'party_id' => in_array($line['source'], [
                        AccountingRule::SOURCE_PARTY_RECEIVABLE,
                        AccountingRule::SOURCE_PARTY_PAYABLE,
                    ], true) ? $party?->id : null,
                    'sequence' => $index + 1,
                    'description' => $narration,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            $this->salesInvoiceService->syncForTransaction($lockedTransaction, $company);

            return $lockedTransaction->fresh([
                'transactionHead', 'moneyAccount', 'party',
                'journalEntry.lines.chartOfAccount', 'salesInvoice',
            ]);
        }, attempts: 5);
    }

    private function validateHeadNature(TransactionHead $head, string $transactionType): void
    {
        $allowedTypes = TransactionTypes::postingTypes($transactionType);

        if ($allowedTypes !== [] && ! in_array($head->postingAccount?->type, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The selected head is linked to an unsuitable account type for this transaction.',
            ]);
        }
    }
}
