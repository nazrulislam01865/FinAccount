<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherNumberingRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EntityDeleteService
{
    /**
     * Build a deletion preview for the selected Chart of Accounts branch.
     *
     * The preview includes every descendant, historical references that remain
     * protected, and configuration references that will be cleared
     * rather than deleting unrelated setup records.
     *
     * @return array<string, mixed>
     */
    public function chartOfAccountDeleteImpact(ChartOfAccount $account): array
    {
        return $this->buildChartOfAccountDeleteImpact(
            collect([$account]),
            $this->chartOfAccountSubtree($account),
            false
        );
    }

    /**
     * Build one combined preview for multiple selected CoA accounts.
     *
     * When both a parent and one of its descendants are selected, only the
     * parent is treated as a deletion root. The descendant is still included
     * in the affected account list through the parent's subtree.
     *
     * @param array<int, int|string> $accountIds
     * @return array<string, mixed>
     */
    public function chartOfAccountsDeleteImpact(array $accountIds): array
    {
        $roots = $this->normalizedChartOfAccountRoots($accountIds);

        if ($roots->isEmpty()) {
            throw new RuntimeException('Select at least one valid Chart of Accounts record to delete.');
        }

        $accounts = $roots
            ->flatMap(fn (ChartOfAccount $root) => $this->chartOfAccountSubtree($root))
            ->unique(fn (ChartOfAccount $item) => (int) $item->id)
            ->values();

        return $this->buildChartOfAccountDeleteImpact($roots, $accounts, true);
    }

    /**
     * Delete one account or an explicitly confirmed complete CoA branch.
     * Configuration mappings are cleared and preserved for reassignment;
     * historical accounting records are never modified by this operation.
     *
     * @return array<string, mixed>
     */
    public function deleteChartOfAccount(ChartOfAccount $account, bool $cascadeConfirmed = false): array
    {
        $impact = $this->chartOfAccountDeleteImpact($account);

        if (! $impact['can_delete']) {
            throw new RuntimeException($impact['blocked_message']);
        }

        if ($impact['requires_confirmation'] && ! $cascadeConfirmed) {
            throw new RuntimeException('This account has lower CoA levels or configuration mappings. Confirm the complete branch deletion before continuing.');
        }

        return $this->performChartOfAccountDeletion($impact);
    }

    /**
     * Delete all selected CoA roots and their descendants as one atomic action.
     *
     * @param array<int, int|string> $accountIds
     * @return array<string, mixed>
     */
    public function deleteChartOfAccounts(array $accountIds, bool $cascadeConfirmed = false): array
    {
        $impact = $this->chartOfAccountsDeleteImpact($accountIds);

        if (! $impact['can_delete']) {
            throw new RuntimeException($impact['blocked_message']);
        }

        if (! $cascadeConfirmed) {
            throw new RuntimeException('Confirm deletion of all selected Chart of Accounts branches before continuing.');
        }

        return $this->performChartOfAccountDeletion($impact);
    }

    /**
     * @param Collection<int, ChartOfAccount> $roots
     * @param Collection<int, ChartOfAccount> $accounts
     * @return array<string, mixed>
     */
    private function buildChartOfAccountDeleteImpact(
        Collection $roots,
        Collection $accounts,
        bool $forceConfirmation
    ): array {
        $accounts = $accounts
            ->unique(fn (ChartOfAccount $item) => (int) $item->id)
            ->values();
        $accountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->values();
        $legacyRules = $this->affectedLegacyAccountingRules($accountIds);
        $legacyRuleIds = $legacyRules->pluck('id')->map(fn ($id) => (int) $id)->values();
        $newRules = $this->affectedAccountingRules($accountIds, $legacyRuleIds);

        $newRuleLegacyIds = $newRules
            ->pluck('legacy_ledger_mapping_rule_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $displayRules = $newRules
            ->map(fn ($rule) => [
                'id' => (int) $rule->id,
                'rule_code' => (string) ($rule->rule_code ?: ('Rule #' . $rule->id)),
                'rule_name' => (string) ($rule->rule_name ?: 'Accounting Rule'),
                'source' => 'Accounting Rule',
            ])
            ->concat(
                $legacyRules
                    ->reject(fn ($rule) => in_array((int) $rule->id, $newRuleLegacyIds, true))
                    ->map(fn ($rule) => [
                        'id' => (int) $rule->id,
                        'rule_code' => (string) ($rule->rule_code ?: ('Rule #' . $rule->id)),
                        'rule_name' => (string) ($rule->rule_name ?: 'Accounting Rule'),
                        'source' => 'Legacy Mapping Rule',
                    ])
            )
            ->unique(fn (array $rule) => $rule['source'] . ':' . $rule['id'])
            ->values();

        $levels = $accounts
            ->groupBy(fn (ChartOfAccount $item) => $this->effectiveCoaLevel($item))
            ->sortKeys()
            ->map(function (Collection $items, int|string $level) {
                $level = (int) $level;

                return [
                    'level' => $level,
                    'label' => ChartOfAccount::COA_LEVELS[$level] ?? ('Level ' . $level),
                    'count' => $items->count(),
                    'accounts' => $items
                        ->sortBy('account_code', SORT_NATURAL)
                        ->map(fn (ChartOfAccount $item) => [
                            'id' => (int) $item->id,
                            'account_code' => (string) $item->account_code,
                            'account_name' => (string) $item->account_name,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $rootRows = $roots
            ->unique(fn (ChartOfAccount $item) => (int) $item->id)
            ->sort(function (ChartOfAccount $left, ChartOfAccount $right) {
                $levelComparison = $this->effectiveCoaLevel($left) <=> $this->effectiveCoaLevel($right);

                return $levelComparison !== 0
                    ? $levelComparison
                    : strnatcasecmp((string) $left->account_code, (string) $right->account_code);
            })
            ->map(fn (ChartOfAccount $item) => [
                'id' => (int) $item->id,
                'account_code' => (string) $item->account_code,
                'account_name' => (string) $item->account_name,
                'display_name' => (string) $item->display_name,
                'coa_level' => $this->effectiveCoaLevel($item),
            ])
            ->values();

        $reassignments = $this->chartOfAccountReassignmentImpact($accountIds, $displayRules);
        $historyReferences = $this->chartOfAccountHistoryImpact($accountIds);

        // Every delete requires explicit user approval. Historical posting rows do
        // not block the operation: the CoA records are soft-deleted while their
        // immutable accounting references remain untouched for reports and audit.
        $requiresConfirmation = true;

        $impact = [
            'root_id' => (int) ($rootRows->first()['id'] ?? 0),
            'root_display_name' => $rootRows->count() === 1
                ? (string) $rootRows->first()['display_name']
                : $rootRows->count() . ' selected CoA branches',
            'selected_roots' => $rootRows->all(),
            'selected_root_count' => $rootRows->count(),
            'account_ids' => $accountIds->all(),
            'account_count' => $accounts->count(),
            'descendant_count' => max(0, $accounts->count() - $rootRows->count()),
            'levels' => $levels->all(),
            'accounting_rule_ids' => $newRules->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'legacy_rule_ids' => $legacyRuleIds
                ->concat($newRuleLegacyIds)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'rules' => $displayRules->all(),
            'rule_count' => $displayRules->count(),
            'reassignments' => $reassignments,
            'reassignment_count' => collect($reassignments)->sum('count'),
            'history_references' => $historyReferences,
            'history_reference_count' => collect($historyReferences)->sum('count'),
            // Historical rows are preserved, not treated as blockers.
            'blockers' => [],
            'can_delete' => true,
            'requires_confirmation' => $requiresConfirmation,
        ];

        $impact['confirmation_message'] = $this->chartOfAccountConfirmationMessage($impact);
        $impact['blocked_message'] = $this->chartOfAccountBlockedMessage($impact);
        $impact['reassignment_message'] = $this->chartOfAccountReassignmentMessage($impact);

        return $impact;
    }

    /**
     * @param array<string, mixed> $impact
     * @return array<string, mixed>
     */
    private function performChartOfAccountDeletion(array $impact): array
    {
        return DB::transaction(function () use ($impact) {
            $accountIds = collect($impact['account_ids'])->map(fn ($id) => (int) $id)->values();
            $accountingRuleIds = collect($impact['accounting_rule_ids'])->map(fn ($id) => (int) $id)->values();
            $legacyRuleIds = collect($impact['legacy_rule_ids'])->map(fn ($id) => (int) $id)->values();

            $this->clearAccountingRuleReferences($accountIds, $accountingRuleIds);
            $this->clearLegacyRuleReferences($accountIds, $legacyRuleIds);
            $this->clearConfigurationLedgerReference('transaction_heads', 'default_primary_ledger_id', $accountIds, true);
            $this->clearConfigurationLedgerReference('cash_bank_accounts', 'linked_ledger_account_id', $accountIds, true);
            $this->clearConfigurationLedgerReference('parties', 'linked_ledger_account_id', $accountIds, true);
            $this->clearConfigurationLedgerReference('party_ledger_mappings', 'chart_of_account_id', $accountIds, true);
            $this->clearConfigurationLedgerReference('party_types', 'default_ledger_account_id', $accountIds, true);

            ChartOfAccount::query()
                ->whereIn('id', $accountIds)
                ->get()
                ->sortByDesc(fn (ChartOfAccount $item) => $this->effectiveCoaLevel($item))
                ->each(fn (ChartOfAccount $item) => $item->delete());

            return [
                'deleted_ids' => $accountIds->all(),
                'deleted_count' => $accountIds->count(),
                'selected_root_count' => (int) ($impact['selected_root_count'] ?? 1),
                'cleared_rule_count' => (int) $impact['rule_count'],
                // Retained for compatibility with any existing front-end code.
                'deleted_rule_count' => 0,
                'affected_rule_ids' => array_values(array_unique(array_merge(
                    $accountingRuleIds->all(),
                    $legacyRuleIds->all()
                ))),
                'reassignments' => $impact['reassignments'],
                'reassignment_count' => (int) $impact['reassignment_count'],
                'reassignment_message' => $impact['reassignment_message'],
                'history_references' => $impact['history_references'] ?? [],
                'history_reference_count' => (int) ($impact['history_reference_count'] ?? 0),
            ];
        });
    }

    /**
     * @param array<int, int|string> $accountIds
     * @return Collection<int, ChartOfAccount>
     */
    private function normalizedChartOfAccountRoots(array $accountIds): Collection
    {
        $selectedIds = collect($accountIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        $selectedSet = array_fill_keys($selectedIds->all(), true);
        $parentMap = ChartOfAccount::query()
            ->get(['id', 'parent_id'])
            ->mapWithKeys(fn (ChartOfAccount $item) => [(int) $item->id => $item->parent_id ? (int) $item->parent_id : null])
            ->all();

        return ChartOfAccount::query()
            ->whereIn('id', $selectedIds)
            ->get()
            ->reject(function (ChartOfAccount $account) use ($selectedSet, $parentMap) {
                $parentId = $account->parent_id ? (int) $account->parent_id : null;
                $guard = 0;

                while ($parentId !== null && $guard < 20) {
                    if (isset($selectedSet[$parentId])) {
                        return true;
                    }

                    $parentId = $parentMap[$parentId] ?? null;
                    $guard++;
                }

                return false;
            })
            ->values();
    }

    /**
     * Clear only the affected ledger fields from modern accounting rule lines.
     * The rule remains available, but is made inactive until a replacement
     * ledger is selected by the user.
     *
     * @param Collection<int, int> $accountIds
     * @param Collection<int, int> $accountingRuleIds
     */
    private function clearAccountingRuleReferences(Collection $accountIds, Collection $accountingRuleIds): void
    {
        if ($accountIds->isNotEmpty()
            && Schema::hasTable('accounting_rule_lines')
            && Schema::hasColumn('accounting_rule_lines', 'ledger_id')) {
            $updates = ['ledger_id' => null];

            if (Schema::hasColumn('accounting_rule_lines', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            DB::table('accounting_rule_lines')
                ->whereIn('accounting_rule_id', $accountingRuleIds)
                ->whereIn('ledger_id', $accountIds)
                ->update($updates);
        }

        $this->deactivateConfigurationRows('accounting_rules', $accountingRuleIds);
    }

    /**
     * Clear only the affected ledger columns from legacy accounting mappings.
     * Other rule conditions and descriptions remain unchanged.
     *
     * @param Collection<int, int> $accountIds
     * @param Collection<int, int> $legacyRuleIds
     */
    private function clearLegacyRuleReferences(Collection $accountIds, Collection $legacyRuleIds): void
    {
        if ($accountIds->isNotEmpty() && Schema::hasTable('ledger_mapping_rules')) {
            foreach ([
                'debit_account_id',
                'credit_account_id',
                'primary_ledger_id',
                'fixed_counter_ledger_id',
            ] as $column) {
                $this->clearConfigurationLedgerReference(
                    'ledger_mapping_rules',
                    $column,
                    $accountIds,
                    false,
                    $legacyRuleIds
                );
            }
        }

        $this->deactivateConfigurationRows('ledger_mapping_rules', $legacyRuleIds);
    }

    /**
     * @param Collection<int, int> $accountIds
     */
    private function clearConfigurationLedgerReference(
        string $table,
        string $column,
        Collection $accountIds,
        bool $deactivateAffectedRows = false,
        ?Collection $rowIds = null
    ): int
    {
        if ($accountIds->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $updates = [$column => null];

        if ($deactivateAffectedRows && Schema::hasColumn($table, 'status')) {
            $updates['status'] = 'Inactive';
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $updates['updated_at'] = now();
        }

        $query = DB::table($table)->whereIn($column, $accountIds);

        if ($rowIds !== null) {
            $query->whereIn('id', $rowIds);
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->update($updates);
    }

    /**
     * @param Collection<int, int> $ids
     */
    private function deactivateConfigurationRows(string $table, Collection $ids): void
    {
        if ($ids->isEmpty() || ! Schema::hasTable($table)) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn($table, 'status')) {
            $updates['status'] = 'Inactive';
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $updates['updated_at'] = now();
        }

        if ($updates !== []) {
            DB::table($table)->whereIn('id', $ids)->update($updates);
        }
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function chartOfAccountSubtree(ChartOfAccount $root): Collection
    {
        $accounts = collect([$root]);
        $seen = [(int) $root->id => true];
        $parentIds = collect([(int) $root->id]);
        $guard = 0;

        while ($parentIds->isNotEmpty() && $guard < 20) {
            $children = ChartOfAccount::query()
                ->whereIn('parent_id', $parentIds)
                ->orderBy('coa_level')
                ->orderBy('account_code')
                ->get()
                ->reject(fn (ChartOfAccount $item) => isset($seen[(int) $item->id]))
                ->values();

            if ($children->isEmpty()) {
                break;
            }

            foreach ($children as $child) {
                $seen[(int) $child->id] = true;
                $accounts->push($child);
            }

            $parentIds = $children->pluck('id')->map(fn ($id) => (int) $id)->values();
            $guard++;
        }

        return $accounts->values();
    }

    private function effectiveCoaLevel(ChartOfAccount $account): int
    {
        return (int) ($account->coa_level ?: ($account->account_level === 'Ledger' ? 4 : 1));
    }

    /**
     * @param Collection<int, int> $accountIds
     * @return Collection<int, object>
     */
    private function affectedAccountingRules(Collection $accountIds, Collection $legacyRuleIds): Collection
    {
        if (! Schema::hasTable('accounting_rules')) {
            return collect();
        }

        $ruleIds = collect();

        if ($accountIds->isNotEmpty() && Schema::hasTable('accounting_rule_lines')) {
            $ruleIds = $ruleIds->concat(
                DB::table('accounting_rule_lines')
                    ->whereIn('ledger_id', $accountIds)
                    ->pluck('accounting_rule_id')
            );
        }

        if ($legacyRuleIds->isNotEmpty()
            && Schema::hasColumn('accounting_rules', 'legacy_ledger_mapping_rule_id')) {
            $ruleIds = $ruleIds->concat(
                DB::table('accounting_rules')
                    ->whereIn('legacy_ledger_mapping_rule_id', $legacyRuleIds)
                    ->pluck('id')
            );
        }

        $ruleIds = $ruleIds->filter()->unique()->values();

        if ($ruleIds->isEmpty()) {
            return collect();
        }

        return DB::table('accounting_rules')
            ->whereIn('id', $ruleIds)
            ->when(Schema::hasColumn('accounting_rules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('rule_code')
            ->get(['id', 'legacy_ledger_mapping_rule_id', 'rule_code', 'rule_name']);
    }

    /**
     * @param Collection<int, int> $accountIds
     * @return Collection<int, object>
     */
    private function affectedLegacyAccountingRules(Collection $accountIds): Collection
    {
        if ($accountIds->isEmpty() || ! Schema::hasTable('ledger_mapping_rules')) {
            return collect();
        }

        $columns = collect([
            'debit_account_id',
            'credit_account_id',
            'primary_ledger_id',
            'fixed_counter_ledger_id',
        ])->filter(fn (string $column) => Schema::hasColumn('ledger_mapping_rules', $column))->values();

        if ($columns->isEmpty()) {
            return collect();
        }

        $query = DB::table('ledger_mapping_rules')
            ->when(Schema::hasColumn('ledger_mapping_rules', 'deleted_at'), fn ($builder) => $builder->whereNull('deleted_at'))
            ->where(function ($builder) use ($columns, $accountIds) {
                foreach ($columns as $index => $column) {
                    if ($index === 0) {
                        $builder->whereIn($column, $accountIds);
                    } else {
                        $builder->orWhereIn($column, $accountIds);
                    }
                }
            })
            ->orderBy('rule_code');

        $select = ['id', 'rule_code'];
        if (Schema::hasColumn('ledger_mapping_rules', 'rule_name')) {
            $select[] = 'rule_name';
        }

        return $query->get($select)->map(function ($rule) {
            if (! property_exists($rule, 'rule_name')) {
                $rule->rule_name = 'Accounting Rule';
            }

            return $rule;
        });
    }

    /**
     * Configuration rows are preserved. Only their deleted ledger assignment
     * is cleared, and the user is told exactly where a replacement is needed.
     *
     * @param Collection<int, int> $accountIds
     * @param Collection<int, array<string, mixed>> $displayRules
     * @return array<int, array<string, mixed>>
     */
    private function chartOfAccountReassignmentImpact(Collection $accountIds, Collection $displayRules): array
    {
        $items = [];

        if ($displayRules->isNotEmpty()) {
            $items[] = [
                'key' => 'accounting_rules',
                'label' => 'Accounting Rules Setup',
                'count' => $displayRules->count(),
                'details' => $displayRules
                    ->take(8)
                    ->map(fn (array $rule) => $rule['rule_code'] . ' - ' . $rule['rule_name'])
                    ->values()
                    ->all(),
                'action' => 'Select replacement ledger(s) and reactivate the affected accounting rule(s).',
            ];
        }

        $definitions = [
            ['transaction_heads', 'default_primary_ledger_id', 'Transaction Heads', 'name', 'Select a replacement Default Primary Ledger and reactivate the affected transaction head.'],
            ['cash_bank_accounts', 'linked_ledger_account_id', 'Cash / Bank Accounts', 'cash_bank_name', 'Assign a new linked CoA ledger and reactivate the affected cash/bank account.'],
            ['parties', 'linked_ledger_account_id', 'Parties / Persons', 'party_name', 'Assign new receivable/payable mappings and reactivate the affected party.'],
            ['party_ledger_mappings', 'chart_of_account_id', 'Party Ledger Mappings', 'mapping_purpose', 'Assign a replacement CoA ledger for each affected party mapping.'],
            ['party_types', 'default_ledger_account_id', 'Party Types', 'name', 'Select a new default ledger and reactivate the affected party type.'],
        ];

        foreach ($definitions as [$table, $column, $label, $nameColumn, $action]) {
            if ($accountIds->isEmpty()
                || ! Schema::hasTable($table)
                || ! Schema::hasColumn($table, $column)
                || ! Schema::hasColumn($table, $nameColumn)) {
                continue;
            }

            $query = DB::table($table)->whereIn($column, $accountIds);

            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $records = $query
                ->orderBy($nameColumn)
                ->get(['id', $nameColumn]);

            if ($records->isEmpty()) {
                continue;
            }

            $items[] = [
                'key' => $table,
                'label' => $label,
                'count' => $records->count(),
                'details' => $records
                    ->take(8)
                    ->map(fn ($record) => (string) $record->{$nameColumn})
                    ->values()
                    ->all(),
                'action' => $action,
            ];
        }

        return $items;
    }

    /**
     * Historical accounting rows are immutable references, not deletion blockers.
     * The selected CoA branch is soft-deleted so these rows keep their original
     * account IDs and continue resolving the archived account name in reports.
     *
     * @param Collection<int, int> $accountIds
     * @return array<int, array<string, mixed>>
     */
    private function chartOfAccountHistoryImpact(Collection $accountIds): array
    {
        $items = [];
        $dependencies = [
            ['opening_balances', 'account_id', 'Opening Balance records'],
            ['voucher_details', 'account_id', 'Voucher transaction lines'],
            ['journal_lines', 'ledger_id', 'Posted journal lines'],
            ['due_register', 'account_id', 'Due Management records'],
            ['advance_register', 'account_id', 'Advance Management records'],
        ];

        foreach ($dependencies as [$table, $column, $label]) {
            $count = $this->dependencyCount($table, $column, $accountIds);

            if ($count > 0) {
                $items[] = [
                    'key' => $table,
                    'label' => $label,
                    'count' => $count,
                    'details' => [],
                    'action' => 'Retained unchanged for accounting history and audit reporting.',
                ];
            }
        }

        return $items;
    }

    /**
     * @param Collection<int, int> $values
     */
    private function dependencyCount(string $table, string $column, Collection $values): int
    {
        if ($values->isEmpty() || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $query = DB::table($table)->whereIn($column, $values);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }

    /**
     * @param array<string, mixed> $impact
     */
    private function chartOfAccountConfirmationMessage(array $impact): string
    {
        $selectedRoots = collect($impact['selected_roots'] ?? []);
        $rootCount = (int) ($impact['selected_root_count'] ?? $selectedRoots->count());
        $rootCount = max(1, $rootCount);

        if ($rootCount > 1) {
            $lines = [
                'Delete ' . $rootCount . ' selected Chart of Accounts branches?',
                '',
                'Selected branches:',
            ];

            foreach ($selectedRoots->take(12) as $root) {
                $lines[] = '- Level ' . ($root['coa_level'] ?? '—') . ': ' . ($root['display_name'] ?? 'Selected account');
            }

            if ($selectedRoots->count() > 12) {
                $lines[] = '- and ' . ($selectedRoots->count() - 12) . ' more selected branches';
            }

            $lines[] = '';
            $lines[] = 'After removing duplicate child selections, this operation affects ' . $impact['account_count'] . ' account(s) across these levels:';
        } else {
            $lines = [
                'Delete "' . $impact['root_display_name'] . '"?',
                '',
                'This selected CoA branch contains ' . $impact['account_count'] . ' account(s) across these levels:',
            ];
        }

        foreach ($impact['levels'] as $level) {
            $lines[] = '- Level ' . $level['level'] . ' (' . $level['label'] . '): ' . $level['count'];

            $accountNames = collect($level['accounts'] ?? [])
                ->take(5)
                ->map(fn (array $item) => $item['account_code'] . ' - ' . $item['account_name'])
                ->implode(', ');

            if ($accountNames !== '') {
                $more = max(0, (int) $level['count'] - 5);
                $lines[] = '  ' . $accountNames . ($more > 0 ? ', and ' . $more . ' more' : '');
            }
        }

        if (($impact['history_references'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = 'Accounting history affected by this deletion:';

            foreach ($impact['history_references'] as $item) {
                $lines[] = '- ' . $item['label'] . ': ' . $item['count'];
            }

            $lines[] = 'These historical rows will NOT be deleted or blanked. They will keep the archived CoA reference so vouchers, journals, opening balances, reports, and audit trails remain correct.';
        }

        if (($impact['reassignments'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = 'The following setup mappings use the selected account(s). Their affected ledger field(s) will be cleared, while the other record information will remain unchanged:';

            foreach ($impact['reassignments'] as $item) {
                $lines[] = '- ' . $item['label'] . ': ' . $item['count'];

                foreach (array_slice($item['details'] ?? [], 0, 4) as $detail) {
                    $lines[] = '  • ' . $detail;
                }
            }

            if (($impact['rule_count'] ?? 0) > 0) {
                $lines[] = '';
                $lines[] = 'Affected accounting rules will NOT be deleted. Their deleted ledger assignment will be blank and the rule will be set to Inactive until you add replacement ledger(s).';
            }
        }

        $lines[] = '';
        $lines[] = $rootCount > 1
            ? 'Every selected root account and all lower-level accounts under those roots will be removed from the active CoA lists as one operation.'
            : 'The selected account and every lower-level account under it will be removed from the active CoA lists.';
        $lines[] = 'Press OK to continue or Cancel to keep the selected CoA account(s).';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $impact
     */
    private function chartOfAccountReassignmentMessage(array $impact): string
    {
        if (($impact['reassignments'] ?? []) === []) {
            return '';
        }

        $lines = [
            'The CoA branch was deleted, but these preserved setup records now require replacement ledger assignments:',
        ];

        foreach ($impact['reassignments'] as $item) {
            $lines[] = '- ' . $item['label'] . ' (' . $item['count'] . '): ' . $item['action'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $impact
     */
    private function chartOfAccountBlockedMessage(array $impact): string
    {
        return '';
    }

    public function deleteCashBankAccount(CashBankAccount $account): void
    {
        $accountId = (int) $account->id;
        $ledgerId = (int) ($account->linked_ledger_account_id ?? 0);

        $references = collect([
            'voucher headers' => $this->referenceCount('voucher_headers', 'cash_bank_account_id', $accountId),
            'voucher lines' => $ledgerId > 0 ? $this->referenceCount('voucher_details', 'account_id', $ledgerId) : 0,
            'journal lines' => $ledgerId > 0 ? $this->referenceCount('journal_lines', 'ledger_id', $ledgerId) : 0,
            'opening balances' => $ledgerId > 0 ? $this->referenceCount('opening_balances', 'account_id', $ledgerId) : 0,
        ])->filter(fn (int $count) => $count > 0);

        if ($references->isNotEmpty()) {
            $details = $references
                ->map(fn (int $count, string $label) => $label . ': ' . $count)
                ->implode('; ');

            throw new RuntimeException(
                'This cash/bank account cannot be deleted because accounting history uses it (' . $details . '). '
                . 'Change the account status to Inactive instead. Posted vouchers, journals, and opening balances were not changed.'
            );
        }

        DB::transaction(fn () => $account->delete());
    }

    public function deleteParty(Party $party): void
    {
        $partyId = (int) $party->id;
        $references = collect([
            'voucher headers' => $this->referenceCount('voucher_headers', 'party_id', $partyId),
            'voucher lines' => $this->referenceCount('voucher_details', 'party_id', $partyId),
            'journal headers' => $this->referenceCount('journal_headers', 'party_id', $partyId),
            'journal lines' => $this->referenceCount('journal_lines', 'party_id', $partyId),
            'opening balances' => $this->referenceCount('opening_balances', 'party_id', $partyId),
            'due movements' => $this->referenceCount('due_register', 'party_id', $partyId),
            'advance movements' => $this->referenceCount('advance_register', 'party_id', $partyId),
        ])->filter(fn (int $count) => $count > 0);

        if ($references->isNotEmpty()) {
            $details = $references
                ->map(fn (int $count, string $label) => $label . ': ' . $count)
                ->implode('; ');

            throw new RuntimeException(
                'This party cannot be deleted because accounting history uses it (' . $details . '). '
                . 'Change the party status to Inactive instead. Posted vouchers and journals were not changed.'
            );
        }

        DB::transaction(fn () => $this->deletePartyById($partyId));
    }

    public function deleteTransactionHead(TransactionHead $head): void
    {
        $headId = (int) $head->id;

        if ((bool) $head->is_system_default) {
            throw new RuntimeException('System Transaction Heads cannot be deleted. Change the status to Inactive instead.');
        }

        $this->blockIfExists(
            'voucher_headers',
            'transaction_head_id',
            $headId,
            'This Transaction Head has transaction history and cannot be deleted. Change the status to Inactive instead.'
        );
        $this->blockIfExists(
            'accounting_rules',
            'transaction_head_id',
            $headId,
            'This Transaction Head has Accounting Rules. Keep the Head and change its status to Inactive, or remove unused rules first.'
        );
        $this->blockIfExists(
            'ledger_mapping_rules',
            'transaction_head_id',
            $headId,
            'This Transaction Head has legacy Accounting Rules. Change the status to Inactive instead.'
        );

        DB::transaction(function () use ($head, $headId): void {
            if (Schema::hasTable('settlement_type_transaction_head')) {
                DB::table('settlement_type_transaction_head')
                    ->where('transaction_head_id', $headId)
                    ->delete();
            }

            $head->delete();
        });
    }

    public function deleteLedgerMappingRule(LedgerMappingRule $rule): void
    {
        DB::table('ledger_mapping_rules')
            ->where('id', $rule->id)
            ->delete();
    }

    public function deleteVoucherNumberingRule(VoucherNumberingRule $rule): void
    {
        if ((int) $rule->last_number > 0) {
            throw new RuntimeException('This voucher numbering rule has already generated voucher numbers and cannot be deleted. Deactivate it instead.');
        }

        DB::table('voucher_numbering_rules')
            ->where('id', $rule->id)
            ->delete();
    }

    public function deleteUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            DB::table('role_user')
                ->where('user_id', $userId)
                ->delete();

            DB::table('users')
                ->where('id', $userId)
                ->delete();
        });
    }

    private function deleteCashBankAccountById(int $accountId): void
    {
        $this->deleteVoucherHeadersByIds(
            DB::table('voucher_headers')
                ->where('cash_bank_account_id', $accountId)
                ->pluck('id')
                ->all()
        );

        DB::table('cash_bank_accounts')
            ->where('id', $accountId)
            ->delete();
    }

    private function deletePartyById(int $partyId): void
    {
        if (Schema::hasTable('party_ledger_mappings')) {
            DB::table('party_ledger_mappings')
                ->where('party_id', $partyId)
                ->delete();
        }

        Party::query()->whereKey($partyId)->firstOrFail()->delete();
    }

    private function referenceCount(string $table, string $column, int $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $value)->count();
    }

    private function deleteVoucherHeadersByIds(array $ids): void
    {
        $ids = collect($ids)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        DB::table('voucher_headers')
            ->whereIn('id', $ids)
            ->delete();
    }

    private function blockIfExists(string $table, string $column, int $value, string $message): void
    {
        if (DB::table($table)->where($column, $value)->exists()) {
            throw new RuntimeException($message);
        }
    }
}
