<?php

namespace App\Http\Requests\AccountingReports;

use Illuminate\Foundation\Http\FormRequest;

class TrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = config('accounting_reports.permissions.view_reports');
        return $ability ? (bool) $this->user()?->can($ability) : true;
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
        return [
            'q' => $this->input('q'),
            'from_date' => $this->input('from_date', now()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', now()->toDateString()),
            'account_type' => $this->input('account_type', 'All'),
            'balance_type' => $this->input('balance_type', 'All'),
        ];
    }
}
