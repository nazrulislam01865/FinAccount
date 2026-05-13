<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise master-data input before validation so ISO currency codes remain consistent in dropdowns.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'symbol' => trim((string) $this->input('symbol')) ?: null,
            'decimal_places' => (int) (($this->input('decimal_places') === null || $this->input('decimal_places') === '') ? 2 : $this->input('decimal_places')),
            'is_default' => $this->boolean('is_default'),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $currencyId = $this->route('currency')?->id;

        return [
            'code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code')->ignore($currencyId),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('currencies', 'name')->ignore($currencyId),
            ],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Currency Code is required.',
            'code.size' => 'Currency Code must be exactly 3 letters.',
            'code.regex' => 'Currency Code must contain only uppercase letters, such as BDT or USD.',
            'code.unique' => 'This Currency Code already exists.',
            'name.required' => 'Currency Name is required.',
            'name.unique' => 'This Currency Name already exists.',
            'decimal_places.required' => 'Decimal Places is required.',
            'decimal_places.max' => 'Decimal Places cannot be more than 6.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
