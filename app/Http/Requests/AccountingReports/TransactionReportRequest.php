<?php

namespace App\Http\Requests\AccountingReports;

use Illuminate\Foundation\Http\FormRequest;

class TransactionReportRequest extends FormRequest
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
            'nature' => ['nullable', 'in:All,Payment,Receipt,Due,Advance,Adjustment'],
            'status' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => $this->input('q'),
            'from_date' => $this->input('from_date'),
            'to_date' => $this->input('to_date'),
            'nature' => $this->input('nature', 'All'),
            'status' => $this->input('status', 'All'),
        ];
    }
}
