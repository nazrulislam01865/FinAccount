<?php

namespace App\Http\Requests\Accounting;

use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccounting('opening_balances.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        return [
            'financial_year_id' => [
                'nullable', 'integer',
                Rule::exists('financial_years', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'balance_date' => ['required', 'date'],
            'chart_of_account_id' => [
                'required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('level', 3)
                    ->where('is_active', true)),
            ],
            'party_id' => [
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'debit' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'credit' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'status' => ['required', Rule::in(array_keys(OpeningBalance::statusOptions()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'financial_year_id' => $this->input('financial_year_id') ?: null,
            'party_id' => $this->input('party_id') ?: null,
            'money_account_id' => $this->input('money_account_id') ?: null,
            'debit' => $this->amount($this->input('debit', 0)),
            'credit' => $this->amount($this->input('credit', 0)),
            'status' => $this->input('status') ?: OpeningBalance::STATUS_POSTED,
            'reference' => trim((string) $this->input('reference')) ?: null,
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $debit = $this->amount($this->input('debit', 0));
            $credit = $this->amount($this->input('credit', 0));

            if ($debit <= 0 && $credit <= 0) {
                $validator->errors()->add('debit', 'Enter either Debit Opening or Credit Opening.');
            }

            if ($debit > 0 && $credit > 0) {
                $validator->errors()->add('debit', 'A row cannot have both Debit Opening and Credit Opening.');
            }

            if ($this->filled('financial_year_id')) {
                $year = FinancialYear::query()
                    ->where('company_id', $this->user()->company_id)
                    ->find($this->integer('financial_year_id'));

                if ($year && $this->filled('balance_date') && ! $year->containsDate((string) $this->input('balance_date'))) {
                    $validator->errors()->add('balance_date', 'Opening date must be inside the selected financial year.');
                }
            }
        });
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), CompanyContext::decimalPlaces());
    }
}
