<?php

namespace App\Services\Accounting;

use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PostingPeriodGuard
{
    /**
     * Reusable data-safety gate for every posting entry point.
     *
     * Phase 2 keeps legacy Active financial years compatible while supporting
     * the SRS lifecycle: Open, Closed, and Locked with an optional lock date.
     */
    public function ensureOpenForDate(FinancialYear $financialYear, Carbon|string $transactionDate): void
    {
        $date = $transactionDate instanceof Carbon
            ? $transactionDate->copy()->startOfDay()
            : Carbon::parse($transactionDate)->startOfDay();

        if ($date->lt($financialYear->start_date) || $date->gt($financialYear->end_date)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'Transaction date must be inside the current Financial Year.',
            ]);
        }

        $status = (string) $financialYear->status;

        if (in_array($status, [FinancialYear::STATUS_CLOSED, FinancialYear::STATUS_LOCKED], true)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'This Financial Year is ' . $status . ' and cannot accept new postings.',
            ]);
        }

        if ($financialYear->lock_date && $date->lte($financialYear->lock_date)) {
            throw ValidationException::withMessages([
                'voucher_date' => 'This transaction date is on or before the Financial Year lock date.',
            ]);
        }
    }
}
