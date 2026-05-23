<?php

namespace App\Http\Requests;

use App\Models\AccountingRule;
use App\Models\CashBankAccount;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionRequirementService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class TransactionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission(['transactions.create', 'transactions.draft']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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

            $this->validateStatusPermission($validator);
            $this->validateDateInsideActiveFinancialYear($validator);
            $this->validateHeadSettlementPair($validator, $head, $settlement);
            $this->validateReferenceRequirement($validator, $head);
            $this->validateDynamicInputRequirements($validator, $head, $settlement);

            $hasV2Rule = $this->hasActiveAccountingRule($head, $settlement);
            $mapping = $this->mappingRule($head, $settlement);

            if (!$hasV2Rule && !$mapping) {
                $validator->errors()->add(
                    'ledger_mapping',
                    'No accounting rule is configured for this transaction purpose and settlement type.'
                );

                return;
            }

            if ($mapping) {
                $this->validateMappingRequirement($validator, $mapping);
                $this->validateCashBankRequirement($validator, $mapping);
            }
        }];
    }


    public function ensureCanUseResolvedVoucherType(?string $voucherType): void
    {
        if ($voucherType === 'Draft Voucher' && $this->user()?->hasAnyPermission(['transactions.create', 'transactions.draft'])) {
            return;
        }

        $permission = $this->permissionForVoucherType($voucherType);

        if (!$permission || $this->user()?->hasPermission($permission)) {
            return;
        }

        throw ValidationException::withMessages([
            'permission' => 'Your assigned role is not allowed to create this transaction type.',
        ]);
    }

    private function permissionForVoucherType(?string $voucherType): ?string
    {
        return match ($voucherType) {
            'Payment Voucher' => 'transactions.payment.create',
            'Receipt Voucher' => 'transactions.receipt.create',
            'Journal Voucher' => 'transactions.journal.create',
            'Contra / Transfer Voucher' => 'transactions.payment.create',
            'Draft Voucher' => 'transactions.draft',
            default => null,
        };
    }

    private function validateStatusPermission(Validator $validator): void
    {
        $user = $this->user();
        $status = $this->input('status', 'Posted');

        if ($status === 'Posted' && !$user?->hasPermission('transactions.create')) {
            $validator->errors()->add(
                'permission',
                'Your role can save draft transactions only. Final posting is locked.'
            );
        }

        if ($status === 'Draft' && !$user?->hasAnyPermission(['transactions.create', 'transactions.draft'])) {
            $validator->errors()->add(
                'permission',
                'Your role is not allowed to save draft transactions.'
            );
        }
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
            'cash_bank_account_id.exists' => 'Selected Cash/Bank account is invalid or inactive.',
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

        $financialYear = FinancialYear::query()
            ->whereIn('status', ['Active', 'Open'])
            ->whereDate('start_date', '<=', $voucherDate)
            ->whereDate('end_date', '>=', $voucherDate)
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->first();

        if (!$financialYear) {
            $validator->errors()->add(
                'voucher_date',
                'Transaction date must be inside an open financial year from Financial Year Setup.'
            );

            return;
        }

        if ($financialYear->lock_date && $voucherDate <= Carbon::parse($financialYear->lock_date)->toDateString()) {
            $validator->errors()->add(
                'voucher_date',
                'Transaction date is inside a locked financial period. Update the lock date or choose a later date.'
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

    private function validateDynamicInputRequirements(
        Validator $validator,
        TransactionHead $head,
        SettlementType $settlement
    ): void {
        $requirements = app(TransactionRequirementService::class)->resolve(
            transactionHeadId: (int) $head->id,
            settlementTypeId: (int) $settlement->id,
            companyId: (int) ($this->user()?->company_id ?? 0)
        );

        if (($requirements['party_required'] ?? false) && !$this->party_id) {
            $validator->errors()->add(
                'party_id',
                'Party / Person is required because the selected accounting rule uses a party/sub-ledger.'
            );

            return;
        }

        if ($this->party_id) {
            $party = Party::query()->find($this->integer('party_id'));

            if ($party && !empty($requirements['party_type_id']) && (int) $party->party_type_id !== (int) $requirements['party_type_id']) {
                $validator->errors()->add(
                    'party_id',
                    'Selected Party / Person does not match the party type required by this accounting rule.'
                );
            }
        }

        if (($requirements['cash_bank_required'] ?? false) && !$this->cash_bank_account_id) {
            $validator->errors()->add(
                'cash_bank_account_id',
                'Cash/Bank account is required because the selected accounting rule requires a cash/bank ledger.'
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
        if (!$this->mappingRequiresCashBank($mapping)) {
            return;
        }

        $cashBankAccount = $this->cash_bank_account_id
            ? CashBankAccount::query()
                ->with('linkedLedger.accountType')
                ->where('status', 'Active')
                ->find($this->integer('cash_bank_account_id'))
            : $this->autoCashBankAccountForMapping($mapping);

        if (!$cashBankAccount || !$cashBankAccount->linkedLedger) {
            $validator->errors()->add(
                'cash_bank_account_id',
                $this->cashBankAutoResolveMessage($mapping)
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
                'Selected Cash/Bank account must be an active Asset cash/bank posting ledger.'
            );
        }
    }

    private function autoCashBankAccountForMapping(LedgerMappingRule $mapping): ?CashBankAccount
    {
        $cashBankLedgerId = $mapping->debitAccount?->is_cash_bank
            ? $mapping->debit_account_id
            : ($mapping->creditAccount?->is_cash_bank ? $mapping->credit_account_id : null);

        if (!$cashBankLedgerId) {
            return null;
        }

        return CashBankAccount::query()
            ->with('linkedLedger.accountType')
            ->where('status', 'Active')
            ->where('linked_ledger_account_id', $cashBankLedgerId)
            ->orderBy('id')
            ->first();
    }

    private function cashBankAutoResolveMessage(LedgerMappingRule $mapping): string
    {
        $key = $this->settlementKey($mapping->settlementType);

        if (in_array($key, ['cash', 'bank', 'advance_paid', 'advance_received'], true)) {
            return 'Cash/Bank account is selected automatically from Accounting Rules Setup. Configure an active Cash/Bank Account linked to the cash/bank ledger used in this accounting rule.';
        }

        return 'Cash/Bank account could not be selected automatically from the active accounting rule.';
    }

    private function hasActiveAccountingRule(TransactionHead $head, SettlementType $settlement): bool
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);

        return AccountingRule::query()
            ->where('transaction_head_id', $head->id)
            ->where('status', 'Active')
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->where(function ($query) use ($settlement) {
                $query->where('settlement_type_id', $settlement->id)
                    ->orWhereNull('settlement_type_id');
            })
            ->exists();
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