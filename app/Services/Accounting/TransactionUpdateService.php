<?php

namespace App\Services\Accounting;

use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionUpdateService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly DecimalAmount $decimalAmount,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function update(Transaction $transaction, array $data, User $user): Transaction
    {
        if ($transaction->company_id !== $user->company_id) {
            abort(404);
        }

        if ($transaction->category !== $data['category']) {
            throw ValidationException::withMessages([
                'category' => 'The transaction category cannot be changed while editing.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $data, $user): Transaction {
            $lockedTransaction = Transaction::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $head = TransactionHead::query()
                ->with(['accountingRule', 'postingAccount'])
                ->where('company_id', $user->company_id)
                ->where('category', $lockedTransaction->category)
                ->where('is_active', true)
                ->findOrFail($data['transaction_head_id']);

            $rule = $head->accountingRule;

            if (! $rule->is_active || $rule->category !== $lockedTransaction->category) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'The selected transaction head is not valid for this category.',
                ]);
            }

            $moneyAccount = filled($data['money_account_id'] ?? null)
                ? MoneyAccount::query()
                    ->with('chartOfAccount')
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->findOrFail($data['money_account_id'])
                : null;

            $party = filled($data['party_id'] ?? null)
                ? Party::query()
                    ->with(['receivableAccount', 'payableAccount'])
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->findOrFail($data['party_id'])
                : null;

            if ($rule->money_required && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => 'A money account is required for this accounting rule.',
                ]);
            }

            if ($rule->party_required && ! $party) {
                throw ValidationException::withMessages([
                    'party_id' => 'A party is required for this accounting rule.',
                ]);
            }

            if ($rule->party_required && $rule->party_type !== 'Any' && $party?->type !== $rule->party_type) {
                throw ValidationException::withMessages([
                    'party_id' => 'This transaction requires a '.$rule->party_type.' party.',
                ]);
            }

            $amount = $this->decimalAmount->normalize($data['amount']);
            $lines = $this->journalBuilder->build($head, $moneyAccount, $party, $amount);
            $narration = filled($data['description'] ?? null) ? $data['description'] : $head->name;

            $lockedTransaction->update([
                'transaction_head_id' => $head->id,
                'money_account_id' => $rule->money_required ? $moneyAccount?->id : null,
                'party_id' => $rule->party_required ? $party?->id : null,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $journalEntry = $lockedTransaction->journalEntry()->firstOrFail();
            $journalEntry->update([
                'entry_date' => $data['transaction_date'],
                'narration' => $narration,
            ]);

            $journalEntry->lines()->delete();

            foreach ($lines as $index => $line) {
                $journalEntry->lines()->create([
                    'company_id' => $user->company_id,
                    'chart_of_account_id' => $line['account']->id,
                    'money_account_id' => $moneyAccount?->chart_of_account_id === $line['account']->id
                        ? $moneyAccount?->id
                        : null,
                    'party_id' => in_array($line['account']->id, [
                        $party?->receivable_account_id,
                        $party?->payable_account_id,
                    ], true) ? $party?->id : null,
                    'sequence' => $index + 1,
                    'description' => $narration,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            return $lockedTransaction->fresh([
                'transactionHead',
                'moneyAccount',
                'party',
                'journalEntry.lines.chartOfAccount',
            ]);
        }, attempts: 5);
    }
}
