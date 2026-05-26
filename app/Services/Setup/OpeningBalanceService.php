<?php

namespace App\Services\Setup;

use App\AccountingEngine\Services\AuditTrailService;
use App\AccountingEngine\Services\FinancialPeriodGuard;
use App\AccountingEngine\Services\JournalValidator;
use App\AccountingEngine\Services\JournalPostingService;
use App\AccountingEngine\Services\PartyRegisterService;
use App\AccountingEngine\Services\VoucherNumberService;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherHeader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    public function __construct(
        private readonly JournalValidator $journalValidator,
        private readonly VoucherNumberService $voucherNumberService,
        private readonly PartyRegisterService $partyRegisterService,
        private readonly AuditTrailService $auditTrailService,
        private readonly FinancialPeriodGuard $financialPeriodGuard,
        private readonly JournalPostingService $journalPostingService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $company = Company::query()->first();
            $financialYear = FinancialYear::query()->findOrFail($data['financial_year_id']);
            $balanceDate = Carbon::parse($data['balance_date'] ?? $financialYear->start_date)->toDateString();
            $branchLocation = $this->blankToNull($data['branch_location'] ?? null);
            $status = $data['status'] ?? 'Draft';

            $this->financialPeriodGuard->assertOpen($financialYear, $balanceDate);
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

                if ($debit > 0 && $credit > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'A single opening balance line cannot contain both debit and credit amounts.',
                    ]);
                }

                $ledger = $this->validateOpeningLedger((int) $item['account_id'], $item['party_id'] ?? null);

                $openingBalance = OpeningBalance::query()->create([
                    'company_id' => $company?->id,
                    'financial_year_id' => $financialYear->id,
                    'balance_date' => $balanceDate,
                    'branch_location' => $branchLocation,
                    'account_id' => $ledger->id,
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
                    'account_id' => (int) $ledger->id,
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

    /**
     * @param array<int, array<string, mixed>> $lines
     */
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
        $voucherDate = Carbon::parse($balanceDate);
        $voucherNumber = $this->voucherNumberService->reserveWithLock('Opening Voucher', $financialYear, $voucherDate);
        $now = now();

        $voucher = VoucherHeader::query()->create([
            'company_id' => $company?->id,
            'financial_year_id' => $financialYear->id,
            'voucher_number' => $voucherNumber,
            'voucher_type' => 'Opening Voucher',
            'voucher_date' => $voucherDate->toDateString(),
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
            'submitted_at' => $now,
            'submitted_by' => $userId,
            'posted_at' => $now,
            'posted_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach ($lines as $index => $line) {
            $entryType = ((float) $line['debit']) > 0 ? 'Debit' : 'Credit';

            $voucher->details()->create([
                'company_id' => $company?->id,
                'branch_id' => null,
                'transaction_date' => $voucherDate->toDateString(),
                'line_no' => $index + 1,
                'account_id' => $line['account_id'],
                'party_id' => $line['party_id'],
                'rule_line_id' => null,
                'amount_source' => 'opening_balance',
                'entry_type' => $entryType,
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'narration' => $line['remarks'] ?: 'Opening balance',
            ]);
        }

        $voucher = $voucher->fresh(['details.account.accountType', 'details.party']);
        $this->journalPostingService->createOrSyncFromVoucher($voucher, 'Opening Balance');
        $this->partyRegisterService->recordOpeningBalance($voucher);
        $this->auditTrailService->recordPostedVoucher($voucher, $userId);

        return $voucher;
    }

    private function validateOpeningLedger(int $accountId, mixed $partyId): ChartOfAccount
    {
        $ledger = $this->journalValidator->assertLedgerIsPostable(
            accountId: $accountId,
            lineNo: 1,
            partyId: $partyId ? (int) $partyId : null
        );

        if ($this->journalValidator->isPartyControlLedger($ledger) && ! $partyId) {
            throw ValidationException::withMessages([
                'items' => "Party is required for opening balance ledger {$ledger->display_name}.",
            ]);
        }

        return $ledger;
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

        if (! $exists) {
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
                'category' => 'Opening Balance',
                'default_movement' => 'No Movement',
                'payment_method_required' => false,
                'party_required_mode' => 'Optional',
                'transaction_screen' => 'Opening Balance',
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
