<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VoucherNumberingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'financial_year_id' => $this->financial_year_id ?: null,
            'voucher_type' => trim((string) $this->input('voucher_type')),
            'prefix' => strtoupper(trim((string) $this->input('prefix'))),
            'format_template' => trim((string) $this->input('format_template')),
            'starting_number' => (int) ($this->starting_number ?: 1),
            'number_length' => (int) ($this->number_length ?: 5),
            'reset_every_year' => filter_var($this->input('reset_every_year', true), FILTER_VALIDATE_BOOLEAN),
            'used_for' => $this->used_for ?: null,
            'status' => $this->status ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $ruleId = $this->route('voucher_numbering_rule')?->id;

        return [
            'financial_year_id' => [
                'required',
                'integer',
                Rule::exists('financial_years', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'voucher_type' => [
                'required',
                'string',
                'max:100',
                Rule::unique('voucher_numbering_rules', 'voucher_type')
                    ->where(fn ($query) => $query
                        ->where('financial_year_id', $this->input('financial_year_id'))
                        ->whereNull('deleted_at'))
                    ->ignore($ruleId),
            ],

            'prefix' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('voucher_numbering_rules', 'prefix')
                    ->where(fn ($query) => $query
                        ->where('financial_year_id', $this->input('financial_year_id'))
                        ->whereNull('deleted_at'))
                    ->ignore($ruleId),
            ],

            'format_template' => ['required', 'string', 'max:100'],
            'starting_number' => ['required', 'integer', 'min:1'],
            'number_length' => ['required', 'integer', 'min:3', 'max:10'],
            'reset_every_year' => ['required', 'boolean'],
            'used_for' => ['nullable', 'string', 'max:1000'],

            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $format = (string) $this->input('format_template');
            $prefix = (string) $this->input('prefix');
            $numberLength = (int) $this->input('number_length');

            if (!str_starts_with($format, $prefix)) {
                $validator->errors()->add(
                    'format_template',
                    'Format must start with the selected prefix.'
                );
            }

            preg_match_all('/\{0+\}/', $format, $matches);

            if (count($matches[0]) !== 1) {
                $validator->errors()->add(
                    'format_template',
                    'Format must contain exactly one number token such as {00000}.'
                );

                return;
            }

            $tokenLength = strlen($matches[0][0]) - 2;

            if ($tokenLength !== $numberLength) {
                $validator->errors()->add(
                    'number_length',
                    'Number Length must match the number of zeros in the format token.'
                );
            }

            if (!str_contains($format, '{YYYY}') && !str_contains($format, '{YY}')) {
                $validator->errors()->add(
                    'format_template',
                    'Format must contain {YYYY} or {YY} so voucher numbers are year-aware.'
                );
            }
        }];
    }

    public function messages(): array
    {
        return [
            'financial_year_id.required' => 'Financial Year is required.',
            'financial_year_id.exists' => 'Selected Financial Year is invalid.',

            'voucher_type.required' => 'Voucher Type is required.',
            'voucher_type.unique' => 'This Voucher Type already has a numbering rule for the selected Financial Year.',

            'prefix.required' => 'Prefix is required.',
            'prefix.regex' => 'Prefix may contain only uppercase letters and numbers.',
            'prefix.unique' => 'This Prefix is already used in the selected Financial Year.',

            'format_template.required' => 'Format is required.',

            'starting_number.required' => 'Starting Number is required.',
            'starting_number.min' => 'Starting Number must be at least 1.',

            'number_length.required' => 'Number Length is required.',
            'number_length.min' => 'Number Length must be at least 3.',
            'number_length.max' => 'Number Length cannot exceed 10.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
