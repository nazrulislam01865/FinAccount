<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterPartyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep party type codes predictable and make the default ledger optional.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'default_ledger_account_id' => $this->input('default_ledger_account_id') ?: null,
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $partyTypeId = $this->route('party_type')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('party_types', 'name')->ignore($partyTypeId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('party_types', 'code')->ignore($partyTypeId),
            ],
            'default_ledger_account_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Party Type Name is required.',
            'name.unique' => 'This Party Type Name already exists.',
            'code.required' => 'Party Type Code is required.',
            'code.regex' => 'Code may contain only uppercase letters, numbers, and underscores.',
            'code.unique' => 'This Party Type Code already exists.',
            'default_ledger_account_id.exists' => 'Selected default ledger is invalid.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
