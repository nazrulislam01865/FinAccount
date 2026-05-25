<?php

namespace App\Http\Requests\AccountingReports;

use App\Services\Accounting\FinancialYearService;
use Illuminate\Foundation\Http\FormRequest;

class CashBankBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = config('accounting_reports.permissions.view_reports');
        return $ability ? (bool) $this->user()?->can($ability) : true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer'],
            'book_type' => ['nullable', 'in:All,Combined Book,Cash Book Only,Bank Book Only'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'transaction_type' => ['nullable', 'in:All,Inflow,Outflow'],
        ];
    }

    public function filters(): array
    {
        $range = app(FinancialYearService::class)->reportRange((int) ($this->user()?->company_id ?? 0));

        return [
            'account_id' => $this->input('account_id'),
            'book_type' => $this->input('book_type', 'All'),
            'from_date' => $this->input('from_date', $range['from_date']),
            'to_date' => $this->input('to_date', $range['to_date']),
            'transaction_type' => $this->input('transaction_type', 'All'),
        ];
    }
}
