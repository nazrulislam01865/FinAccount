<?php

namespace App\Services\Company;

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\TimeZone;
use App\Models\Transaction;
use App\Services\Accounting\SafeDelete\DeletionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyMasterDeletionService
{
    public function inspectBusinessType(BusinessType $businessType): DeletionPlan
    {
        $count = Company::query()->where('business_type_id', $businessType->id)->count();

        return new DeletionPlan(
            'Business Type',
            $businessType->code.' — '.$businessType->name,
            $count > 0 ? [[
                'label' => 'Company Setup',
                'count' => $count,
                'effect' => 'The business type will be cleared and Company Setup will require a replacement.',
            ]] : [],
        );
    }

    public function deleteBusinessType(BusinessType $businessType): void
    {
        DB::transaction(function () use ($businessType): void {
            $record = BusinessType::query()->lockForUpdate()->findOrFail($businessType->id);
            Company::query()->where('business_type_id', $record->id)->update([
                'business_type_id' => null,
                'setup_completed_at' => null,
                'updated_at' => now(),
            ]);
            $record->delete();
        }, attempts: 5);
    }

    public function inspectCurrency(Currency $currency): DeletionPlan
    {
        $count = Company::query()->where('currency_id', $currency->id)->count();

        return new DeletionPlan(
            'Currency',
            $currency->code.' — '.$currency->name,
            $count > 0 ? [[
                'label' => 'Company Setup',
                'count' => $count,
                'effect' => 'The selected currency will be cleared and Company Setup will require a replacement.',
            ]] : [],
        );
    }

    public function deleteCurrency(Currency $currency): void
    {
        DB::transaction(function () use ($currency): void {
            $record = Currency::query()->lockForUpdate()->findOrFail($currency->id);
            Company::query()->where('currency_id', $record->id)->update([
                'currency_id' => null,
                'setup_completed_at' => null,
                'updated_at' => now(),
            ]);
            $record->delete();
        }, attempts: 5);
    }

    public function inspectTimeZone(TimeZone $timeZone): DeletionPlan
    {
        $count = Company::query()->where('time_zone_id', $timeZone->id)->count();

        return new DeletionPlan(
            'Time Zone',
            $timeZone->name.' — '.$timeZone->utc_offset,
            $count > 0 ? [[
                'label' => 'Company Setup',
                'count' => $count,
                'effect' => 'The selected time zone will be cleared and Company Setup will require a replacement.',
            ]] : [],
        );
    }

    public function deleteTimeZone(TimeZone $timeZone): void
    {
        DB::transaction(function () use ($timeZone): void {
            $record = TimeZone::query()->lockForUpdate()->findOrFail($timeZone->id);
            Company::query()->where('time_zone_id', $record->id)->update([
                'time_zone_id' => null,
                'setup_completed_at' => null,
                'updated_at' => now(),
            ]);
            $record->delete();
        }, attempts: 5);
    }

    public function inspectFinancialYear(FinancialYear $financialYear): DeletionPlan
    {
        $defaultCount = Company::query()->where('default_financial_year_id', $financialYear->id)->count();
        $transactionCount = Transaction::query()
            ->where('company_id', $financialYear->company_id)
            ->whereBetween('transaction_date', [
                $financialYear->start_date->toDateString(),
                $financialYear->end_date->toDateString(),
            ])->count();

        $dependencies = [];
        if ($defaultCount > 0) {
            $dependencies[] = [
                'label' => 'Company Setup',
                'count' => $defaultCount,
                'effect' => 'The current financial year will be cleared and Company Setup will require a replacement.',
            ];
        }
        if ($transactionCount > 0) {
            $dependencies[] = [
                'label' => 'Transactions in this date range',
                'count' => $transactionCount,
                'effect' => 'Historical transactions exist. This financial year cannot be deleted; close or deactivate it instead.',
            ];
        }

        return new DeletionPlan(
            'Financial Year',
            $financialYear->name,
            $dependencies,
            confirmationText: $transactionCount > 0
                ? 'Deletion is blocked because transactions exist inside this financial year.'
                : null,
        );
    }

    public function deleteFinancialYear(FinancialYear $financialYear): void
    {
        DB::transaction(function () use ($financialYear): void {
            // Transaction posting also locks the company first. Using the same
            // lock order prevents a new transaction from entering this period
            // while its Financial Year is being deleted.
            Company::query()->lockForUpdate()->findOrFail($financialYear->company_id);
            $record = FinancialYear::query()->lockForUpdate()->findOrFail($financialYear->id);
            $transactionCount = Transaction::query()
                ->where('company_id', $record->company_id)
                ->whereBetween('transaction_date', [
                    $record->start_date->toDateString(),
                    $record->end_date->toDateString(),
                ])->count();

            if ($transactionCount > 0) {
                throw ValidationException::withMessages([
                    'financial_year' => 'This financial year cannot be deleted because transactions exist inside its date range. Close or deactivate it instead.',
                ]);
            }

            Company::query()->where('default_financial_year_id', $record->id)->update([
                'default_financial_year_id' => null,
                'setup_completed_at' => null,
                'updated_at' => now(),
            ]);
            $record->delete();
        }, attempts: 5);
    }
}
