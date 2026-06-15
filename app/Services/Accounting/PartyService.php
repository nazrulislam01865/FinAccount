<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\Party;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartyService
{
    /**
     * @return array{parties: Collection<int, Party>, receivableAccounts: Collection<int, ChartOfAccount>, payableAccounts: Collection<int, ChartOfAccount>, balances: array<int, float>}
     */
    public function pageData(int $companyId): array
    {
        $parties = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $receivableAccounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('type', 'Asset')
            ->orderBy('code')
            ->get();

        $payableAccounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('type', ['Liability', 'Equity'])
            ->orderBy('code')
            ->get();

        return [
            'parties' => $parties,
            'receivableAccounts' => $receivableAccounts,
            'payableAccounts' => $payableAccounts,
            'balances' => $this->balancesFor($parties, $companyId),
        ];
    }

    /**
     * @param Collection<int, Party> $parties
     * @return array<int, float>
     */
    public function balancesFor(Collection $parties, int $companyId): array
    {
        $movements = JournalLine::query()
            ->with('chartOfAccount:id,normal_balance')
            ->where('company_id', $companyId)
            ->whereNotNull('party_id')
            ->get(['id', 'party_id', 'chart_of_account_id', 'debit', 'credit'])
            ->groupBy('party_id')
            ->map(function (Collection $lines): float {
                return (float) $lines->sum(function (JournalLine $line): float {
                    $debitLessCredit = (float) $line->debit - (float) $line->credit;

                    return $line->chartOfAccount?->normal_balance === 'Credit'
                        ? -$debitLessCredit
                        : $debitLessCredit;
                });
            });

        return $parties->mapWithKeys(fn (Party $party): array => [
            $party->id => (float) $party->opening_balance + (float) ($movements[$party->id] ?? 0),
        ])->all();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): Party
    {
        $this->validateAccountTypes($data, $companyId);

        return DB::transaction(fn (): Party => Party::query()->create([
            'company_id' => $companyId,
            ...$this->normalized($data),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(Party $party, array $data): Party
    {
        $this->validateAccountTypes($data, $party->company_id);
        DB::transaction(fn () => $party->update($this->normalized($data)), attempts: 5);

        return $party->refresh();
    }

    public function delete(Party $party): void
    {
        if ($party->transactions()->exists()) {
            throw ValidationException::withMessages([
                'party' => 'Cannot delete. Used by transaction.',
            ]);
        }

        DB::transaction(fn () => $party->delete(), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    private function validateAccountTypes(array $data, int $companyId): void
    {
        if (filled($data['receivable_account_id'] ?? null)) {
            $valid = ChartOfAccount::query()
                ->whereKey($data['receivable_account_id'])
                ->where('company_id', $companyId)
                ->where('type', 'Asset')
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'receivable_account_id' => 'Receivable COA must be an Asset account.',
                ]);
            }
        }

        if (filled($data['payable_account_id'] ?? null)) {
            $valid = ChartOfAccount::query()
                ->whereKey($data['payable_account_id'])
                ->where('company_id', $companyId)
                ->whereIn('type', ['Liability', 'Equity'])
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'payable_account_id' => 'Payable or capital COA must be a Liability or Equity account.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'type' => $data['type'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'receivable_account_id' => $data['receivable_account_id'] ?: null,
            'payable_account_id' => $data['payable_account_id'] ?: null,
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
