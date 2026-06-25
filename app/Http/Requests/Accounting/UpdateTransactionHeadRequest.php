<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionHeadRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user()?->canAccounting('transaction_heads.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $transactionHead = $this->route('transaction_head');

        return [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_TRANSACTION_CATEGORY)],
            'posting_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'allowed_settlements' => ['required', 'array', 'min:1'],
            'allowed_settlements.*' => ['required', 'distinct', $this->activeAccountingOption(AccountingOption::GROUP_SETTLEMENT_TYPE)],
            'party_type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_RULE_PARTY_TYPE)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'category' => strtoupper(trim((string) $this->input('category'))),
            'allowed_settlements' => array_values(array_unique(array_map(
                static fn ($value): string => strtoupper(trim((string) $value)),
                (array) $this->input('allowed_settlements', []),
            ))),
            'party_type' => trim((string) $this->input('party_type', 'Any')) ?: 'Any',
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
