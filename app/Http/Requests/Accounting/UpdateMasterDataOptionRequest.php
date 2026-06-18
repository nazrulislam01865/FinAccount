<?php

namespace App\Http\Requests\Accounting;

use App\Models\AccountingOption;
use App\Services\Accounting\MasterDataService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterDataOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ((string) $this->route('section')) {
            'party-types' => 'party_types.manage',
            'money-account-types' => 'money_account_types.manage',
            'transaction-categories' => 'transaction_categories.manage',
            default => null,
        };

        return $permission !== null && ($this->user()?->canAccounting($permission) ?? false);
    }

    public function rules(): array
    {
        $configuration = app(MasterDataService::class)->configuration((string) $this->route('section'));
        $option = $this->route('accountingOption');
        $isTransactionCategory = $configuration['group'] === AccountingOption::GROUP_TRANSACTION_CATEGORY;

        $rules = [
            'value' => [
                'required',
                'string',
                'max:'.($isTransactionCategory ? 30 : 60),
                'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('accounting_options', 'value')
                    ->where('option_group', $configuration['group'])
                    ->ignore($option?->id),
            ],
            'label' => [
                'required',
                'string',
                'max:120',
                Rule::unique('accounting_options', 'label')
                    ->where('option_group', $configuration['group'])
                    ->ignore($option?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($isTransactionCategory) {
            $rules['money_label'] = ['required', 'string', 'max:120'];
            $rules['voucher_prefix'] = ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Z0-9]+$/'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $option = $this->route('accountingOption');

        $payload = [
            'value' => trim((string) ($this->has('value') ? $this->input('value') : $option?->value)),
            'label' => trim((string) $this->input('label')),
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : (bool) $option?->is_active,
        ];

        if ($this->has('money_label')) {
            $payload['money_label'] = trim((string) $this->input('money_label'));
        } elseif ($option?->option_group === AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            $payload['money_label'] = trim((string) ($option->metadata['money_label'] ?? 'Money Account'));
        }

        if ($this->has('voucher_prefix')) {
            $payload['voucher_prefix'] = strtoupper(trim((string) $this->input('voucher_prefix')));
        } elseif ($option?->option_group === AccountingOption::GROUP_TRANSACTION_CATEGORY) {
            $payload['voucher_prefix'] = strtoupper(trim((string) ($option->metadata['voucher_prefix'] ?? '')));
        }

        $this->merge($payload);
    }
}
