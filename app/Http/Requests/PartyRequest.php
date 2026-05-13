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
            'sub_type' => $this->sub_type ?: null,
            'default_ledger_nature' => $this->default_ledger_nature ?: null,
            'opening_balance' => $this->opening_balance ?: 0,
            // Notes and balance-side inputs were removed because they are not PRD fields.
        ]);
    }

    public function rules(): array
    {
        $partyId = $this->route('party')?->id;

        return [
            'party_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parties', 'party_name')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
            ],

            'party_type_id' => [
                'required',
                'integer',
                'exists:party_types,id',
            ],

            'sub_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'mobile' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('parties', 'mobile')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('parties', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
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
                    ->where('status', 'Active')
                    ->where('posting_allowed', true),
            ],

            'default_ledger_nature' => [
                'nullable',
                Rule::in(['Payable', 'Receivable', 'Advance Paid', 'Advance Received', 'No Effect']),
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
            'party_name.required' => 'Party Name is required.',
            'party_name.unique' => 'This Party Name already exists. Please use another name.',

            'party_type_id.required' => 'Party Type is required.',
            'party_type_id.exists' => 'Selected Party Type is invalid.',

            'mobile.max' => 'Mobile cannot exceed 50 characters.',
            'mobile.unique' => 'This Mobile number already exists. Please use another mobile number.',

            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This Email already exists. Please use another email address.',

            'linked_ledger_account_id.required' => 'Linked Ledger / Group is required.',
            'linked_ledger_account_id.exists' => 'Selected Linked Ledger / Group is invalid.',

            'default_ledger_nature.in' => 'Default Ledger Nature must be Payable, Receivable, Advance Paid, Advance Received, or No Effect.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
