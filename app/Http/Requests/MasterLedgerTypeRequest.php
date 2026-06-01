<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterLedgerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('master-data.manage');
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_]+/', '_', (string) ($this->input('code') ?: $this->input('name'))));
        $code = trim($code, '_');

        $this->merge([
            'code' => $code,
            'is_system' => $this->boolean('is_system'),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $ledgerTypeId = $this->route('ledger_type')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('ledger_types', 'name')->ignore($ledgerTypeId)],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('ledger_types', 'code')->ignore($ledgerTypeId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_system' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}
