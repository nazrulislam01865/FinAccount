<?php

namespace App\Http\Requests;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $accountLevel = $this->input('account_level') ?: 'Ledger';

        $this->merge([
            'parent_id' => $this->parent_id ?: null,
            'account_level' => in_array($accountLevel, ['Group', 'Ledger'], true) ? $accountLevel : 'Ledger',
            'normal_balance' => $this->normal_balance ?: null,
            'posting_allowed' => $this->boolean('posting_allowed', $accountLevel !== 'Group'),
            'is_cash_bank' => $this->boolean('is_cash_bank'),
        ]);
    }

    public function rules(): array
    {
        $accountId = $this->route('chart_of_account')?->id;
        $companyId = $this->companyId();

        return [
            'account_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9.\-_]+$/',
                Rule::unique('chart_of_accounts', 'account_code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->whereNull('deleted_at')
                    ->ignore($accountId),
            ],

            'account_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts', 'account_name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $accountType = AccountType::query()->find($this->integer('account_type_id'));
            $accountId = $this->route('chart_of_account')?->id;
            $parentId = $this->input('parent_id');
            $isGroup = $this->input('account_level') === 'Group';
            $isCashBank = $this->boolean('is_cash_bank');
            $postingAllowed = $this->boolean('posting_allowed');

            if (!$accountType) {
                return;
            }

            if ($this->filled('normal_balance') && $this->input('normal_balance') !== $accountType->normal_balance) {
                $validator->errors()->add('normal_balance', 'Normal Balance must match the selected Account Type.');
            }

            if ($isGroup && $postingAllowed) {
                $validator->errors()->add('posting_allowed', 'Group accounts cannot be used for posting.');
            }

            if ($isGroup && $isCashBank) {
                $validator->errors()->add('is_cash_bank', 'Group accounts cannot be marked as Cash/Bank accounts.');
            }

            if ($isCashBank && $accountType->name !== 'Asset') {
                $validator->errors()->add('is_cash_bank', 'Cash/Bank accounts must use the Asset account type.');
            }

            if ($isCashBank && !$postingAllowed) {
                $validator->errors()->add('is_cash_bank', 'Cash/Bank accounts must be posting ledger accounts.');
            }

            if (!$parentId) {
                return;
            }

            if ($accountId && (int) $parentId === (int) $accountId) {
                $validator->errors()->add('parent_id', 'An account cannot be its own parent.');
                return;
            }

            $parent = ChartOfAccount::query()
                ->whereKey($parentId)
                ->first();

            if (!$parent) {
                return;
            }

            if ($parent->status !== 'Active') {
                $validator->errors()->add('parent_id', 'Parent Account must be active.');
            }

            if ($parent->account_level !== 'Group') {
                $validator->errors()->add('parent_id', 'Parent Account must be a Group account.');
            }

            if ((int) $parent->account_type_id !== (int) $this->input('account_type_id')) {
                $validator->errors()->add('parent_id', 'Parent Account must use the same Account Type.');
            }

            if ($accountId && $this->isDescendantOf((int) $parentId, (int) $accountId)) {
                $validator->errors()->add('parent_id', 'Parent Account cannot be one of this account\'s child accounts.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'account_code.required' => 'Account Code is required.',
            'account_code.regex' => 'Account Code may contain only letters, numbers, dots, hyphens, and underscores.',
            'account_code.unique' => 'This Account Code already exists for this company. Please add another Account Code.',

            'account_name.required' => 'Account Name is required.',
            'account_name.unique' => 'This Account Name already exists for this company. Please add another Account Name.',

            'account_level.required' => 'Account Level is required.',
            'account_level.in' => 'Account Level must be Group or Ledger.',
            'normal_balance.in' => 'Normal Balance must be Debit or Credit.',

            'account_type_id.required' => 'Account Type is required.',
            'account_type_id.exists' => 'Selected Account Type is invalid.',

            'parent_id.exists' => 'Selected Parent Account is invalid.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function companyId(): ?int
    {
        return \App\Models\Company::query()->value('id');
    }

    private function isDescendantOf(int $candidateParentId, int $accountId): bool
    {
        $currentParentId = ChartOfAccount::query()
            ->whereKey($candidateParentId)
            ->value('parent_id');

        while ($currentParentId) {
            if ((int) $currentParentId === $accountId) {
                return true;
            }

            $currentParentId = ChartOfAccount::query()
                ->whereKey($currentParentId)
                ->value('parent_id');
        }

        return false;
    }
}