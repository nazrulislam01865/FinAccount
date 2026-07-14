<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\DocumentSequence;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    public function __construct(
        private readonly SafeDeleteService $safeDeleteService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    public const CORE_TRANSACTION_CATEGORIES = [
        TransactionTypes::SALE,
        TransactionTypes::PURCHASE,
        TransactionTypes::CUSTOMER_COLLECTION,
        TransactionTypes::SUPPLIER_PAYMENT,
        TransactionTypes::EXPENSE,
        TransactionTypes::OWNER_INVESTMENT,
        TransactionTypes::OWNER_WITHDRAWAL,
        TransactionTypes::LOAN_RECEIVED,
        TransactionTypes::LOAN_REPAYMENT,
        TransactionTypes::LOAN_INTEREST_PAYMENT,
        TransactionTypes::ASSET_PURCHASE,
    ];

    /** @return array<string, array<string, mixed>> */
    public function configurations(): array
    {
        return [
            'party-types' => [
                'group' => AccountingOption::GROUP_PARTY_TYPE,
                'title' => 'Party Types',
                'description' => '',
                'value_placeholder' => 'Use letters, numbers, spaces, hyphens, or underscores.',
                'menu_group' => 'Business Masters',
                'creatable' => true,
                'editable' => true,
                'deletable' => true,
                'protected' => false,
            ],
            'money-account-types' => [
                'group' => AccountingOption::GROUP_MONEY_ACCOUNT_KIND,
                'title' => 'Money Account Types',
                'description' => '',
                'value_placeholder' => 'Use letters, numbers, spaces, hyphens, or underscores.',
                'menu_group' => 'Business Masters',
                'creatable' => true,
                'editable' => true,
                'deletable' => true,
                'protected' => false,
            ],
            'transaction-categories' => [
                'group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
                'title' => 'Transaction Types',
                'description' => '',
                'menu_group' => 'Transaction Setup',
                'creatable' => true,
                'editable' => true,
                'deletable' => true,
                'protected' => true,
                'core_values' => self::CORE_TRANSACTION_CATEGORIES,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function configuration(string $section): array
    {
        $configuration = $this->configurations()[$section] ?? null;

        abort_if($configuration === null, 404);

        return ['section' => $section, ...$configuration];
    }

    /**
     * @return array{
     *     configuration:array<string,mixed>,
     *     options:Collection<int,AccountingOption>,
     *     usage:array<int,array{count:int,summary:string}>
     * }
     */
    public function pageData(string $section): array
    {
        $configuration = $this->configuration($section);
        $options = AccountingOption::query()
            ->forGroup($configuration['group'])
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return [
            'configuration' => $configuration,
            'options' => $options,
            'usage' => $options->mapWithKeys(fn (AccountingOption $option): array => [
                $option->id => $this->usageFor($option),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(string $section, array $data): AccountingOption
    {
        $configuration = $this->configuration($section);

        if (! $configuration['creatable']) {
            throw ValidationException::withMessages([
                'master_data' => 'New records cannot be added to this protected master.',
            ]);
        }

        if ($configuration['group'] === AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            return $this->createTransactionCategory($data);
        }

        return DB::transaction(function () use ($configuration, $data): AccountingOption {
            AccountingOption::query()
                ->forGroup($configuration['group'])
                ->lockForUpdate()
                ->get(['id']);

            $data['value'] = $this->automaticCodeService->initialValue(
                (string) $data['label'],
                (string) $configuration['group'],
            );
            $this->validateReservedValue($configuration['group'], (string) $data['value']);

            $option = AccountingOption::query()->create([
                'option_group' => $configuration['group'],
                'value' => trim((string) $data['value']),
                'label' => trim((string) $data['label']),
                'sort_order' => (int) $data['sort_order'],
                'metadata' => null,
                'is_active' => (bool) $data['is_active'],
            ]);

            if ($option->option_group === AccountingOption::GROUP_PARTY_TYPE) {
                $this->syncRulePartyType(null, $option);
            }

            return $option;
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(string $section, AccountingOption $option, array $data): AccountingOption
    {
        $configuration = $this->configuration($section);
        $this->ensureOptionMatchesSection($option, $configuration['group']);

        if (! $configuration['editable']) {
            throw ValidationException::withMessages([
                'master_data' => 'This master value is read-only.',
            ]);
        }

        if ($option->option_group === AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            return $this->updateTransactionCategory($option, $data);
        }

        $newValue = in_array($configuration['group'], [
            AccountingOption::GROUP_PARTY_TYPE,
            AccountingOption::GROUP_MONEY_ACCOUNT_KIND,
        ], true)
            ? $option->value
            : trim((string) $data['value']);
        $this->validateReservedValue($configuration['group'], $newValue);
        $usage = $this->usageFor($option);
        $isChangingValue = $newValue !== $option->value;
        $isDeactivating = $option->is_active && ! (bool) $data['is_active'];

        if (($isChangingValue || $isDeactivating) && $usage['count'] > 0) {
            throw ValidationException::withMessages([
                $isChangingValue ? 'value' : 'is_active' => 'This value is already used by '.$usage['summary'].'. Change those records first.',
            ]);
        }

        if ($isDeactivating && $this->activeCount($option->option_group) <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one active value must remain.',
            ]);
        }

        return DB::transaction(function () use ($option, $data, $newValue): AccountingOption {
            $oldValue = $option->value;

            $option->update([
                'value' => $newValue,
                'label' => trim((string) $data['label']),
                'sort_order' => (int) $data['sort_order'],
                'is_active' => (bool) $data['is_active'],
            ]);

            if ($option->option_group === AccountingOption::GROUP_PARTY_TYPE) {
                $this->syncRulePartyType($oldValue, $option->refresh());
            }

            return $option->refresh();
        }, attempts: 5);
    }

    public function assertSafeDeletable(string $section, AccountingOption $option): void
    {
        $configuration = $this->configuration($section);
        $this->ensureOptionMatchesSection($option, $configuration['group']);

        if (! $configuration['deletable']) {
            throw ValidationException::withMessages([
                'master_data' => 'This protected master value cannot be deleted.',
            ]);
        }

        if ($this->isCoreTransactionCategory($option)) {
            throw ValidationException::withMessages([
                'master_data' => 'System transaction types cannot be deleted.',
            ]);
        }
    }

    public function delete(string $section, AccountingOption $option): void
    {
        $this->assertSafeDeletable($section, $option);
        $this->safeDeleteService->deleteAccountingOption($option);
    }

    /** @return array{count:int,summary:string} */
    public function usageFor(AccountingOption $option): array
    {
        $parts = match ($option->option_group) {
            AccountingOption::GROUP_PARTY_TYPE => $this->nonZeroParts([
                'parties' => Party::query()->where('type', $option->value)->count(),
                'accounting rules' => AccountingRule::query()->where('party_type', $option->value)->count(),
            ]),
            AccountingOption::GROUP_MONEY_ACCOUNT_KIND => $this->nonZeroParts([
                'money accounts' => MoneyAccount::query()->where('kind', $option->value)->count(),
            ]),
            AccountingOption::GROUP_TRANSACTION_CATEGORY => $this->nonZeroParts([
                'accounting rules' => AccountingRule::query()->where('category', $option->value)->count(),
                'transaction heads' => TransactionHead::query()->where('category', $option->value)->count(),
                'voucher numbering' => DocumentSequence::query()->where('category', $option->value)->count(),
                'transactions' => Transaction::query()->where('category', $option->value)->count(),
            ]),
            default => [],
        };

        return [
            'count' => array_sum(array_column($parts, 'count')),
            'summary' => $parts === []
                ? 'Not used'
                : collect($parts)->map(fn (array $part): string => $part['label'].': '.$part['count'])->implode(', '),
        ];
    }

    /**
     * @param array<string, int> $items
     * @return array<int, array{label:string,count:int}>
     */
    private function nonZeroParts(array $items): array
    {
        $parts = [];

        foreach ($items as $label => $count) {
            if ($count > 0) {
                $parts[] = ['label' => $label, 'count' => $count];
            }
        }

        return $parts;
    }

    /** @param array<string, mixed> $data */
    private function createTransactionCategory(array $data): AccountingOption
    {
        $prefix = strtoupper(trim((string) $data['voucher_prefix']));
        $this->assertVoucherPrefixAvailable($prefix);

        return DB::transaction(fn (): AccountingOption => AccountingOption::query()->create([
            'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
            'value' => trim((string) $data['value']),
            'label' => trim((string) $data['label']),
            'sort_order' => (int) $data['sort_order'],
            'metadata' => [
                'voucher_prefix' => $prefix,
                'money_label' => trim((string) $data['money_label']),
                'flow' => (string) $data['flow'],
                'allowed_settlements' => TransactionTypes::ALL_SETTLEMENTS,
                'default_settlements' => [TransactionTypes::CASH],
            ],
            'is_active' => (bool) $data['is_active'],
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    private function updateTransactionCategory(AccountingOption $option, array $data): AccountingOption
    {
        $newValue = trim((string) $data['value']);
        $isCore = $this->isCoreTransactionCategory($option);
        $isChangingValue = $newValue !== $option->value;
        $isDeactivating = $option->is_active && ! (bool) $data['is_active'];
        $usage = $this->usageFor($option);
        $newPrefix = strtoupper(trim((string) $data['voucher_prefix']));
        $currentMetadata = is_array($option->metadata) ? $option->metadata : [];
        $oldPrefix = strtoupper(trim((string) ($currentMetadata['voucher_prefix'] ?? '')));
        $isChangingPrefix = $newPrefix !== $oldPrefix;
        $currentFlow = TransactionTypes::flow($option->value, $currentMetadata);
        $newFlow = $isCore
            ? TransactionTypes::flow($option->value)
            : (string) $data['flow'];
        $isChangingFlow = $newFlow !== $currentFlow;
        $data['flow'] = $newFlow;

        $this->assertVoucherPrefixAvailable($newPrefix, $option->id);

        if ($isChangingPrefix && DocumentSequence::query()->where('category', $option->value)->exists()) {
            throw ValidationException::withMessages([
                'voucher_prefix' => 'The voucher prefix cannot be changed after Voucher Numbering exists for this category.',
            ]);
        }

        if ($isChangingFlow && AccountingRule::query()->where('category', $option->value)->exists()) {
            throw ValidationException::withMessages([
                'flow' => 'The transaction direction cannot be changed after accounting rules exist for this transaction type.',
            ]);
        }

        if ($isCore && $isChangingValue) {
            throw ValidationException::withMessages([
                'value' => 'The internal code of a system transaction type cannot be changed.',
            ]);
        }

        if ($isCore && $isDeactivating) {
            throw ValidationException::withMessages([
                'is_active' => 'System transaction types cannot be deactivated.',
            ]);
        }

        if (! $isCore && ($isChangingValue || $isDeactivating) && $usage['count'] > 0) {
            throw ValidationException::withMessages([
                $isChangingValue ? 'value' : 'is_active' => 'This category is already used by '.$usage['summary'].'. Change those records first.',
            ]);
        }

        if ($isDeactivating && $this->activeCount($option->option_group) <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one active transaction type must remain.',
            ]);
        }

        if ($isChangingValue && DocumentSequence::query()->where('category', $newValue)->exists()) {
            throw ValidationException::withMessages([
                'value' => 'Voucher numbering already exists for the new internal value.',
            ]);
        }

        return DB::transaction(function () use ($option, $data, $newValue, $newPrefix, $isChangingValue): AccountingOption {
            $oldValue = $option->value;
            $metadata = is_array($option->metadata) ? $option->metadata : [];
            $metadata['voucher_prefix'] = $newPrefix;
            $metadata['money_label'] = trim((string) $data['money_label']);
            $metadata['flow'] = (string) $data['flow'];
            $metadata['allowed_settlements'] = TransactionTypes::ALL_SETTLEMENTS;
            $metadata['default_settlements'] = [TransactionTypes::CASH];

            $option->update([
                'value' => $newValue,
                'label' => trim((string) $data['label']),
                'sort_order' => (int) $data['sort_order'],
                'metadata' => $metadata,
                'is_active' => (bool) $data['is_active'],
            ]);

            if ($isChangingValue) {
                DocumentSequence::query()
                    ->where('category', $oldValue)
                    ->update(['category' => $newValue]);
            }

            return $option->refresh();
        }, attempts: 5);
    }

    private function assertVoucherPrefixAvailable(string $prefix, ?int $ignoreOptionId = null): void
    {
        $used = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->when($ignoreOptionId !== null, fn ($query) => $query->where('id', '!=', $ignoreOptionId))
            ->get(['id', 'metadata'])
            ->contains(function (AccountingOption $option) use ($prefix): bool {
                $metadata = is_array($option->metadata) ? $option->metadata : [];

                return strtoupper((string) ($metadata['voucher_prefix'] ?? '')) === $prefix;
            });

        if ($used) {
            throw ValidationException::withMessages([
                'voucher_prefix' => 'This voucher prefix is already assigned to another transaction type.',
            ]);
        }
    }

    private function syncRulePartyType(?string $oldValue, AccountingOption $partyType): void
    {
        if ($oldValue !== null && $oldValue !== $partyType->value) {
            AccountingOption::query()
                ->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE)
                ->where('value', $oldValue)
                ->delete();
        }

        AccountingOption::query()->updateOrCreate(
            [
                'option_group' => AccountingOption::GROUP_RULE_PARTY_TYPE,
                'value' => $partyType->value,
            ],
            [
                'label' => $partyType->label,
                'sort_order' => $partyType->sort_order,
                'metadata' => null,
                'is_active' => $partyType->is_active,
            ],
        );
    }

    private function validateReservedValue(string $group, string $value): void
    {
        if ($group === AccountingOption::GROUP_PARTY_TYPE && strcasecmp(trim($value), 'Any') === 0) {
            throw ValidationException::withMessages([
                'value' => 'Any is reserved for accounting-rule matching and cannot be used as a party type.',
            ]);
        }
    }

    private function isCoreTransactionCategory(AccountingOption $option): bool
    {
        return $option->option_group === AccountingOption::GROUP_TRANSACTION_CATEGORY
            && in_array($option->value, self::CORE_TRANSACTION_CATEGORIES, true);
    }

    private function activeCount(string $group): int
    {
        return AccountingOption::query()->forGroup($group)->active()->count();
    }

    private function ensureOptionMatchesSection(AccountingOption $option, string $group): void
    {
        abort_unless($option->option_group === $group, 404);
    }
}
