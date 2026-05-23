<?php

namespace App\AccountingEngine\Services;

use App\Services\Accounting\MappingResolverService;

class JournalBuilder
{
    public function __construct(
        private readonly MappingResolverService $mappingResolver
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFromTransaction(
        int $transactionHeadId,
        int $settlementTypeId,
        float $amount,
        ?int $cashBankAccountId = null,
        ?int $partyId = null,
        ?int $companyId = null,
        ?int $userId = null,
        ?string $voucherDate = null
    ): array {
        $preview = $this->mappingResolver->preview(
            transactionHeadId: $transactionHeadId,
            settlementTypeId: $settlementTypeId,
            amount: $amount,
            cashBankAccountId: $cashBankAccountId,
            partyId: $partyId,
            companyId: $companyId,
            userId: $userId,
            voucherDate: $voucherDate
        );

        $preview['entries'] = array_values($preview['entries'] ?? []);

        return $preview;
    }
}
