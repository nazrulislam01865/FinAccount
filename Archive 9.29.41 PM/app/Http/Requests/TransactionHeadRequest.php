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
                $settlementIds = SettlementType::whereIn('name', $names)->pluck('id')->all();
            }
        }

        $this->merge([
            'requires_party' => filter_var($this->input('requires_party', false), FILTER_VALIDATE_BOOLEAN),
            'requires_reference' => filter_var($this->input('requires_reference', false), FILTER_VALIDATE_BOOLEAN),
            'settlement_type_ids' => $settlementIds ?: [],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transaction_heads', 'name')->whereNull('deleted_at'),
            ],
            'nature' => ['required', Rule::in(['Payment', 'Receipt', 'Due', 'Advance', 'Adjustment'])],
            'default_party_type_id' => ['nullable', 'exists:party_types,id'],
            'requires_party' => ['nullable', 'boolean'],
            'requires_reference' => ['nullable', 'boolean'],
            'settlement_type_ids' => ['required', 'array', 'min:1'],
            'settlement_type_ids.*' => ['exists:settlement_types,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}
