<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use Illuminate\Support\Facades\DB;

class OpeningBalanceService
{
    public function save(array $data, ?int $userId = null): void
    {
        DB::transaction(function () use ($data, $userId) {
            $company = Company::query()->first();
            $financialYear = FinancialYear::query()->find($data['financial_year_id']);
            $balanceDate = $data['balance_date'] ?? $financialYear?->start_date?->toDateString();
            $branchLocation = $data['branch_location'] ?? null;

            OpeningBalance::query()
                ->where('financial_year_id', $data['financial_year_id'])
                ->where(function ($query) use ($branchLocation) {
                    if ($branchLocation === null || $branchLocation === '') {
                        $query->whereNull('branch_location');
                    } else {
                        $query->where('branch_location', $branchLocation);
                    }
                })
                ->delete();

            foreach ($data['items'] as $item) {
                $debit = $this->amount($item['debit_opening'] ?? 0);
                $credit = $this->amount($item['credit_opening'] ?? 0);

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                OpeningBalance::query()->create([
                    'company_id' => $company?->id,
                    'financial_year_id' => $data['financial_year_id'],
                    'balance_date' => $balanceDate,
                    'branch_location' => $branchLocation,
                    'account_id' => $item['account_id'],
                    'party_id' => $item['party_id'] ?? null,
                    'debit_opening' => $debit,
                    'credit_opening' => $credit,
                    'remarks' => $item['remarks'] ?? null,
                    'status' => $data['status'] ?? 'Draft',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        });
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }
}
