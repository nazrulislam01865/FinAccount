<?php

namespace App\AccountingEngine\Services;

use App\Models\AccountingRuleLine;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class LedgerResolver
{
    public function resolve(
        AccountingRuleLine $line,
        ?CashBankAccount $cashBankAccount,
        ?Party $party,
        ?TransactionHead $transactionHead
    ): ChartOfAccount {
        $source = $this->normalizeLedgerSource($line->ledger_source);

        $ledger = match ($source) {
            'user_cash_bank' => $this->cashBankLedger($cashBankAccount),
            'party_control' => $this->partyControlLedger($line, $party),
            'transaction_head' => $this->transactionHeadLedger($line, $transactionHead),
            'system_derived' => $this->systemDerivedLedger($line, $cashBankAccount, $party, $transactionHead),
            default => $line->ledger,
        };

        if (! $ledger) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "Accounting rule line {$line->line_role} has no resolvable ledger.",
            ]);
        }

        $this->assertPostingLedger($ledger, "Resolved {$line->line_role} ledger");

        return $ledger;
    }

    public function normalizeLedgerSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return match (true) {
            $source === 'user_cash_bank', str_contains($source, 'cash'), str_contains($source, 'bank'), str_contains($source, 'payment method') => 'user_cash_bank',
            $source === 'party_control', str_contains($source, 'party') => 'party_control',
            $source === 'transaction_head', str_contains($source, 'transaction head') => 'transaction_head',
            $source === 'system_derived', str_contains($source, 'system') => 'system_derived',
            default => 'fixed',
        };
    }

    public function assertPostingLedger(?ChartOfAccount $ledger, string $label = 'Ledger'): void
    {
        if (! $ledger) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "{$label} is missing.",
            ]);
        }

        if ($ledger->status !== 'Active') {
            throw ValidationException::withMessages([
                'ledger_mapping' => "{$label} must be active.",
            ]);
        }

        $isPostingLevel = $ledger->account_level === 'Ledger' || (int) $ledger->coa_level === 4;

        if (! $isPostingLevel || ! (bool) $ledger->posting_allowed) {
            throw ValidationException::withMessages([
                'ledger_mapping' => "{$label} must be an active Level 4 posting ledger.",
            ]);
        }
    }

    private function cashBankLedger(?CashBankAccount $cashBankAccount): ?ChartOfAccount
    {
        if (! $cashBankAccount) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Cash/Bank account is required for this accounting rule.',
            ]);
        }

        $cashBankAccount->loadMissing('linkedLedger.accountType');
        $ledger = $cashBankAccount->linkedLedger;

        if (! $ledger || ! (bool) $ledger->is_cash_bank) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Selected Cash/Bank account must be linked to an active Cash/Bank posting ledger.',
            ]);
        }

        return $ledger;
    }

    private function partyControlLedger(AccountingRuleLine $line, ?Party $party): ?ChartOfAccount
    {
        if ($party) {
            $party->loadMissing('linkedLedger.accountType', 'partyType');

            if ($party->linkedLedger) {
                return $party->linkedLedger;
            }
        }

        if ($line->ledger) {
            return $line->ledger;
        }

        return ChartOfAccount::query()
            ->postingLedgers()
            ->where('is_party_control', true)
            ->when($party?->party_type_id, fn ($query, $partyTypeId) => $query->where('party_type_id', $partyTypeId))
            ->with('accountType')
            ->orderBy('account_code')
            ->first();
    }

    private function transactionHeadLedger(AccountingRuleLine $line, ?TransactionHead $transactionHead): ?ChartOfAccount
    {
        if ($transactionHead) {
            $transactionHead->loadMissing('defaultPrimaryLedger.accountType');

            if ($transactionHead->defaultPrimaryLedger) {
                return $transactionHead->defaultPrimaryLedger;
            }
        }

        return $line->ledger;
    }

    private function systemDerivedLedger(
        AccountingRuleLine $line,
        ?CashBankAccount $cashBankAccount,
        ?Party $party,
        ?TransactionHead $transactionHead
    ): ?ChartOfAccount {
        if ($line->ledger) {
            return $line->ledger;
        }

        $allowedType = strtolower(trim((string) $line->allowed_ledger_type));

        if (str_contains($allowedType, 'cash') || str_contains($allowedType, 'bank')) {
            return $this->cashBankLedger($cashBankAccount);
        }

        if (str_contains($allowedType, 'party') || str_contains($allowedType, 'receivable') || str_contains($allowedType, 'payable')) {
            return $this->partyControlLedger($line, $party);
        }

        return $this->transactionHeadLedger($line, $transactionHead);
    }
}
