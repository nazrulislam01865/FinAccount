<?php

namespace App\Http\Requests\AccountingReports;

use Illuminate\Foundation\Http\FormRequest;

class PartyBalanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission(['reports.view', 'reports.full', 'customer-ledgers.view', 'supplier-ledgers.view']);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'party_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'include_zero_balances' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        return [
            'q' => $this->input('q'),
            'party_id' => $this->input('party_id'),
            'from_date' => $this->input('from_date', now()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', now()->toDateString()),
            'include_zero_balances' => $this->boolean('include_zero_balances'),
            'company_id' => (int) ($this->user()?->company_id ?? 0),
        ];
    }
}
