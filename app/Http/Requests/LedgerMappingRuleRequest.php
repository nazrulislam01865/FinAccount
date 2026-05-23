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
        return (bool) $this->user()?->hasAnyPermission('ledger-mapping.manage');
    }

    protected function prepareForValidation(): void
    {
        $primaryLedgerId = $this->primary_ledger_id ?: null;
        $counterLedgerId = $this->fixed_counter_ledger_id ?: null;
        $primarySide = $this->input('primary_posting_side', 'Debit') ?: 'Debit';
        $counterSide = $this->input('counter_posting_side') ?: ($primarySide === 'Debit' ? 'Credit' : 'Debit');

        $debitAccountId = $this->debit_account_id ?: null;
        $creditAccountId = $this->credit_account_id ?: null;

        if ($primaryLedgerId && $counterLedgerId) {
            if ($primarySide === 'Debit') {
                $debitAccountId = $primaryLedgerId;
                $creditAccountId = $counterLedgerId;
            } else {
                $debitAccountId = $counterLedgerId;
                $creditAccountId = $primaryLedgerId;
            }
        }

        $partyRequiredMode = (string) ($this->input('party_required_mode') ?: $this->input('party_required', 'No'));
        $partyRequiredMode = match ($partyRequiredMode) {
            'Required', 'Yes' => 'Yes',
            'Optional' => 'Optional',
            default => 'No',
        };

        $status = $this->input('rule_status', $this->input('status', 'Active')) ?: 'Active';

        $this->merge([
            'rule_code' => $this->blankToNull($this->rule_code),
            'rule_name' => $this->blankToNull($this->rule_name),
            'transaction_head_id' => $this->transaction_head_id ?: null,
            'settlement_type_id' => $this->settlement_type_id ?: null,
            'transaction_screen' => $this->blankToNull($this->transaction_screen),
            'rule_trigger' => $this->blankToNull($this->rule_trigger) ?: 'Transaction Head selected',
            'amount_required' => filter_var($this->input('amount_required', true), FILTER_VALIDATE_BOOLEAN),
            'payment_method_required' => filter_var($this->input('payment_method_required', false), FILTER_VALIDATE_BOOLEAN),
            'allowed_payment_method' => $this->blankToNull($this->allowed_payment_method) ?: 'N/A',
            'cash_bank_ledger_required' => filter_var($this->input('cash_bank_ledger_required', false), FILTER_VALIDATE_BOOLEAN),
            'party_required_mode' => $partyRequiredMode,
            'party_sub_ledger_type' => $this->blankToNull($this->party_sub_ledger_type),
            'other_required_input' => $this->blankToNull($this->other_required_input),
            'primary_ledger_source' => $this->blankToNull($this->primary_ledger_source) ?: 'Fixed Ledger',
            'primary_ledger_id' => $primaryLedgerId,
            'primary_ledger_movement' => $this->blankToNull($this->primary_ledger_movement) ?: 'Increase',
            'primary_posting_side' => $primarySide,
            'primary_explanation' => $this->blankToNull($this->primary_explanation),
            'counter_ledger_source' => $this->blankToNull($this->counter_ledger_source) ?: 'Fixed Ledger',
            'counter_selection_method' => $this->blankToNull($this->counter_selection_method) ?: 'Fixed by Rule',
            'fixed_counter_ledger_id' => $counterLedgerId,
            'allowed_counter_ledger_type' => $this->blankToNull($this->allowed_counter_ledger_type) ?: 'N/A',
            'counter_ledger_movement' => $this->blankToNull($this->counter_ledger_movement) ?: 'Decrease',
            'counter_posting_side' => $counterSide,
            'counter_explanation' => $this->blankToNull($this->counter_explanation),
            'debit_account_id' => $debitAccountId,
            'credit_account_id' => $creditAccountId,
            'party_ledger_effect' => $this->blankToNull($this->party_ledger_effect),
            'auto_post' => filter_var($this->input('auto_post', true), FILTER_VALIDATE_BOOLEAN),
            'description' => $this->blankToNull($this->description),
            'status' => $status,
        ]);
    }

    public function rules(): array
    {
        $ruleId = $this->route('ledger_mapping_rule')?->id;
        $postingLedgerRule = Rule::exists('chart_of_accounts', 'id')
            ->where(fn ($query) => $query
                ->where('status', 'Active')
                ->where('account_level', 'Ledger')
                ->where('posting_allowed', true)
                ->whereNull('deleted_at'));

        return [
            'rule_code' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9.\-_]+$/',
                Rule::unique('ledger_mapping_rules', 'rule_code')
                    ->whereNull('deleted_at')
                    ->ignore($ruleId),
            ],
            'rule_name' => ['required', 'string', 'max:150'],
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
            'transaction_screen' => ['nullable', 'string', 'max:100'],
            'rule_trigger' => ['required', Rule::in([
                'Transaction Head selected',
                'Payment Method selected',
                'Party Type selected',
                'System mapping matched',
            ])],
            'amount_required' => ['required', 'boolean'],
            'payment_method_required' => ['required', 'boolean'],
            'allowed_payment_method' => ['required', Rule::in(['Cash, Bank', 'Cash', 'Bank', 'N/A'])],
            'cash_bank_ledger_required' => ['required', 'boolean'],
            'party_required_mode' => ['required', Rule::in(['No', 'Yes', 'Optional'])],
            'party_sub_ledger_type' => ['nullable', Rule::in(['None', 'Customer', 'Supplier', 'Employee', 'Owner'])],
            'other_required_input' => ['nullable', 'string', 'max:255'],
            'primary_ledger_source' => ['required', Rule::in([
                'Fixed Ledger',
                'User Selected Cash/Bank Ledger',
                'Transaction Head Based Ledger',
                'System Derived Ledger',
            ])],
            'primary_ledger_id' => ['required', 'integer', $postingLedgerRule],
            'primary_ledger_movement' => ['required', Rule::in(['Increase', 'Decrease'])],
            'primary_posting_side' => ['required', Rule::in(['Debit', 'Credit'])],
            'primary_explanation' => ['nullable', 'string', 'max:1000'],
            'counter_ledger_source' => ['required', Rule::in([
                'Fixed Ledger',
                'User Selected Cash/Bank Ledger',
                'User Selected Party Control Ledger',
                'Transaction Head Based Ledger',
                'Payment Method Based Ledger',
                'Party Type Based Ledger',
                'System Derived Ledger',
            ])],
            'counter_selection_method' => ['required', Rule::in([
                'Fixed by Rule',
                'Selected by User',
                'Derived from Payment Method',
                'Derived from Party Type',
                'Derived from Transaction Head',
                'Derived from System Mapping',
            ])],
            'fixed_counter_ledger_id' => ['required', 'integer', 'different:primary_ledger_id', $postingLedgerRule],
            'allowed_counter_ledger_type' => ['required', Rule::in([
                'Cash/Bank',
                'Customer Receivable',
                'Supplier Payable',
                'Income',
                'Expense',
                'Asset',
                'Liability',
                'Equity',
                'Party Control',
                'N/A',
            ])],
            'counter_ledger_movement' => ['required', Rule::in(['Increase', 'Decrease'])],
            'counter_posting_side' => ['required', Rule::in(['Debit', 'Credit'])],
            'counter_explanation' => ['nullable', 'string', 'max:1000'],
            'debit_account_id' => ['required', 'integer', $postingLedgerRule],
            'credit_account_id' => ['required', 'integer', 'different:debit_account_id', $postingLedgerRule],
            'party_ledger_effect' => ['nullable', Rule::in(LedgerMappingRule::PARTY_EFFECTS)],
            'auto_post' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'Draft', 'Pending Review'])],
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

            $this->validateHeadSettlementPair($validator, $head, $settlement);
            $this->validateDuplicateMapping($validator, $head, $settlement);
            $this->validatePostingAccount($validator, $debit, 'debit_account_id', 'Debit Account');
            $this->validatePostingAccount($validator, $credit, 'credit_account_id', 'Credit Account');
            $this->validateSettlementAccounting($validator, $head, $settlement, $debit, $credit);
            $this->validatePartyEffectAccounting($validator, $debit, $credit);
        }];
    }

    private function validateHeadSettlementPair(
        Validator $validator,
        TransactionHead $head,
        SettlementType $settlement
    ): void {
        if (!$head->settlementTypes->contains('id', $settlement->id)) {
            $validator->errors()->add(
                'settlement_type_id',
                'This settlement type is not allowed for the selected transaction head.'
            );
        }
    }

    private function validateDuplicateMapping(
        Validator $validator,
        TransactionHead $head,
        SettlementType $settlement
    ): void {
        $rule = $this->route('ledger_mapping_rule');

        $duplicate = LedgerMappingRule::query()
            ->where('transaction_head_id', $head->id)
            ->where('settlement_type_id', $settlement->id)
            ->whereNull('deleted_at')
            ->when($rule, fn ($query) => $query->whereKeyNot($rule->id))
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'settlement_type_id',
                'An accounting rule already exists for this transaction head and settlement type.'
            );
        }
    }

    private function validatePostingAccount(
        Validator $validator,
        ChartOfAccount $account,
        string $field,
        string $label
    ): void {
        if ($account->status !== 'Active') {
            $validator->errors()->add($field, "{$label} must be active.");
        }

        if ($account->account_level !== 'Ledger') {
            $validator->errors()->add($field, "{$label} must be a Ledger account, not a Group account.");
        }

        if (!$account->posting_allowed) {
            $validator->errors()->add($field, "{$label} must allow posting.");
        }

        if (!$account->accountType) {
            $validator->errors()->add($field, "{$label} must have a valid Account Type.");
        }
    }

    private function validateSettlementAccounting(
        Validator $validator,
        TransactionHead $head,
        SettlementType $settlement,
        ChartOfAccount $debit,
        ChartOfAccount $credit
    ): void {
        $settlementKey = $this->settlementKey($settlement);
        $cashBankCount = (int) $debit->is_cash_bank + (int) $credit->is_cash_bank;

        if (in_array($settlementKey, ['cash', 'bank', 'advance_paid', 'advance_received'], true)) {
            if ($cashBankCount !== 1) {
                $validator->errors()->add(
                    'settlement_type_id',
                    'Cash, Bank, and advance money movements must include exactly one Cash/Bank ledger side.'
                );
            }

            $expectedCashBankSide = $this->expectedCashBankSide($head, $settlementKey);

            if ($expectedCashBankSide === 'Debit' && !$debit->is_cash_bank) {
                $validator->errors()->add(
                    'debit_account_id',
                    'Receipt or advance received mappings must debit the Cash/Bank ledger.'
                );
            }

            if ($expectedCashBankSide === 'Credit' && !$credit->is_cash_bank) {
                $validator->errors()->add(
                    'credit_account_id',
                    'Payment or advance paid mappings must credit the Cash/Bank ledger.'
                );
            }
        }

        if (in_array($settlementKey, ['due', 'adjustment'], true) && $cashBankCount > 0) {
            $validator->errors()->add(
                'settlement_type_id',
                'Due and adjustment mappings must not affect Cash/Bank directly.'
            );
        }

        if ($settlementKey === 'due') {
            $this->validateDueMappingShape($validator, $head, $debit, $credit);
        }

        if ($settlementKey === 'advance_paid') {
            $this->validateAccountType($validator, $debit, 'debit_account_id', 'Asset', 'Advance paid must debit an asset account.');
        }

        if ($settlementKey === 'advance_received') {
            $this->validateAccountType($validator, $credit, 'credit_account_id', 'Liability', 'Advance received must credit a liability account.');
        }
    }

    private function validateDueMappingShape(
        Validator $validator,
        TransactionHead $head,
        ChartOfAccount $debit,
        ChartOfAccount $credit
    ): void {
        $headText = strtoupper($head->nature . ' ' . $head->name);

        if (str_contains($headText, 'RECEIPT') || str_contains($headText, 'INCOME') || str_contains($headText, 'SALES')) {
            $this->validateAccountType(
                $validator,
                $debit,
                'debit_account_id',
                'Asset',
                'Due receivable mapping must debit an Asset account such as Accounts Receivable.'
            );

            return;
        }

        $this->validateAccountType(
            $validator,
            $credit,
            'credit_account_id',
            'Liability',
            'Due payable mapping must credit a Liability account such as Accounts Payable.'
        );
    }

    private function validatePartyEffectAccounting(
        Validator $validator,
        ChartOfAccount $debit,
        ChartOfAccount $credit
    ): void {
        $effect = $this->input('party_ledger_effect');

        if (!$effect || $effect === 'No Effect') {
            return;
        }

        $expectations = [
            'Increase Liability' => [
                $credit,
                'Liability',
                'credit_account_id',
                'Increasing a payable/advance liability must credit a liability account.',
            ],
            'Decrease Liability' => [
                $debit,
                'Liability',
                'debit_account_id',
                'Decreasing a payable/advance liability must debit a liability account.',
            ],
            'Increase Receivable' => [
                $debit,
                'Asset',
                'debit_account_id',
                'Increasing receivable must debit an asset account.',
            ],
            'Decrease Receivable' => [
                $credit,
                'Asset',
                'credit_account_id',
                'Decreasing receivable must credit an asset account.',
            ],
            'Increase Asset' => [
                $debit,
                'Asset',
                'debit_account_id',
                'Increasing an advance asset must debit an asset account.',
            ],
            'Decrease Asset' => [
                $credit,
                'Asset',
                'credit_account_id',
                'Decreasing an advance asset must credit an asset account.',
            ],
            'Increase Advance Asset' => [
                $debit,
                'Asset',
                'debit_account_id',
                'Advance paid must debit an asset account.',
            ],
            'Decrease Advance Asset' => [
                $credit,
                'Asset',
                'credit_account_id',
                'Advance paid adjustment must credit the advance asset account.',
            ],
            'Increase Advance Liability' => [
                $credit,
                'Liability',
                'credit_account_id',
                'Advance received must credit a liability account.',
            ],
            'Decrease Advance Liability' => [
                $debit,
                'Liability',
                'debit_account_id',
                'Advance received adjustment must debit the advance liability account.',
            ],
        ];

        if (!isset($expectations[$effect])) {
            return;
        }

        /** @var ChartOfAccount $account */
        [$account, $expectedType, $field, $message] = $expectations[$effect];
        $this->validateAccountType($validator, $account, $field, $expectedType, $message);
    }

    private function validateAccountType(
        Validator $validator,
        ChartOfAccount $account,
        string $field,
        string $expectedType,
        string $message
    ): void {
        if ($account->accountType?->name !== $expectedType) {
            $validator->errors()->add($field, $message);
        }
    }

    private function settlementKey(SettlementType $settlement): string
    {
        $code = strtoupper((string) $settlement->code);
        $name = strtoupper((string) $settlement->name);
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

    private function expectedCashBankSide(TransactionHead $head, string $settlementKey): string
    {
        if (in_array($settlementKey, ['advance_received'], true)) {
            return 'Debit';
        }

        if (in_array($settlementKey, ['advance_paid'], true)) {
            return 'Credit';
        }

        $headText = strtoupper($head->nature . ' ' . $head->name);

        if (
            str_contains($headText, 'RECEIPT')
            || str_contains($headText, 'RECEIVED')
            || str_contains($headText, 'COLLECTION')
            || str_contains($headText, 'INCOME')
            || str_contains($headText, 'CAPITAL')
        ) {
            return 'Debit';
        }

        return 'Credit';
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function messages(): array
    {
        return [
            'rule_code.regex' => 'Rule Code may contain only letters, numbers, dots, hyphens, and underscores.',
            'rule_code.unique' => 'This Accounting Rule Code already exists. Please use another code.',

            'transaction_head_id.required' => 'Transaction Head is required.',
            'transaction_head_id.exists' => 'Selected Transaction Head is invalid or inactive.',

            'settlement_type_id.required' => 'Settlement Type is required.',
            'settlement_type_id.exists' => 'Selected Settlement Type is invalid or inactive.',

            'debit_account_id.required' => 'Debit Account is required.',
            'debit_account_id.exists' => 'Selected Debit Account must be an active posting Ledger account.',

            'credit_account_id.required' => 'Credit Account is required.',
            'credit_account_id.different' => 'Debit Account and Credit Account cannot be the same.',
            'credit_account_id.exists' => 'Selected Credit Account must be an active posting Ledger account.',

            'party_ledger_effect.in' => 'Selected Party Ledger Effect is invalid.',

            'auto_post.required' => 'Auto Post is required.',
            'auto_post.boolean' => 'Auto Post must be Yes or No.',

            'status.required' => 'Status is required.',
            'status.in' => 'Status must be Active, Inactive, Draft, or Pending Review.',
        ];
    }
}
