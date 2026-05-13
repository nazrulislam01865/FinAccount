<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterSettlementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise master-data input before validation so codes remain consistent in dropdowns.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $settlementTypeId = $this->route('settlement_type')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('settlement_types', 'name')->ignore($settlementTypeId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('settlement_types', 'code')->ignore($settlementTypeId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Settlement Type Name is required.',
            'name.unique' => 'This Settlement Type Name already exists.',
            'code.required' => 'Settlement Type Code is required.',
            'code.regex' => 'Code may contain only uppercase letters, numbers, and underscores.',
            'code.unique' => 'This Settlement Type Code already exists.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
