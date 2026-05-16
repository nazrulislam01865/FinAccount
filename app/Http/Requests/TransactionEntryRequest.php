<?php

namespace App\Http\Requests;

use App\Models\CashBankAccount;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'voucher_type' => $this->voucher_type ?: 'Auto Select',
            'party_id' => $this->party_id ?: null,
            'cash_bank_account_id' => $this->cash_bank_account_id ?: null,
            'amount' => $this->money($this->amount),
            'status' => $this->status ?: 'Posted',
            'reference' => $this->blankToNull($this->reference),
            'notes' => $this->blankToNull($this->notes),
        ]);
    }

    public function rules(): array
    {
        return [
            'voucher_date' => ['required', 'date'],
            'voucher_type' => ['nullable', 'string', 'max:100'],

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

            'party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'cash_bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_bank_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'status' => [
                'required',
                Rule::in(['Draft', 'Posted']),
            ],

            'attachment' => ['nullable', 'file', 'max:5120'],
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

            if (!$head || !$settlement) {
                return;
            }

            $this->validateDateInsideActiveFinancialYear($validator);
            $this->validateHeadSettlementPair($validator, $head, $settlement);
            $this->validatePartyRequirement($validator, $head);
            $this->validateReferenceRequirement($validator, $head);

            $mapping = $this->mappingRule($head, $settlement);

            if (!$mapping) {
                $validator->errors()->add(
                    'ledger_mapping',
                    'No accounting rule is configured for this transaction purpose and settlement type.'
                );

                return;
            }

            $this->validateMappingRequirement($validator, $mapping);
            $this->validateCashBankRequirement($validator, $mapping);
        }];
    }

    public function messages(): array
    {
        return [
            'voucher_date.required' => 'Transaction date is required.',
            'voucher_date.date' => 'Transaction date must be a valid date.',
            'transaction_head_id.required' => 'Transaction Head is required.',
            'transaction_head_id.exists' => 'Selected Transaction Head is invalid or inactive.',
            'settlement_type_id.required' => 'Settlement Type is required.',
            'settlement_type_id.exists' => 'Selected Settlement Type is invalid or inactive.',
            'party_id.exists' => 'Selected Party / Person is invalid or inactive.',
            'cash_bank_account_id.exists' => 'Selected Paid From / Received In account is invalid or inactive.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be greater than zero.',
            'status.in' => 'Transaction status must be Draft or Posted.',
            'attachment.max' => 'Attachment size cannot exceed 5MB.',
        ];
    }

    private function validateDateInsideActiveFinancialYear(Validator $validator): void
    {
        $voucherDate = Carbon::parse($this->input('voucher_date'))->toDateString();

        $exists = FinancialYear::query()
            ->where('status', 'Active')
            ->whereDate('start_date', '<=', $voucherDate)
            ->whereDate('end_date', '>=', $voucherDate)
            ->exists();

        if (!$exists) {
            $validator->errors()->add(
                'voucher_date',
                'Transaction date must be inside an active financial year.'
            );
        }
    }

    private function validateHeadSettlementPair(
        Validator $validator,
        TransactionHead $head,
        SettlementType $settlement
    ): void {
        $allowedSettlementIds = $head->settlementTypes
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!in_array((int) $settlement->id, $allowedSettlementIds, true)) {
            $validator->errors()->add(
                'settlement_type_id',
                'Selected Settlement Type is not allowed for this Transaction Head.'
            );
        }
    }

    private function validatePartyRequirement(Validator $validator, TransactionHead $head): void
    {
        if ($head->requires_party && !$this->party_id) {
            $validator->errors()->add(
                'party_id',
                'Party / Person is required for this Transaction Head.'
            );

            return;
        }

        if (!$this->party_id) {
            return;
        }

        $party = Party::query()->find($this->integer('party_id'));

        if (!$party) {
            return;
        }

        if ($head->default_party_type_id && (int) $party->party_type_id !== (int) $head->default_party_type_id) {
            $validator->errors()->add(
                'party_id',
                'Selected Party / Person does not match the party type required by this Transaction Head.'
            );
        }
    }

    private function validateReferenceRequirement(Validator $validator, TransactionHead $head): void
    {
        if ($head->requires_reference && !$this->reference) {
            $validator->errors()->add(
                'reference',
                'Reference is required for this Transaction Head.'
            );
        }
    }

    private function validateMappingRequirement(Validator $validator, LedgerMappingRule $mapping): void
    {
        foreach ([$mapping->debitAccount, $mapping->creditAccount] as $account) {
            if (!$account || $account->status !== 'Active' || $account->account_level !== 'Ledger' || !$account->posting_allowed) {
                $validator->errors()->add(
                    'ledger_mapping',
                    'Accounting rule uses an inactive, group, or non-posting ledger account.'
                );

                return;
            }
        }

        if ((int) $mapping->debit_account_id === (int) $mapping->credit_account_id) {
            $validator->errors()->add(
                'ledger_mapping',
                'Accounting rule is invalid: Debit Account and Credit Account cannot be the same.'
            );
        }
    }

    private function validateCashBankRequirement(Validator $validator, LedgerMappingRule $mapping): void
    {
        $requiresCashBank = $this->mappingRequiresCashBank($mapping);

        if ($requiresCashBank && !$this->cash_bank_account_id) {
            $validator->errors()->add(
                'cash_bank_account_id',
                $this->cashBankRequiredMessage($mapping)
            );

            return;
        }

        if (!$this->cash_bank_account_id) {
            return;
        }

        $cashBankAccount = CashBankAccount::query()
            ->with('linkedLedger.accountType')
            ->where('status', 'Active')
            ->find($this->integer('cash_bank_account_id'));

        if (!$cashBankAccount || !$cashBankAccount->linkedLedger) {
            $validator->errors()->add(
                'cash_bank_account_id',
                'Selected Paid From / Received In account is invalid or inactive.'
            );

            return;
        }

        $ledger = $cashBankAccount->linkedLedger;
        $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;

        if (
            $ledger->status !== 'Active'
            || $ledger->account_level !== 'Ledger'
            || !$ledger->posting_allowed
            || !$ledger->is_cash_bank
            || $ledger->accountType?->name !== 'Asset'
            || $normalBalance !== 'Debit'
        ) {
            $validator->errors()->add(
                'cash_bank_account_id',
                'Paid From / Received In must be an active Asset cash/bank posting ledger.'
            );
        }

        if (!$requiresCashBank) {
            $validator->errors()->add(
                'cash_bank_account_id',
                'Paid From / Received In is not used for this transaction. Remove the cash/bank selection.'
            );
        }
    }

    private function mappingRule(TransactionHead $head, SettlementType $settlement): ?LedgerMappingRule
    {
        return LedgerMappingRule::query()
            ->with(['debitAccount.accountType', 'creditAccount.accountType', 'settlementType', 'transactionHead'])
            ->where('transaction_head_id', $head->id)
            ->where('settlement_type_id', $settlement->id)
            ->where('status', 'Active')
            ->first();
    }

    private function mappingRequiresCashBank(LedgerMappingRule $mapping): bool
    {
        return (bool) $mapping->debitAccount?->is_cash_bank
            || (bool) $mapping->creditAccount?->is_cash_bank
            || in_array($this->settlementKey($mapping->settlementType), ['cash', 'bank', 'advance_paid', 'advance_received'], true);
    }

    private function cashBankRequiredMessage(LedgerMappingRule $mapping): string
    {
        $key = $this->settlementKey($mapping->settlementType);

        if (in_array($key, ['cash', 'bank', 'advance_paid'], true)) {
            return 'Please select the account from which payment was made.';
        }

        if ($key === 'advance_received') {
            return 'Please select the account where money was received.';
        }

        return 'Paid From / Received In is required for this transaction.';
    }

    private function settlementKey(?SettlementType $settlement): string
    {
        $code = strtoupper((string) $settlement?->code);
        $name = strtoupper((string) $settlement?->name);
        $value = $code . ' ' . $name;

        return match (true) {
            str_contains($value, 'ADVANCE_PAID') || str_contains($value, 'ADVANCE PAID') => 'advance_paid',
            str_contains($value, 'ADVANCE_RECEIVED') || str_contains($value, 'ADVANCE RECEIVED') => 'advance_received',
            str_contains($value, 'CASH') => 'cash',
            str_contains($value, 'BANK') => 'bank',
            str_contains($value, 'DUE') => 'due',
            str_contains($value, 'ADJUST') => 'adjustment',
            default => 'other',
        };
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }
}