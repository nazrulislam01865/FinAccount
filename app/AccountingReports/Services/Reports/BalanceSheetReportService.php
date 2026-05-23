<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Support\Collection;

class BalanceSheetReportService extends BaseVoucherDetailReportService
{
    public function build(array $filters = []): array
    {
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $asOfDate = $filters['as_of_date'] ?? $filters['to_date'] ?? now()->toDateString();
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);
        $includeInactive = (bool) ($filters['include_inactive_accounts'] ?? false);
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        $allRows = $this->ledgerBalanceRows($companyId, null, $asOfDate, $includeInactive)
            ->filter(function (object $row) use ($includeZero, $search) {
                $isBalanceSheetAccount = in_array($row->account_type, ['Asset', 'Liability', 'Equity'], true)
                    || in_array($row->ledger_type, ['Asset', 'Loan', 'Liability', 'Equity', 'Equity Contra'], true);

                if (! $isBalanceSheetAccount) {
                    return false;
                }

                if (! $includeZero && abs((float) $row->report_balance) < 0.01) {
                    return false;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower($row->account_code . ' ' . $row->account_name . ' ' . $row->parent_account_name);
                    return str_contains($haystack, $search);
                }

                return true;
            })
            ->map(function (object $row) {
                $row->section = $this->sectionFor($row);
                return $row;
            })
            ->values();

        $assets = round((float) $allRows->where('section', 'Assets')->sum('report_balance'), 2);
        $liabilities = round((float) $allRows->where('section', 'Liabilities')->sum('report_balance'), 2);
        $equity = round((float) $allRows->where('section', 'Equity')->sum('report_balance'), 2);
        $profit = $this->netProfitUntil($companyId, $asOfDate);
        $liabilitiesAndEquity = round($liabilities + $equity + $profit, 2);
        $difference = round($assets - $liabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOfDate,
            'rows' => $allRows,
            'groups' => $allRows->groupBy('section'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_profit' => $profit,
            'liabilities_and_equity' => $liabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    private function sectionFor(object $row): string
    {
        if ($row->account_type === 'Asset' || in_array($row->ledger_type, ['Asset', 'Cash', 'Bank', 'Party Control'], true)) {
            if ($row->account_type !== 'Liability') {
                return 'Assets';
            }
        }

        if ($row->account_type === 'Equity' || in_array($row->ledger_type, ['Equity', 'Equity Contra'], true)) {
            return 'Equity';
        }

        return 'Liabilities';
    }

    private function netProfitUntil(?int $companyId, string $asOfDate): float
    {
        $rows = $this->ledgerBalanceRows($companyId, null, $asOfDate, true)
            ->filter(fn (object $row) => in_array($row->account_type, ['Income', 'Expense'], true));

        $income = (float) $rows->where('account_type', 'Income')->sum('report_balance');
        $expense = (float) $rows->where('account_type', 'Expense')->sum('report_balance');

        return round($income - $expense, 2);
    }
}
