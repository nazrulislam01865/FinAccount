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
        return (bool) $this->user()?->hasAnyPermission('chart-of-accounts.manage');
    }

    protected function prepareForValidation(): void
    {
        $coaLevel = (int) ($this->input('coa_level') ?: ($this->input('account_level') === 'Group' ? 3 : 4));
        $coaLevel = in_array($coaLevel, [1, 2, 3, 4], true) ? $coaLevel : 4;
        $isPostingLevel = $coaLevel === 4;
        $ledgerType = $isPostingLevel ? (string) ($this->input('ledger_type') ?: 'Asset') : 'Group';
        $isCashBank = in_array($ledgerType, ['Cash', 'Bank'], true);
        $isPartyControl = $ledgerType === 'Party Control';

        $this->merge([
            'parent_id' => $coaLevel === 1 ? null : ($this->parent_id ?: null),
            'coa_level' => $coaLevel,
            'account_level' => $isPostingLevel ? 'Ledger' : 'Group',
            'normal_balance' => $this->normal_balance ?: null,
            'posting_allowed' => $isPostingLevel,
            'ledger_type' => $ledgerType,
            'is_cash_bank' => $isCashBank,
            'is_party_control' => $isPartyControl,
            'party_type_id' => $isPartyControl ? ($this->party_type_id ?: null) : null,
            'is_system_ledger' => $this->boolean('is_system_ledger'),
            'is_user_selectable' => $isPostingLevel && ! $isPartyControl && $this->boolean('is_user_selectable', true),
            'account_group' => $this->account_group ?: null,
            'account_sub_group' => $this->account_sub_group ?: null,
            'account_nature' => $this->account_nature ?: null,
            'example_usage' => $this->example_usage ?: null,
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

            'coa_level' => [
                'required',
                'integer',
                Rule::in([1, 2, 3, 4]),
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

            'account_group' => [
                'nullable',
                'string',
                'max:100',
            ],

            'account_sub_group' => [
                'nullable',
                'string',
                'max:100',
            ],

            'account_nature' => [
                'nullable',
                'string',
                'max:50',
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

            'ledger_type' => [
                'required',
                Rule::in(ChartOfAccount::LEDGER_TYPES),
            ],

            'is_cash_bank' => [
                'required',
                'boolean',
            ],

            'is_party_control' => [
                'required',
                'boolean',
            ],

            'party_type_id' => [
                'nullable',
                'integer',
                'exists:party_types,id',
            ],

            'is_system_ledger' => [
                'required',
                'boolean',
            ],

            'is_user_selectable' => [
                'required',
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

            'example_usage' => [
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
            $coaLevel = $this->integer('coa_level');
            $isGroup = $coaLevel < 4;
            $isPostingLevel = $coaLevel === 4;
            $ledgerType = (string) $this->input('ledger_type');
            $isCashBank = $this->boolean('is_cash_bank');
            $isPartyControl = $this->boolean('is_party_control');
            $postingAllowed = $this->boolean('posting_allowed');
            $isUserSelectable = $this->boolean('is_user_selectable');

            if (! $accountType) {
                return;
            }


            if ($isGroup && $postingAllowed) {
                $validator->errors()->add('posting_allowed', 'Only Level 4 Ledger Head accounts can be used for posting.');
            }

            if ($isGroup && $ledgerType !== 'Group') {
                $validator->errors()->add('ledger_type', 'Level 1, 2, and 3 accounts must use Ledger Type Group.');
            }

            if (! $isPostingLevel && ($isCashBank || $isPartyControl || $isUserSelectable)) {
                $validator->errors()->add('coa_level', 'Only Level 4 Ledger Head accounts can be cash/bank, party control, or user selectable.');
            }

            if ($isPostingLevel && ! $postingAllowed) {
                $validator->errors()->add('posting_allowed', 'Level 4 Ledger Head accounts must allow posting.');
            }

            if ($isCashBank && ! in_array($ledgerType, ['Cash', 'Bank'], true)) {
                $validator->errors()->add('ledger_type', 'Cash/Bank flag must match Ledger Type Cash or Bank.');
            }

            if ($isCashBank && $accountType->name !== 'Asset') {
                $validator->errors()->add('ledger_type', 'Cash and Bank ledgers must use the Asset account class.');
            }

            if ($isPartyControl && $ledgerType !== 'Party Control') {
                $validator->errors()->add('ledger_type', 'Party control accounts must use Ledger Type Party Control.');
            }

            if ($isPartyControl && ! $this->filled('party_type_id')) {
                $validator->errors()->add('party_type_id', 'Party Type is required for Party Control ledgers.');
            }

            if ($isPartyControl && $isUserSelectable) {
                $validator->errors()->add('is_user_selectable', 'Party Control ledgers should be controlled by rules and party selection, not directly selected by normal users.');
            }

            if ($coaLevel === 1 && $parentId) {
                $validator->errors()->add('parent_id', 'Level 1 Account Class cannot have a parent account.');
                return;
            }

            if ($coaLevel > 1 && ! $parentId) {
                $validator->errors()->add('parent_id', 'Parent Account is required for Level 2, Level 3, and Level 4 accounts.');
                return;
            }

            if (! $parentId) {
                return;
            }

            if ($accountId && (int) $parentId === (int) $accountId) {
                $validator->errors()->add('parent_id', 'An account cannot be its own parent.');
                return;
            }

            $parent = ChartOfAccount::query()
                ->whereKey($parentId)
                ->first();

            if (! $parent) {
                return;
            }

            if ($parent->status !== 'Active') {
                $validator->errors()->add('parent_id', 'Parent Account must be active.');
            }

            if ($parent->account_level !== 'Group') {
                $validator->errors()->add('parent_id', 'Parent Account must be a non-posting Group account.');
            }

            $parentLevel = (int) ($parent->coa_level ?: ($parent->account_level === 'Ledger' ? 4 : max(1, $coaLevel - 1)));
            if ($parentLevel !== $coaLevel - 1) {
                $validator->errors()->add('parent_id', 'Parent Account must be exactly one level above this account.');
            }

            if ((int) $parent->account_type_id !== (int) $this->input('account_type_id')) {
                $validator->errors()->add('parent_id', 'Parent Account must use the same Account Class.');
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

            'coa_level.required' => 'CoA Level is required.',
            'coa_level.in' => 'CoA Level must be Level 1, Level 2, Level 3, or Level 4.',
            'account_level.required' => 'Account Level is required.',
            'account_level.in' => 'Account Level must be Group or Ledger.',
            'normal_balance.in' => 'Normal Balance must be Debit or Credit.',

            'account_type_id.required' => 'Account Class is required.',
            'account_type_id.exists' => 'Selected Account Class is invalid.',

            'parent_id.exists' => 'Selected Parent Account is invalid.',
            'party_type_id.exists' => 'Selected Party Type is invalid.',

            'ledger_type.required' => 'Ledger Type is required.',
            'ledger_type.in' => 'Selected Ledger Type is invalid.',

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
