<?php

namespace App\Http\Requests;

use App\Models\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('company.manage');
    }

    protected function prepareForValidation(): void
    {
        $financialYear = $this->filled('financial_year_id')
            ? FinancialYear::query()->find($this->integer('financial_year_id'))
            : null;

        $this->merge([
            'business_type_id' => $this->business_type_id ?: null,
            'accounting_method' => $this->input('accounting_method') ?: 'Accrual',
            'tax_id_bin' => $this->tax_id_bin ?: $this->bin_vat_registration_no ?: null,
            'bin_vat_registration_no' => $this->bin_vat_registration_no ?: $this->tax_id_bin ?: null,
            'tin' => $this->tin ?: null,
            'status' => $this->input('status') ?: 'Active',
            'default_branch' => $this->default_branch ?: null,
            'financial_year_start' => $financialYear?->start_date?->toDateString() ?: $this->financial_year_start,
            'financial_year_end' => $financialYear?->end_date?->toDateString() ?: $this->financial_year_end,
        ]);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:120'],

            'business_type_id' => ['required', 'integer', 'exists:business_types,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'accounting_method' => ['required', 'string', 'in:Cash,Accrual'],
            'time_zone_id' => ['required', 'integer', 'exists:time_zones,id'],

            'trade_license_no' => ['nullable', 'string', 'max:100'],
            'tax_id_bin' => ['nullable', 'string', 'max:100'],
            'bin_vat_registration_no' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],

            'financial_year_id' => ['required', 'integer', 'exists:financial_years,id'],
            'financial_year_start' => ['required', 'date'],
            'financial_year_end' => ['required', 'date', 'after:financial_year_start'],
            'default_branch' => ['nullable', 'string', 'max:150'],

            'address' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'status' => ['required', 'string', 'in:Active,Inactive'],

        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Company Name is required.',
            'short_name.required' => 'Short Name is required.',
            'business_type_id.required' => 'Business Type is required.',
            'currency_id.required' => 'Currency is required.',
            'accounting_method.required' => 'Accounting Method is required.',
            'time_zone_id.required' => 'Time Zone is required.',
            'financial_year_id.required' => 'Financial Year is required. Please create/select it from Master Setup first.',
            'financial_year_id.exists' => 'Selected Financial Year must exist in Master Setup.',
            'financial_year_start.required' => 'Financial Year Start is required.',
            'financial_year_end.required' => 'Financial Year End is required.',
            'financial_year_end.after' => 'Financial Year End must be after Financial Year Start.',
        ];
    }
}
