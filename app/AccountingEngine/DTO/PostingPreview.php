<?php

namespace App\AccountingEngine\DTO;

final readonly class PostingPreview
{
    /**
     * @param array<int, JournalLineData> $journalLines
     */
    public function __construct(
        public ?int $financialYearId,
        public ?string $financialYearName,
        public string $voucherType,
        public string $voucherNumber,
        public string $voucherDate,
        public string $transactionHead,
        public ?string $nature,
        public string $settlementType,
        public string $partyLedgerEffect,
        public string $cashBankEffect,
        public ?int $cashBankAccountId,
        public array $journalLines,
        public float $totalDebit,
        public float $totalCredit,
        public bool $balanced,
        public bool $mappingFound,
        public ?string $accountingPrinciple = null,
    ) {
    }

    /**
     * @param array<string, mixed> $preview
     */
    public static function fromLegacyPreview(array $preview): self
    {
        $lines = array_map(
            fn (array $line): JournalLineData => JournalLineData::fromArray($line),
            $preview['entries'] ?? []
        );

        return new self(
            financialYearId: isset($preview['financial_year_id']) ? (int) $preview['financial_year_id'] : null,
            financialYearName: isset($preview['financial_year_name']) ? (string) $preview['financial_year_name'] : null,
            voucherType: (string) ($preview['voucher_type'] ?? ''),
            voucherNumber: (string) ($preview['voucher_number'] ?? ''),
            voucherDate: (string) ($preview['voucher_date'] ?? ''),
            transactionHead: (string) ($preview['transaction_head'] ?? ''),
            nature: isset($preview['nature']) ? (string) $preview['nature'] : null,
            settlementType: (string) ($preview['settlement_type'] ?? ''),
            partyLedgerEffect: (string) ($preview['party_ledger_effect'] ?? 'No Effect'),
            cashBankEffect: (string) ($preview['cash_bank_effect'] ?? 'No Cash/Bank'),
            cashBankAccountId: isset($preview['cash_bank_account_id']) ? (int) $preview['cash_bank_account_id'] : null,
            journalLines: $lines,
            totalDebit: round((float) ($preview['total_debit'] ?? 0), 2),
            totalCredit: round((float) ($preview['total_credit'] ?? 0), 2),
            balanced: (bool) ($preview['balanced'] ?? false),
            mappingFound: (bool) ($preview['mapping_found'] ?? false),
            accountingPrinciple: isset($preview['accounting_principle']) ? (string) $preview['accounting_principle'] : null,
        );
    }

    /**
     * Keeps the API response stable for the existing transaction-entry UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'financial_year_id' => $this->financialYearId,
            'financial_year_name' => $this->financialYearName,
            'voucher_type' => $this->voucherType,
            'voucher_number' => $this->voucherNumber,
            'voucher_date' => $this->voucherDate,
            'transaction_head' => $this->transactionHead,
            'nature' => $this->nature,
            'settlement_type' => $this->settlementType,
            'party_ledger_effect' => $this->partyLedgerEffect,
            'cash_bank_effect' => $this->cashBankEffect,
            'cash_bank_account_id' => $this->cashBankAccountId,
            'entries' => array_map(
                fn (JournalLineData $line): array => $line->toArray(),
                $this->journalLines
            ),
            'total_debit' => $this->totalDebit,
            'total_credit' => $this->totalCredit,
            'balanced' => $this->balanced,
            'mapping_found' => $this->mappingFound,
            'accounting_principle' => $this->accountingPrinciple,
        ];
    }
}
