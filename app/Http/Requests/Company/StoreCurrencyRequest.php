<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canAccounting('currencies.manage') ?? false; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique('currencies')->where('company_id', $this->user()?->company_id)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:12'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:2'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'symbol' => $this->filled('symbol') ? trim((string) $this->input('symbol')) : null,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
