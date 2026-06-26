<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyAccountRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user()?->canAccounting('money_accounts.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('money_accounts')->where('company_id', $companyId)],
            'kind' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_MONEY_ACCOUNT_KIND)],
            'chart_of_account_id' => [
                'required', 'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('level', 3)
                    ->where('type', 'Asset')
                    ->where('is_active', true),
                Rule::unique('money_accounts')->where('company_id', $companyId),
            ],
            'opening_balance' => ['nullable', 'numeric', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
