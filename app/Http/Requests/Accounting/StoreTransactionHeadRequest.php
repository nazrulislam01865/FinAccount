<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('transaction_heads')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['Sales', 'Payment', 'Liability'])],
            'accounting_rule_id' => ['required', 'integer', Rule::exists('accounting_rules', 'id')->where('company_id', $companyId)],
            'posting_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
