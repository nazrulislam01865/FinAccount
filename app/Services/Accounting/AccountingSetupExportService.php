<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\ChartOfAccount;
use App\Models\TransactionHead;
use App\Services\Exports\XlsxTableExporter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AccountingSetupExportService
{
    public function __construct(
        private readonly AccountingOptionService $optionService,
        private readonly XlsxTableExporter $exporter,
    ) {}

    public function chartOfAccounts(int $companyId, string $companyName): BinaryFileResponse
    {
        $accounts = ChartOfAccount::query()
            ->with('parent:id,code,name')
            ->where('company_id', $companyId)
            ->orderBy('level')
            ->orderBy('code')
            ->get();

        $rows = $this->hierarchicalAccounts($accounts)
            ->map(fn (ChartOfAccount $account): array => [
                $account->code,
                $account->name,
                'Level '.$account->level,
                $account->parent ? $account->parent->code.' — '.$account->parent->name : ((int) $account->level === 1 ? 'None — top level' : 'Unassigned'),
                $account->type,
                $account->normal_balance,
                $account->is_active ? 'Active' : 'Inactive',
            ])
            ->all();

        return $this->exporter->download(
            'hisebghor_chart_of_accounts_'.now()->format('Y-m-d_His').'.xlsx',
            'Chart of Accounts',
            'Chart of Accounts',
            $companyName,
            ['Code', 'Account Name', 'Level', 'Parent Account', 'Type', 'Normal Balance', 'Status'],
            $rows,
            [16, 34, 13, 34, 18, 18, 14],
        );
    }

    public function transactionHeads(int $companyId, string $companyName): BinaryFileResponse
    {
        $categoryLabels = $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $settlementLabels = $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE);
        $partyTypeLabels = $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE);

        $rows = TransactionHead::query()
            ->with('postingAccount:id,code,name')
            ->where('company_id', $companyId)
            ->orderBy('category')
            ->orderBy('code')
            ->get()
            ->map(fn (TransactionHead $head): array => [
                $head->code,
                $head->name,
                $categoryLabels[$head->category] ?? $head->category,
                $head->postingAccount?->code ?? '',
                $head->postingAccount?->name ?? 'Not linked',
                collect($head->allowedSettlementCodes())
                    ->map(fn (string $value): string => $settlementLabels[$value] ?? $value)
                    ->join(', ') ?: 'Not configured',
                $partyTypeLabels[$head->party_type] ?? ($head->party_type ?: 'Any'),
                $head->is_active ? 'Active' : 'Inactive',
            ])
            ->all();

        return $this->exporter->download(
            'hisebghor_transaction_heads_'.now()->format('Y-m-d_His').'.xlsx',
            'Transaction Heads',
            'Transaction Heads',
            $companyName,
            ['Code', 'Head Name', 'Transaction Type', 'Linked Account Code', 'Linked Account Name', 'Allowed Payment Types', 'Party Type', 'Status'],
            $rows,
            [20, 32, 25, 22, 34, 44, 18, 14],
        );
    }

    public function accountingRules(int $companyId, string $companyName): BinaryFileResponse
    {
        $categoryLabels = $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $settlementLabels = $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE);
        $sourceLabels = $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE);
        $partyTypeLabels = $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE);
        $amountBasisLabels = [
            AccountingRuleLine::BASIS_TOTAL => 'Total Amount',
            AccountingRuleLine::BASIS_PAID => 'Paid/Received Now',
            AccountingRuleLine::BASIS_DUE => 'Remaining Due',
        ];

        $rows = AccountingRule::query()
            ->with(['lines', 'transactionHead'])
            ->where('company_id', $companyId)
            ->orderBy('category')
            ->orderBy('settlement_type')
            ->orderBy('code')
            ->get()
            ->map(function (AccountingRule $rule) use ($categoryLabels, $settlementLabels, $sourceLabels, $partyTypeLabels, $amountBasisLabels): array {
                $posting = $rule->lines
                    ->values()
                    ->map(function (AccountingRuleLine $line, int $index) use ($sourceLabels, $amountBasisLabels): string {
                        $direction = $line->line_side === AccountingRuleLine::SIDE_DEBIT ? 'Value goes to' : 'Value comes from';
                        $source = $sourceLabels[$line->account_source] ?? $line->account_source;
                        $basis = $amountBasisLabels[$line->amount_basis] ?? $line->amount_basis;

                        return ($index + 1).'. '.$direction.': '.$source.' ('.$basis.')';
                    })
                    ->join("\n");

                $requirements = collect([
                    $rule->money_required ? 'Cash / Bank / Mobile Account' : null,
                    $rule->party_required ? ($partyTypeLabels[$rule->party_type] ?? ($rule->party_type ?: 'Party')) : null,
                ])->filter()->join(', ');

                return [
                    $rule->code,
                    $rule->name,
                    $categoryLabels[$rule->category] ?? $rule->category,
                    $rule->transactionHead?->name ?? 'All '.($categoryLabels[$rule->category] ?? $rule->category).' Heads',
                    $settlementLabels[$rule->settlement_type] ?? $rule->settlement_type,
                    $posting !== '' ? $posting : 'No posting lines configured',
                    $requirements !== '' ? $requirements : 'No additional selection',
                    $rule->is_active ? 'Active' : 'Inactive',
                ];
            })
            ->all();

        return $this->exporter->download(
            'hisebghor_accounting_rules_'.now()->format('Y-m-d_His').'.xlsx',
            'Accounting Rules',
            'Accounting Rules',
            $companyName,
            ['Code', 'Accounting Rule', 'Transaction Type', 'Rule Scope', 'Payment Type', 'Automatic Posting', 'Required Information', 'Status'],
            $rows,
            [20, 32, 25, 32, 24, 56, 40, 14],
        );
    }

    /**
     * @param Collection<int, ChartOfAccount> $accounts
     * @return Collection<int, ChartOfAccount>
     */
    private function hierarchicalAccounts(Collection $accounts): Collection
    {
        $childrenByParent = $accounts
            ->whereNotNull('parent_id')
            ->groupBy(fn (ChartOfAccount $account): int => (int) $account->parent_id);
        $result = collect();
        $seen = [];

        $append = function (ChartOfAccount $account) use (&$append, &$result, &$seen, $childrenByParent): void {
            if (isset($seen[$account->id])) {
                return;
            }

            $seen[$account->id] = true;
            $result->push($account);

            foreach ($childrenByParent->get((int) $account->id, collect())->sortBy('code') as $child) {
                $append($child);
            }
        };

        foreach ($accounts->where('level', 1)->whereNull('parent_id')->sortBy('code') as $root) {
            $append($root);
        }

        foreach ($accounts->sortBy(fn (ChartOfAccount $account): string => sprintf('%d-%020s', $account->level, $account->code)) as $account) {
            $append($account);
        }

        return $result->values();
    }
}
