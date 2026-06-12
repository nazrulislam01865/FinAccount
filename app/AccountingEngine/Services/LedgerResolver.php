<?php

namespace App\AccountingEngine\Services;

use App\Models\AccountingRuleLine;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Models\TransactionHead;
use Illuminate\Validation\ValidationException;

class LedgerResolver
{
    private PartyLedgerResolver $partyLedgerResolver;

    public function __construct(?PartyLedgerResolver $partyLedgerResolver = null)
    {
        $this->partyLedgerResolver = $partyLedgerResolver ?? new PartyLedgerResolver();
    }

    public function resolve(
        AccountingRuleLine $line,
        ?CashBankAccount $cashBankAccount,
        ?Party $party,
        ?TransactionHead $transactionHead
    ): ChartOfAccount {
        $source = $this->normalizeLedgerSource($line->ledger_source);

        $ledger = match ($source) {
            'user_cash_bank' => $this->cashBankLedger($cashBankAccount),
            'party_receivable' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_RECEIVABLE),
            'party_payable' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_PAYABLE),
            'party_advance_paid' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_ADVANCE_PAID),
            'party_advance_received' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED),
            'party_loan_payable' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_LOAN_PAYABLE),
            'party_salary_payable' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_SALARY_PAYABLE),
            'party_capital' => $this->partyLedger($party, PartyLedgerMapping::PURPOSE_CAPITAL),
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
        $source = strtolower(trim(str_replace(['-', ' '], '_', (string) $source)));
        $source = preg_replace('/_+/', '_', $source) ?: '';

        return match (true) {
            in_array($source, ['party_receivable', 'customer_receivable'], true),
            str_contains($source, 'party_receivable'),
            str_contains($source, 'customer_receivable') => 'party_receivable',

            in_array($source, ['party_payable', 'supplier_payable'], true),
            str_contains($source, 'party_payable'),
            str_contains($source, 'supplier_payable') => 'party_payable',

            str_contains($source, 'party_advance_paid') => 'party_advance_paid',
            str_contains($source, 'party_advance_received') => 'party_advance_received',
            str_contains($source, 'party_loan_payable') => 'party_loan_payable',
            str_contains($source, 'party_salary_payable') => 'party_salary_payable',
            str_contains($source, 'party_capital'), str_contains($source, 'owner_capital') => 'party_capital',

            $source === 'user_cash_bank', str_contains($source, 'cash'), str_contains($source, 'bank'), str_contains($source, 'payment_method') => 'user_cash_bank',
            $source === 'party_control', str_contains($source, 'party') => 'party_control',
            $source === 'transaction_head', str_contains($source, 'transaction_head') => 'transaction_head',
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

    private function partyLedger(?Party $party, string $purpose): ?ChartOfAccount
    {
        if (! $party) {
            throw ValidationException::withMessages([
                'party_id' => 'Party is required for this accounting rule.',
            ]);
        }

        return $this->partyLedgerResolver->resolve($party, $purpose);
    }

    private function partyControlLedger(AccountingRuleLine $line, ?Party $party): ?ChartOfAccount
    {
        if ($party) {
            $purpose = $this->partyLedgerResolver->purposeForLegacyPartyControl(
                $party,
                $line->allowed_ledger_type
            );

            $mappedLedger = $this->partyLedgerResolver->resolve($party, $purpose, false);
            if ($mappedLedger) {
                return $mappedLedger;
            }

            $party->loadMissing('linkedLedger.accountType');
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
            ->when($party?->company_id, fn ($query, $companyId) => $query->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)->orWhereNull('company_id');
            }))
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

        if ($party) {
            $purpose = $this->partyLedgerResolver->purposeForLegacyPartyControl($party, $allowedType);
            $mappedLedger = $this->partyLedgerResolver->resolve($party, $purpose, false);

            if ($mappedLedger) {
                return $mappedLedger;
            }
        }

        if (str_contains($allowedType, 'party') || str_contains($allowedType, 'receivable') || str_contains($allowedType, 'payable')) {
            return $this->partyControlLedger($line, $party);
        }

        return $this->transactionHeadLedger($line, $transactionHead);
    }
}
