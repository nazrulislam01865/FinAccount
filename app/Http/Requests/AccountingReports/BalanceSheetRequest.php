<?php

namespace App\Http\Requests\AccountingReports;

use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission(['reports.view', 'reports.full']);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'as_of_date' => ['nullable', 'date'],
            'include_zero_balances' => ['nullable', 'boolean'],
            'include_inactive_accounts' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => $this->input('q'),
            'as_of_date' => $this->input('as_of_date', now()->toDateString()),
            'include_zero_balances' => $this->boolean('include_zero_balances'),
            'include_inactive_accounts' => $this->boolean('include_inactive_accounts'),
            'company_id' => (int) ($this->user()?->company_id ?? 0),
        ];
    }
}
