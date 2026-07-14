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
        return $this->user()?->canAccounting('accounting_rules.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $accountingRule = $this->route('accounting_rule');

        return [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'transaction_head_id' => ['nullable', 'integer', Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'category' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_TRANSACTION_CATEGORY)],
            'settlement_type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_SETTLEMENT_TYPE)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'transaction_head_id' => filled($this->input('transaction_head_id')) ? (int) $this->input('transaction_head_id') : null,
            'category' => $this->canonicalActiveAccountingOption(
                AccountingOption::GROUP_TRANSACTION_CATEGORY,
                $this->input('category'),
            ),
            'settlement_type' => strtoupper(trim((string) $this->input('settlement_type'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
