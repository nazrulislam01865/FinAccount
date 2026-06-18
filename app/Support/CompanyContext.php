<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class CompanyContext
{
    public static function company(): ?Company
    {
        $user = Auth::user();

        if (! $user?->company_id || ! Schema::hasTable('companies')) {
            return null;
        }

        $company = $user->relationLoaded('company')
            ? $user->getRelation('company')
            : $user->company()->with(['currency', 'timeZone', 'defaultFinancialYear'])->first();

        if ($company && ! $user->relationLoaded('company')) {
            $user->setRelation('company', $company);
        }

        return $company;
    }

    public static function currency(): ?Currency
    {
        return self::company()?->currency;
    }

    public static function currencyCode(): string
    {
        return self::currency()?->code
            ?: self::company()?->currency_code
            ?: 'BDT';
    }

    public static function currencySymbol(): string
    {
        $currency = self::currency();

        if (filled($currency?->symbol)) {
            return (string) $currency->symbol;
        }

        return match (self::currencyCode()) {
            'BDT' => '৳',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => self::currencyCode(),
        };
    }

    public static function decimalPlaces(): int
    {
        return max(0, min(2, (int) (self::currency()?->decimal_places ?? 2)));
    }

    public static function amountStep(): string
    {
        $places = self::decimalPlaces();

        return $places === 0 ? '1' : '0.'.str_repeat('0', $places - 1).'1';
    }

    public static function money(mixed $amount, bool $includeCode = false): string
    {
        $formatted = number_format((float) ($amount ?? 0), self::decimalPlaces());
        $value = trim(self::currencySymbol().' '.$formatted);

        return $includeCode ? $value.' '.self::currencyCode() : $value;
    }

    /** @return array<string, mixed> */
    public static function data(): array
    {
        $company = self::company();

        return [
            'company' => $company,
            'currency_code' => self::currencyCode(),
            'currency_symbol' => self::currencySymbol(),
            'decimal_places' => self::decimalPlaces(),
            'timezone' => $company?->timeZone?->php_timezone ?: $company?->timezone ?: config('app.timezone', 'Asia/Dhaka'),
            'financial_year' => $company?->defaultFinancialYear,
        ];
    }
}
