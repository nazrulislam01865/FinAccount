<?php

namespace App\Services\Company;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\TimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanySetupDefaultsService
{
    public function ensureForCompany(Company $company, bool $attachSelections = true): Company
    {
        if (! Schema::hasTable('business_types') || ! Schema::hasColumn('companies', 'business_type_id')) {
            return $company;
        }

        return DB::transaction(function () use ($company, $attachSelections): Company {
            $businessTypes = [
                ['code' => 'TRADING', 'name' => 'Trading', 'description' => 'Buying and selling goods.', 'sort_order' => 10],
                ['code' => 'SERVICE', 'name' => 'Service', 'description' => 'Professional or operational services.', 'sort_order' => 20],
                ['code' => 'MANUFACTURING', 'name' => 'Manufacturing', 'description' => 'Production and manufacturing activities.', 'sort_order' => 30],
                ['code' => 'AGRICULTURE', 'name' => 'Agriculture', 'description' => 'Farm, fisheries, livestock, and agriculture.', 'sort_order' => 40],
                ['code' => 'OTHER', 'name' => 'Other Business', 'description' => 'General business category.', 'sort_order' => 100],
            ];

            foreach ($businessTypes as $item) {
                BusinessType::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $item['code']],
                    [...$item, 'is_default' => $item['code'] === 'OTHER', 'is_active' => true],
                );
            }

            $currencies = [
                ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimal_places' => 2, 'sort_order' => 10],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'sort_order' => 20],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'sort_order' => 30],
            ];

            foreach ($currencies as $item) {
                Currency::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $item['code']],
                    [...$item, 'is_default' => $item['code'] === 'BDT', 'is_active' => true],
                );
            }

            $timeZones = [
                ['code' => 'ASIA_DHAKA', 'name' => 'Dhaka', 'utc_offset' => 'UTC+06:00', 'php_timezone' => 'Asia/Dhaka', 'sort_order' => 10],
                ['code' => 'UTC', 'name' => 'UTC', 'utc_offset' => 'UTC+00:00', 'php_timezone' => 'UTC', 'sort_order' => 20],
                ['code' => 'ASIA_KOLKATA', 'name' => 'Kolkata', 'utc_offset' => 'UTC+05:30', 'php_timezone' => 'Asia/Kolkata', 'sort_order' => 30],
            ];

            foreach ($timeZones as $item) {
                TimeZone::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $item['code']],
                    [...$item, 'is_default' => $item['code'] === 'ASIA_DHAKA', 'is_active' => true],
                );
            }

            $year = (int) now()->format('Y');
            $financialYear = FinancialYear::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => 'FY '.$year],
                [
                    'start_date' => $year.'-01-01',
                    'end_date' => $year.'-12-31',
                    'is_active' => true,
                    'is_current' => true,
                    'status' => FinancialYear::STATUS_OPEN,
                ],
            );

            $businessType = BusinessType::query()->forCompany($company->id)->where('code', 'OTHER')->first();
            $currency = Currency::query()->forCompany($company->id)->where('code', $company->currency_code ?: 'BDT')->first()
                ?: Currency::query()->forCompany($company->id)->where('code', 'BDT')->first();
            $timeZone = TimeZone::query()->forCompany($company->id)->where('php_timezone', $company->timezone ?: 'Asia/Dhaka')->first()
                ?: TimeZone::query()->forCompany($company->id)->where('code', 'ASIA_DHAKA')->first();

            if ($attachSelections) {
                $company->forceFill([
                    'short_name' => $company->short_name ?: mb_substr($company->name, 0, 120),
                    'business_type_id' => $company->business_type_id ?: $businessType?->id,
                    'currency_id' => $company->currency_id ?: $currency?->id,
                    'currency_code' => $currency?->code ?: ($company->currency_code ?: 'BDT'),
                    'time_zone_id' => $company->time_zone_id ?: $timeZone?->id,
                    'timezone' => $timeZone?->php_timezone ?: ($company->timezone ?: 'Asia/Dhaka'),
                    'default_financial_year_id' => $company->default_financial_year_id ?: $financialYear->id,
                    'accounting_method' => $company->accounting_method ?: 'accrual',
                    'setup_completed_at' => $company->setup_completed_at ?: now(),
                ])->save();
            }

            return $company->fresh(['businessType', 'currency', 'timeZone', 'defaultFinancialYear']);
        }, attempts: 5);
    }
}
