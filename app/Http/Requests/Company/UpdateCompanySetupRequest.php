<?php

namespace App\Http\Requests\Company;

use App\Models\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccounting('company_setup.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:120'],
            'business_type_id' => ['required', 'integer', Rule::exists('business_types', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'trade_license_no' => ['nullable', 'string', 'max:100'],
            'bin_vat_registration_no' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'accounting_method' => ['required', Rule::in(['accrual'])],
            'time_zone_id' => ['required', 'integer', Rule::exists('time_zones', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))],
            'default_financial_year_id' => [
                'required', 'integer',
                Rule::exists('financial_years', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('status', FinancialYear::STATUS_OPEN)),
            ],
            'default_branch' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $website = trim((string) $this->input('website'));
        if ($website !== '' && ! preg_match('~^https?://~i', $website)) {
            $website = 'https://'.$website;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'short_name' => trim((string) $this->input('short_name')),
            'trade_license_no' => $this->filled('trade_license_no') ? trim((string) $this->input('trade_license_no')) : null,
            'bin_vat_registration_no' => $this->filled('bin_vat_registration_no') ? trim((string) $this->input('bin_vat_registration_no')) : null,
            'tin' => $this->filled('tin') ? trim((string) $this->input('tin')) : null,
            'accounting_method' => strtolower((string) $this->input('accounting_method', 'accrual')),
            'default_branch' => $this->filled('default_branch') ? trim((string) $this->input('default_branch')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            'contact_email' => $this->filled('contact_email') ? strtolower(trim((string) $this->input('contact_email'))) : null,
            'contact_phone' => $this->filled('contact_phone') ? trim((string) $this->input('contact_phone')) : null,
            'website' => $website !== '' ? $website : null,
            'status' => strtolower((string) $this->input('status', 'active')),
        ]);
    }
}
