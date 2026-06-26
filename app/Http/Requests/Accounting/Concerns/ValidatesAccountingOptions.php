<?php

namespace App\Http\Requests\Accounting\Concerns;

use App\Models\AccountingOption;
use App\Support\TransactionTypes;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesAccountingOptions
{
    protected function activeAccountingOption(string $group): Exists|Closure
    {
        if ($group !== AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            return Rule::exists('accounting_options', 'value')
                ->where(fn ($query) => $query
                    ->where('option_group', $group)
                    ->where('is_active', true));
        }

        return static function (string $attribute, mixed $value, Closure $fail) use ($group): void {
            $canonicalValue = TransactionTypes::normalize((string) $value);

            $exists = AccountingOption::query()
                ->forGroup($group)
                ->active()
                ->get(['value'])
                ->contains(fn (AccountingOption $option): bool =>
                    TransactionTypes::normalize((string) $option->value) === $canonicalValue
                );

            if (! $exists) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }
}
