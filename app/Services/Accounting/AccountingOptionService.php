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
        $options = AccountingOption::query()
            ->forGroup($group)
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        if ($group !== AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            return $options;
        }

        // Older MySQL installations could keep a value such as "Expense"
        // while newer code expects "EXPENSE". Expose canonical core values to
        // every form immediately; the repair migration persists the same fix.
        return $options
            ->map(function (AccountingOption $option): AccountingOption {
                $option->setAttribute('value', TransactionTypes::normalize((string) $option->value));

                return $option;
            })
            ->unique('value')
            ->values();
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
        if ($group !== AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            return AccountingOption::query()
                ->forGroup($group)
                ->active()
                ->where('value', $value)
                ->exists();
        }

        $canonicalValue = TransactionTypes::normalize($value);

        return AccountingOption::query()
            ->forGroup($group)
            ->active()
            ->get(['value'])
            ->contains(fn (AccountingOption $option): bool =>
                TransactionTypes::normalize((string) $option->value) === $canonicalValue
            );
    }
}
