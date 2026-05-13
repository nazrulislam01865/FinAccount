<?php

namespace App\Services\Accounting;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
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
                'ledger_mapping' => 'Ledger mapping is missing for the selected Transaction Head and Settlement Type. Posting is blocked.',
            ]);
        }

        if ((int) $rule->debit_account_id === (int) $rule->credit_account_id) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid ledger mapping: Debit Account and Credit Account cannot be the same.',
            ]);
        }

        if ($rule->debitAccount?->status !== 'Active' || $rule->creditAccount?->status !== 'Active') {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid ledger mapping: Debit or Credit Account is inactive.',
            ]);
        }

        if (!$rule->debitAccount?->posting_allowed || !$rule->creditAccount?->posting_allowed) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid ledger mapping: Debit and Credit Accounts must both be posting ledger accounts.',
            ]);
        }

        return $rule;
    }

    public function preview(
        int $transactionHeadId,
        int $settlementTypeId,
        float $amount,
        ?int $cashBankAccountId = null
    ): array {
        $rule = $this->resolve($transactionHeadId, $settlementTypeId);

        $cashBankAccount = null;
        $debitAccount = $rule->debitAccount;
        $creditAccount = $rule->creditAccount;

        if ($this->requiresCashBank($rule)) {
            if (!$cashBankAccountId) {
                throw ValidationException::withMessages([
                    'cash_bank_account_id' => 'Paid From / Received In is required for Cash or Bank settlement.',
                ]);
            }

            $cashBankAccount = CashBankAccount::query()
                ->with('linkedLedger.accountType')
                ->where('status', 'Active')
                ->find($cashBankAccountId);

            if (!$cashBankAccount || !$cashBankAccount->linkedLedger) {
                throw ValidationException::withMessages([
                    'cash_bank_account_id' => 'Selected cash/bank account is invalid or inactive.',
                ]);
            }

            if ($rule->debitAccount?->is_cash_bank) {
                $debitAccount = $cashBankAccount->linkedLedger;
            }

            if ($rule->creditAccount?->is_cash_bank) {
                $creditAccount = $cashBankAccount->linkedLedger;
            }
        }

        if ((int) $debitAccount->id === (int) $creditAccount->id) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Invalid posting result: Debit and Credit account cannot be same.',
            ]);
        }

        $amount = round($amount, 2);

        return [
            'rule' => $rule,
            'cash_bank_account' => $cashBankAccount,
            'entries' => [
                $this->entryPayload($debitAccount, 'Debit', $amount, 0.00),
                $this->entryPayload($creditAccount, 'Credit', 0.00, $amount),
            ],
        ];
    }

    private function entryPayload(
        ChartOfAccount $account,
        string $entryType,
        float $debit,
        float $credit
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
            'accounting_note' => "{$entryType} entry {$postingEffect}s {$accountType} account",
            'is_cash_bank_account' => (bool) $account->is_cash_bank,
        ];
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

    private function requiresCashBank(LedgerMappingRule $rule): bool
    {
        $code = strtoupper((string) $rule->settlementType?->code);
        $name = strtolower((string) $rule->settlementType?->name);

        return in_array($code, ['CASH', 'BANK'], true)
            || in_array($name, ['cash', 'bank'], true);
    }
}
