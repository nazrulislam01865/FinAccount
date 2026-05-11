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
            'is_cash_bank' => $this->boolean('is_cash_bank'),
            'opening_balance' => $this->opening_balance ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'account_code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('chart_of_accounts', 'account_code')
                    ->whereNull('deleted_at'),
            ],

            'account_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts', 'account_name')
                    ->whereNull('deleted_at'),
            ],

            'account_type_id' => [
                'required',
                'integer',
                'exists:account_types,id',
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

            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
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

            'account_type_id.required' => 'Account Type is required.',
            'account_type_id.exists' => 'Selected Account Type is invalid.',

            'parent_id.exists' => 'Selected Parent Account is invalid.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
