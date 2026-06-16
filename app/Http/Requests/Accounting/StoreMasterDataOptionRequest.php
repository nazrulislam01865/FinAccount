<?php

namespace App\Http\Requests\Accounting;

use App\Models\AccountingOption;
use App\Services\Accounting\MasterDataService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterDataOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $configuration = app(MasterDataService::class)->configuration((string) $this->route('section'));
        $isTransactionCategory = $configuration['group'] === AccountingOption::GROUP_TRANSACTION_CATEGORY;

        $rules = [
            'value' => [
                'required',
                'string',
                'max:'.($isTransactionCategory ? 30 : 60),
                'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('accounting_options', 'value')
                    ->where('option_group', $configuration['group']),
            ],
            'label' => [
                'required',
                'string',
                'max:120',
                Rule::unique('accounting_options', 'label')
                    ->where('option_group', $configuration['group']),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($isTransactionCategory) {
            $rules['money_label'] = ['required', 'string', 'max:120'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'value' => trim((string) $this->input('value')),
            'label' => trim((string) $this->input('label')),
            'is_active' => $this->boolean('is_active'),
        ];

        if ($this->has('money_label')) {
            $payload['money_label'] = trim((string) $this->input('money_label'));
        }

        $this->merge($payload);
    }
}
