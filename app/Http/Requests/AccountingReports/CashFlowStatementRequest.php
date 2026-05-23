<?php

namespace App\Http\Requests\AccountingReports;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission(['reports.view', 'reports.full']);
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'section' => ['nullable', 'in:All,Operating Activities,Investing Activities,Financing Activities'],
        ];
    }

    public function filters(): array
    {
        return [
            'from_date' => $this->input('from_date', now()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', now()->toDateString()),
            'section' => $this->input('section', 'All'),
            'company_id' => (int) ($this->user()?->company_id ?? 0),
        ];
    }
}
