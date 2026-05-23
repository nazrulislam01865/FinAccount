<?php

namespace App\AccountingEngine\Services;

use App\Models\ChartOfAccount;
use App\Models\Party;
use Illuminate\Validation\ValidationException;

class JournalValidator
{
    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function assertValid(
        array $entries,
        float $amount,
        ?int $partyId = null,
        bool $cashBankRequired = false,
        ?int $cashBankAccountId = null
    ): void {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        if (count($entries) < 2) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'At least two accounting lines are required for posting.',
            ]);
        }

        $totalDebit = round(collect($entries)->sum(fn (array $entry) => (float) ($entry['debit'] ?? 0)), 2);
        $totalCredit = round(collect($entries)->sum(fn (array $entry) => (float) ($entry['credit'] ?? 0)), 2);

        if ($totalDebit <= 0 || $totalCredit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'ledger_preview' => 'Debit and Credit totals must be equal before posting.',
            ]);
        }

        if ($cashBankRequired && ! $cashBankAccountId) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Cash/Bank account is required for this transaction.',
            ]);
        }

        foreach ($entries as $index => $entry) {
            $this->assertLedgerIsPostable((int) ($entry['account_id'] ?? 0), $index + 1, $partyId);
        }
    }

    public function assertLedgerIsPostable(int $accountId, int $lineNo = 1, ?int $partyId = null): ChartOfAccount
    {
        $ledger = ChartOfAccount::query()->with(['accountType', 'partyType'])->find($accountId);

        if (! $ledger) {
            throw ValidationException::withMessages([
                'ledger_preview' => "Journal line {$lineNo} has no valid ledger account.",
            ]);
        }

        if ($ledger->status !== 'Active') {
            throw ValidationException::withMessages([
                'ledger_preview' => "Journal line {$lineNo} uses inactive ledger {$ledger->display_name}.",
            ]);
        }

        $level = (int) ($ledger->coa_level ?: ($ledger->account_level === 'Ledger' ? 4 : 0));

        if ($ledger->account_level !== 'Ledger' || $level !== 4 || ! $ledger->posting_allowed) {
            throw ValidationException::withMessages([
                'ledger_preview' => "Journal line {$lineNo} must use an active Level 4 posting ledger.",
            ]);
        }

        if ($this->isPartyControlLedger($ledger)) {
            if (! $partyId) {
                throw ValidationException::withMessages([
                    'party_id' => "Party/Sub-Ledger is required for {$ledger->display_name}.",
                ]);
            }

            $party = Party::query()->with('partyType')->find($partyId);

            if (! $party || $party->status !== 'Active') {
                throw ValidationException::withMessages([
                    'party_id' => 'Selected party must be active.',
                ]);
            }

            if ($ledger->party_type_id && (int) $party->party_type_id !== (int) $ledger->party_type_id) {
                throw ValidationException::withMessages([
                    'party_id' => "Selected party type does not match {$ledger->display_name}.",
                ]);
            }
        }

        return $ledger;
    }

    public function isPartyControlLedger(ChartOfAccount $ledger): bool
    {
        return (bool) $ledger->is_party_control || $ledger->ledger_type === 'Party Control';
    }
}
