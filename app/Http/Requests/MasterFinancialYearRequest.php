<?php

namespace App\Http\Requests;

use App\Models\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('master-data.manage');
    }

    /**
     * Generate a readable year name when the user leaves it blank and normalize
     * legacy Active/Inactive input to the Phase 2 Open/Closed lifecycle.
     */
    protected function prepareForValidation(): void
    {
        $start = $this->input('start_date');
        $end = $this->input('end_date');
        $name = trim((string) $this->input('name'));
        $status = (string) ($this->input('status') ?: FinancialYear::STATUS_OPEN);

        if ($name === '' && $start && $end) {
            $name = date('Y', strtotime($start)) . '-' . date('Y', strtotime($end));
        }

        $status = match ($status) {
            'Active' => FinancialYear::STATUS_OPEN,
            'Inactive' => FinancialYear::STATUS_CLOSED,
            default => $status,
        };

        $isCurrent = filter_var($this->input('is_current', $this->input('is_active', false)), FILTER_VALIDATE_BOOLEAN);

        $this->merge([
            'name' => $name,
            'lock_date' => $this->lock_date ?: null,
            'is_active' => $isCurrent,
            'is_current' => $isCurrent,
            'status' => $status,
        ]);
    }

    public function rules(): array
    {
        $financialYearId = $this->route('financial_year')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('financial_years', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($financialYearId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'lock_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'is_active' => ['required', 'boolean'],
            'is_current' => ['required', 'boolean'],
            'status' => ['required', Rule::in(FinancialYear::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Financial Year Name is required.',
            'name.unique' => 'This Financial Year Name already exists.',
            'start_date.required' => 'Financial Year Start Date is required.',
            'end_date.required' => 'Financial Year End Date is required.',
            'end_date.after' => 'Financial Year End Date must be after the start date.',
            'lock_date.after_or_equal' => 'Lock Date cannot be before the Financial Year Start Date.',
            'lock_date.before_or_equal' => 'Lock Date cannot be after the Financial Year End Date.',
            'status.in' => 'Status must be Open, Closed, or Locked.',
        ];
    }
}
