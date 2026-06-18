<?php

namespace App\Http\Requests\Company;

use App\Models\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialYearRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canAccounting('financial_years.manage') ?? false; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('financial_years')->where('company_id', $this->user()?->company_id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'lock_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'status' => ['required', Rule::in(array_keys(FinancialYear::statusOptions()))],
            'is_current' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'lock_date' => $this->filled('lock_date') ? $this->input('lock_date') : null,
            'status' => strtolower((string) $this->input('status', FinancialYear::STATUS_OPEN)),
            'is_current' => $this->boolean('is_current'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
