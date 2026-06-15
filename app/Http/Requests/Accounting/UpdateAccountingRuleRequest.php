<?php

namespace App\Http\Requests\Accounting;

use App\Models\AccountingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $accountingRule = $this->route('accounting_rule');
        $sources = [
            AccountingRule::SOURCE_SELECTED_MONEY,
            AccountingRule::SOURCE_HEAD_ACCOUNT,
            AccountingRule::SOURCE_PARTY_RECEIVABLE,
            AccountingRule::SOURCE_PARTY_PAYABLE,
        ];

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('accounting_rules')->where('company_id', $companyId)->ignore($accountingRule),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['Sales', 'Payment', 'Liability'])],
            'party_type' => ['required', Rule::in(['Any', 'Customer', 'Supplier', 'Worker', 'Owner', 'Lender'])],
            'debit_source' => ['required', Rule::in($sources)],
            'credit_source' => ['required', Rule::in($sources)],
            'money_required' => ['required', 'boolean'],
            'party_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
