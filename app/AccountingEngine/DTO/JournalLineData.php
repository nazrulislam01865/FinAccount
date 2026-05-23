<?php

namespace App\AccountingEngine\DTO;

final readonly class JournalLineData
{
    public function __construct(
        public ?int $accountId,
        public ?string $accountCode,
        public ?string $accountName,
        public ?string $accountType,
        public string $entryType,
        public float $debit,
        public float $credit,
        public ?string $sourceLabel = null,
        public ?int $partyId = null,
        public ?string $narration = null,
    ) {
    }

    /**
     * @param array<string, mixed> $line
     */
    public static function fromArray(array $line): self
    {
        return new self(
            accountId: isset($line['account_id']) ? (int) $line['account_id'] : null,
            accountCode: isset($line['account_code']) ? (string) $line['account_code'] : null,
            accountName: isset($line['account_name']) ? (string) $line['account_name'] : null,
            accountType: isset($line['account_type']) ? (string) $line['account_type'] : null,
            entryType: (string) ($line['entry_type'] ?? (((float) ($line['debit'] ?? 0)) > 0 ? 'Debit' : 'Credit')),
            debit: round((float) ($line['debit'] ?? 0), 2),
            credit: round((float) ($line['credit'] ?? 0), 2),
            sourceLabel: isset($line['source_label']) ? (string) $line['source_label'] : null,
            partyId: isset($line['party_id']) ? (int) $line['party_id'] : null,
            narration: isset($line['narration']) ? (string) $line['narration'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'account_type' => $this->accountType,
            'entry_type' => $this->entryType,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'source_label' => $this->sourceLabel,
            'party_id' => $this->partyId,
            'narration' => $this->narration,
        ];
    }
}
