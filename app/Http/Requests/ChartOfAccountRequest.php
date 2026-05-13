<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->parent_id ?: null,
            'account_level' => $this->account_level ?: 'Ledger',
            'normal_balance' => $this->normal_balance ?: null,
            'posting_allowed' => $this->boolean('posting_allowed', true),
            'is_cash_bank' => $this->boolean('is_cash_bank'),
            // Opening balances are captured in the dedicated Opening Balance module.
        ]);
    }

    public function rules(): array
    {
        $accountId = $this->route('chart_of_account')?->id;

        return [
            'account_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('chart_of_accounts', 'account_code')
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'account_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts', 'account_name')
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'account_level' => [
                'required',
                Rule::in(['Group', 'Ledger']),
            ],

            'account_type_id' => [
                'required',
                'integer',
                'exists:account_types,id',
            ],

            'normal_balance' => [
                'nullable',
                Rule::in(['Debit', 'Credit']),
            ],

            'parent_id' => [
                'nullable',
                'integer',
                'exists:chart_of_accounts,id',
            ],

            'is_cash_bank' => [
                'nullable',
                'boolean',
            ],

            'posting_allowed' => [
                'required',
                'boolean',
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
            'account_code.required' => 'Account Code is required.',
            'account_code.unique' => 'This Account Code already exists. Please add another Account Code.',

            'account_name.required' => 'Account Name is required.',
            'account_name.unique' => 'This Account Name already exists. Please add another Account Name.',

            'account_level.in' => 'Account Level must be Group or Ledger.',
            'normal_balance.in' => 'Normal Balance must be Debit or Credit.',

            'account_type_id.required' => 'Account Type is required.',
            'account_type_id.exists' => 'Selected Account Type is invalid.',

            'parent_id.exists' => 'Selected Parent Account is invalid.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
