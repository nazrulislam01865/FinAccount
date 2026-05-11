<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Party;
use App\Models\TransactionHead;

class SetupProgressService
{
    public function steps(): array
    {
        return [
            'company' => Company::exists(),
            'chart_of_accounts' => ChartOfAccount::exists(),
            'cash_bank_accounts' => CashBankAccount::exists(),
            'parties' => Party::exists(),
            'transaction_heads' => TransactionHead::exists(),
        ];
    }

    public function percent(): int
    {
        $steps = $this->steps();
        return (int) round((collect($steps)->filter()->count() / count($steps)) * 100);
    }
}
