<?php

namespace App\AccountingEngine\Services;

use App\Models\FinancialYear;
use App\Services\Accounting\VoucherNumberGeneratorService;
use Carbon\CarbonInterface;

class VoucherNumberService
{
    public function __construct(
        private readonly VoucherNumberGeneratorService $legacyVoucherNumberGenerator
    ) {
    }

    public function preview(string $voucherType, FinancialYear $financialYear, ?CarbonInterface $voucherDate = null): string
    {
        return $this->legacyVoucherNumberGenerator->preview($voucherType, $financialYear, $voucherDate);
    }

    public function reserveWithLock(string $voucherType, FinancialYear $financialYear, ?CarbonInterface $voucherDate = null): string
    {
        return $this->legacyVoucherNumberGenerator->reserve($voucherType, $financialYear, $voucherDate);
    }
}
