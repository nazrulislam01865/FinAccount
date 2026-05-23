<?php

namespace App\AccountingEngine\DTO;

use App\Models\VoucherHeader;

final readonly class PostingResult
{
    public function __construct(
        public int $voucherId,
        public string $voucherNumber,
        public string $voucherType,
        public string $status,
        public ?string $voucherDate,
        public float $amount,
        public float $totalDebit,
        public float $totalCredit,
        public ?int $companyId = null,
        public ?int $financialYearId = null,
    ) {
    }

    public static function fromVoucher(VoucherHeader $voucher): self
    {
        return new self(
            voucherId: (int) $voucher->id,
            voucherNumber: (string) $voucher->voucher_number,
            voucherType: (string) $voucher->voucher_type,
            status: (string) $voucher->status,
            voucherDate: $voucher->voucher_date?->toDateString(),
            amount: round((float) $voucher->amount, 2),
            totalDebit: round((float) $voucher->total_debit, 2),
            totalCredit: round((float) $voucher->total_credit, 2),
            companyId: $voucher->company_id ? (int) $voucher->company_id : null,
            financialYearId: $voucher->financial_year_id ? (int) $voucher->financial_year_id : null,
        );
    }

    public function posted(): bool
    {
        return $this->status === VoucherHeader::STATUS_POSTED;
    }

    public function message(): string
    {
        return $this->posted()
            ? 'Transaction posted successfully.'
            : 'Transaction saved as draft.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->voucherId,
            'voucher_number' => $this->voucherNumber,
            'voucher_type' => $this->voucherType,
            'status' => $this->status,
            'voucher_date' => $this->voucherDate,
            'amount' => $this->amount,
            'total_debit' => $this->totalDebit,
            'total_credit' => $this->totalCredit,
            'company_id' => $this->companyId,
            'financial_year_id' => $this->financialYearId,
        ];
    }
}
