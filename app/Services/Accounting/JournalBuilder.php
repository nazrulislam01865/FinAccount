<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class JournalBuilder
{
    public function __construct(
        private readonly AccountResolver $accountResolver,
        private readonly TransactionSettlementService $settlementService,
    ) {}

    /**
     * @return array<int, array{account: ChartOfAccount, source: string, debit: string, credit: string}>
     */
    public function build(
        TransactionHead $head,
        ?MoneyAccount $moneyAccount,
        ?Party $party,
        string $amount,
    ): array {
        $rule = $head->accountingRule;

        $debitAccount = $this->accountResolver->resolve(
            $rule->debit_source,
            $head,
            $moneyAccount,
            $party,
        );

        $creditAccount = $this->accountResolver->resolve(
            $rule->credit_source,
            $head,
            $moneyAccount,
            $party,
        );

        if ($debitAccount->is($creditAccount)) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'Debit and credit accounts cannot be the same account.',
            ]);
        }

        return [
            [
                'account' => $debitAccount,
                'source' => $rule->debit_source,
                'debit' => $amount,
                'credit' => '0.00',
            ],
            [
                'account' => $creditAccount,
                'source' => $rule->credit_source,
                'debit' => '0.00',
                'credit' => $amount,
            ],
        ];
    }

    /**
     * Build the journal directly from accounting rule lines.
     *
     * A normal rule normally has two lines with amount_basis=total.
     * A split rule has lines like paid + due = total, for example:
     *   Dr Selected Money Account       paid
     *   Dr Party Receivable             due
     *       Cr Head Posting Account     total
     *
     * @return array<int, array{account: ChartOfAccount, source: string, debit: string, credit: string}>
     */
    public function buildFromRule(
        TransactionHead $head,
        ?MoneyAccount $moneyAccount,
        ?Party $party,
        string $totalAmount,
        ?string $paidAmount = null,
        ?string $dueAmount = null,
    ): array {
        $rule = $head->accountingRule;
        $ruleLines = $this->settlementService->effectiveLines($rule);

        if ($ruleLines->isEmpty()) {
            return $this->build($head, $moneyAccount, $party, $totalAmount);
        }

        $lines = [];

        foreach ($ruleLines as $ruleLine) {
            $lineSide = $this->settlementService->lineValue($ruleLine, 'line_side');
            $source = $this->settlementService->lineValue($ruleLine, 'account_source');
            $basis = $this->settlementService->lineValue($ruleLine, 'amount_basis');
            $amount = match ($basis) {
                AccountingRuleLine::BASIS_TOTAL => $totalAmount,
                AccountingRuleLine::BASIS_PAID => $paidAmount,
                AccountingRuleLine::BASIS_DUE => $dueAmount,
                default => null,
            };

            if ($amount === null) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'The selected accounting rule requires a paid/due amount that could not be calculated.',
                ]);
            }

            $account = $this->accountResolver->resolve(
                $source,
                $head,
                $moneyAccount,
                $party,
            );

            $lines[] = [
                'account' => $account,
                'source' => $source,
                'debit' => $lineSide === AccountingRuleLine::SIDE_DEBIT ? $amount : '0.00',
                'credit' => $lineSide === AccountingRuleLine::SIDE_CREDIT ? $amount : '0.00',
            ];
        }

        $this->assertBalanced($lines);
        $this->assertNoSameAccountOnBothSides($lines);

        return $lines;
    }

    /**
     * Compatibility wrapper for older callers. New transaction posting uses buildFromRule().
     *
     * @return array<int, array{account: ChartOfAccount, source: string, debit: string, credit: string}>
     */
    public function buildPartial(
        TransactionHead $head,
        ?MoneyAccount $moneyAccount,
        ?Party $party,
        string $totalAmount,
        string $paidAmount,
        string $dueAmount,
    ): array {
        return $this->buildFromRule($head, $moneyAccount, $party, $totalAmount, $paidAmount, $dueAmount);
    }

    /** @param array<int, array{debit: string, credit: string}> $lines */
    private function assertBalanced(array $lines): void
    {
        $totalDebit = round(array_sum(array_map(fn (array $line): float => (float) $line['debit'], $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn (array $line): float => (float) $line['credit'], $lines)), 2);

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The selected accounting rule is not balanced. Check its debit, credit, and amount basis lines.',
            ]);
        }
    }

    /** @param array<int, array{account: ChartOfAccount, debit: string, credit: string}> $lines */
    private function assertNoSameAccountOnBothSides(array $lines): void
    {
        $debitAccountIds = collect($lines)
            ->filter(fn (array $line): bool => (float) $line['debit'] > 0)
            ->pluck('account.id')
            ->all();

        $creditAccountIds = collect($lines)
            ->filter(fn (array $line): bool => (float) $line['credit'] > 0)
            ->pluck('account.id')
            ->all();

        if (array_intersect($debitAccountIds, $creditAccountIds) !== []) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The selected accounting rule resolves the same COA on both debit and credit side. Check money, party, and head COA mappings.',
            ]);
        }
    }
}
