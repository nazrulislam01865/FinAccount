<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountService
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $balanceService,
        private readonly AccountingOptionService $optionService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(
        int $companyId,
        string $search = '',
        int $modalAccountId = 0,
        int $levelFilter = 0,
    ): array {
        $allAccounts = ChartOfAccount::query()
            ->with('parent:id,code,name,level,type')
            ->withCount('children')
            ->where('company_id', $companyId)
            ->orderBy('level')
            ->orderBy('code')
            ->get();

        $accounts = $search === '' && $levelFilter === 0
            ? $this->hierarchicalAccounts($allAccounts)
            : $allAccounts
                ->filter(function (ChartOfAccount $account) use ($search, $levelFilter): bool {
                    if ($levelFilter > 0 && (int) $account->level !== $levelFilter) {
                        return false;
                    }

                    if ($search === '') {
                        return true;
                    }

                    $haystack = strtolower(implode(' ', [
                        $account->code,
                        $account->name,
                        $account->type,
                        $account->parent?->code,
                        $account->parent?->name,
                        'level '.$account->level,
                    ]));

                    return str_contains($haystack, strtolower($search));
                })
                ->sortBy(fn (ChartOfAccount $account): string => sprintf('%d-%020s', $account->level, $account->code))
                ->values();

        $parentOptions = $this->hierarchicalAccounts($allAccounts)
            ->filter(fn (ChartOfAccount $account): bool => (int) $account->level < 3 && $account->is_active)
            ->values();

        $nextCodes = ['root' => $this->previewNextCode($companyId)];
        foreach ($parentOptions as $parent) {
            $nextCodes[(string) $parent->id] = $this->previewNextCode($companyId, (int) $parent->id);
        }

        return [
            'accounts' => $accounts,
            'balances' => $this->balanceService->balancesFor($allAccounts, $companyId),
            'search' => $search,
            'levelFilter' => $levelFilter,
            'levelCounts' => [
                1 => $allAccounts->where('level', 1)->count(),
                2 => $allAccounts->where('level', 2)->count(),
                3 => $allAccounts->where('level', 3)->count(),
            ],
            'modalAccount' => $modalAccountId > 0
                ? ChartOfAccount::query()
                    ->with('parent:id,code,name,level,type')
                    ->withCount('children')
                    ->where('company_id', $companyId)
                    ->find($modalAccountId)
                : null,
            'parentOptions' => $parentOptions,
            'accountTypes' => $this->optionService->forGroup(AccountingOption::GROUP_ACCOUNT_TYPE),
            'normalBalances' => $this->optionService->forGroup(AccountingOption::GROUP_NORMAL_BALANCE),
            'nextCodes' => $nextCodes,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): ChartOfAccount
    {
        return DB::transaction(function () use ($data, $companyId): ChartOfAccount {
            $this->automaticCodeService->lockCompany($companyId);
            $parent = $this->resolveParent($companyId, $data['parent_id'] ?? null);
            $level = $parent ? ((int) $parent->level + 1) : 1;
            $type = $parent?->type ?? (string) $data['type'];
            $normalBalance = $parent?->normal_balance ?? (string) $data['normal_balance'];
            $name = trim((string) $data['name']);
            $reportSetup = $this->automaticReportSetup($type, $name, $parent, $level);

            $this->ensureUniqueName((int) $companyId, $name);

            return ChartOfAccount::query()->create([
                'company_id' => $companyId,
                'parent_id' => $parent?->id,
                'level' => $level,
                'code' => $this->automaticCodeService->nextChartOfAccountCode($companyId, $parent?->id),
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normalBalance,
                'report_section' => $reportSetup['report_section'],
                'cash_flow_section' => $reportSetup['cash_flow_section'],
                'is_cash_bank' => $reportSetup['is_cash_bank'],
                'is_party_control' => $reportSetup['is_party_control'],
                'is_posting' => $reportSetup['is_posting'],
                'sort_order' => $reportSetup['sort_order'],
                'is_active' => (bool) $data['is_active'],
            ]);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        DB::transaction(function () use ($account, $data): void {
            $this->automaticCodeService->lockCompany((int) $account->company_id);
            $locked = ChartOfAccount::query()
                ->withCount('children')
                ->lockForUpdate()
                ->findOrFail($account->id);

            $parent = $this->resolveParent(
                (int) $locked->company_id,
                $data['parent_id'] ?? null,
                (int) $locked->id,
            );
            $newParentId = $parent?->id;
            $parentChanged = (int) ($locked->parent_id ?? 0) !== (int) ($newParentId ?? 0);
            $legacyUnassignedLedger = (int) $locked->level === 3
                && $locked->parent_id === null
                && $newParentId === null;
            $newLevel = $legacyUnassignedLedger
                ? 3
                : ($parent ? ((int) $parent->level + 1) : 1);
            $newType = $parent?->type ?? (string) $data['type'];
            $newNormalBalance = $parent?->normal_balance ?? (string) $data['normal_balance'];
            $name = trim((string) $data['name']);
            $reportSetup = $this->automaticReportSetup($newType, $name, $parent, $newLevel);
            $typeChanged = (string) $locked->type !== $newType;
            $normalBalanceChanged = (string) $locked->normal_balance !== $newNormalBalance;

            if ((int) $locked->children_count > 0 && ($parentChanged || $typeChanged)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Move or delete this account’s child accounts before changing its parent or account type.',
                ]);
            }

            if ((int) $locked->children_count > 0 && ! (bool) $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'A parent account cannot be made inactive while it still has child accounts.',
                ]);
            }

            $this->validateMappedAccountType($locked, $newType);
            $this->validateMappedAccountLevel($locked, $newLevel);
            $this->ensureUniqueName((int) $locked->company_id, $name, (int) $locked->id);

            $locked->update([
                'parent_id' => $newParentId,
                'level' => $newLevel,
                'code' => $parentChanged
                    ? $this->automaticCodeService->nextChartOfAccountCode(
                        (int) $locked->company_id,
                        $newParentId,
                        (int) $locked->id,
                    )
                    : $locked->code,
                'name' => $name,
                'type' => $newType,
                'normal_balance' => $newNormalBalance,
                'report_section' => $reportSetup['report_section'],
                'cash_flow_section' => $reportSetup['cash_flow_section'],
                'is_cash_bank' => $reportSetup['is_cash_bank'],
                'is_party_control' => $reportSetup['is_party_control'],
                'is_posting' => $reportSetup['is_posting'],
                'sort_order' => $reportSetup['sort_order'],
                'is_active' => (bool) $data['is_active'],
            ]);

            if ($normalBalanceChanged || (int) $locked->children_count > 0) {
                $this->cascadeNormalBalance((int) $locked->id, (int) $locked->company_id, $newNormalBalance);
            }
        }, attempts: 5);

        return $account->refresh();
    }

    public function delete(ChartOfAccount $account): void
    {
        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Move or delete the child accounts before deleting this parent account.',
            ]);
        }

        $uses = collect([
            'money account' => $account->moneyAccounts()->exists(),
            'party' => $account->receivableParties()->exists() || $account->payableParties()->exists(),
            'transaction head' => $account->transactionHeads()->exists(),
            'journal' => $account->journalLines()->exists(),
        ])->filter()->keys();

        if ($uses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete. Used by '.$uses->implode(', ').'.',
            ]);
        }

        DB::transaction(fn () => $account->delete(), attempts: 5);
    }


    /** @return array{report_section: string, cash_flow_section: ?string, is_cash_bank: bool, is_party_control: bool, is_posting: bool, sort_order: int} */
    private function automaticReportSetup(string $type, string $name, ?ChartOfAccount $parent, int $level): array
    {
        $reportSection = $this->guessReportSection($type, $name, $parent);

        return [
            'report_section' => $reportSection,
            'cash_flow_section' => $this->guessCashFlowSection($type, $name, $reportSection),
            'is_cash_bank' => $this->looksLikeCashBankAccount($type, $name),
            'is_party_control' => $this->looksLikePartyControlAccount($type, $name, $reportSection),
            'is_posting' => $level >= 3,
            'sort_order' => $this->reportSortOrder($type, $reportSection),
        ];
    }

    private function guessReportSection(string $type, string $name, ?ChartOfAccount $parent = null): string
    {
        $text = $this->normaliseText($name);

        // Level 2 parents become report groups. Level 3 posting ledgers under
        // those parents inherit the same report group automatically.
        if ($parent && (int) $parent->level >= 2 && filled($parent->report_section)) {
            return (string) $parent->report_section;
        }

        return match ($type) {
            'Income' => $this->matchesAny($text, [
                'interest', 'discount received', 'gain', 'commission received', 'other income', 'misc income', 'non operating',
            ]) ? 'Other Income' : 'Revenue',

            'Expense' => match (true) {
                $this->matchesAny($text, [
                    'purchase', 'cost of sale', 'cost of sales', 'cogs', 'product cost', 'service cost', 'direct cost',
                    'production', 'raw material', 'factory', 'manufacturing', 'inventory cost',
                ]) => 'Cost of Sales',
                $this->matchesAny($text, [
                    'bank charge', 'bank fee', 'finance cost', 'interest', 'loan', 'processing fee', 'card charge',
                ]) => 'Financial Expense',
                $this->matchesAny($text, ['tax', 'vat', 'ait', 'income tax']) => 'Tax Expense',
                $this->matchesAny($text, [
                    'advertisement', 'advertising', 'marketing', 'delivery', 'sales commission', 'promotion', 'courier',
                ]) => 'Selling Expense',
                $this->matchesAny($text, ['office', 'admin', 'stationery', 'audit', 'legal', 'professional']) => 'Administrative Expense',
                default => 'Operating Expense',
            },

            'Asset' => match (true) {
                $this->matchesAny($text, [
                    'fixed', 'equipment', 'furniture', 'vehicle', 'building', 'land', 'machinery', 'computer',
                    'depreciation', 'non current', 'non-current',
                ]) => 'Fixed Asset',
                default => 'Current Asset',
            },

            'Liability' => $this->matchesAny($text, ['long term', 'long-term', 'non current', 'non-current'])
                ? 'Non Current Liability'
                : 'Current Liability',

            'Equity' => match (true) {
                $this->matchesAny($text, ['capital', 'owner']) => 'Owner Capital',
                $this->matchesAny($text, ['retained']) => 'Retained Earnings',
                default => 'Equity',
            },

            default => 'General',
        };
    }

    private function guessCashFlowSection(string $type, string $name, string $reportSection): ?string
    {
        if ($this->looksLikeCashBankAccount($type, $name)) {
            return 'Cash Bank';
        }

        return match ($type) {
            'Income', 'Expense' => 'Operating',
            'Asset' => $reportSection === 'Current Asset' ? 'Operating' : 'Investing',
            'Liability', 'Equity' => 'Financing',
            default => null,
        };
    }

    private function looksLikeCashBankAccount(string $type, string $name): bool
    {
        if ($type !== 'Asset') {
            return false;
        }

        return $this->matchesAny($this->normaliseText($name), [
            'cash', 'bank', 'petty cash', 'bkash', 'b-kash', 'nagad', 'rocket', 'wallet', 'mobile banking', 'card',
        ]);
    }

    private function looksLikePartyControlAccount(string $type, string $name, string $reportSection): bool
    {
        $text = $this->normaliseText($name);

        if (in_array($type, ['Asset', 'Liability'], true) && $this->matchesAny($text, [
            'receivable', 'payable', 'customer', 'supplier', 'vendor', 'party', 'due', 'advance',
        ])) {
            return true;
        }

        return in_array($reportSection, ['Current Asset', 'Current Liability'], true)
            && $this->matchesAny($text, ['receivable', 'payable']);
    }

    private function reportSortOrder(string $type, string $reportSection): int
    {
        return [
            'Current Asset' => 100,
            'Fixed Asset' => 120,
            'Non Current Asset' => 130,
            'Current Liability' => 200,
            'Non Current Liability' => 220,
            'Equity' => 300,
            'Owner Capital' => 310,
            'Retained Earnings' => 320,
            'Revenue' => 400,
            'Cost of Sales' => 500,
            'Operating Expense' => 600,
            'Administrative Expense' => 610,
            'Selling Expense' => 620,
            'Financial Expense' => 700,
            'Other Income' => 800,
            'Tax Expense' => 900,
        ][$reportSection] ?? match ($type) {
            'Asset' => 100,
            'Liability' => 200,
            'Equity' => 300,
            'Income' => 400,
            'Expense' => 600,
            default => 999,
        };
    }

    /** @param array<int, string> $needles */
    private function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normaliseText(string $value): string
    {
        return str_replace(['_', '-', '/', '.', ','], ' ', mb_strtolower(trim($value)));
    }

    private function resolveParent(
        int $companyId,
        mixed $parentId,
        ?int $accountId = null,
    ): ?ChartOfAccount {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return null;
        }

        $parent = ChartOfAccount::query()
            ->whereKey((int) $parentId)
            ->where('company_id', $companyId)
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Select a valid parent account from this company.',
            ]);
        }

        if ($accountId !== null && (int) $parent->id === $accountId) {
            throw ValidationException::withMessages([
                'parent_id' => 'An account cannot be its own parent.',
            ]);
        }

        if ((int) $parent->level >= 3) {
            throw ValidationException::withMessages([
                'parent_id' => 'Select a Level 1 or Level 2 parent. Level 3 is the final posting level.',
            ]);
        }

        if (! $parent->is_active) {
            throw ValidationException::withMessages([
                'parent_id' => 'Select an active parent account.',
            ]);
        }

        if ($accountId !== null && $this->parentChainContains($parent, $accountId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A child account cannot be selected as its parent.',
            ]);
        }

        return $parent;
    }

    private function cascadeNormalBalance(int $parentId, int $companyId, string $normalBalance): void
    {
        $children = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('parent_id', $parentId)
            ->get(['id', 'company_id']);

        foreach ($children as $child) {
            $childAccount = ChartOfAccount::query()->find($child->id);
            if (! $childAccount) {
                continue;
            }

            $reportSetup = $this->automaticReportSetup(
                (string) $childAccount->type,
                (string) $childAccount->name,
                ChartOfAccount::query()->find($parentId),
                (int) $childAccount->level,
            );

            ChartOfAccount::query()
                ->whereKey($child->id)
                ->update([
                    'normal_balance' => $normalBalance,
                    'report_section' => $reportSetup['report_section'],
                    'cash_flow_section' => $reportSetup['cash_flow_section'],
                    'is_cash_bank' => $reportSetup['is_cash_bank'],
                    'is_party_control' => $reportSetup['is_party_control'],
                    'is_posting' => $reportSetup['is_posting'],
                    'sort_order' => $reportSetup['sort_order'],
                ]);

            $this->cascadeNormalBalance((int) $child->id, $companyId, $normalBalance);
        }
    }

    private function parentChainContains(ChartOfAccount $parent, int $accountId): bool
    {
        $current = $parent;

        while ($current->parent_id !== null) {
            if ((int) $current->parent_id === $accountId) {
                return true;
            }

            $current = ChartOfAccount::query()->find($current->parent_id);
            if (! $current) {
                break;
            }
        }

        return false;
    }

    private function ensureUniqueName(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $normalisedName = mb_strtolower(trim($name));

        $duplicate = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [$normalisedName])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'This account name already exists in the Chart of Accounts. Use a unique account name.',
            ]);
        }
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

        // Legacy flat rows and any damaged/orphaned rows remain visible instead
        // of disappearing from the COA page.
        foreach ($accounts->sortBy(fn (ChartOfAccount $account): string => sprintf('%d-%020s', $account->level, $account->code)) as $account) {
            $append($account);
        }

        return $result->values();
    }

    private function previewNextCode(int $companyId, ?int $parentId = null): string
    {
        try {
            return $this->automaticCodeService->nextChartOfAccountCode($companyId, $parentId);
        } catch (ValidationException) {
            return '';
        }
    }


    private function validateMappedAccountLevel(ChartOfAccount $account, int $newLevel): void
    {
        if ($newLevel === 3) {
            return;
        }

        if (
            $account->moneyAccounts()->exists()
            || $account->receivableParties()->exists()
            || $account->payableParties()->exists()
            || $account->transactionHeads()->exists()
            || $account->journalLines()->exists()
        ) {
            throw ValidationException::withMessages([
                'parent_id' => 'This account is already used for posting and must remain a Level 3 ledger.',
            ]);
        }
    }

    private function validateMappedAccountType(ChartOfAccount $account, string $newType): void
    {
        if (($account->moneyAccounts()->exists() || $account->receivableParties()->exists()) && $newType !== 'Asset') {
            throw ValidationException::withMessages([
                'type' => 'This account must remain an Asset because it is mapped to a money account or party receivable.',
            ]);
        }

        if ($account->payableParties()->exists() && ! in_array($newType, ['Liability', 'Equity'], true)) {
            throw ValidationException::withMessages([
                'type' => 'This account must remain a Liability or Equity account because it is mapped to party payable/capital.',
            ]);
        }
    }
}
