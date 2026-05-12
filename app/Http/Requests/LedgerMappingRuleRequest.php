<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LedgerMappingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'transaction_head_id' => $this->transaction_head_id ?: null,
            'settlement_type_id' => $this->settlement_type_id ?: null,
            'debit_account_id' => $this->debit_account_id ?: null,
            'credit_account_id' => $this->credit_account_id ?: null,
            'party_ledger_effect' => $this->party_ledger_effect ?: 'No Effect',
            'auto_post' => filter_var($this->input('auto_post', true), FILTER_VALIDATE_BOOLEAN),
            'description' => $this->description ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'transaction_head_id' => [
                'required',
                'integer',
                Rule::exists('transaction_heads', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'settlement_type_id' => [
                'required',
                'integer',
                Rule::exists('settlement_types', 'id')
                    ->where(fn ($query) => $query->where('status', 'Active')),
            ],

            'debit_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'credit_account_id' => [
                'required',
                'integer',
                'different:debit_account_id',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'party_ledger_effect' => [
                'required',
                Rule::in(LedgerMappingRule::PARTY_EFFECTS),
            ],

            'auto_post' => ['required', 'boolean'],

            'description' => ['nullable', 'string', 'max:1000'],

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

            $head = TransactionHead::query()
                ->with('settlementTypes')
                ->find($this->integer('transaction_head_id'));

            $settlement = SettlementType::query()
                ->find($this->integer('settlement_type_id'));

            $debit = ChartOfAccount::query()
                ->with('accountType')
                ->find($this->integer('debit_account_id'));

            $credit = ChartOfAccount::query()
                ->with('accountType')
                ->find($this->integer('credit_account_id'));

            if (!$head || !$settlement || !$debit || !$credit) {
                return;
            }

            if (!$head->settlementTypes->contains('id', $settlement->id)) {
                $validator->errors()->add(
                    'settlement_type_id',
                    'This settlement type is not allowed for the selected transaction head.'
                );
            }

            $duplicate = LedgerMappingRule::query()
                ->where('transaction_head_id', $head->id)
                ->where('settlement_type_id', $settlement->id)
                ->when(
                    $this->route('ledger_mapping_rule'),
                    fn ($query, $rule) => $query->where('id', '!=', $rule->id)
                )
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'settlement_type_id',
                    'A mapping rule already exists for this transaction head and settlement type.'
                );
            }

            $settlementName = strtolower($settlement->name);
            $usesCashBank = $debit->is_cash_bank || $credit->is_cash_bank;

            if (in_array($settlementName, ['cash', 'bank'], true) && !$usesCashBank) {
                $validator->errors()->add(
                    'debit_account_id',
                    'Cash or Bank settlement must include a cash/bank ledger on either debit or credit side.'
                );
            }

            if ($settlementName === 'due' && $usesCashBank) {
                $validator->errors()->add(
                    'settlement_type_id',
                    'Due entry must not affect cash or bank. Use Cash or Bank settlement when paying or collecting a previous due.'
                );
            }

            $this->validatePartyEffectAccounting($validator, $debit, $credit);
        }];
    }

    private function validatePartyEffectAccounting(
        Validator $validator,
        ChartOfAccount $debit,
        ChartOfAccount $credit
    ): void {
        $effect = $this->input('party_ledger_effect');

        $debitType = $debit->accountType?->name;
        $creditType = $credit->accountType?->name;

        $expectations = [
            'Increase Liability' => [
                $creditType,
                'Liability',
                'credit_account_id',
                'Increasing a payable/advance liability must credit a liability account.',
            ],

            'Decrease Liability' => [
                $debitType,
                'Liability',
                'debit_account_id',
                'Decreasing a payable/advance liability must debit a liability account.',
            ],

            'Increase Receivable' => [
                $debitType,
                'Asset',
                'debit_account_id',
                'Increasing receivable must debit an asset account.',
            ],

            'Decrease Receivable' => [
                $creditType,
                'Asset',
                'credit_account_id',
                'Decreasing receivable must credit an asset account.',
            ],

            'Increase Advance Asset' => [
                $debitType,
                'Asset',
                'debit_account_id',
                'Advance paid must debit an asset account.',
            ],

            'Decrease Advance Asset' => [
                $creditType,
                'Asset',
                'credit_account_id',
                'Advance paid adjustment must credit the advance asset account.',
            ],

            'Increase Advance Liability' => [
                $creditType,
                'Liability',
                'credit_account_id',
                'Advance received must credit a liability account.',
            ],

            'Decrease Advance Liability' => [
                $debitType,
                'Liability',
                'debit_account_id',
                'Advance received adjustment must debit the advance liability account.',
            ],
        ];

        if (!isset($expectations[$effect])) {
            return;
        }

        [$actualType, $expectedType, $field, $message] = $expectations[$effect];

        if ($actualType !== $expectedType) {
            $validator->errors()->add($field, $message);
        }
    }

    public function messages(): array
    {
        return [
            'transaction_head_id.required' => 'Transaction Head is required.',
            'transaction_head_id.exists' => 'Selected Transaction Head is invalid or inactive.',

            'settlement_type_id.required' => 'Settlement Type is required.',
            'settlement_type_id.exists' => 'Selected Settlement Type is invalid or inactive.',

            'debit_account_id.required' => 'Debit Account is required.',
            'debit_account_id.exists' => 'Selected Debit Account is invalid or inactive.',

            'credit_account_id.required' => 'Credit Account is required.',
            'credit_account_id.different' => 'Debit Account and Credit Account cannot be the same.',
            'credit_account_id.exists' => 'Selected Credit Account is invalid or inactive.',

            'party_ledger_effect.required' => 'Party Ledger Effect is required.',
            'party_ledger_effect.in' => 'Selected Party Ledger Effect is invalid.',

            'auto_post.required' => 'Auto Post is required.',
            'auto_post.boolean' => 'Auto Post must be Yes or No.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }
}
