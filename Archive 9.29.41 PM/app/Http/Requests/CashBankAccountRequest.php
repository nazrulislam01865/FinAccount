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
            'bank_id' => $this->bank_id ?: null,
            'branch_name' => $this->branch_name ?: null,
            'account_number' => $this->account_number ?: null,
            'opening_balance' => $this->opening_balance ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'cash_bank_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cash_bank_accounts', 'cash_bank_name')
                    ->whereNull('deleted_at'),
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
                    ->where('is_cash_bank', true),
            ],

            'bank_id' => [
                'nullable',
                'required_if:type,Bank',
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
                'digits:13',
                Rule::unique('cash_bank_accounts', 'account_number')
                    ->whereNull('deleted_at'),
            ],

            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
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
            'cash_bank_name.required' => 'Cash/Bank Account Name is required.',
            'cash_bank_name.unique' => 'This Cash/Bank Account Name already exists. Please use another name.',

            'type.required' => 'Type is required.',
            'type.in' => 'Type must be Cash, Bank, or Mobile Banking.',

            'linked_ledger_account_id.required' => 'Linked Ledger Account is required.',
            'linked_ledger_account_id.exists' => 'Linked Ledger Account must be an active Cash/Bank ledger account.',

            'bank_id.required_if' => 'Bank Name is required when Type is Bank.',
            'bank_id.exists' => 'Selected Bank is invalid.',

            'account_number.digits' => 'Account Number must be exactly 13 digits.',
            'account_number.unique' => 'This Account Number already exists. Please add another Account Number.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
