<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\Party;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartyService
{
    public function __construct(
        private readonly AccountingOptionService $optionService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    /**
     * @return array{parties: Collection<int, Party>, balances: array<int, float>, partyTypes: Collection<int, AccountingOption>, partyTypeLabels: array<string, string>, nextPartyCodes: array<string, string>}
     */
    public function pageData(int $companyId): array
    {
        $parties = Party::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $partyTypes = $this->optionService->forGroup(AccountingOption::GROUP_PARTY_TYPE);

        return [
            'parties' => $parties,
            'balances' => $this->balancesFor($parties, $companyId),
            'partyTypes' => $partyTypes,
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
            'nextPartyCodes' => $partyTypes->mapWithKeys(fn (AccountingOption $option): array => [
                $option->value => $this->automaticCodeService->nextPartyCode($companyId, $option->value),
            ])->all(),
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
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
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

        $openingBalances = OpeningBalanceService::postedPartyOpeningBalances($parties, $companyId);

        return $parties->mapWithKeys(fn (Party $party): array => [
            $party->id => (float) ($openingBalances[$party->id] ?? 0) + (float) ($movements[$party->id] ?? 0),
        ])->all();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): Party
    {
        $data = $this->applyDefaultAccountMapping($data, $companyId);
        $this->validateAccountTypes($data, $companyId);

        return DB::transaction(function () use ($data, $companyId): Party {
            $this->automaticCodeService->lockCompany($companyId);
            $data['code'] = $this->automaticCodeService->nextPartyCode($companyId, (string) $data['type']);

            return Party::query()->create([
                'company_id' => $companyId,
                ...$this->normalized($data),
            ]);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(Party $party, array $data): Party
    {
        $data = $this->applyDefaultAccountMapping($data, (int) $party->company_id);
        $this->validateAccountTypes($data, $party->company_id);

        DB::transaction(function () use ($party, $data): void {
            $this->automaticCodeService->lockCompany((int) $party->company_id);
            $data['code'] = (string) $data['type'] === (string) $party->type
                ? $party->code
                : $this->automaticCodeService->nextPartyCode(
                    (int) $party->company_id,
                    (string) $data['type'],
                    (int) $party->id,
                );
            $party->update($this->normalized($data));
        }, attempts: 5);

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
    private function applyDefaultAccountMapping(array $data, int $companyId): array
    {
        $type = trim((string) ($data['type'] ?? ''));

        $data['receivable_account_id'] = null;
        $data['payable_account_id'] = null;

        if ($type === 'Customer') {
            $data['receivable_account_id'] = $this->defaultAccountId(
                $companyId,
                ['Asset'],
                ['Customer Receivable', 'Accounts Receivable', 'Trade Receivable'],
                ['customer receivable', 'accounts receivable', 'trade receivable', 'customer'],
                'Create a Level 3 Asset ledger named Customer Receivable before adding Customer parties.',
            );
        }

        if ($type === 'Supplier') {
            $data['payable_account_id'] = $this->defaultAccountId(
                $companyId,
                ['Liability'],
                ['Supplier Payable', 'Accounts Payable', 'Trade Payable'],
                ['supplier payable', 'accounts payable', 'trade payable', 'supplier'],
                'Create a Level 3 Liability ledger named Supplier Payable before adding Supplier parties.',
            );
        }

        if ($type === 'Worker') {
            $data['payable_account_id'] = $this->defaultAccountId(
                $companyId,
                ['Liability'],
                ['Salary Payable', 'Wages Payable'],
                ['salary payable', 'wages payable', 'salary'],
                'Create a Level 3 Liability ledger named Salary Payable before adding Worker parties.',
            );
        }

        if ($type === 'Lender') {
            $data['payable_account_id'] = $this->defaultAccountId(
                $companyId,
                ['Liability'],
                ['Loan from Bank / Lender', 'Loan Payable', 'Bank Loan'],
                ['loan from bank', 'loan payable', 'bank loan', 'lender', 'loan'],
                'Create a Level 3 Liability ledger named Loan from Bank / Lender before adding Lender parties.',
            );
        }

        if ($type === 'Owner') {
            $data['payable_account_id'] = $this->defaultAccountId(
                $companyId,
                ['Equity'],
                ['Owner Capital', 'Capital Account', 'Owner Equity'],
                ['owner capital', 'capital account', 'owner equity', 'capital'],
                'Create a Level 3 Equity ledger named Owner Capital before adding Owner parties.',
            );
        }

        return $data;
    }

    /**
     * @param array<int, string> $types
     * @param array<int, string> $exactNames
     * @param array<int, string> $nameKeywords
     */
    private function defaultAccountId(
        int $companyId,
        array $types,
        array $exactNames,
        array $nameKeywords,
        string $missingMessage,
    ): int {
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->whereIn('type', $types)
            ->where(function ($query) use ($exactNames): void {
                foreach ($exactNames as $index => $name) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('LOWER(name) = ?', [mb_strtolower($name)]);
                }
            })
            ->orderBy('code')
            ->first();

        if ($account instanceof ChartOfAccount) {
            return (int) $account->id;
        }

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->whereIn('type', $types)
            ->where(function ($query) use ($nameKeywords): void {
                foreach ($nameKeywords as $index => $keyword) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('LOWER(name) LIKE ?', ['%'.mb_strtolower($keyword).'%']);
                }
            })
            ->orderBy('code')
            ->first();

        if ($account instanceof ChartOfAccount) {
            return (int) $account->id;
        }

        throw ValidationException::withMessages([
            'type' => $missingMessage,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function validateAccountTypes(array $data, int $companyId): void
    {
        if (filled($data['receivable_account_id'] ?? null)) {
            $valid = ChartOfAccount::query()
                ->whereKey($data['receivable_account_id'])
                ->where('company_id', $companyId)
                ->where('level', 3)
                ->where('type', 'Asset')
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'receivable_account_id' => 'Receivable COA must be a Level 3 Asset ledger.',
                ]);
            }
        }

        if (filled($data['payable_account_id'] ?? null)) {
            $valid = ChartOfAccount::query()
                ->whereKey($data['payable_account_id'])
                ->where('company_id', $companyId)
                ->where('level', 3)
                ->whereIn('type', ['Liability', 'Equity'])
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'payable_account_id' => 'Payable or capital COA must be a Level 3 Liability or Equity ledger.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data): array
    {
        $normalized = [
            'code' => trim((string) ($data['code'] ?? '')),
            'name' => trim((string) $data['name']),
            'type' => $data['type'],
            'receivable_account_id' => $data['receivable_account_id'] ?: null,
            'payable_account_id' => $data['payable_account_id'] ?: null,
            'is_active' => (bool) $data['is_active'],
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'address' => filled($data['address'] ?? null) ? $data['address'] : null,
        ];

        if (array_key_exists('profile_pic', $data)) {
            $normalized['profile_pic'] = $data['profile_pic'];
        }

        return $normalized;
    }
}
