<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;

class AccountingOptionService
{
    /** @return Collection<int, AccountingOption> */
    public function forGroup(string $group, bool $activeOnly = true): Collection
    {
        return AccountingOption::query()
            ->forGroup($group)
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Payment types are system-defined accounting behaviours. Return the
     * canonical list so a stale or partially seeded cloud database cannot
     * hide valid choices from setup forms.
     *
     * @return Collection<int, AccountingOption>
     */
    public function systemSettlementTypes(): Collection
    {
        return collect(TransactionTypes::settlementDefinitions())
            ->map(function (array $definition, string $value): AccountingOption {
                return new AccountingOption([
                    'option_group' => AccountingOption::GROUP_SETTLEMENT_TYPE,
                    'value' => $value,
                    'label' => $definition['label'],
                    'is_active' => true,
                ]);
            })
            ->values();
    }

    /** @return array<string, string> */
    public function systemSettlementLabels(): array
    {
        return collect(TransactionTypes::settlementDefinitions())
            ->mapWithKeys(fn (array $definition, string $value): array => [$value => $definition['label']])
            ->all();
    }

    /** @return array<int, string> */
    public function values(string $group, bool $activeOnly = true): array
    {
        return $this->forGroup($group, $activeOnly)
            ->pluck('value')
            ->all();
    }

    /** @return array<string, string> */
    public function labels(string $group, bool $activeOnly = true): array
    {
        return $this->forGroup($group, $activeOnly)
            ->pluck('label', 'value')
            ->all();
    }

    public function firstValue(string $group, ?string $fallback = null): ?string
    {
        return $this->forGroup($group)->first()?->value ?? $fallback;
    }

    public function isActiveValue(string $group, string $value): bool
    {
        return AccountingOption::query()
            ->forGroup($group)
            ->active()
            ->where('value', $value)
            ->exists();
    }
}
