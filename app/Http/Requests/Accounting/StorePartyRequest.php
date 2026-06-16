<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('parties')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_PARTY_TYPE)],
            'opening_balance' => ['nullable', 'numeric', 'decimal:0,2'],
            'receivable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'payable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
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
