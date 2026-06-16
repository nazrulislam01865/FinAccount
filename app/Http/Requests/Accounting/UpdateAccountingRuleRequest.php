<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingRuleRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $accountingRule = $this->route('accounting_rule');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('accounting_rules')->where('company_id', $companyId)->ignore($accountingRule),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_TRANSACTION_CATEGORY)],
            'party_type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_RULE_PARTY_TYPE)],
            'debit_source' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNTING_SOURCE)],
            'credit_source' => ['required', 'different:debit_source', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNTING_SOURCE)],
            'money_required' => ['required', 'boolean'],
            'party_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'money_required' => $this->boolean('money_required'),
            'party_required' => $this->boolean('party_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
