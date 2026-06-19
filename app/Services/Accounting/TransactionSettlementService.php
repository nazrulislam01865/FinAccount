<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TransactionSettlementService
{
    public function __construct(private readonly DecimalAmount $decimalAmount) {}

    /**
     * @param array<string, mixed> $data
     * @return array{settlement_type: string, paid_amount: ?string, due_amount: ?string, due_date: ?string}
     */
    public function prepare(string $totalAmount, array $data, int $scale = 2, bool $requiresSplitAmounts = false): array
    {
        if (! $requiresSplitAmounts) {
            return [
                'settlement_type' => Transaction::SETTLEMENT_NORMAL,
                'paid_amount' => null,
                'due_amount' => null,
                'due_date' => null,
            ];
        }

        $paidAmount = $this->decimalAmount->normalize($data['paid_amount'] ?? 0, $scale);
        $totalMinor = $this->minorUnits($totalAmount, $scale);
        $paidMinor = $this->minorUnits($paidAmount, $scale);

        if ($totalMinor <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Total amount must be greater than zero for this rule-based partial transaction.',
            ]);
        }

        if ($paidMinor <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid amount is required because the selected accounting rule has a paid amount posting line.',
            ]);
        }

        if ($paidMinor >= $totalMinor) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid amount must be less than total amount because the selected accounting rule also posts a due amount.',
            ]);
        }

        return [
            'settlement_type' => Transaction::SETTLEMENT_PARTIAL,
            'paid_amount' => $this->formatMinor($paidMinor, $scale),
            'due_amount' => $this->formatMinor($totalMinor - $paidMinor, $scale),
            'due_date' => filled($data['due_date'] ?? null) ? (string) $data['due_date'] : null,
        ];
    }

    /** @return Collection<int, AccountingRuleLine|array<string, string>> */
    public function effectiveLines(AccountingRule $rule): Collection
    {
        $lines = $rule->relationLoaded('lines')
            ? $rule->lines
            : $rule->lines()->orderBy('sort_order')->get();

        if ($lines->isNotEmpty()) {
            return $lines->values();
        }

        return collect([
            [
                'line_side' => AccountingRuleLine::SIDE_DEBIT,
                'account_source' => $rule->debit_source,
                'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
            ],
            [
                'line_side' => AccountingRuleLine::SIDE_CREDIT,
                'account_source' => $rule->credit_source,
                'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
            ],
        ]);
    }

    public function requiresSplitAmounts(AccountingRule $rule): bool
    {
        return $this->effectiveLines($rule)->contains(fn ($line): bool => in_array(
            $this->lineValue($line, 'amount_basis'),
            [AccountingRuleLine::BASIS_PAID, AccountingRuleLine::BASIS_DUE],
            true,
        ));
    }

    public function requiresMoney(AccountingRule $rule): bool
    {
        return $this->effectiveLines($rule)->contains(fn ($line): bool => $this->lineValue($line, 'account_source') === AccountingRule::SOURCE_SELECTED_MONEY);
    }

    public function requiresParty(AccountingRule $rule): bool
    {
        return $this->effectiveLines($rule)->contains(fn ($line): bool => in_array(
            $this->lineValue($line, 'account_source'),
            [AccountingRule::SOURCE_PARTY_RECEIVABLE, AccountingRule::SOURCE_PARTY_PAYABLE],
            true,
        ));
    }

    public function lineValue(AccountingRuleLine|array $line, string $field): string
    {
        return $line instanceof AccountingRuleLine ? (string) $line->{$field} : (string) ($line[$field] ?? '');
    }

    private function minorUnits(string $amount, int $scale): int
    {
        $scale = max(0, min(2, $scale));
        $normalized = $this->decimalAmount->normalize($amount, $scale);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
        $minor = ((int) $whole * (10 ** $scale)) + (int) str_pad(substr($decimal, 0, $scale), $scale, '0');

        return $negative ? -$minor : $minor;
    }

    private function formatMinor(int $minor, int $scale): string
    {
        $scale = max(0, min(2, $scale));
        $negative = $minor < 0;
        $minor = abs($minor);

        if ($scale === 0) {
            return ($negative ? '-' : '').(string) $minor;
        }

        $base = 10 ** $scale;
        $whole = intdiv($minor, $base);
        $decimal = str_pad((string) ($minor % $base), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$decimal;
    }
}
