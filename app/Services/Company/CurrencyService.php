<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    /** @return array{currencies:Collection<int,Currency>,usage:array<int,int>} */
    public function pageData(int $companyId): array
    {
        $currencies = Currency::query()->forCompany($companyId)->orderBy('sort_order')->orderBy('code')->get();
        $usage = $currencies->mapWithKeys(fn (Currency $item): array => [
            $item->id => Company::query()->where('currency_id', $item->id)->count(),
        ])->all();

        return compact('currencies', 'usage');
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId): Currency
    {
        $this->assertDefaultIsActive($data);

        return DB::transaction(function () use ($data, $companyId): Currency {
            if ($data['is_default']) {
                Currency::query()->forCompany($companyId)->update(['is_default' => false]);
            }
            return Currency::query()->create(['company_id' => $companyId, ...$this->normalized($data)]);
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    public function update(Currency $currency, array $data): Currency
    {
        $this->assertDefaultIsActive($data);

        if (! $data['is_active'] && $currency->companies()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Select a replacement Currency in Company Setup before making this value inactive.',
            ]);
        }

        DB::transaction(function () use ($currency, $data): void {
            $company = Company::query()->lockForUpdate()->findOrFail($currency->company_id);
            $currency = Currency::query()->lockForUpdate()->findOrFail($currency->id);
            $isSelected = (int) $company->currency_id === (int) $currency->id;

            if ($isSelected
                && (int) $currency->decimal_places !== (int) $data['decimal_places']
                && Transaction::query()->where('company_id', $currency->company_id)->exists()) {
                throw ValidationException::withMessages([
                    'decimal_places' => 'Decimal places cannot be changed for the selected currency after transactions exist.',
                ]);
            }

            if ($data['is_default']) {
                Currency::query()->forCompany($currency->company_id)->whereKeyNot($currency->id)->update(['is_default' => false]);
            }

            $currency->update($this->normalized($data));

            if ($isSelected) {
                $company->update(['currency_code' => $currency->code]);
            }
        }, attempts: 5);

        return $currency->refresh();
    }


    /** @param array<string,mixed> $data */
    private function assertDefaultIsActive(array $data): void
    {
        if ($data['is_default'] && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'A default Currency must remain active.',
            ]);
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'symbol' => filled($data['symbol'] ?? null) ? trim((string) $data['symbol']) : null,
            'decimal_places' => (int) $data['decimal_places'],
            'is_default' => (bool) $data['is_default'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ];
    }
}
