<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\FinancialYear;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanySetupService
{
    public function save(array $data, ?UploadedFile $logo = null, ?int $userId = null): Company
    {
        return DB::transaction(function () use ($data, $logo, $userId) {
            $company = Company::first();
            $selectedFinancialYear = ! empty($data['financial_year_id'])
                ? FinancialYear::query()->find((int) $data['financial_year_id'])
                : null;

            if ($selectedFinancialYear) {
                $data['financial_year_start'] = $selectedFinancialYear->start_date->toDateString();
                $data['financial_year_end'] = $selectedFinancialYear->end_date->toDateString();
            }

            if ($logo) {
                $data['logo_path'] = $logo->store('company-logos', 'public');
                if ($company?->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }
            }

            $payload = Arr::only($data, [
                'company_name', 'short_name', 'business_type_id', 'trade_license_no', 'tax_id_bin',
                'currency_id', 'time_zone_id', 'financial_year_start', 'financial_year_end', 'default_branch', 'address',
                'contact_email', 'contact_phone', 'website', 'logo_path'
            ]);
            $payload[$company ? 'updated_by' : 'created_by'] = $userId;
            $payload['updated_by'] = $userId;

            $company = Company::updateOrCreate(['id' => $company?->id], $payload);

            if ($selectedFinancialYear) {
                FinancialYear::where('company_id', $company->id)
                    ->orWhereNull('company_id')
                    ->update([
                        'is_active' => false,
                        'is_current' => false,
                        'updated_by' => $userId,
                    ]);

                $selectedFinancialYear->forceFill([
                    'company_id' => $company->id,
                    'is_active' => true,
                    'is_current' => true,
                    'updated_by' => $userId,
                ])->save();
            }

            return $company->fresh(['businessType', 'currency', 'timeZone']);
        });
    }
}
