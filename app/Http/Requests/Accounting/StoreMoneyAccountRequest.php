<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyAccountRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
                    ->where('type', 'Asset')
                    ->where('is_active', true),
                Rule::unique('money_accounts')->where('company_id', $companyId),
            ],
            'opening_balance' => ['nullable', 'numeric', 'decimal:0,2'],
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
