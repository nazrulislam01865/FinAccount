<?php

namespace App\Http\Requests\AccountingReports;

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
        return [
            'account_id' => $this->input('account_id'),
            'book_type' => $this->input('book_type', 'All'),
            'from_date' => $this->input('from_date', now()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', now()->toDateString()),
            'transaction_type' => $this->input('transaction_type', 'All'),
        ];
    }
}
