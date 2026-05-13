<?php

namespace App\Http\Requests;

use App\Models\SettlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $settlementIds = $this->input('settlement_type_ids');

        if (!$settlementIds && $this->filled('allowed_settlement_types')) {
            $raw = $this->input('allowed_settlement_types');
            $names = is_string($raw) ? json_decode($raw, true) : $raw;

            if (is_array($names)) {
                $settlementIds = SettlementType::query()
                    ->whereIn('name', $names)
                    ->pluck('id')
                    ->all();
            }
        }

        $this->merge([
            'head_code' => $this->head_code ?: null,
            'default_party_type_id' => $this->default_party_type_id ?: null,
            'requires_party' => filter_var($this->input('requires_party', false), FILTER_VALIDATE_BOOLEAN),
            'requires_reference' => filter_var($this->input('requires_reference', false), FILTER_VALIDATE_BOOLEAN),
            'settlement_type_ids' => $settlementIds ?: [],
            'description' => $this->description ?: null,
        ]);
    }

    public function rules(): array
    {
        $headId = $this->route('transaction_head')?->id;

        return [
            'head_code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('transaction_heads', 'head_code')
                    ->whereNull('deleted_at')
                    ->ignore($headId),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transaction_heads', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($headId),
            ],

            'nature' => [
                'required',
                Rule::in(['Payment', 'Receipt', 'Due', 'Advance', 'Adjustment', 'Expense', 'Journal']),
            ],

            'default_party_type_id' => [
                'nullable',
                'integer',
                'exists:party_types,id',
            ],

            'requires_party' => [
                'required',
                'boolean',
            ],

            'requires_reference' => [
                'nullable',
                'boolean',
            ],

            'settlement_type_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'settlement_type_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:settlement_types,id',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'head_code.unique' => 'This Transaction Head Code already exists. Please use another code.',

            'name.required' => 'Transaction Head Name is required.',
            'name.unique' => 'This Transaction Head Name already exists. Please use another name.',

            'nature.required' => 'Nature is required.',
            'nature.in' => 'Nature must be Payment, Receipt, Due, Advance, Adjustment, Expense, or Journal.',

            'default_party_type_id.exists' => 'Selected Default Party Type is invalid.',

            'requires_party.required' => 'Requires Party is required.',
            'requires_party.boolean' => 'Requires Party must be Yes or No.',

            'requires_reference.boolean' => 'Requires Reference must be Yes or No.',

            'settlement_type_ids.required' => 'Allowed Settlement Types are required.',
            'settlement_type_ids.array' => 'Allowed Settlement Types must be selected.',
            'settlement_type_ids.min' => 'Select at least one Allowed Settlement Type.',
            'settlement_type_ids.*.exists' => 'One of the selected Settlement Types is invalid.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
