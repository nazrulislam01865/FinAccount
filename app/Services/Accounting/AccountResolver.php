<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class AccountResolver
{
    public function resolve(
        string $source,
        TransactionHead $head,
        ?MoneyAccount $moneyAccount,
        ?Party $party,
    ): ChartOfAccount {
        $account = match ($source) {
            AccountingRule::SOURCE_SELECTED_MONEY => $moneyAccount?->chartOfAccount,
            AccountingRule::SOURCE_HEAD_ACCOUNT => $head->postingAccount,
            AccountingRule::SOURCE_PARTY_RECEIVABLE => $party?->receivableAccount,
            AccountingRule::SOURCE_PARTY_PAYABLE => $party?->payableAccount,
            default => null,
        };

        if (! $account) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The accounting account could not be resolved. Check the selected transaction head, money account, and party mappings.',
            ]);
        }

        if ($account->company_id !== $head->company_id || ! $account->is_active) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The resolved accounting account is inactive or belongs to another company.',
            ]);
        }

        return $account;
    }
}
