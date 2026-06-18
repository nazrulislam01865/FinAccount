<?php

namespace App\Http\Requests\Accounting;

use App\Models\AccountingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoucherSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccounting('voucher_numbering.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                'max:30',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                    ->where('is_active', true)),
            ],
            'prefix' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Z0-9_-]+$/'],
            'next_number' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'between:2,10'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => trim((string) $this->input('category')),
            'prefix' => strtoupper(trim((string) $this->input('prefix'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
