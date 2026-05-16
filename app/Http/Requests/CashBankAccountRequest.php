<?php

namespace App\Http\Requests;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cash_bank_code' => $this->blankToNull($this->cash_bank_code),
            'cash_bank_name' => trim((string) $this->cash_bank_name),
            'type' => $this->type ?: null,
            'linked_ledger_account_id' => $this->linked_ledger_account_id ?: null,
            'bank_id' => $this->bank_id ?: null,
            'bank_name' => $this->blankToNull($this->bank_name),
            'branch_name' => $this->blankToNull($this->branch_name),
            'account_number' => $this->blankToNull($this->account_number),
            'opening_balance' => $this->normalizeMoney($this->opening_balance),
            'usage_note' => $this->blankToNull($this->usage_note),
        ]);
    }

    public function rules(): array
    {
        $cashBankAccountId = $this->route('cash_bank_account')?->id;

        return [
            'cash_bank_code' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9.\-_]+$/',
                Rule::unique('cash_bank_accounts', 'cash_bank_code')
                    ->whereNull('deleted_at')
                    ->ignore($cashBankAccountId),
            ],

            'cash_bank_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cash_bank_accounts', 'cash_bank_name')
                    ->whereNull('deleted_at')
                    ->ignore($cashBankAccountId),
            ],

            'type' => [
                'required',
                Rule::in(['Cash', 'Bank', 'Mobile Banking']),
            ],

            'linked_ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->where('account_level', 'Ledger')
                        ->where('is_cash_bank', true)
                        ->where('posting_allowed', true)
                        ->whereNull('deleted_at')),
                Rule::unique('cash_bank_accounts', 'linked_ledger_account_id')
                    ->whereNull('deleted_at')
                    ->ignore($cashBankAccountId),
            ],

            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')->where(fn ($query) => $query->where('status', 'Active')),
            ],

            'bank_name' => [
                'nullable',
                'required_if:type,Bank',
                'string',
                'max:255',
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
                    ->ignore($cashBankAccountId),
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ledger = ChartOfAccount::query()
                ->with('accountType')
                ->find($this->integer('linked_ledger_account_id'));

            if (!$ledger) {
                return;
            }

            if ($ledger->account_level !== 'Ledger') {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be a Ledger account, not a Group account.');
            }

            if (!$ledger->posting_allowed) {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must allow posting.');
            }

            if (!$ledger->is_cash_bank) {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be marked as Cash/Bank in Chart of Accounts.');
            }

            if ($ledger->accountType?->name !== 'Asset') {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be an Asset account.');
            }

            $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;

            if ($normalBalance !== 'Debit') {
                $validator->errors()->add('linked_ledger_account_id', 'Cash/Bank ledger normal balance must be Debit.');
            }

            $cashBankAccountId = $this->route('cash_bank_account')?->id;
            $alreadyLinked = CashBankAccount::query()
                ->where('linked_ledger_account_id', $ledger->id)
                ->when($cashBankAccountId, fn ($query) => $query->whereKeyNot($cashBankAccountId))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('linked_ledger_account_id', 'This ledger is already linked with another Cash/Bank account.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'cash_bank_code.regex' => 'Cash/Bank Account Code may contain only letters, numbers, dots, hyphens, and underscores.',
            'cash_bank_code.unique' => 'This Cash/Bank Account Code already exists. Please use another code.',

            'cash_bank_name.required' => 'Cash/Bank Account Name is required.',
            'cash_bank_name.unique' => 'This Cash/Bank Account Name already exists. Please use another name.',

            'type.required' => 'Type is required.',
            'type.in' => 'Type must be Cash, Bank, or Mobile Banking.',

            'linked_ledger_account_id.required' => 'Linked Ledger Account is required.',
            'linked_ledger_account_id.exists' => 'Linked Ledger Account must be an active Asset posting ledger marked as Cash/Bank.',
            'linked_ledger_account_id.unique' => 'This Linked Ledger Account is already used by another Cash/Bank account.',

            'bank_id.exists' => 'Selected Bank is invalid.',
            'bank_name.required_if' => 'Bank Name is required when Type is Bank.',

            'account_number.max' => 'Account Number cannot exceed 100 characters.',
            'account_number.unique' => 'This Account Number already exists. Please add another Account Number.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }
}