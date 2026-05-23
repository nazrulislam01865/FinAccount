<?php

namespace App\Http\Requests;

use App\Models\SettlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('transaction-heads.manage');
    }

    protected function prepareForValidation(): void
    {
        $settlementIds = $this->input('settlement_type_ids');

        if (! $settlementIds && $this->filled('allowed_settlement_types')) {
            $raw = $this->input('allowed_settlement_types');
            $names = is_string($raw) ? json_decode($raw, true) : $raw;

            if (is_array($names)) {
                $settlementIds = SettlementType::query()
                    ->whereIn('name', $names)
                    ->pluck('id')
                    ->all();
            }
        }

        $category = $this->blankToNull($this->input('category'));
        $nature = $this->blankToNull($this->input('nature')) ?: $this->natureFromCategory($category);

        $partyRequiredMode = (string) ($this->input('party_required_mode') ?: ($this->boolean('requires_party') ? 'Required' : 'No'));
        $partyRequiredMode = match ($partyRequiredMode) {
            'Yes' => 'Required',
            'Optional' => 'Optional',
            'Required' => 'Required',
            default => 'No',
        };

        $this->merge([
            'head_code' => $this->blankToNull($this->head_code),
            'name' => $this->blankToNull($this->name),
            'nature' => $nature,
            'category' => $category ?: $nature,
            'default_party_type_id' => $this->default_party_type_id ?: null,
            'default_primary_ledger_id' => $this->default_primary_ledger_id ?: null,
            'default_movement' => $this->default_movement ?: 'Increase',
            'payment_method_required' => filter_var($this->input('payment_method_required', false), FILTER_VALIDATE_BOOLEAN),
            'party_required_mode' => $partyRequiredMode,
            'requires_party' => $partyRequiredMode !== 'No',
            'requires_reference' => filter_var($this->input('requires_reference', false), FILTER_VALIDATE_BOOLEAN),
            'is_system_default' => filter_var($this->input('is_system_default', false), FILTER_VALIDATE_BOOLEAN),
            'is_user_selectable' => filter_var($this->input('is_user_selectable', true), FILTER_VALIDATE_BOOLEAN),
            'sort_order' => $this->sort_order === null || $this->sort_order === '' ? null : (int) $this->sort_order,
            'linked_accounting_rule_code' => $this->blankToNull($this->linked_accounting_rule_code),
            'settlement_type_ids' => $settlementIds ?: [],
            'transaction_screen' => $this->blankToNull($this->transaction_screen),
            'description' => $this->blankToNull($this->description),
            'help_text' => $this->blankToNull($this->help_text),
            'developer_note' => $this->blankToNull($this->developer_note),
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
                Rule::in(['Payment', 'Receipt', 'Due', 'Advance', 'Adjustment', 'Expense', 'Journal', 'Purchase', 'Sales', 'Equity', 'Loan', 'Asset']),
            ],

            'category' => ['required', 'string', 'max:50'],

            'default_party_type_id' => [Rule::requiredIf($this->input('party_required_mode') === 'Required'), 'nullable', 'integer', 'exists:party_types,id'],
            'default_primary_ledger_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'default_movement' => ['required', Rule::in(['Increase', 'Decrease', 'No Movement'])],
            'payment_method_required' => ['required', 'boolean'],
            'party_required_mode' => ['required', Rule::in(['No', 'Optional', 'Required'])],
            'requires_party' => ['required', 'boolean'],
            'requires_reference' => ['nullable', 'boolean'],
            'is_system_default' => ['required', 'boolean'],
            'is_user_selectable' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'linked_accounting_rule_code' => ['nullable', 'string', 'max:30'],
            'transaction_screen' => ['nullable', 'string', 'max:100'],
            'settlement_type_ids' => ['required', 'array', 'min:1'],
            'settlement_type_ids.*' => ['required', 'integer', 'distinct', 'exists:settlement_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'developer_note' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'head_code.unique' => 'This Transaction Head Code already exists. Please use another code.',
            'name.required' => 'Transaction Head Name is required.',
            'name.unique' => 'This Transaction Head Name already exists. Please use another name.',
            'nature.required' => 'Nature is required.',
            'nature.in' => 'Nature must be Payment, Receipt, Due, Advance, Adjustment, Expense, Journal, Purchase, Sales, Equity, Loan, or Asset.',
            'category.required' => 'Transaction Category is required.',
            'default_party_type_id.required' => 'Party Type is required when Party Required is set to Required.',
            'default_party_type_id.exists' => 'Selected Default Party Type is invalid.',
            'default_primary_ledger_id.exists' => 'Selected Default Primary Ledger is invalid.',
            'party_required_mode.in' => 'Party requirement must be No, Optional, or Required.',
            'default_movement.in' => 'Default Movement must be Increase, Decrease, or No Movement.',
            'settlement_type_ids.required' => 'Allowed Settlement Types are required.',
            'settlement_type_ids.min' => 'Select at least one Allowed Settlement Type.',
            'settlement_type_ids.*.exists' => 'One of the selected Settlement Types is invalid.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function natureFromCategory(?string $category): string
    {
        return match ($category) {
            'Sales', 'Receipt' => 'Receipt',
            'Purchase', 'Due' => 'Due',
            'Expense Payment' => 'Expense',
            'Asset Purchase' => 'Asset',
            'Equity' => 'Equity',
            'Loan' => 'Loan',
            'Advance' => 'Advance',
            'Adjustment' => 'Adjustment',
            'Other' => 'Journal',
            default => 'Payment',
        };
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
