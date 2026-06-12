<?php

namespace App\Http\Requests;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\OpeningBalance;
use App\Models\VoucherHeader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CashBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('cash-bank.manage');
    }

    protected function prepareForValidation(): void
    {
        // The business ID is generated only by the backend and can never be changed
        // from the browser, API payload, or an intercepted request.
        $this->request->remove('cash_bank_code');

        $payload = [
            'cash_bank_name' => trim((string) $this->cash_bank_name),
            'type' => $this->type ?: null,
            'linked_ledger_account_id' => $this->linked_ledger_account_id ?: null,
            'bank_id' => $this->bank_id ?: null,
            'bank_name' => $this->blankToNull($this->bank_name),
            'branch_name' => $this->blankToNull($this->branch_name),
            'account_number' => $this->blankToNull($this->account_number),
            'usage_note' => $this->blankToNull($this->usage_note),
        ];

        // Opening balance is not part of the normal Cash/Bank edit form. Only
        // normalize it when a dedicated workflow explicitly submits it.
        if ($this->exists('opening_balance')) {
            $payload['opening_balance'] = $this->normalizeMoney($this->opening_balance);
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $cashBankAccountId = $this->route('cash_bank_account')?->id;
        $companyId = $this->companyId();

        $companyScope = fn ($query) => $companyId > 0
            ? $query->where('company_id', $companyId)
            : $query->whereNull('company_id');

        return [
            // cash_bank_code is intentionally absent. It is server generated.
            'cash_bank_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cash_bank_accounts', 'cash_bank_name')
                    ->where(fn ($query) => $companyScope($query)->whereNull('deleted_at'))
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
                    ->where(fn ($query) => $companyScope($query)
                        ->where('status', 'Active')
                        ->where('account_level', 'Ledger')
                        ->where('is_cash_bank', true)
                        ->where('posting_allowed', true)
                        ->whereNull('deleted_at')),
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
                    ->where(fn ($query) => $companyScope($query)->whereNull('deleted_at'))
                    ->ignore($cashBankAccountId),
            ],

            'opening_balance' => [
                'sometimes',
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

            $companyId = $this->companyId();
            $account = $this->route('cash_bank_account');

            if ($account instanceof CashBankAccount
                && $companyId > 0
                && (int) $account->company_id !== $companyId) {
                $validator->errors()->add('cash_bank_name', 'The selected Cash/Bank account does not belong to your company.');
                return;
            }

            $ledger = ChartOfAccount::query()
                ->with('accountType')
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->find($this->integer('linked_ledger_account_id'));

            if (! $ledger) {
                return;
            }

            if ($ledger->account_level !== 'Ledger') {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be a Ledger account, not a Group account.');
            }

            if (! $ledger->posting_allowed) {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must allow posting.');
            }

            if (! $ledger->is_cash_bank) {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be marked as Cash/Bank in Chart of Accounts.');
            }

            if ($ledger->accountType?->name !== 'Asset') {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account must be an Asset account.');
            }

            $type = (string) $this->input('type');

            if ($type === 'Cash' && $ledger->ledger_type !== 'Cash') {
                $validator->errors()->add('linked_ledger_account_id', 'Cash type must be linked with a Cash ledger.');
            }

            if ($type === 'Bank' && $ledger->ledger_type !== 'Bank') {
                $validator->errors()->add('linked_ledger_account_id', 'Bank type must be linked with a Bank ledger.');
            }

            if ($type === 'Mobile Banking' && $ledger->ledger_type !== 'Mobile Wallet') {
                $isLegacyMobileMapping = $account instanceof CashBankAccount
                    && $account->type === 'Mobile Banking'
                    && (int) $account->linked_ledger_account_id === (int) $ledger->id
                    && $ledger->ledger_type === 'Bank';

                if (! $isLegacyMobileMapping) {
                    $validator->errors()->add('linked_ledger_account_id', 'Mobile Banking type must be linked with a Mobile Wallet ledger.');
                }
            }

            $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;

            if ($normalBalance !== 'Debit') {
                $validator->errors()->add('linked_ledger_account_id', 'Cash/Bank ledger normal balance must be Debit.');
            }

            $alreadyLinked = CashBankAccount::query()
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->where('linked_ledger_account_id', $ledger->id)
                ->when($account?->id, fn ($query) => $query->whereKeyNot($account->id))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('linked_ledger_account_id', 'This ledger is already linked with another Cash/Bank account.');
            }

            if ($account instanceof CashBankAccount && $this->hasAccountingHistory($account)) {
                if ((string) $account->type !== $type) {
                    $validator->errors()->add('type', 'Type cannot be changed because this account already has accounting history. Deactivate it and create a new account instead.');
                }

                if ((int) $account->linked_ledger_account_id !== (int) $ledger->id) {
                    $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger Account cannot be changed because this account already has accounting history. Deactivate it and create a new account instead.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'cash_bank_name.required' => 'Cash/Bank Account Name is required.',
            'cash_bank_name.unique' => 'This Cash/Bank Account Name already exists in your company. Please use another name.',
            'type.required' => 'Type is required.',
            'type.in' => 'Type must be Cash, Bank, or Mobile Banking.',
            'linked_ledger_account_id.required' => 'Linked Ledger Account is required.',
            'linked_ledger_account_id.exists' => 'Linked Ledger Account must be an active Asset posting ledger marked as Cash/Bank for your company.',
            'bank_id.exists' => 'Selected Bank is invalid.',
            'bank_name.required_if' => 'Bank Name is required when Type is Bank.',
            'account_number.max' => 'Account Number cannot exceed 100 characters.',
            'account_number.unique' => 'This Account Number already exists in your company. Please add another Account Number.',
            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function companyId(): int
    {
        return (int) ($this->user()?->company_id
            ?? $this->route('cash_bank_account')?->company_id
            ?? Company::query()->orderBy('id')->value('id')
            ?? 0);
    }

    private function hasAccountingHistory(CashBankAccount $account): bool
    {
        if (VoucherHeader::query()
            ->where('cash_bank_account_id', $account->id)
            ->where('status', VoucherHeader::STATUS_POSTED)
            ->exists()) {
            return true;
        }

        if ($account->linked_ledger_account_id
            && JournalLine::query()->where('ledger_id', $account->linked_ledger_account_id)->exists()) {
            return true;
        }

        return (bool) ($account->linked_ledger_account_id
            && OpeningBalance::query()
                ->where('account_id', $account->linked_ledger_account_id)
                ->where('status', 'Final')
                ->exists());
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
