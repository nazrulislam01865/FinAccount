<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => $this->mobile ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'opening_balance' => $this->opening_balance ?: 0,
            'opening_balance_type' => $this->opening_balance_type ?: null,
            'notes' => $this->notes ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'party_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parties', 'party_name')
                    ->whereNull('deleted_at'),
            ],

            'party_type_id' => [
                'required',
                'integer',
                'exists:party_types,id',
            ],

            'mobile' => [
                'nullable',
                'regex:/^\+8801\d{3}-\d{6}$/',
                Rule::unique('parties', 'mobile')
                    ->whereNull('deleted_at'),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('parties', 'email')
                    ->whereNull('deleted_at'),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'linked_ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('status', 'Active'),
            ],

            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'opening_balance_type' => [
                'nullable',
                Rule::in(['Debit', 'Credit']),
            ],

            'notes' => [
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
            'party_name.required' => 'Party Name is required.',
            'party_name.unique' => 'This Party Name already exists. Please use another name.',

            'party_type_id.required' => 'Party Type is required.',
            'party_type_id.exists' => 'Selected Party Type is invalid.',

            'mobile.regex' => 'Mobile must be in this format: +8801XXX-XXXXXX.',
            'mobile.unique' => 'This Mobile number already exists. Please use another mobile number.',

            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This Email already exists. Please use another email address.',

            'linked_ledger_account_id.required' => 'Linked Ledger / Group is required.',
            'linked_ledger_account_id.exists' => 'Selected Linked Ledger / Group is invalid.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'opening_balance_type.in' => 'Opening Balance Type must be Debit or Credit.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
