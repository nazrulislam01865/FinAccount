<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\Company;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Company\CompanyAccountingPeriodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionUpdateService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly DecimalAmount $decimalAmount,
        private readonly TransactionSettlementService $settlementService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
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
                'category' => 'The transaction category cannot be changed while editing a complete posted transaction.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $data, $user): Transaction {
            $company = Company::query()
                ->with(['defaultFinancialYear', 'currency', 'timeZone'])
                ->lockForUpdate()
                ->findOrFail($user->company_id);

            $this->accountingPeriodService->assertPostingAllowed(
                $company,
                (string) $data['transaction_date'],
            );

            $lockedTransaction = Transaction::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $head = TransactionHead::query()
                ->with(['accountingRule.lines', 'postingAccount'])
                ->where('company_id', $user->company_id)
                ->where('category', $data['category'])
                ->where('is_active', true)
                ->whereNotNull('accounting_rule_id')
                ->whereNotNull('posting_account_id')
                ->whereHas('accountingRule', fn ($query) => $query
                    ->where('company_id', $user->company_id)
                    ->where('category', $data['category'])
                    ->where('is_active', true))
                ->whereHas('postingAccount', fn ($query) => $query
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true))
                ->findOrFail($data['transaction_head_id']);

            $rule = $head->accountingRule;

            if (! $rule->is_active || $rule->category !== $data['category']) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'The selected transaction head is not valid for this category.',
                ]);
            }

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

            $party = filled($data['party_id'] ?? null)
                ? Party::query()
                    ->with(['receivableAccount', 'payableAccount'])
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->findOrFail($data['party_id'])
                : null;

            $requiresSplitAmounts = $this->settlementService->requiresSplitAmounts($rule);
            $requiresMoney = $this->settlementService->requiresMoney($rule);
            $requiresParty = $this->settlementService->requiresParty($rule);

            $scale = (int) ($company->currency?->decimal_places ?? 2);
            $amount = $this->decimalAmount->normalize($data['amount'], $scale);
            $settlement = $this->settlementService->prepare($amount, $data, $scale, $requiresSplitAmounts);

            if ($requiresMoney && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => 'A money account is required because the selected accounting rule has a Selected Money posting line.',
                ]);
            }

            if ($requiresParty && ! $party) {
                throw ValidationException::withMessages([
                    'party_id' => 'A party is required because the selected accounting rule has a party receivable/payable posting line.',
                ]);
            }

            if ($requiresParty && $rule->party_type !== 'Any' && $party?->type !== $rule->party_type) {
                throw ValidationException::withMessages([
                    'party_id' => 'This transaction requires a '.$rule->party_type.' party.',
                ]);
            }

            $lines = $this->journalBuilder->buildFromRule(
                $head,
                $moneyAccount,
                $party,
                $amount,
                $settlement['paid_amount'],
                $settlement['due_amount'],
            );
            $narration = filled($data['description'] ?? null) ? $data['description'] : $head->name;

            $lockedTransaction->update([
                'category' => $data['category'],
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
                'transactionHead',
                'moneyAccount',
                'party',
                'journalEntry.lines.chartOfAccount',
                'salesInvoice',
            ]);
        }, attempts: 5);
    }
}
