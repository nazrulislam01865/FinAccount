<?php

namespace App\Services\Accounting;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use Illuminate\Validation\ValidationException;

class MappingResolverService
{
    public function resolve(int $transactionHeadId, int $settlementTypeId): LedgerMappingRule
    {
        $rule = LedgerMappingRule::query()
            ->with([
                'transactionHead',
                'settlementType',
                'debitAccount.accountType',
                'creditAccount.accountType',
            ])
            ->where('transaction_head_id', $transactionHeadId)
            ->where('settlement_type_id', $settlementTypeId)
            ->where('status', 'Active')
            ->first();

        if (!$rule) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'No accounting rule is configured for this transaction purpose and settlement type.',
            ]);
        }

        $this->validateBaseRule($rule);

        return $rule;
    }

    public function preview(
        int $transactionHeadId,
        int $settlementTypeId,
        float $amount,
        ?int $cashBankAccountId = null,
        ?int $partyId = null
    ): array {
        $rule = $this->resolve($transactionHeadId, $settlementTypeId);
        $amount = round($amount, 2);

        $cashBankAccount = $this->resolveCashBankAccount($rule, $cashBankAccountId);
        $party = $partyId
            ? Party::query()->with(['partyType', 'linkedLedger.accountType'])->find($partyId)
            : null;

        $debitAccount = $this->resolveSideAccount(
            rule: $rule,
            configuredAccount: $rule->debitAccount,
            side: 'Debit',
            cashBankAccount: $cashBankAccount,
            party: $party
        );

        $creditAccount = $this->resolveSideAccount(
            rule: $rule,
            configuredAccount: $rule->creditAccount,
            side: 'Credit',
            cashBankAccount: $cashBankAccount,
            party: $party
        );

        $this->validatePostingResult($rule, $debitAccount, $creditAccount, $cashBankAccount);

        $entries = [
            $this->entryPayload(
                account: $debitAccount,
                entryType: 'Debit',
                debit: $amount,
                credit: 0.00,
                sourceType: $this->sourceType($rule->debitAccount, 'Debit'),
                sourceLabel: $this->sourceLabel($rule->debitAccount, 'Debit')
            ),
            $this->entryPayload(
                account: $creditAccount,
                entryType: 'Credit',
                debit: 0.00,
                credit: $amount,
                sourceType: $this->sourceType($rule->creditAccount, 'Credit'),
                sourceLabel: $this->sourceLabel($rule->creditAccount, 'Credit')
            ),
        ];

        return [
            'rule' => $rule,
            'cash_bank_account' => $cashBankAccount,
            'party' => $party,
            'party_ledger_effect' => $this->partyLedgerEffect($rule, $entries),
            'entries' => $entries,
        ];
    }

    public function requiresCashBank(int $transactionHeadId, int $settlementTypeId): bool
    {
        $rule = $this->resolve($transactionHeadId, $settlementTypeId);

        return $this->ruleRequiresCashBank($rule);
    }

    private function validateBaseRule(LedgerMappingRule $rule): void
    {
        if ((int) $rule->debit_account_id === (int) $rule->credit_account_id) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid accounting rule: Debit Account and Credit Account cannot be the same.',
            ]);
        }

        $this->validatePostingAccount($rule->debitAccount, 'Debit Account');
        $this->validatePostingAccount($rule->creditAccount, 'Credit Account');
        $this->validateRuntimeSettlementShape($rule);
    }

    private function validatePostingAccount(?ChartOfAccount $account, string $label): void
    {
        if (!$account) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Invalid accounting rule: {$label} is missing.",
            ]);
        }

        if ($account->status !== 'Active') {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Invalid accounting rule: {$label} is inactive.",
            ]);
        }

        if ($account->account_level !== 'Ledger') {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Invalid accounting rule: {$label} must be a Ledger account, not a Group account.",
            ]);
        }

        if (!$account->posting_allowed) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Invalid accounting rule: {$label} must allow posting.",
            ]);
        }

        if (!$account->accountType) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Invalid accounting rule: {$label} must have a valid Account Type.",
            ]);
        }
    }

    private function resolveCashBankAccount(LedgerMappingRule $rule, ?int $cashBankAccountId): ?CashBankAccount
    {
        if (!$this->ruleRequiresCashBank($rule)) {
            return null;
        }

        if (!$cashBankAccountId) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => $this->cashBankRequiredMessage($rule),
            ]);
        }

        $cashBankAccount = CashBankAccount::query()
            ->where('status', 'Active')
            ->with('linkedLedger.accountType')
            ->find($cashBankAccountId);

        if (!$cashBankAccount || !$cashBankAccount->linkedLedger) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Selected Paid From / Received In account is invalid or inactive.',
            ]);
        }

        $ledger = $cashBankAccount->linkedLedger;

        if (
            $ledger->status !== 'Active'
            || $ledger->account_level !== 'Ledger'
            || !$ledger->posting_allowed
            || !$ledger->is_cash_bank
            || $this->accountTypeName($ledger) !== 'Asset'
            || $this->normalBalance($ledger) !== 'Debit'
        ) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Paid From / Received In must be an active Asset cash/bank posting ledger.',
            ]);
        }

        return $cashBankAccount;
    }

    private function resolveSideAccount(
        LedgerMappingRule $rule,
        ChartOfAccount $configuredAccount,
        string $side,
        ?CashBankAccount $cashBankAccount,
        ?Party $party
    ): ChartOfAccount {
        if ($configuredAccount->is_cash_bank) {
            return $cashBankAccount?->linkedLedger ?: $configuredAccount;
        }

        if ($party?->linkedLedger && $this->partyLedgerCanReplace($configuredAccount, $party->linkedLedger)) {
            return $party->linkedLedger;
        }

        return $configuredAccount;
    }

    private function partyLedgerCanReplace(ChartOfAccount $configuredAccount, ChartOfAccount $partyLedger): bool
    {
        if ($configuredAccount->is_cash_bank || $partyLedger->is_cash_bank) {
            return false;
        }

        if ($partyLedger->status !== 'Active' || $partyLedger->account_level !== 'Ledger' || !$partyLedger->posting_allowed) {
            return false;
        }

        return $this->accountTypeName($configuredAccount) === $this->accountTypeName($partyLedger)
            && $this->normalBalance($configuredAccount) === $this->normalBalance($partyLedger);
    }

    private function validatePostingResult(
        LedgerMappingRule $rule,
        ChartOfAccount $debitAccount,
        ChartOfAccount $creditAccount,
        ?CashBankAccount $cashBankAccount
    ): void {
        $this->validatePostingAccount($debitAccount, 'Resolved Debit Account');
        $this->validatePostingAccount($creditAccount, 'Resolved Credit Account');

        if ((int) $debitAccount->id === (int) $creditAccount->id) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid posting result: Debit and Credit account cannot be the same.',
            ]);
        }

        $key = $this->settlementKey($rule);
        $cashBankCount = (int) $debitAccount->is_cash_bank + (int) $creditAccount->is_cash_bank;

        if (in_array($key, ['cash', 'bank', 'advance_paid', 'advance_received'], true) && $cashBankCount !== 1) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid posting result: cash/bank money movement must include exactly one Cash/Bank ledger side.',
            ]);
        }

        if (in_array($key, ['due', 'adjustment'], true) && $cashBankCount > 0) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid posting result: Due and adjustment entries must not affect Cash/Bank directly.',
            ]);
        }

        if ($cashBankAccount && $cashBankCount === 0) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Paid From / Received In was selected, but the accounting rule does not use a Cash/Bank ledger.',
            ]);
        }
    }

    private function validateRuntimeSettlementShape(LedgerMappingRule $rule): void
    {
        $key = $this->settlementKey($rule);
        $cashBankCount = (int) $rule->debitAccount?->is_cash_bank + (int) $rule->creditAccount?->is_cash_bank;

        if (in_array($key, ['cash', 'bank', 'advance_paid', 'advance_received'], true) && $cashBankCount !== 1) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid accounting rule: Cash, Bank, and advance money movements must include exactly one Cash/Bank ledger side.',
            ]);
        }

        if (in_array($key, ['due', 'adjustment'], true) && $cashBankCount > 0) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid accounting rule: Due and adjustment mappings must not affect Cash/Bank directly.',
            ]);
        }
    }

    private function partyLedgerEffect(LedgerMappingRule $rule, array $entries): string
    {
        if ($rule->party_ledger_effect && $rule->party_ledger_effect !== 'No Effect') {
            return $rule->party_ledger_effect;
        }

        $debit = collect($entries)->firstWhere('entry_type', 'Debit');
        $credit = collect($entries)->firstWhere('entry_type', 'Credit');

        $debitType = $debit['account_type'] ?? null;
        $creditType = $credit['account_type'] ?? null;
        $key = $this->settlementKey($rule);
        $headText = strtoupper(($rule->transactionHead?->nature ?? '') . ' ' . ($rule->transactionHead?->name ?? ''));

        if ($key === 'due') {
            if ($debitType === 'Asset' && $creditType === 'Income') {
                return 'Increase Receivable';
            }

            if ($creditType === 'Liability') {
                return 'Increase Liability';
            }
        }

        if (in_array($key, ['cash', 'bank'], true)) {
            if (($debit['is_cash_bank_account'] ?? false) && $creditType === 'Asset') {
                return 'Decrease Receivable';
            }

            if ($debitType === 'Liability' && ($credit['is_cash_bank_account'] ?? false)) {
                return 'Decrease Liability';
            }
        }

        if ($key === 'advance_paid' || str_contains($headText, 'ADVANCE PAID')) {
            return 'Increase Advance Asset';
        }

        if ($key === 'advance_received' || str_contains($headText, 'ADVANCE RECEIVED')) {
            return 'Increase Advance Liability';
        }

        if ($key === 'adjustment' && str_contains($headText, 'ADVANCE PAID')) {
            return 'Decrease Advance Asset';
        }

        if ($key === 'adjustment' && str_contains($headText, 'ADVANCE RECEIVED')) {
            return 'Decrease Advance Liability';
        }

        return 'No Effect';
    }

    private function entryPayload(
        ChartOfAccount $account,
        string $entryType,
        float $debit,
        float $credit,
        string $sourceType,
        string $sourceLabel
    ): array {
        $accountType = $this->accountTypeName($account);
        $normalBalance = $this->normalBalance($account);
        $postingEffect = $this->postingEffect($account, $entryType);

        return [
            'account_id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->display_name ?? $account->account_name,
            'plain_account_name' => $account->account_name,
            'account_level' => $account->account_level,
            'posting_allowed' => (bool) $account->posting_allowed,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'entry_type' => $entryType,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'posting_effect' => $postingEffect,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel,
            'accounting_note' => "{$entryType} entry {$postingEffect}s {$accountType} account",
            'is_cash_bank_account' => (bool) $account->is_cash_bank,
        ];
    }

    private function sourceType(ChartOfAccount $account, string $side): string
    {
        if ($account->is_cash_bank) {
            return $side === 'Debit' ? 'receiving' : 'payment';
        }

        $name = strtoupper($account->account_name);

        if (str_contains($name, 'SALARY PAYABLE')) {
            return 'salarypay';
        }

        if (str_contains($name, 'RECEIVABLE') || str_contains($name, 'CUSTOMER DUE')) {
            return 'ar';
        }

        if (str_contains($name, 'PAYABLE') || str_contains($name, 'SUPPLIER DUE')) {
            return 'ap';
        }

        return 'fixed';
    }

    private function sourceLabel(ChartOfAccount $account, string $side): string
    {
        return match ($this->sourceType($account, $side)) {
            'payment' => 'Paid From Account',
            'receiving' => 'Received In Account',
            'ar' => 'Accounts Receivable',
            'ap' => 'Accounts Payable',
            'salarypay' => 'Salary Payable',
            default => 'Fixed Ledger Account',
        };
    }

    private function accountTypeName(ChartOfAccount $account): string
    {
        return $account->accountType?->name ?? 'Asset';
    }

    private function normalBalance(ChartOfAccount $account): string
    {
        $normalBalance = $account->normal_balance ?: $account->accountType?->normal_balance;

        if (in_array($normalBalance, ['Debit', 'Credit'], true)) {
            return $normalBalance;
        }

        return match ($this->accountTypeName($account)) {
            'Asset', 'Expense' => 'Debit',
            'Liability', 'Equity', 'Income' => 'Credit',
            default => 'Debit',
        };
    }

    private function postingEffect(ChartOfAccount $account, string $entryType): string
    {
        return $this->normalBalance($account) === $entryType
            ? 'Increase'
            : 'Decrease';
    }

    private function ruleRequiresCashBank(LedgerMappingRule $rule): bool
    {
        return (bool) $rule->debitAccount?->is_cash_bank
            || (bool) $rule->creditAccount?->is_cash_bank
            || in_array($this->settlementKey($rule), ['cash', 'bank', 'advance_paid', 'advance_received'], true);
    }

    private function cashBankRequiredMessage(LedgerMappingRule $rule): string
    {
        $key = $this->settlementKey($rule);

        if (in_array($key, ['cash', 'bank', 'advance_paid'], true)) {
            return 'Please select the account from which payment was made.';
        }

        if ($key === 'advance_received') {
            return 'Please select the account where money was received.';
        }

        return 'Paid From / Received In is required for this transaction.';
    }

    private function settlementKey(LedgerMappingRule $rule): string
    {
        $code = strtoupper((string) $rule->settlementType?->code);
        $name = strtoupper((string) $rule->settlementType?->name);
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
}