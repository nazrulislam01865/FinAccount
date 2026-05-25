<?php

namespace App\Http\Requests\AccountingReports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.full');
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
        $range = app(FinancialYearService::class)->reportRange((int) ($this->user()?->company_id ?? 0));

        return [
            'q' => $this->input('q'),
            'as_of_date' => $this->input('as_of_date', $range['to_date']),
            'include_zero_balances' => $this->boolean('include_zero_balances'),
            'include_inactive_accounts' => $this->boolean('include_inactive_accounts'),
            'company_id' => (int) ($this->user()?->company_id ?? 0),
        ];
    }
}
