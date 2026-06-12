<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\TransactionHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('transaction-heads.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->blankToNull($this->input('name')),
            'category' => $this->blankToNull($this->input('category')),
            'default_primary_ledger_id' => $this->filled('default_primary_ledger_id')
                ? (int) $this->input('default_primary_ledger_id')
                : null,
            'help_text' => $this->blankToNull($this->input('help_text')),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $head = $this->route('transaction_head');
        $headId = $head instanceof TransactionHead ? $head->id : null;
        $companyId = (int) ($this->user()?->company_id ?? 0);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transaction_heads', 'name')
                    ->where(function ($query) use ($companyId) {
                        $query->whereNull('deleted_at')
                            ->where(function ($scope) use ($companyId) {
                                if ($companyId > 0) {
                                    $scope->where('company_id', $companyId)
                                        ->orWhere(function ($global) {
                                            $global->whereNull('company_id')->where('is_system_default', true);
                                        });
                                } else {
                                    $scope->whereNull('company_id');
                                }
                            });
                    })
                    ->ignore($headId),
            ],
            'category' => [
                'required',
                'string',
                'max:50',
                Rule::in(TransactionHead::transactionCategories()),
            ],
            'default_primary_ledger_id' => [
                'required',
                'integer',
                'exists:chart_of_accounts,id',
            ],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $ledgerId = $this->integer('default_primary_ledger_id');

            if ($ledgerId <= 0) {
                return;
            }

            $ledger = ChartOfAccount::query()->find($ledgerId);

            if (! $ledger) {
                return;
            }

            $companyId = (int) ($this->user()?->company_id ?? 0);
            if ($companyId > 0 && $ledger->company_id !== null && (int) $ledger->company_id !== $companyId) {
                $validator->errors()->add(
                    'default_primary_ledger_id',
                    'The selected Posting COA belongs to another company.'
                );
            }

            $isLevelFour = (int) ($ledger->coa_level ?: ($ledger->account_level === 'Ledger' ? 4 : 0)) === 4;

            if (! $isLevelFour || $ledger->account_level !== 'Ledger' || ! $ledger->posting_allowed) {
                $validator->errors()->add(
                    'default_primary_ledger_id',
                    'Posting COA must be an active Level 4 posting ledger.'
                );
            }

            if ($ledger->status !== 'Active') {
                $validator->errors()->add(
                    'default_primary_ledger_id',
                    'Posting COA must be active.'
                );
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Transaction Head Name is required.',
            'name.unique' => 'This Transaction Head Name already exists in your company.',
            'category.required' => 'Transaction Category is required.',
            'category.in' => 'Select a valid Transaction Category.',
            'default_primary_ledger_id.required' => 'Posting COA is required.',
            'default_primary_ledger_id.exists' => 'Selected Posting COA is invalid.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
