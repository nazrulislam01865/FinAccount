<?php

namespace App\Services\Accounting;

use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class JournalBuilder
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    /**
     * @return array<int, array{account: \App\Models\ChartOfAccount, source: string, debit: string, credit: string}>
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
}
