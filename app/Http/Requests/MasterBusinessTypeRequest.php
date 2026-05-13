<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterBusinessTypeRequest extends FormRequest
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
            'description' => trim((string) $this->input('description')) ?: null,
            'is_default' => $this->boolean('is_default'),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $businessTypeId = $this->route('business_type')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('business_types', 'name')->ignore($businessTypeId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('business_types', 'code')->ignore($businessTypeId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Business Type Name is required.',
            'name.unique' => 'This Business Type Name already exists.',
            'code.required' => 'Business Type Code is required.',
            'code.regex' => 'Code may contain only uppercase letters, numbers, and underscores.',
            'code.unique' => 'This Business Type Code already exists.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
