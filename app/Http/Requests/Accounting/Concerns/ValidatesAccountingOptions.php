<?php

namespace App\Http\Requests\Accounting\Concerns;

use App\Services\Accounting\AccountingOptionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesAccountingOptions
{
    protected function activeAccountingOption(string $group): Exists
    {
        return Rule::exists('accounting_options', 'value')
            ->where(fn ($query) => $query
                ->where('option_group', $group)
                ->where('is_active', true));
    }

    protected function canonicalActiveAccountingOption(string $group, mixed $value): string
    {
        $candidate = trim((string) $value);

        return app(AccountingOptionService::class)->canonicalActiveValue($group, $candidate)
            ?? $candidate;
    }
}
