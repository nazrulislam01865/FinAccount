<?php

namespace App\Services\Company;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\TimeZone;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanySetupService
{
    /** @return array<string, mixed> */
    public function pageData(Company $company): array
    {
        $company->loadMissing(['businessType', 'currency', 'timeZone', 'defaultFinancialYear']);

        return [
            'company' => $company,
            'businessTypes' => BusinessType::query()->forCompany($company->id)->active()->orderBy('sort_order')->orderBy('name')->get(),
            'currencies' => Currency::query()->forCompany($company->id)->active()->orderBy('sort_order')->orderBy('code')->get(),
            'timeZones' => TimeZone::query()->forCompany($company->id)->active()->orderBy('sort_order')->orderBy('name')->get(),
            'financialYears' => FinancialYear::query()->forCompany($company->id)->active()->where('status', FinancialYear::STATUS_OPEN)->orderByDesc('is_current')->orderByDesc('start_date')->get(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(Company $company, array $data, User $user): Company
    {
        return DB::transaction(function () use ($company, $data, $user): Company {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $businessType = BusinessType::query()->forCompany($company->id)->active()->findOrFail($data['business_type_id']);
            $currency = Currency::query()->forCompany($company->id)->active()->findOrFail($data['currency_id']);
            $timeZone = TimeZone::query()->forCompany($company->id)->active()->findOrFail($data['time_zone_id']);
            $financialYear = FinancialYear::query()->forCompany($company->id)->active()->lockForUpdate()->findOrFail($data['default_financial_year_id']);

            if ($financialYear->status !== FinancialYear::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'default_financial_year_id' => 'Select an Open Financial Year as the current year.',
                ]);
            }

            FinancialYear::query()
                ->forCompany($company->id)
                ->whereKeyNot($financialYear->id)
                ->update(['is_current' => false, 'updated_by' => $user->id]);

            $financialYear->forceFill([
                'is_active' => true,
                'is_current' => true,
                'status' => FinancialYear::STATUS_OPEN,
                'updated_by' => $user->id,
            ])->save();

            $company->update([
                'name' => trim((string) $data['name']),
                'short_name' => trim((string) $data['short_name']),
                'business_type_id' => $businessType->id,
                'trade_license_no' => $data['trade_license_no'] ?: null,
                'bin_vat_registration_no' => $data['bin_vat_registration_no'] ?: null,
                'tin' => $data['tin'] ?: null,
                'currency_id' => $currency->id,
                'currency_code' => $currency->code,
                'accounting_method' => $data['accounting_method'],
                'time_zone_id' => $timeZone->id,
                'timezone' => $timeZone->php_timezone,
                'default_financial_year_id' => $financialYear->id,
                'default_branch' => $data['default_branch'] ?: null,
                'address' => $data['address'] ?: null,
                'contact_email' => $data['contact_email'] ?: null,
                'contact_phone' => $data['contact_phone'] ?: null,
                'website' => $data['website'] ?: null,
                'status' => $data['status'],
                'setup_completed_at' => now(),
                'updated_by' => $user->id,
            ]);

            return $company->fresh(['businessType', 'currency', 'timeZone', 'defaultFinancialYear']);
        }, attempts: 5);
    }
}
