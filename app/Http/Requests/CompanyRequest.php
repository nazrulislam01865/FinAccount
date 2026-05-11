<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_type_id' => $this->business_type_id ?: null,
            'enable_multi_branch' => $this->boolean('enable_multi_branch'),
        ]);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:120'],

            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'time_zone_id' => ['required', 'integer', 'exists:time_zones,id'],

            'trade_license_no' => ['nullable', 'string', 'max:100'],
            'tax_id_bin' => ['nullable', 'string', 'max:100'],

            'financial_year_start' => ['required', 'date'],
            'financial_year_end' => ['required', 'date', 'after:financial_year_start'],

            'address' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            'journal_voucher_prefix' => ['nullable', 'string', 'max:20'],
            'payment_voucher_prefix' => ['nullable', 'string', 'max:20'],
            'receipt_voucher_prefix' => ['nullable', 'string', 'max:20'],

            'enable_multi_branch' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Company Name is required.',
            'short_name.required' => 'Short Name is required.',
            'currency_id.required' => 'Currency is required.',
            'time_zone_id.required' => 'Time Zone is required.',
            'financial_year_start.required' => 'Financial Year Start is required.',
            'financial_year_end.required' => 'Financial Year End is required.',
            'financial_year_end.after' => 'Financial Year End must be after Financial Year Start.',
        ];
    }
}
