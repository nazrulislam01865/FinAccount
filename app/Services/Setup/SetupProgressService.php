<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherNumberingRule;

class SetupProgressService
{
    /**
     * The setup progress bar is intentionally tied to setup completion, not
     * to the currently opened setup page. Keep these keys aligned with the
     * sidebar setup menu and the setup-progress Blade component.
     */
    public function definitions(): array
    {
        return [
            1 => [
                'key' => 'company',
                'label' => 'Company Setup',
                'route' => 'setup.company',
            ],
            2 => [
                'key' => 'chart_of_accounts',
                'label' => 'Chart of Accounts',
                'route' => 'setup.chart-of-accounts',
            ],
            3 => [
                'key' => 'cash_bank_accounts',
                'label' => 'Cash / Bank Setup',
                'route' => 'setup.cash-bank-accounts',
            ],
            4 => [
                'key' => 'parties',
                'label' => 'Party / Person Setup',
                'route' => 'setup.parties',
            ],
            5 => [
                'key' => 'transaction_heads',
                'label' => 'Transaction Head Setup',
                'route' => 'setup.transaction-heads',
            ],
            6 => [
                'key' => 'accounting_rules',
                'label' => 'Accounting Rules Setup',
                'route' => 'setup.accounting-rules-setup',
                'legacy_route' => 'setup.ledger-mapping',
            ],
            7 => [
                'key' => 'opening_balances',
                'label' => 'Opening Balance Setup',
                'route' => 'setup.opening-balances',
                // Opening balance rows are loaded from Chart of Accounts,
                // Cash/Bank, and Party/Person setup. It remains navigable,
                // but it must not block the global setup progress from
                // reaching 100% when all manual setup modules are complete.
                'auto_generated' => true,
            ],
            8 => [
                'key' => 'voucher_numbering',
                'label' => 'Voucher Numbering',
                'route' => 'setup.voucher-numbering',
            ],
        ];
    }

    public function steps(): array
    {
        $completion = $this->completionMap();

        return collect($this->definitions())
            ->map(function (array $step) use ($completion) {
                $step['completed'] = (bool) ($completion[$step['key']] ?? false);

                return $step;
            })
            ->all();
    }

    public function percent(?array $steps = null): int
    {
        $requiredSteps = $this->requiredSteps($steps ?? $this->steps());
        $total = max(1, count($requiredSteps));
        $completed = collect($requiredSteps)->where('completed', true)->count();

        return (int) round(($completed / $total) * 100);
    }

    public function completedCount(?array $steps = null): int
    {
        return collect($this->requiredSteps($steps ?? $this->steps()))
            ->where('completed', true)
            ->count();
    }

    public function totalCount(?array $steps = null): int
    {
        return count($this->requiredSteps($steps ?? $this->steps()));
    }

    private function requiredSteps(array $steps): array
    {
        return collect($steps)
            ->reject(fn (array $step) => (bool) ($step['auto_generated'] ?? false))
            ->values()
            ->all();
    }

    private function completionMap(): array
    {
        $activeFinancialYear = FinancialYear::query()
            ->where('status', 'Active')
            ->where('is_active', true)
            ->first();

        $activeFinancialYear ??= FinancialYear::query()
            ->where('status', 'Active')
            ->latest('start_date')
            ->first();

        return [
            'company' => $this->companyCompleted(),
            'chart_of_accounts' => $this->chartOfAccountsCompleted(),
            'cash_bank_accounts' => $this->cashBankCompleted(),
            'parties' => $this->partiesCompleted(),
            'transaction_heads' => $this->transactionHeadsCompleted(),
            'accounting_rules' => $this->accountingRulesCompleted(),
            'opening_balances' => $this->openingBalancesCompleted($activeFinancialYear),
            'voucher_numbering' => $this->voucherNumberingCompleted($activeFinancialYear),
        ];
    }

    private function companyCompleted(): bool
    {
        return Company::query()
            ->whereNotNull('company_name')
            ->whereNotNull('currency_id')
            ->whereNotNull('financial_year_start')
            ->whereNotNull('financial_year_end')
            ->exists();
    }

    private function chartOfAccountsCompleted(): bool
    {
        return ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->exists();
    }

    private function cashBankCompleted(): bool
    {
        return CashBankAccount::query()
            ->where('status', 'Active')
            ->whereNotNull('linked_ledger_account_id')
            ->exists();
    }

    private function partiesCompleted(): bool
    {
        return Party::query()
            ->where('status', 'Active')
            ->whereNotNull('linked_ledger_account_id')
            ->exists();
    }

    private function transactionHeadsCompleted(): bool
    {
        return TransactionHead::query()
            ->where('status', 'Active')
            ->exists();
    }

    private function accountingRulesCompleted(): bool
    {
        return LedgerMappingRule::query()
            ->where('status', 'Active')
            ->whereHas('transactionHead', fn ($query) => $query->where('status', 'Active'))
            ->whereHas('settlementType', fn ($query) => $query->where('status', 'Active'))
            ->whereHas('debitAccount', fn ($query) => $query
                ->where('status', 'Active')
                ->where('posting_allowed', true))
            ->whereHas('creditAccount', fn ($query) => $query
                ->where('status', 'Active')
                ->where('posting_allowed', true))
            ->exists();
    }

    private function openingBalancesCompleted(?FinancialYear $financialYear): bool
    {
        if (! $financialYear) {
            return false;
        }

        if (OpeningBalance::query()
            ->where('financial_year_id', $financialYear->id)
            ->exists()) {
            return true;
        }

        // Opening Balance Setup is populated from the opening balance values
        // already entered in Chart of Accounts, Cash/Bank Setup, and
        // Party/Person Setup. Therefore, non-zero source balances mean the
        // opening page has data available for review/posting; they should not
        // keep the global setup progress stuck at 88%.
        return $this->chartOfAccountsCompleted();
    }

    private function voucherNumberingCompleted(?FinancialYear $financialYear): bool
    {
        if (! $financialYear) {
            return false;
        }

        $requiredTypes = collect(VoucherNumberingRule::VOUCHER_TYPES);

        $activeTypes = VoucherNumberingRule::query()
            ->where('financial_year_id', $financialYear->id)
            ->where('status', 'Active')
            ->whereIn('voucher_type', $requiredTypes->all())
            ->pluck('voucher_type')
            ->unique()
            ->values();

        return $requiredTypes->diff($activeTypes)->isEmpty();
    }
}
