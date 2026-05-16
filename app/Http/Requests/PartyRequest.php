<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\PartyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartyRequest extends FormRequest
{
    public const LEDGER_NATURES = [
        'Receivable',
        'Payable',
        'Advance Paid',
        'Advance Received',
        'No Effect',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $partyType = $this->party_type_id
            ? PartyType::query()->find($this->party_type_id)
            : null;

        $defaultLedgerNature = $this->default_ledger_nature
            ?: $this->inferLedgerNatureFromPartyType($partyType);

        $linkedLedgerId = $this->linked_ledger_account_id
            ?: $partyType?->default_ledger_account_id;

        $openingBalance = $this->normalizeMoney($this->opening_balance);
        $linkedLedger = $linkedLedgerId
            ? ChartOfAccount::query()->with('accountType')->find($linkedLedgerId)
            : null;

        $this->merge([
            'party_name' => trim((string) $this->party_name),
            'party_type_id' => $this->party_type_id ?: null,
            'mobile' => $this->blankToNull($this->mobile),
            'email' => $this->blankToNull($this->email),
            'address' => $this->blankToNull($this->address),
            'sub_type' => $this->blankToNull($this->sub_type),
            'linked_ledger_account_id' => $linkedLedgerId ?: null,
            'default_ledger_nature' => $defaultLedgerNature,
            'opening_balance' => $openingBalance,
            'opening_balance_type' => $openingBalance > 0
                ? $this->openingSideFromLedgerNature($defaultLedgerNature, $linkedLedger)
                : null,
            'notes' => $this->blankToNull($this->notes),
            'status' => $this->status ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        $partyId = $this->route('party')?->id;

        return [
            'party_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parties', 'party_name')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
            ],

            'party_type_id' => [
                'required',
                'integer',
                Rule::exists('party_types', 'id')
                    ->where(fn ($query) => $query->where('status', 'Active')),
            ],

            'sub_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'mobile' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('parties', 'mobile')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('parties', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($partyId),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'linked_ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->where('account_level', 'Ledger')
                        ->where('posting_allowed', true)
                        ->whereNull('deleted_at')),
            ],

            'default_ledger_nature' => [
                'required',
                Rule::in(self::LEDGER_NATURES),
            ],

            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'opening_balance_type' => [
                'nullable',
                Rule::in(['Debit', 'Credit']),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ledger = ChartOfAccount::query()
                ->with('accountType')
                ->find($this->integer('linked_ledger_account_id'));

            if (!$ledger) {
                return;
            }

            $ledgerType = $ledger->accountType?->name;
            $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;
            $nature = $this->input('default_ledger_nature');

            if ($ledger->account_level !== 'Ledger') {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger must be a Ledger account, not a Group account.');
            }

            if (!$ledger->posting_allowed) {
                $validator->errors()->add('linked_ledger_account_id', 'Linked Ledger must allow posting.');
            }

            if ($ledger->is_cash_bank) {
                $validator->errors()->add('linked_ledger_account_id', 'Party ledger cannot be a Cash/Bank account. Use receivable, payable, advance, salary payable, or owner ledger accounts.');
            }

            if (in_array($ledgerType, ['Income', 'Expense'], true)) {
                $validator->errors()->add('linked_ledger_account_id', 'Party ledger cannot be an Income or Expense account. Party balances must use Asset, Liability, or Equity accounts.');
            }

            if (in_array($nature, ['Receivable', 'Advance Paid'], true) && !($ledgerType === 'Asset' && $normalBalance === 'Debit')) {
                $validator->errors()->add('linked_ledger_account_id', 'Receivable and Advance Paid parties must link to an Asset ledger with Debit normal balance.');
            }

            if (in_array($nature, ['Payable', 'Advance Received'], true) && !($ledgerType === 'Liability' && $normalBalance === 'Credit')) {
                $validator->errors()->add('linked_ledger_account_id', 'Payable and Advance Received parties must link to a Liability ledger with Credit normal balance.');
            }

            if ($nature === 'No Effect' && !in_array($ledgerType, ['Asset', 'Liability', 'Equity'], true)) {
                $validator->errors()->add('linked_ledger_account_id', 'No Effect parties may only link to Asset, Liability, or Equity ledgers.');
            }

            $openingBalance = $this->normalizeMoney($this->input('opening_balance'));

            if ($openingBalance > 0 && !$this->input('opening_balance_type')) {
                $validator->errors()->add('opening_balance', 'Opening Balance side could not be resolved from the party ledger nature.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'party_name.required' => 'Party Name is required.',
            'party_name.unique' => 'This Party Name already exists. Please use another name.',

            'party_type_id.required' => 'Party Type is required.',
            'party_type_id.exists' => 'Selected Party Type is invalid or inactive.',

            'mobile.max' => 'Mobile cannot exceed 50 characters.',
            'mobile.unique' => 'This Mobile number already exists. Please use another mobile number.',

            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This Email already exists. Please use another email address.',

            'linked_ledger_account_id.required' => 'Linked Ledger is required.',
            'linked_ledger_account_id.exists' => 'Selected Linked Ledger must be an active posting ledger.',

            'default_ledger_nature.required' => 'Default Ledger Nature is required.',
            'default_ledger_nature.in' => 'Default Ledger Nature must be Receivable, Payable, Advance Paid, Advance Received, or No Effect.',

            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }

    private function inferLedgerNatureFromPartyType(?PartyType $partyType): string
    {
        $code = strtoupper((string) $partyType?->code);
        $name = strtoupper((string) $partyType?->name);
        $value = $code . ' ' . $name;

        if (str_contains($value, 'CUSTOMER') || str_contains($value, 'CUS') || str_contains($value, 'TENANT')) {
            return 'Receivable';
        }

        if (
            str_contains($value, 'SUPPLIER')
            || str_contains($value, 'SUP')
            || str_contains($value, 'VENDOR')
            || str_contains($value, 'LANDLORD')
        ) {
            return 'Payable';
        }

        if (str_contains($value, 'EMPLOYEE') || str_contains($value, 'DRIVER')) {
            return 'Payable';
        }

        if (str_contains($value, 'OWNER')) {
            return 'No Effect';
        }

        return 'No Effect';
    }

    private function openingSideFromLedgerNature(?string $nature, ?ChartOfAccount $ledger = null): ?string
    {
        return match ($nature) {
            'Receivable', 'Advance Paid' => 'Debit',
            'Payable', 'Advance Received' => 'Credit',
            default => $ledger?->normal_balance ?: $ledger?->accountType?->normal_balance,
        };
    }
}
