<?php

namespace App\Services\Setup;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherHeader;
use App\Models\VoucherNumberingRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    public function save(array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $company = Company::query()->first();
            $financialYear = FinancialYear::query()->findOrFail($data['financial_year_id']);
            $balanceDate = Carbon::parse($data['balance_date'] ?? $financialYear->start_date)->toDateString();
            $branchLocation = $this->blankToNull($data['branch_location'] ?? null);
            $status = $data['status'] ?? 'Draft';

            $this->blockIfAlreadyFinalized($financialYear->id, $branchLocation);

            OpeningBalance::query()
                ->where('financial_year_id', $financialYear->id)
                ->where(function ($query) use ($branchLocation) {
                    if ($branchLocation === null) {
                        $query->whereNull('branch_location');
                    } else {
                        $query->where('branch_location', $branchLocation);
                    }
                })
                ->where('status', 'Draft')
                ->delete();

            $lines = [];

            foreach ($data['items'] as $item) {
                $debit = $this->amount($item['debit_opening'] ?? 0);
                $credit = $this->amount($item['credit_opening'] ?? 0);

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                $openingBalance = OpeningBalance::query()->create([
                    'company_id' => $company?->id,
                    'financial_year_id' => $financialYear->id,
                    'balance_date' => $balanceDate,
                    'branch_location' => $branchLocation,
                    'account_id' => $item['account_id'],
                    'party_id' => $item['party_id'] ?? null,
                    'debit_opening' => $debit,
                    'credit_opening' => $credit,
                    'remarks' => $item['remarks'] ?? null,
                    'status' => $status,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $lines[] = [
                    'opening_balance' => $openingBalance,
                    'account_id' => (int) $item['account_id'],
                    'party_id' => $item['party_id'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'remarks' => $item['remarks'] ?? null,
                ];
            }

            $voucher = null;

            if ($status === 'Final') {
                $voucher = $this->postOpeningVoucher(
                    company: $company,
                    financialYear: $financialYear,
                    balanceDate: $balanceDate,
                    branchLocation: $branchLocation,
                    lines: $lines,
                    userId: $userId
                );
            }

            return [
                'status' => $status,
                'voucher' => $voucher,
                'line_count' => count($lines),
                'total_debit' => round(collect($lines)->sum('debit'), 2),
                'total_credit' => round(collect($lines)->sum('credit'), 2),
            ];
        });
    }

    private function postOpeningVoucher(
        ?Company $company,
        FinancialYear $financialYear,
        string $balanceDate,
        ?string $branchLocation,
        array $lines,
        ?int $userId
    ): VoucherHeader {
        $totalDebit = round(collect($lines)->sum('debit'), 2);
        $totalCredit = round(collect($lines)->sum('credit'), 2);

        if ($totalDebit <= 0 || $totalCredit <= 0 || $totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'items' => 'Opening balance cannot be posted unless total debit equals total credit and both totals are greater than zero.',
            ]);
        }

        $transactionHead = $this->openingTransactionHead($company, $userId);
        $settlementType = $this->openingSettlementType($transactionHead);
        $voucherNumbering = $this->openingVoucherNumbering($company, $financialYear, $userId);

        $nextNumber = $voucherNumbering->next_number;
        $voucherNumber = $voucherNumbering->generate($nextNumber, Carbon::parse($balanceDate));

        if (VoucherHeader::query()->where('voucher_number', $voucherNumber)->exists()) {
            throw ValidationException::withMessages([
                'voucher_number' => 'Opening voucher number already exists. Please check Voucher Numbering Setup.',
            ]);
        }

        $voucherNumbering->update([
            'last_number' => $nextNumber,
            'updated_by' => $userId,
        ]);

        $voucher = VoucherHeader::query()->create([
            'company_id' => $company?->id,
            'financial_year_id' => $financialYear->id,
            'voucher_number' => $voucherNumber,
            'voucher_type' => 'Opening Voucher',
            'voucher_date' => $balanceDate,
            'transaction_head_id' => $transactionHead->id,
            'settlement_type_id' => $settlementType->id,
            'party_id' => null,
            'cash_bank_account_id' => null,
            'amount' => $totalDebit,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'party_ledger_effect' => 'Opening Balance',
            'cash_bank_effect' => 'Opening Balance',
            'reference' => $branchLocation,
            'notes' => trim('Opening balance for ' . $financialYear->name . ($branchLocation ? ' - ' . $branchLocation : '')),
            'status' => VoucherHeader::STATUS_POSTED,
            'posted_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach ($lines as $index => $line) {
            $entryType = $line['debit'] > 0 ? 'Debit' : 'Credit';

            $voucher->details()->create([
                'line_no' => $index + 1,
                'account_id' => $line['account_id'],
                'party_id' => $line['party_id'],
                'entry_type' => $entryType,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'narration' => $line['remarks'] ?: 'Opening balance',
            ]);
        }

        AuditLog::query()->create([
            'auditable_type' => VoucherHeader::class,
            'auditable_id' => $voucher->id,
            'event' => 'opening_balance_posted',
            'old_values' => null,
            'new_values' => $voucher->load('details')->toArray(),
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        return $voucher->fresh(['details.account.accountType', 'details.party']);
    }

    private function blockIfAlreadyFinalized(int $financialYearId, ?string $branchLocation): void
    {
        $exists = OpeningBalance::query()
            ->where('financial_year_id', $financialYearId)
            ->where('status', 'Final')
            ->where(function ($query) use ($branchLocation) {
                if ($branchLocation === null) {
                    $query->whereNull('branch_location');
                } else {
                    $query->where('branch_location', $branchLocation);
                }
            })
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Opening balance is already finalized for this Financial Year and Branch/Location. Posted opening balances cannot be edited directly.',
        ]);
    }

    private function openingTransactionHead(?Company $company, ?int $userId): TransactionHead
    {
        return TransactionHead::query()->firstOrCreate(
            [
                'company_id' => $company?->id,
                'name' => 'Opening Balance',
            ],
            [
                'head_code' => 'OPENING_BALANCE',
                'nature' => 'Journal',
                'requires_party' => false,
                'requires_reference' => false,
                'description' => 'System transaction head used to post opening balance lines.',
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function openingSettlementType(TransactionHead $transactionHead): SettlementType
    {
        $settlementType = SettlementType::query()->firstOrCreate(
            ['code' => 'OPENING_BALANCE'],
            [
                'name' => 'Opening Balance',
                'status' => 'Active',
                'sort_order' => 1,
            ]
        );

        $transactionHead->settlementTypes()->syncWithoutDetaching([$settlementType->id]);

        return $settlementType;
    }

    private function openingVoucherNumbering(?Company $company, FinancialYear $financialYear, ?int $userId): VoucherNumberingRule
    {
        return VoucherNumberingRule::query()->firstOrCreate(
            [
                'company_id' => $company?->id,
                'financial_year_id' => $financialYear->id,
                'voucher_type' => 'Opening Voucher',
            ],
            [
                'prefix' => 'OP',
                'format_template' => 'OP-{YYYY}-{00000}',
                'starting_number' => 1,
                'number_length' => 5,
                'last_number' => 0,
                'reset_every_year' => true,
                'used_for' => 'Opening balance',
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }
}