<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutomaticCodeService
{
    /** @var array<string, int> */
    private const COA_BASES = [
        'asset' => 1000,
        'liability' => 2000,
        'income' => 3000,
        'expense' => 4000,
        'equity' => 5000,
    ];

    public function lockCompany(int $companyId): void
    {
        DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
    }

    public function nextChartOfAccountCode(int $companyId, string $type, ?int $ignoreId = null): string
    {
        $base = self::COA_BASES[strtolower(trim($type))] ?? null;

        if ($base === null) {
            throw ValidationException::withMessages([
                'type' => 'No automatic account-code series is configured for this account type.',
            ]);
        }

        $maximum = $base + 999;
        $lastNumber = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('code')
            ->map(static fn ($code): ?int => preg_match('/^\d+$/', (string) $code) === 1 ? (int) $code : null)
            ->filter(static fn (?int $number): bool => $number !== null && $number >= $base && $number <= $maximum)
            ->max();

        $candidate = max($base, ((int) ($lastNumber ?? ($base - 1))) + 1);

        while ($candidate <= $maximum && ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('code', (string) $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate++;
        }

        if ($candidate > $maximum) {
            throw ValidationException::withMessages([
                'code' => ucfirst(strtolower($type)).' account-code series is full.',
            ]);
        }

        return (string) $candidate;
    }

    public function accountingRuleCode(int $companyId, string $name, ?int $ignoreId = null): string
    {
        return $this->uniqueNameCode(
            AccountingRule::query()->where('company_id', $companyId),
            $name,
            'RULE',
            $ignoreId,
        );
    }

    public function transactionHeadCode(int $companyId, string $name, ?int $ignoreId = null): string
    {
        return $this->uniqueNameCode(
            TransactionHead::query()->where('company_id', $companyId),
            $name,
            'HEAD',
            $ignoreId,
        );
    }

    public function initialValue(string $label, string $group, ?int $ignoreId = null): string
    {
        $base = $this->initials($label, $group === AccountingOption::GROUP_PARTY_TYPE ? 'P' : 'M');
        $candidate = $base;
        $counter = 2;

        while (AccountingOption::query()
            ->where('option_group', $group)
            ->where('value', $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $suffix = (string) $counter++;
            $candidate = Str::limit($base, 60 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }

    public function nextPartyCode(int $companyId, string $partyType, ?int $ignoreId = null): string
    {
        $label = AccountingOption::query()
            ->where('option_group', AccountingOption::GROUP_PARTY_TYPE)
            ->where('value', $partyType)
            ->value('label');

        $prefix = $this->initials((string) ($label ?: $partyType), 'P');
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d+)$/i';

        $lastNumber = Party::query()
            ->where('company_id', $companyId)
            ->where('type', $partyType)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('code')
            ->map(static function ($code) use ($pattern): ?int {
                return preg_match($pattern, (string) $code, $matches) === 1 ? (int) $matches[1] : null;
            })
            ->filter(static fn (?int $number): bool => $number !== null)
            ->max();

        $candidateNumber = ((int) ($lastNumber ?? 0)) + 1;

        do {
            $candidate = $prefix.'-'.str_pad((string) $candidateNumber, 3, '0', STR_PAD_LEFT);
            $candidateNumber++;
        } while (Party::query()
            ->where('company_id', $companyId)
            ->where('code', $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists());

        return $candidate;
    }

    /**
     * @param Builder<AccountingRule>|Builder<TransactionHead> $query
     */
    private function uniqueNameCode(Builder $query, string $name, string $fallback, ?int $ignoreId): string
    {
        $base = $this->slugCode($name, $fallback);
        $candidate = $base;
        $counter = 2;

        while ((clone $query)
            ->where('code', $candidate)
            ->when($ignoreId !== null, fn (Builder $builder) => $builder->whereKeyNot($ignoreId))
            ->exists()) {
            $suffix = '-'.$counter++;
            $candidate = Str::limit($base, 50 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }

    private function slugCode(string $name, string $fallback): string
    {
        $ascii = Str::ascii(trim($name));
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii));
        $code = trim($code, '-');

        return Str::limit($code !== '' ? $code : $fallback, 50, '');
    }

    private function initials(string $label, string $fallback): string
    {
        $ascii = Str::ascii(trim($label));
        $words = preg_split('/[^A-Za-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = implode('', array_map(static fn (string $word): string => strtoupper($word[0]), $words));
        $initials = preg_replace('/[^A-Z0-9]/', '', $initials) ?: '';

        return Str::limit($initials !== '' ? $initials : $fallback, 10, '');
    }
}
