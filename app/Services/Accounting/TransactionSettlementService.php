<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TransactionSettlementService
{
    public function __construct(private readonly DecimalAmount $decimalAmount) {}

    /**
     * Determine payment handling from the total and the amount paid/received now.
     *
     * When paid_amount is present it is always the source of truth:
     * 0 = CREDIT, less than total = PARTIAL, equal to total = CASH.
     * The legacy settlement_type value is only used for internal/older callers that
     * do not send paid_amount at all.
     *
     * @param array<string, mixed> $data
     */
    public function inferSettlementType(string $totalAmount, array $data, int $scale = 2): string
    {
        $totalMinor = $this->minorUnits($totalAmount, $scale);

        if ($totalMinor <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $hasPaidAmount = array_key_exists('paid_amount', $data)
            && $data['paid_amount'] !== null
            && $data['paid_amount'] !== '';

        if (! $hasPaidAmount) {
            $legacyType = strtoupper(trim((string) ($data['settlement_type'] ?? '')));

            if (in_array($legacyType, [
                TransactionTypes::CASH,
                TransactionTypes::CREDIT,
                TransactionTypes::PARTIAL,
            ], true)) {
                return $legacyType;
            }

            return TransactionTypes::CASH;
        }

        $paidAmount = $this->decimalAmount->normalize($data['paid_amount'], $scale);
        $paidMinor = $this->minorUnits($paidAmount, $scale);

        if ($paidMinor < 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'The amount paid or received now cannot be negative.',
            ]);
        }

        if ($paidMinor > $totalMinor) {
            throw ValidationException::withMessages([
                'paid_amount' => 'The amount paid or received now cannot be greater than the total amount.',
            ]);
        }

        if ($paidMinor === 0) {
            return TransactionTypes::CREDIT;
        }

        if ($paidMinor < $totalMinor) {
            return TransactionTypes::PARTIAL;
        }

        return TransactionTypes::CASH;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{settlement_type: string, paid_amount: string, due_amount: string, due_date: ?string}
     */
    public function prepare(string $totalAmount, array $data, int $scale = 2, ?string $legacySettlementType = null): array
    {
        if (
            ! array_key_exists('paid_amount', $data)
            && $legacySettlementType !== null
            && ! array_key_exists('settlement_type', $data)
        ) {
            $data['settlement_type'] = $legacySettlementType;
        }

        $settlementType = $this->inferSettlementType($totalAmount, $data, $scale);
        $totalMinor = $this->minorUnits($totalAmount, $scale);

        if ($settlementType === TransactionTypes::CASH) {
            return [
                'settlement_type' => TransactionTypes::CASH,
                'paid_amount' => $this->formatMinor($totalMinor, $scale),
                'due_amount' => $this->formatMinor(0, $scale),
                'due_date' => null,
            ];
        }

        if ($settlementType === TransactionTypes::CREDIT) {
            return [
                'settlement_type' => TransactionTypes::CREDIT,
                'paid_amount' => $this->formatMinor(0, $scale),
                'due_amount' => $this->formatMinor($totalMinor, $scale),
                'due_date' => filled($data['due_date'] ?? null) ? (string) $data['due_date'] : null,
            ];
        }

        $paidAmount = $this->decimalAmount->normalize($data['paid_amount'] ?? 0, $scale);
        $paidMinor = $this->minorUnits($paidAmount, $scale);

        if ($paidMinor <= 0 || $paidMinor >= $totalMinor) {
            throw ValidationException::withMessages([
                'paid_amount' => 'For a partial transaction, enter an amount greater than zero and less than the total amount.',
            ]);
        }

        return [
            'settlement_type' => TransactionTypes::PARTIAL,
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
        return $rule->settlement_type === TransactionTypes::PARTIAL
            || $this->effectiveLines($rule)->contains(fn ($line): bool => in_array(
                $this->lineValue($line, 'amount_basis'),
                [AccountingRuleLine::BASIS_PAID, AccountingRuleLine::BASIS_DUE],
                true,
            ));
    }

    public function requiresMoney(AccountingRule $rule): bool
    {
        return $rule->money_required || $this->effectiveLines($rule)->contains(
            fn ($line): bool => $this->lineValue($line, 'account_source') === AccountingRule::SOURCE_SELECTED_MONEY,
        );
    }

    public function requiresParty(AccountingRule $rule): bool
    {
        return $rule->party_required || $this->effectiveLines($rule)->contains(fn ($line): bool => in_array(
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
