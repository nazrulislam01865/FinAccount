<?php

namespace App\AccountingEngine\Services;

use App\AccountingEngine\DTO\TransactionInput;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Party;
use Illuminate\Validation\ValidationException;

class AccountingRulePreviewService
{
    public function __construct(
        private readonly RuleResolver $ruleResolver,
        private readonly LedgerResolver $ledgerResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function preview(array $data, int $companyId, ?int $userId = null): ?array
    {
        $input = TransactionInput::fromArray($data, $companyId, (int) ($userId ?? 0));
        $resolved = $this->ruleResolver->resolve($input);

        if (! $resolved instanceof AccountingRule) {
            return null;
        }

        return $this->buildPreview($resolved, $input);
    }

    public function requiresCashBank(int $transactionHeadId, ?int $settlementTypeId, int $companyId): ?bool
    {
        $resolved = $this->ruleResolver->resolve([
            'company_id' => $companyId,
            'transaction_head_id' => $transactionHeadId,
            'settlement_type_id' => $settlementTypeId,
        ]);

        if (! $resolved instanceof AccountingRule) {
            return null;
        }

        return $resolved->cash_bank_ledger_required
            || $resolved->lines->contains(fn (AccountingRuleLine $line): bool => $this->ledgerResolver->normalizeLedgerSource($line->ledger_source) === 'user_cash_bank');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreview(AccountingRule $rule, TransactionInput $input): array
    {
        $rule->loadMissing([
            'lines.ledger.accountType',
            'transactionHead.defaultPrimaryLedger.accountType',
            'settlementType',
            'partyType',
        ]);

        if ($rule->lines->count() < 2) {
            throw ValidationException::withMessages([
                'ledger_mapping' => 'Active accounting rule must contain at least two journal lines.',
            ]);
        }

        if ($rule->requiresParty() && ! $input->partyId) {
            throw ValidationException::withMessages([
                'party_id' => 'Party/Sub-Ledger is required for this accounting rule.',
            ]);
        }

        if ($rule->cash_bank_ledger_required && ! $input->cashBankAccountId) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Cash/Bank account is required for this accounting rule.',
            ]);
        }

        $cashBankAccount = $this->cashBankAccount($input->cashBankAccountId);
        $party = $this->party($input->partyId);
        $entries = [];

        foreach ($rule->lines as $line) {
            $ledger = $this->ledgerResolver->resolve(
                line: $line,
                cashBankAccount: $cashBankAccount,
                party: $party,
                transactionHead: $rule->transactionHead
            );

            $amount = $this->amountForLine($line, $input);
            $entries[] = $this->entryPayload($rule, $line, $ledger, $amount);
        }

        $this->assertBalanced($entries);

        return [
            'rule' => $rule,
            'rule_model' => AccountingRule::class,
            'accounting_rule_id' => $rule->id,
            'accounting_rule_code' => $rule->rule_code,
            'legacy_ledger_mapping_rule_id' => $rule->legacy_ledger_mapping_rule_id,
            'cash_bank_account' => $cashBankAccount,
            'cash_bank_account_id' => $cashBankAccount?->id,
            'party' => $party,
            'party_ledger_effect' => $rule->party_ledger_effect ?: 'No Effect',
            'entries' => $entries,
        ];
    }

    private function cashBankAccount(?int $cashBankAccountId): ?CashBankAccount
    {
        if (! $cashBankAccountId) {
            return null;
        }

        return CashBankAccount::query()
            ->where('status', 'Active')
            ->with('linkedLedger.accountType')
            ->find($cashBankAccountId);
    }

    private function party(?int $partyId): ?Party
    {
        if (! $partyId) {
            return null;
        }

        return Party::query()
            ->with(['partyType', 'linkedLedger.accountType'])
            ->find($partyId);
    }

    private function amountForLine(AccountingRuleLine $line, TransactionInput $input): float
    {
        $source = strtolower(trim((string) ($line->amount_source ?: 'transaction_amount')));
        $formula = trim((string) $line->amount_formula);

        $amount = match ($source) {
            'zero' => 0.00,
            'fixed_amount' => $this->numericFormula($formula),
            'percentage_of_amount' => $input->amount * ($this->percentageFormula($formula) / 100),
            default => $input->amount,
        };

        return round((float) $amount, 2);
    }

    private function numericFormula(string $formula): float
    {
        if ($formula === '') {
            return 0.00;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', $formula);
    }

    private function percentageFormula(string $formula): float
    {
        if ($formula === '') {
            return 100.00;
        }

        return (float) str_replace('%', '', $formula);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(
        AccountingRule $rule,
        AccountingRuleLine $line,
        ChartOfAccount $account,
        float $amount
    ): array {
        $entryType = $line->side === 'Credit' ? 'Credit' : 'Debit';
        $debit = $entryType === 'Debit' ? $amount : 0.00;
        $credit = $entryType === 'Credit' ? $amount : 0.00;
        $accountType = $this->accountTypeName($account);
        $normalBalance = $this->normalBalance($account);
        $postingEffect = $normalBalance === $entryType ? 'Increase' : 'Decrease';

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
            'source_type' => $line->ledger_source,
            'source_label' => $this->sourceLabel($line),
            'accounting_note' => "{$entryType} entry {$postingEffect}s {$accountType} account",
            'is_cash_bank_account' => (bool) $account->is_cash_bank,
            'accounting_rule_id' => $rule->id,
            'accounting_rule_code' => $rule->rule_code,
            'rule_line_id' => $line->id,
            'amount_source' => $line->amount_source ?: 'transaction_amount',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function assertBalanced(array $entries): void
    {
        $totalDebit = round((float) collect($entries)->sum('debit'), 2);
        $totalCredit = round((float) collect($entries)->sum('credit'), 2);

        if (count($entries) < 2 || $totalDebit <= 0 || $totalCredit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'Accounting rule must generate balanced debit and credit journal lines.',
            ]);
        }
    }

    private function sourceLabel(AccountingRuleLine $line): string
    {
        return match ($this->ledgerResolver->normalizeLedgerSource($line->ledger_source)) {
            'user_cash_bank' => $line->side === 'Debit' ? 'Received In Account' : 'Paid From Account',
            'party_control' => 'Party/Sub-Ledger Control Account',
            'transaction_head' => 'Transaction Head Default Ledger',
            'system_derived' => 'System Derived Ledger',
            default => ucfirst($line->line_role) . ' Fixed Ledger',
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
            'Liability', 'Equity', 'Owner’s Equity', 'Owner\'s Equity', 'Income' => 'Credit',
            default => 'Debit',
        };
    }
}
