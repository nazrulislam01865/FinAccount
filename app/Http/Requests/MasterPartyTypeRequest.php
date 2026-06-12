<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\PartyType;
use App\Support\PartyAccountingProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MasterPartyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('master-data.manage');
    }

    /**
     * Keep party type codes predictable and make the default ledger optional.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'default_ledger_account_id' => $this->input('default_ledger_account_id') ?: null,
            'default_ledger_nature' => $this->input('default_ledger_nature')
                ?: $this->inferLedgerNature((string) $this->input('code'), (string) $this->input('name')),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'status' => $this->input('status') ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $partyTypeId = $this->route('party_type')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('party_types', 'name')->ignore($partyTypeId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('party_types', 'code')->ignore($partyTypeId),
            ],
            'default_ledger_account_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],
            'default_ledger_nature' => ['required', Rule::in(PartyAccountingProfile::NATURES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->input('default_ledger_account_id')) {
                return;
            }

            $ledger = ChartOfAccount::query()
                ->with('accountType')
                ->find($this->integer('default_ledger_account_id'));

            if (! $ledger) {
                return;
            }

            $accountType = $ledger->accountType?->name;
            $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;
            $nature = (string) $this->input('default_ledger_nature');

            if ($ledger->is_cash_bank || in_array($accountType, ['Income', 'Expense'], true)) {
                $validator->errors()->add(
                    'default_ledger_account_id',
                    'Party Type defaults must use an Asset, Liability, or Equity non-cash posting ledger.'
                );
                return;
            }

            if (in_array($nature, ['Receivable', 'Advance Paid'], true)
                && ($accountType !== 'Asset' || $normalBalance !== 'Debit')) {
                $validator->errors()->add(
                    'default_ledger_account_id',
                    'Receivable and Advance Paid Party Types require a Debit-normal Asset ledger.'
                );
            }

            if (in_array($nature, ['Payable', 'Advance Received'], true)
                && ($accountType !== 'Liability' || $normalBalance !== 'Credit')) {
                $validator->errors()->add(
                    'default_ledger_account_id',
                    'Payable and Advance Received Party Types require a Credit-normal Liability ledger.'
                );
            }

            if ($nature === PartyAccountingProfile::NATURE_CAPITAL
                && ($accountType !== 'Equity' || $normalBalance !== 'Credit')) {
                $validator->errors()->add(
                    'default_ledger_account_id',
                    'Capital Party Types require a Credit-normal Equity ledger.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Party Type Name is required.',
            'name.unique' => 'This Party Type Name already exists.',
            'code.required' => 'Party Type Code is required.',
            'code.regex' => 'Code may contain only uppercase letters, numbers, and underscores.',
            'code.unique' => 'This Party Type Code already exists.',
            'default_ledger_account_id.exists' => 'Selected default ledger is invalid.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function inferLedgerNature(string $code, string $name): string
    {
        $partyType = new PartyType([
            'code' => $code,
            'name' => $name,
        ]);

        return PartyAccountingProfile::inferFromPartyType($partyType);
    }

}
