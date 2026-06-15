<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $accountExists = Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId);

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('parties')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Customer', 'Supplier', 'Worker', 'Owner', 'Lender'])],
            'opening_balance' => ['nullable', 'numeric'],
            'receivable_account_id' => ['nullable', 'integer', $accountExists],
            'payable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
