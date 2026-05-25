<?php

namespace App\Http\Requests\AccountingReports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Foundation\Http\FormRequest;

class AccountMovementReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('reports.full');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'account_id' => ['nullable', 'integer'],
            'transaction_head_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'include_zero_balances' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        $range = app(FinancialYearService::class)->reportRange((int) ($this->user()?->company_id ?? 0));

        return [
            'q' => $this->input('q'),
            'account_id' => $this->input('account_id'),
            'transaction_head_id' => $this->input('transaction_head_id'),
            'party_id' => $this->input('party_id'),
            'from_date' => $this->input('from_date', $range['from_date']),
            'to_date' => $this->input('to_date', $range['to_date']),
            'include_zero_balances' => $this->boolean('include_zero_balances'),
            'company_id' => (int) ($this->user()?->company_id ?? 0),
        ];
    }
}
