<?php

namespace App\AccountingEngine\DTO;

use App\Models\VoucherHeader;
use Illuminate\Http\Request;

final readonly class TransactionInput
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $transactionDate,
        public int $transactionHeadId,
        public ?int $settlementTypeId,
        public ?int $partyId,
        public ?int $cashBankAccountId,
        public float $amount,
        public ?string $referenceNo,
        public ?string $narration,
        public string $status = VoucherHeader::STATUS_POSTED,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self(
            companyId: (int) ($user?->company_id ?? 0),
            userId: (int) ($user?->id ?? 0),
            transactionDate: (string) $request->input('voucher_date'),
            transactionHeadId: (int) $request->input('transaction_head_id'),
            settlementTypeId: $request->integer('settlement_type_id') ?: null,
            partyId: $request->integer('party_id') ?: null,
            cashBankAccountId: $request->integer('cash_bank_account_id') ?: null,
            amount: round((float) $request->input('amount'), 2),
            referenceNo: self::blankToNull($request->input('reference_no', $request->input('reference'))),
            narration: self::blankToNull($request->input('narration', $request->input('notes'))),
            status: (string) $request->input('status', VoucherHeader::STATUS_POSTED),
        );
    }


    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload, int $companyId, int $userId): self
    {
        return new self(
            companyId: $companyId,
            userId: $userId,
            transactionDate: (string) ($payload['voucher_date'] ?? $payload['transaction_date'] ?? now()->toDateString()),
            transactionHeadId: (int) ($payload['transaction_head_id'] ?? 0),
            settlementTypeId: isset($payload['settlement_type_id']) ? (int) $payload['settlement_type_id'] : null,
            partyId: isset($payload['party_id']) ? (int) $payload['party_id'] : null,
            cashBankAccountId: isset($payload['cash_bank_account_id']) && $payload['cash_bank_account_id'] !== null
                ? (int) $payload['cash_bank_account_id']
                : null,
            amount: round((float) ($payload['amount'] ?? 0), 2),
            referenceNo: self::blankToNull($payload['reference_no'] ?? $payload['reference'] ?? null),
            narration: self::blankToNull($payload['narration'] ?? $payload['notes'] ?? null),
            status: (string) ($payload['status'] ?? VoucherHeader::STATUS_POSTED),
        );
    }

    public function isDraft(): bool
    {
        return $this->status === VoucherHeader::STATUS_DRAFT;
    }

    /**
     * Compatibility payload for the legacy TransactionPostingService.
     *
     * Keeping this conversion in one DTO lets later phases change the engine internals
     * without rewriting controllers or UI forms again.
     *
     * @return array<string, mixed>
     */
    public function toLegacyPayload(): array
    {
        return [
            'company_id' => $this->companyId > 0 ? $this->companyId : null,
            'user_id' => $this->userId > 0 ? $this->userId : null,
            'voucher_date' => $this->transactionDate,
            'transaction_head_id' => $this->transactionHeadId,
            'settlement_type_id' => $this->settlementTypeId,
            'party_id' => $this->partyId,
            'cash_bank_account_id' => $this->cashBankAccountId,
            'amount' => $this->amount,
            'reference' => $this->referenceNo,
            'notes' => $this->narration,
            'status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'transaction_date' => $this->transactionDate,
            'transaction_head_id' => $this->transactionHeadId,
            'settlement_type_id' => $this->settlementTypeId,
            'party_id' => $this->partyId,
            'cash_bank_account_id' => $this->cashBankAccountId,
            'amount' => $this->amount,
            'reference_no' => $this->referenceNo,
            'narration' => $this->narration,
            'status' => $this->status,
        ];
    }

    private static function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
