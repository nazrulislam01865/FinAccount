<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
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

    public function activeOption(string $group, ?string $value): ?AccountingOption
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return null;
        }

        return $this->forGroup($group)
            ->first(fn (AccountingOption $option): bool => strcasecmp($option->value, $candidate) === 0);
    }

    public function canonicalActiveValue(string $group, ?string $value): ?string
    {
        return $this->activeOption($group, $value)?->value;
    }

    public function isActiveValue(string $group, string $value): bool
    {
        return $this->activeOption($group, $value) !== null;
    }
}
