<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\AccountingRule;
use App\Models\Bank;
use App\Models\BusinessType;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\LedgerMappingRule;
use App\Models\LedgerType;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Models\PartyType;
use App\Models\SettlementType;
use App\Models\TimeZone;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionHeadConfigurationService;
use App\Services\Accounting\TransactionRequirementService;
use App\Support\PartyAccountingProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropdownController extends Controller
{
    public function businessTypes(): JsonResponse
    {
        return $this->ok(
            BusinessType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                ])
        );
    }

    public function currencies(): JsonResponse
    {
        return $this->ok(
            Currency::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'symbol' => $item->symbol,
                    'display_name' => trim($item->code . ' - ' . $item->name),
                ])
        );
    }

    public function timeZones(): JsonResponse
    {
        return $this->ok(
            TimeZone::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'utc_offset' => $item->utc_offset,
                    'php_timezone' => $item->php_timezone,
                    'display_name' => trim($item->utc_offset . ' - ' . $item->name),
                ])
        );
    }

    public function accountTypes(): JsonResponse
    {
        return $this->ok(
            AccountType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'normal_balance' => $item->normal_balance,
                    'display_name' => $item->name,
                ])
        );
    }

    public function coaLevels(): JsonResponse
    {
        return $this->ok(
            collect(ChartOfAccount::COA_LEVELS)->map(fn ($name, $id) => [
                'id' => (int) $id,
                'name' => 'Level ' . $id,
                'display_name' => 'Level ' . $id . ' - ' . $name,
            ])->values()
        );
    }

    public function ledgerTypes(): JsonResponse
    {
        return $this->ok(
            collect(LedgerType::activeNames())->map(fn ($type) => [
                'id' => $type,
                'name' => $type,
                'display_name' => $type,
            ])->values()
        );
    }

    public function partyTypes(): JsonResponse
    {
        return $this->ok(
            PartyType::query()
                ->where('status', 'Active')
                ->with('defaultLedger.accountType')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (PartyType $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'default_ledger_account_id' => $item->default_ledger_account_id,
                    'default_ledger_nature' => PartyAccountingProfile::deriveNature($item),
                    'default_ledger_name' => $item->defaultLedger?->display_name,
                    'default_ledger_account_type' => $item->defaultLedger?->accountType?->name,
                    'display_name' => $item->name,
                ])
        );
    }

    public function banks(): JsonResponse
    {
        return $this->ok(
            Bank::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('bank_name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'bank_name' => $item->bank_name,
                    'short_name' => $item->short_name,
                    'name' => $item->bank_name,
                    'display_name' => $item->short_name
                        ? $item->bank_name . ' (' . $item->short_name . ')'
                        : $item->bank_name,
                ])
        );
    }

    public function cashBankAccountTypes(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Cash', 'name' => 'Cash', 'display_name' => 'Cash'],
            ['id' => 'Bank', 'name' => 'Bank', 'display_name' => 'Bank'],
            ['id' => 'Mobile Banking', 'name' => 'Mobile Banking', 'display_name' => 'Mobile Banking'],
        ]);
    }

    public function partyBalanceTypes(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Debit', 'name' => 'Debit', 'display_name' => 'Debit'],
            ['id' => 'Credit', 'name' => 'Credit', 'display_name' => 'Credit'],
        ]);
    }

    public function transactionHeadNatures(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Payment', 'name' => 'Payment', 'display_name' => 'Payment'],
            ['id' => 'Receipt', 'name' => 'Receipt', 'display_name' => 'Receipt'],
            ['id' => 'Due', 'name' => 'Due', 'display_name' => 'Due'],
            ['id' => 'Advance', 'name' => 'Advance', 'display_name' => 'Advance'],
            ['id' => 'Adjustment', 'name' => 'Adjustment', 'display_name' => 'Adjustment'],
            ['id' => 'Expense', 'name' => 'Expense', 'display_name' => 'Expense'],
            ['id' => 'Journal', 'name' => 'Journal', 'display_name' => 'Journal'],
        ]);
    }

    public function yesNoOptions(): JsonResponse
    {
        return $this->ok([
            ['id' => 1, 'name' => 'Yes', 'display_name' => 'Yes'],
            ['id' => 0, 'name' => 'No', 'display_name' => 'No'],
        ]);
    }

    public function transactionHeads(Request $request): JsonResponse
    {
        $selectedCategory = $request->filled('category')
            ? TransactionHead::normaliseCategory((string) $request->input('category'))
            : null;
        $search = strtolower(trim((string) $request->input('q', $request->input('search', ''))));
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $configuration = app(TransactionHeadConfigurationService::class);

        $heads = TransactionHead::query()
            ->where('status', 'Active')
            ->where('is_user_selectable', true)
            ->when($companyId > 0, fn ($query) => $query->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)
                    ->orWhere(function ($global) {
                        $global->whereNull('company_id')->where('is_system_default', true);
                    });
            }))
            ->with([
                'defaultPrimaryLedger.accountType',
                'accountingRules.lines',
                'accountingRules.settlementType',
                'accountingRules.partyType',
                'ledgerMappingRules.settlementType',
                'settlementTypes',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (TransactionHead $item) use ($configuration) {
                $profile = $configuration->summarize($item);

                if (! $profile['ready']) {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => trim(($item->head_code ? $item->head_code . ' - ' : '') . $item->name),
                    'head_code' => $item->head_code,
                    'nature' => TransactionHead::natureFromCategory($item->category),
                    'category' => TransactionHead::normaliseCategory($item->category),
                    'raw_category' => $item->category,
                    'default_primary_ledger_id' => $item->default_primary_ledger_id,
                    'default_party_type_id' => $profile['party_type_id'],
                    'default_party_type_name' => $profile['party_type_name'],
                    'payment_method_required' => (bool) $profile['payment_method_required'],
                    'party_required_mode' => $profile['party_required_mode'],
                    'transaction_screen' => $profile['transaction_screen'],
                    'is_user_selectable' => true,
                    'help_text' => $item->help_text,
                    'requires_party' => (bool) $profile['party_required'],
                    'requires_reference' => (bool) $profile['requires_reference'],
                    'settlement_type_ids' => $profile['settlement_type_ids'],
                    'settlement_types' => collect($profile['settlements'])->map(fn (SettlementType $settlement) => [
                        'id' => $settlement->id,
                        'name' => $settlement->name,
                        'code' => $settlement->code,
                        'display_name' => $settlement->name,
                    ])->values(),
                ];
            })
            ->filter();

        if ($selectedCategory) {
            $heads = $heads->filter(fn (array $item) => $item['category'] === $selectedCategory);
        }

        if ($search !== '') {
            $heads = $heads->filter(function (array $item) use ($search) {
                $target = strtolower(trim(($item['head_code'] ? $item['head_code'] . ' ' : '') . $item['name'] . ' ' . $item['category']));

                return str_contains($target, $search);
            });
        }

        return $this->ok($heads->values());
    }

    public function settlementTypes(Request $request): JsonResponse
    {
        $query = SettlementType::query()
            ->where('status', 'Active');

        $head = null;
        $mappedOnly = $request->boolean('mapped_only');

        if ($request->filled('transaction_head_id')) {
            $companyId = (int) ($request->user()?->company_id ?? 0);
            $head = TransactionHead::query()
                ->where('status', 'Active')
                ->when($companyId > 0, fn ($builder) => $builder->where(function ($scope) use ($companyId) {
                    $scope->where('company_id', $companyId)
                        ->orWhere(function ($global) {
                            $global->whereNull('company_id')->where('is_system_default', true);
                        });
                }))
                ->whereKey($request->integer('transaction_head_id'))
                ->first();

            if (! $head) {
                return $this->ok(collect());
            }

            if ($mappedOnly) {
                $legacySettlementIds = LedgerMappingRule::query()
                    ->where(function ($scope) use ($companyId) {
                        $scope->where('company_id', $companyId)->orWhereNull('company_id');
                    })
                    ->where('transaction_head_id', $head->id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->pluck('settlement_type_id')
                    ->filter();

                $v2SpecificSettlementIds = AccountingRule::query()
                    ->where('company_id', $companyId)
                    ->where('transaction_head_id', $head->id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->whereNotNull('settlement_type_id')
                    ->pluck('settlement_type_id')
                    ->filter();

                $hasGenericV2Rule = AccountingRule::query()
                    ->where('company_id', $companyId)
                    ->where('transaction_head_id', $head->id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->whereNull('settlement_type_id')
                    ->exists();

                if (! $hasGenericV2Rule) {
                    $mappedSettlementIds = $legacySettlementIds
                        ->merge($v2SpecificSettlementIds)
                        ->unique()
                        ->values();

                    // Compatibility fallback for old installations that have
                    // not yet migrated Settlement mappings into Rules.
                    if ($mappedSettlementIds->isEmpty()) {
                        $mappedSettlementIds = $head->settlementTypes()
                            ->where('settlement_types.status', 'Active')
                            ->pluck('settlement_types.id');
                    }

                    $query->whereIn('id', $mappedSettlementIds);
                }
            }
        }

        $requirementService = app(TransactionRequirementService::class);
        $companyId = (int) ($request->user()?->company_id ?? 0);

        return $this->ok(
            $query->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function (SettlementType $item) use ($head, $requirementService, $companyId) {
                    $requirements = $head
                        ? $requirementService->resolve((int) $head->id, (int) $item->id, $companyId)
                        : [];

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'code' => $item->code,
                        'display_name' => $item->name,
                        'party_required_mode' => $requirements['party_required_mode'] ?? 'No',
                        'party_required' => (bool) ($requirements['party_required'] ?? false),
                        'party_optional' => (bool) ($requirements['party_optional'] ?? false),
                        'party_type_id' => $requirements['party_type_id'] ?? null,
                        'party_type_name' => $requirements['party_type_name'] ?? null,
                        'payment_method_required' => (bool) ($requirements['payment_method_required'] ?? false),
                        'cash_bank_required' => (bool) ($requirements['cash_bank_required'] ?? false),
                        'requirement_source' => $requirements['source'] ?? null,
                    ];
                })
        );
    }

    public function cashBankAccounts(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        return $this->ok(
            CashBankAccount::query()
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->where('status', 'Active')
                ->with(['linkedLedger.accountType', 'bank'])
                ->orderBy('cash_bank_code')
                ->get()
                ->map(fn (CashBankAccount $account) => [
                    'id' => $account->id,
                    'cash_bank_code' => $account->cash_bank_code,
                    'cash_bank_name' => $account->cash_bank_name,
                    'name' => $account->cash_bank_name,
                    'type' => $account->type,

                    'linked_ledger_account_id' => $account->linked_ledger_account_id,
                    'linked_ledger_name' => $account->linkedLedger?->display_name
                        ?: trim(($account->linkedLedger?->account_code ? $account->linkedLedger->account_code . ' - ' : '') . ($account->linkedLedger?->account_name ?? '')),
                    'linked_ledger_account_type' => $account->linkedLedger?->accountType?->name,
                    'linked_ledger_normal_balance' => $account->linkedLedger?->normal_balance
                        ?: $account->linkedLedger?->accountType?->normal_balance,

                    'bank_name' => $account->bank_name ?? $account->bank?->bank_name,
                    'branch_name' => $account->branch_name,
                    'account_number' => $account->account_number,
                    'opening_balance' => $account->opening_balance,
                    'usage_note' => $account->usage_note,

                    'display_name' => trim(($account->cash_bank_code ? $account->cash_bank_code . ' - ' : '') . $account->cash_bank_name),
                ])
        );
    }

    public function parties(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $query = Party::query()
            ->where('status', 'Active')
            ->when($companyId > 0, fn ($builder) => $builder->where('company_id', $companyId))
            ->with(['partyType', 'linkedLedger.accountType', 'ledgerMappings.ledger.accountType']);

        if ($request->filled('party_type_id')) {
            $query->where('party_type_id', $request->integer('party_type_id'));
        }

        if ($request->filled('ledger_nature')) {
            $query->where('default_ledger_nature', $request->string('ledger_nature'));
        }

        if ($request->filled('linked_ledger_account_id')) {
            $query->where('linked_ledger_account_id', $request->integer('linked_ledger_account_id'));
        }

        if ($request->filled('mapping_purpose')) {
            $purpose = strtolower(trim(str_replace([' ', '-'], '_', (string) $request->input('mapping_purpose'))));
            $query->whereHas('ledgerMappings', fn ($mappingQuery) => $mappingQuery
                ->where('mapping_purpose', $purpose)
                ->where('status', 'Active')
                ->whereNotNull('chart_of_account_id'));
        }

        return $this->ok(
            $query->orderBy('party_name')
                ->get()
                ->map(fn (Party $party) => [
                    'id' => $party->id,
                    'party_code' => $party->party_code,
                    'party_name' => $party->party_name,
                    'sub_type' => $party->sub_type,
                    'default_ledger_nature' => $party->effectiveLedgerNature(),
                    'opening_balance' => $party->opening_balance,
                    'opening_balance_type' => $party->opening_balance_type,
                    'party_type_id' => $party->party_type_id,
                    'party_type_name' => $party->partyType?->name,
                    'linked_ledger_account_id' => $party->linked_ledger_account_id,
                    'linked_ledger_name' => $party->linkedLedger?->display_name
                        ?: trim(($party->linkedLedger?->account_code ? $party->linkedLedger->account_code . ' - ' : '') . ($party->linkedLedger?->account_name ?? '')),
                    'linked_ledger_account_type' => $party->linkedLedger?->accountType?->name,
                    'linked_ledger_normal_balance' => $party->linkedLedger?->normal_balance
                        ?: $party->linkedLedger?->accountType?->normal_balance,
                    'ledger_mappings' => $party->ledgerMappings
                        ->where('status', 'Active')
                        ->map(fn ($mapping) => [
                            'purpose' => $mapping->mapping_purpose,
                            'chart_of_account_id' => $mapping->chart_of_account_id,
                            'ledger_name' => $mapping->ledger?->display_name,
                        ])->values(),
                    'receivable_ledger_account_id' => $party->ledgerMappings
                        ->first(fn ($mapping) => $mapping->status === 'Active' && $mapping->mapping_purpose === PartyLedgerMapping::PURPOSE_RECEIVABLE)
                        ?->chart_of_account_id,
                    'payable_ledger_account_id' => $party->ledgerMappings
                        ->first(fn ($mapping) => $mapping->status === 'Active' && $mapping->mapping_purpose === PartyLedgerMapping::PURPOSE_PAYABLE)
                        ?->chart_of_account_id,
                    'capital_ledger_account_id' => $party->ledgerMappings
                        ->first(fn ($mapping) => $mapping->status === 'Active' && $mapping->mapping_purpose === PartyLedgerMapping::PURPOSE_CAPITAL)
                        ?->chart_of_account_id,
                    'display_name' => trim(($party->party_code ? $party->party_code . ' - ' : '') . $party->party_name),
                ])
        );
    }

    public function parentAccounts(Request $request): JsonResponse
    {
        $query = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Group')
            ->with('accountType');

        if ($request->filled('child_level')) {
            $childLevel = max(1, min(4, $request->integer('child_level')));
            $query->where('coa_level', max(1, $childLevel - 1));
        }

        if ($request->filled('account_type_id')) {
            $query->where('account_type_id', $request->integer('account_type_id'));
        }

        if ($request->filled('exclude_id')) {
            $excludedIds = $this->descendantAccountIds($request->integer('exclude_id'));
            $excludedIds[] = $request->integer('exclude_id');

            $query->whereNotIn('id', array_unique($excludedIds));
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->unique('id')
                ->values()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    public function cashBankLedgers(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $type = trim((string) $request->input('type'));
        $excludeAccountId = $request->integer('exclude_cash_bank_account_id');

        $editingAccount = $excludeAccountId > 0
            ? CashBankAccount::query()
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->find($excludeAccountId)
            : null;

        $usedLedgerIds = CashBankAccount::query()
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($editingAccount, fn ($query) => $query->whereKeyNot($editingAccount->id))
            ->pluck('linked_ledger_account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = ChartOfAccount::query()
            ->when($companyId > 0, fn ($builder) => $builder->where('company_id', $companyId))
            ->where('status', 'Active')
            ->where(function ($nested) {
                $nested->where('coa_level', 4)
                    ->orWhere('account_level', 'Ledger');
            })
            ->where('is_cash_bank', true)
            ->where('posting_allowed', true)
            ->whereNotIn('id', $usedLedgerIds)
            ->whereHas('accountType', fn ($builder) => $builder
                ->where('name', 'Asset')
                ->where('normal_balance', 'Debit'))
            ->with('accountType');

        if ($type === 'Cash') {
            $query->where('ledger_type', 'Cash');
        } elseif ($type === 'Bank') {
            $query->where('ledger_type', 'Bank');
        } elseif ($type === 'Mobile Banking') {
            $legacyLedgerId = $editingAccount?->type === 'Mobile Banking'
                && $editingAccount?->linkedLedger?->ledger_type === 'Bank'
                    ? (int) $editingAccount->linked_ledger_account_id
                    : 0;

            $query->where(function ($builder) use ($legacyLedgerId) {
                $builder->where('ledger_type', 'Mobile Wallet');

                if ($legacyLedgerId > 0) {
                    $builder->orWhereKey($legacyLedgerId);
                }
            });
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    public function ledgerAccounts(Request $request): JsonResponse
    {
        $partyType = $request->filled('party_type_id')
            ? PartyType::query()->find($request->integer('party_type_id'))
            : null;

        $mappingPurpose = strtolower(trim(str_replace([' ', '-'], '_', (string) $request->input('mapping_purpose'))));
        $ledgerNature = $request->input('ledger_nature')
            ?: $this->natureForMappingPurpose($mappingPurpose)
            ?: ($partyType ? PartyAccountingProfile::deriveNature($partyType) : null);
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $query = ChartOfAccount::query()
            ->when($companyId > 0, fn ($builder) => $builder->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->where('status', 'Active')
            ->where(function ($nested) {
                $nested->where('coa_level', 4)
                    ->orWhere('account_level', 'Ledger');
            })
            ->where('posting_allowed', true)
            ->with('accountType');

        if ($request->boolean('for_party') || $partyType || $ledgerNature || $mappingPurpose !== '') {
            $query->where('is_cash_bank', false);
            $this->applyPartyLedgerNatureFilter($query, $ledgerNature);
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    private function formatAccount(ChartOfAccount $account): array
    {
        $displayName = trim($account->account_code . ' - ' . $account->account_name);

        return [
            'id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'account_level' => $account->account_level,
            'coa_level' => (int) ($account->coa_level ?: ($account->account_level === 'Ledger' ? 4 : 1)),
            'level_name' => $account->level_name,
            'account_type_id' => $account->account_type_id,
            'account_type' => $account->accountType?->name,
            'account_group' => $account->account_group,
            'account_sub_group' => $account->account_sub_group,
            'account_nature' => $account->account_nature ?: $account->accountType?->name,
            'normal_balance' => $account->normal_balance ?: $account->accountType?->normal_balance,
            'ledger_type' => $account->ledger_type,
            'posting_allowed' => (bool) $account->posting_allowed,
            'is_cash_bank' => (bool) $account->is_cash_bank,
            'is_party_control' => (bool) $account->is_party_control,
            'party_type_id' => $account->party_type_id,
            'is_system_ledger' => (bool) $account->is_system_ledger,
            'is_user_selectable' => (bool) $account->is_user_selectable,
            'name' => $account->account_name,
            'display_name' => $account->display_name ?: $displayName,
        ];
    }

    private function descendantAccountIds(int $accountId): array
    {
        $childrenByParent = ChartOfAccount::query()
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $stack = [$accountId];

        while ($stack) {
            $currentId = array_pop($stack);

            foreach ($childrenByParent->get($currentId, collect()) as $child) {
                $ids[] = (int) $child->id;
                $stack[] = (int) $child->id;
            }
        }

        return $ids;
    }

    private function applyPartyLedgerNatureFilter($query, ?string $ledgerNature): void
    {
        match ($ledgerNature) {
            'Receivable', 'Advance Paid' => $query->whereHas('accountType', fn ($relation) => $relation
                ->where('name', 'Asset')
                ->where('normal_balance', 'Debit')),

            'Payable', 'Advance Received' => $query->whereHas('accountType', fn ($relation) => $relation
                ->where('name', 'Liability')
                ->where('normal_balance', 'Credit')),

            'Capital' => $query->whereHas('accountType', fn ($relation) => $relation
                ->where('name', 'Equity')
                ->where('normal_balance', 'Credit')),

            'No Effect' => $query->whereHas('accountType', fn ($relation) => $relation
                ->whereIn('name', ['Asset', 'Liability', 'Equity'])),

            default => null,
        };
    }


    private function natureForMappingPurpose(string $purpose): ?string
    {
        $nature = PartyAccountingProfile::natureFromPurpose($purpose);

        return $nature === PartyAccountingProfile::NATURE_NO_EFFECT ? null : $nature;
    }

    public function partyLedgerEffects(): JsonResponse
    {
        return $this->ok(
            collect(LedgerMappingRule::PARTY_EFFECTS)->map(fn ($effect) => [
                'id' => $effect,
                'name' => $effect,
                'display_name' => $effect,
            ])->values()
        );
    }

    private function ok($data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}