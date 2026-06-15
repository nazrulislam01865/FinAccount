<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('money_accounts')->where('company_id', $companyId)],
            'kind' => ['required', Rule::in(['Cash', 'Bank', 'Digital'])],
            'chart_of_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId),
                Rule::unique('money_accounts')->where('company_id', $companyId),
            ],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
