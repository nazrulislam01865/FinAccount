<?php

namespace App\Http\Requests\AccountingReports;

use App\Services\Accounting\FinancialYearService;
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
            'account_group_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'integer'],
            'voucher_type' => ['nullable', 'string', 'max:60'],
            'transaction_head_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40'],
            'nature' => ['nullable', 'in:All,Payment,Receipt,Due,Advance,Adjustment'],
        ];
    }

    public function filters(): array
    {
        $range = app(FinancialYearService::class)->reportRange((int) ($this->user()?->company_id ?? 0));

        return [
            'q' => $this->input('q'),
            'from_date' => $this->input('from_date', $range['from_date']),
            'to_date' => $this->input('to_date', $range['to_date']),
            'account_group_id' => $this->input('account_group_id'),
            'account_id' => $this->input('account_id'),
            'party_id' => $this->input('party_id'),
            'voucher_type' => $this->input('voucher_type', 'All'),
            'transaction_head_id' => $this->input('transaction_head_id'),
            'status' => $this->input('status', 'All'),
            'nature' => $this->input('nature', 'All'),
        ];
    }
}
