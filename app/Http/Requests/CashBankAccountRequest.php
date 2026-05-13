<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cash_bank_code' => $this->cash_bank_code ?: null,
            'bank_id' => $this->bank_id ?: null,
            'bank_name' => $this->bank_name ?: null,
            'branch_name' => $this->branch_name ?: null,
            'account_number' => $this->account_number ?: null,
            'opening_balance' => $this->opening_balance ?: 0,
            'usage_note' => $this->usage_note ?: null,
        ]);
    }

    public function rules(): array
    {
        $accountId = $this->route('cash_bank_account')?->id;

        return [
            'cash_bank_code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('cash_bank_accounts', 'cash_bank_code')
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'cash_bank_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cash_bank_accounts', 'cash_bank_name')
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'type' => [
                'required',
                Rule::in(['Cash', 'Bank', 'Mobile Banking']),
            ],

            'linked_ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('status', 'Active')
                    ->where('is_cash_bank', true)
                    ->where('posting_allowed', true),
            ],

            'bank_name' => [
                'nullable',
                'required_if:type,Bank',
                'string',
                'max:255',
            ],

            'bank_id' => [
                'nullable',
                'integer',
                'exists:banks,id',
            ],

            'branch_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'account_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('cash_bank_accounts', 'account_number')
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'usage_note' => [
                'nullable',
                'string',
                'max:255',
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
            'cash_bank_code.unique' => 'This Cash/Bank Account Code already exists. Please use another code.',

            'cash_bank_name.required' => 'Cash/Bank Account Name is required.',
            'cash_bank_name.unique' => 'This Cash/Bank Account Name already exists. Please use another name.',

            'type.required' => 'Type is required.',
            'type.in' => 'Type must be Cash, Bank, or Mobile Banking.',

            'linked_ledger_account_id.required' => 'Linked Ledger Account is required.',
            'linked_ledger_account_id.exists' => 'Linked Ledger Account must be an active Cash/Bank ledger account.',

            'bank_name.required_if' => 'Bank Name is required when Type is Bank.',
            'bank_id.exists' => 'Selected Bank is invalid.',

            'account_number.max' => 'Account Number cannot exceed 100 characters.',
            'account_number.unique' => 'This Account Number already exists. Please add another Account Number.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
