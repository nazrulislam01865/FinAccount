<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereIn('level', [1, 2])
                    ->where('is_active', true)),
            ],
            'level' => ['nullable', 'integer', 'between:1,3'],
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
            'parent_id' => filled($this->input('parent_id')) ? (int) $this->input('parent_id') : null,
            'level' => filled($this->input('level')) ? (int) $this->input('level') : null,
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'type' => trim((string) $this->input('type')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
