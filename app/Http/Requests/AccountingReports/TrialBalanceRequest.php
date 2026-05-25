<?php

namespace App\Http\Requests\AccountingReports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Foundation\Http\FormRequest;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.full');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'account_type' => ['nullable', 'string', 'max:60'],
            'balance_type' => ['nullable', 'in:All,Debit,Credit,Zero'],
        ];
    }

    public function filters(): array
    {
        $range = app(FinancialYearService::class)->reportRange((int) ($this->user()?->company_id ?? 0));

        return [
            'q' => $this->input('q'),
            'from_date' => $this->input('from_date', $range['from_date']),
            'to_date' => $this->input('to_date', $range['to_date']),
            'account_type' => $this->input('account_type', 'All'),
            'balance_type' => $this->input('balance_type', 'All'),
        ];
    }
}
