<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

trait ValidatesBackdatedTransactionDate
{
    /** @return array<int, string> */
    protected function backdatedEntryRules(): array
    {
        return ['nullable', 'boolean'];
    }

    protected function normalizedBackdatedEntryFlag(): bool
    {
        return $this->boolean('is_backdated');
    }

    protected function addBackdatedEntryValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('transaction_date');

            if (! filled($value) || $validator->errors()->has('transaction_date')) {
                return;
            }

            try {
                $transactionDate = CarbonImmutable::parse((string) $value)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $today = now()->startOfDay();

            if ($transactionDate->gt($today)) {
                $validator->errors()->add(
                    'transaction_date',
                    'Future transaction dates are not allowed.',
                );

                return;
            }

            if ($transactionDate->lt($today) && ! $this->boolean('is_backdated')) {
                $validator->errors()->add(
                    'transaction_date',
                    'Enable Backdated Entry before posting a transaction dated before today.',
                );
            }
        });
    }
}
