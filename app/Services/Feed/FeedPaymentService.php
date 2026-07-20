<?php

namespace App\Services\Feed;

use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FeedPaymentService
{
    /**
     * @param array<int, array<string, mixed>> $payments
     * @return Collection<int, array{money_account: MoneyAccount, reference: ?string, amount: float}>
     */
    public function prepare(array $payments, int $companyId): Collection
    {
        $prepared = collect($payments)
            ->filter(fn (array $payment): bool => (float) ($payment['amount'] ?? 0) > 0)
            ->values();

        if ($prepared->isEmpty()) {
            return collect();
        }

        $accountIds = $prepared
            ->pluck('money_account_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($accountIds) !== count(array_unique($accountIds))) {
            throw ValidationException::withMessages([
                'payments' => 'Use each payment account only once. Combine amounts paid through the same account.',
            ]);
        }

        $accounts = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('chart_of_account_id')
            ->whereHas('chartOfAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== count($accountIds)) {
            throw ValidationException::withMessages([
                'payments' => 'One or more payment accounts are inactive or are not mapped to an active COA account.',
            ]);
        }

        return $prepared->map(fn (array $payment): array => [
            'money_account' => $accounts->get((int) $payment['money_account_id']),
            'reference' => filled($payment['reference'] ?? null) ? trim((string) $payment['reference']) : null,
            'amount' => round((float) $payment['amount'], 2),
        ]);
    }

    /**
     * Persist the payment breakdown and replace the central posting service's
     * single selected-money journal line with one line per payment account.
     *
     * @param Collection<int, array{money_account: MoneyAccount, reference: ?string, amount: float}> $payments
     */
    public function apply(Transaction $transaction, Collection $payments): void
    {
        $transaction->loadMissing('journalEntry.lines');
        $expectedPaid = round((float) $transaction->paid_amount, 2);
        $paymentTotal = round((float) $payments->sum('amount'), 2);

        if (abs($expectedPaid - $paymentTotal) > 0.005) {
            throw ValidationException::withMessages([
                'payments' => 'The payment breakdown does not match the paid/received amount.',
            ]);
        }

        if ($transaction->payments()->exists()) {
            $stored = $transaction->payments()
                ->orderBy('sequence')
                ->get()
                ->map(fn ($payment): array => [
                    'money_account_id' => (int) $payment->money_account_id,
                    'reference' => $payment->reference,
                    'amount' => $this->money($payment->amount),
                ])
                ->values()
                ->all();
            $incoming = $payments
                ->map(fn (array $payment): array => [
                    'money_account_id' => (int) $payment['money_account']->id,
                    'reference' => $payment['reference'] ?? null,
                    'amount' => $this->money($payment['amount']),
                ])
                ->values()
                ->all();

            if ($stored !== $incoming) {
                throw ValidationException::withMessages([
                    'request_token' => 'This request was already posted with a different payment breakdown.',
                ]);
            }

            return;
        }

        if ($payments->isEmpty()) {
            return;
        }

        $primaryAccountId = (int) $payments->first()['money_account']->id;
        $moneyLine = $transaction->journalEntry->lines
            ->first(fn (JournalLine $line): bool => (int) $line->money_account_id === $primaryAccountId
                && abs(((float) $line->debit + (float) $line->credit) - $expectedPaid) <= 0.005);

        if (! $moneyLine) {
            throw ValidationException::withMessages([
                'payments' => 'The selected-money journal line could not be resolved for the payment breakdown.',
            ]);
        }

        $journalRows = [];

        foreach ($transaction->journalEntry->lines->sortBy([['sequence', 'asc'], ['id', 'asc']]) as $line) {
            if (! $line->is($moneyLine)) {
                $journalRows[] = [
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'money_account_id' => $line->money_account_id,
                    'party_id' => $line->party_id,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ];

                continue;
            }

            foreach ($payments as $payment) {
                $journalRows[] = [
                    'chart_of_account_id' => $payment['money_account']->chart_of_account_id,
                    'money_account_id' => $payment['money_account']->id,
                    'party_id' => null,
                    'description' => $line->description,
                    'debit' => (float) $line->debit > 0 ? $this->money($payment['amount']) : '0.00',
                    'credit' => (float) $line->credit > 0 ? $this->money($payment['amount']) : '0.00',
                ];
            }
        }

        $transaction->journalEntry->lines()->delete();

        foreach ($journalRows as $index => $row) {
            $transaction->journalEntry->lines()->create([
                'company_id' => $transaction->company_id,
                'sequence' => $index + 1,
                ...$row,
            ]);
        }

        foreach ($payments as $index => $payment) {
            $transaction->payments()->create([
                'company_id' => $transaction->company_id,
                'money_account_id' => $payment['money_account']->id,
                'reference' => $payment['reference'] ?? null,
                'sequence' => $index + 1,
                'amount' => $this->money($payment['amount']),
            ]);
        }
    }

    private function money(float|int|string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
