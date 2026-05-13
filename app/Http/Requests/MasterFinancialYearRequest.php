<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Generate a readable year name when the user leaves it blank.
     */
    protected function prepareForValidation(): void
    {
        $start = $this->input('start_date');
        $end = $this->input('end_date');
        $name = trim((string) $this->input('name'));

        if ($name === '' && $start && $end) {
            $name = date('Y', strtotime($start)) . '-' . date('Y', strtotime($end));
        }

        $this->merge([
            'name' => $name,
            'is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOLEAN),
            'status' => $this->input('status') ?: 'Active',
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
            'is_active' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
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
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
