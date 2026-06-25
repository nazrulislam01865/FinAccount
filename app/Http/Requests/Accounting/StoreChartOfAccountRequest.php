<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user()?->canAccounting('chart_of_accounts.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNT_TYPE)],
            'normal_balance' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_NORMAL_BALANCE)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
