<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\JournalEntry;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionPostingService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly VoucherNumberService $voucherNumberService,
        private readonly DecimalAmount $decimalAmount,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function post(array $data, User $user): Transaction
    {
        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'Your user account is not connected to a company.',
            ]);
        }

        return DB::transaction(function () use ($data, $user): Transaction {
            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $head = TransactionHead::query()
                ->with(['accountingRule', 'postingAccount'])
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

            if (! $rule->is_active || $rule->category !== $data['category'] || $head->category !== $data['category']) {
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

            $sequence = $this->voucherNumberService->lock($user->company_id, $data['category']);

            // Recheck after acquiring the sequence lock. This makes a repeated
            // browser/network submission return the first completed transaction
            // instead of consuming a second voucher number.
            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $voucherNo = $this->voucherNumberService->issue($sequence);
            $now = now();

            $transaction = Transaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_head_id' => $head->id,
                'money_account_id' => $rule->money_required ? $moneyAccount?->id : null,
                'party_id' => $party?->id,
                'created_by' => $user->id,
                'voucher_no' => $voucherNo,
                'category' => $data['category'],
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'request_token' => $data['request_token'],
                'status' => 'posted',
                'posted_at' => $now,
            ]);

            $journalEntry = JournalEntry::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_id' => $transaction->id,
                'posted_by' => $user->id,
                'voucher_no' => $voucherNo,
                'entry_date' => $data['transaction_date'],
                'narration' => filled($data['description'] ?? null) ? $data['description'] : $head->name,
                'status' => 'posted',
                'posted_at' => $now,
            ]);

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
                    'description' => filled($data['description'] ?? null) ? $data['description'] : $head->name,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            return $transaction->load(['transactionHead', 'moneyAccount', 'party', 'journalEntry.lines.chartOfAccount']);
        }, attempts: 5);
    }
}
