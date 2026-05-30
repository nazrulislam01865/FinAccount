<?php

namespace App\Services\Dashboard;

use App\Models\AccountingRule;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\User;
use App\Models\VoucherHeader;
use App\Services\Setup\SetupProgressService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    private const CACHE_VERSION = 4;

    public function __construct(private readonly SetupProgressService $setupProgressService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user): array
    {
        $companyId = $this->resolveCompanyId($user);
        $today = now()->toDateString();

        return Cache::remember(
            $this->cacheKey($companyId, $user?->id, $today),
            now()->addSeconds((int) config('performance.cache.dashboard_ttl_seconds', 120)),
            function () use ($companyId): array {
            $monthStart = now()->startOfMonth()->toDateString();
            $monthEnd = now()->endOfMonth()->toDateString();
            $setupSteps = $this->setupProgressService->steps();
            $monthlyIncome = $this->monthlyIncome($companyId, $monthStart, $monthEnd);
            $monthlyExpense = $this->monthlyExpense($companyId, $monthStart, $monthEnd);

            return [
                'company_id' => $companyId,
                'cash_in_hand' => $this->cashBalance($companyId),
                'bank_balance' => $this->bankBalance($companyId),
                'total_receivable' => $this->partyControlBalance($companyId, 'Customer', 'receivable'),
                'total_payable' => $this->partyControlBalance($companyId, 'Supplier', 'payable'),
                'monthly_income' => $monthlyIncome,
                'monthly_expense' => $monthlyExpense,
                'net_profit_loss' => round($monthlyIncome - $monthlyExpense, 2),
                'recent_transactions' => $this->recentTransactions($companyId),
                'pending_approvals' => $this->pendingApprovals($companyId),
                'setup_completion' => [
                    'percent' => $this->setupProgressService->percent($setupSteps),
                    'completed' => $this->setupProgressService->completedCount($setupSteps),
                    'total' => $this->setupProgressService->totalCount($setupSteps),
                    'steps' => $setupSteps,
                ],
                'setup_counts' => $this->setupCounts($companyId),
                'month_range' => [$monthStart, $monthEnd],
            ];
        });
    }

    public function clear(?int $companyId = null, ?int $userId = null): void
    {
        $companyId ??= $this->resolveCompanyId(null);
        Cache::forget($this->cacheKey($companyId, $userId, now()->toDateString()));
    }

    private function cacheKey(int $companyId, ?int $userId, string $date): string
    {
        return "company:{$companyId}:user:" . ($userId ?: 'all') . ":dashboard:v" . self::CACHE_VERSION . ":{$date}";
    }

    private function resolveCompanyId(?User $user): int
    {
        $companyId = (int) ($user?->company_id ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        return (int) Company::query()->orderBy('id')->value('id');
    }

    private function cashBalance(int $companyId): float
    {
        $ledgerIds = $this->cashBankLedgerIds($companyId, 'Cash');

        if ($ledgerIds->isEmpty()) {
            $ledgerIds = $this->ledgerIdsByType($companyId, ['Cash']);
        }

        return $this->ledgerBalance($companyId, $ledgerIds);
    }

    private function bankBalance(int $companyId): float
    {
        $ledgerIds = $this->cashBankLedgerIds($companyId, 'Bank');

        if ($ledgerIds->isEmpty()) {
            $ledgerIds = $this->ledgerIdsByType($companyId, ['Bank']);
        }

        return $this->ledgerBalance($companyId, $ledgerIds);
    }

    private function monthlyIncome(int $companyId, string $from, string $to): float
    {
        return $this->movementByAccountType($companyId, ['Income'], $from, $to, creditMinusDebit: true);
    }

    private function monthlyExpense(int $companyId, string $from, string $to): float
    {
        return $this->movementByAccountType($companyId, ['Expense'], $from, $to, creditMinusDebit: false);
    }

    private function partyControlBalance(int $companyId, string $partyTypeName, string $mode): float
    {
        $ledgerIds = ChartOfAccount::query()
            ->leftJoin('party_types as pt', 'pt.id', '=', 'chart_of_accounts.party_type_id')
            ->where('chart_of_accounts.status', 'Active')
            ->where(function ($query) {
                $query->where('chart_of_accounts.is_party_control', true)
                    ->orWhere('chart_of_accounts.ledger_type', 'Party Control');
            })
            ->where(function ($query) use ($companyId) {
                $query->where('chart_of_accounts.company_id', $companyId)
                    ->orWhereNull('chart_of_accounts.company_id');
            })
            ->where(function ($query) use ($partyTypeName) {
                $query->whereRaw("LOWER(COALESCE(pt.name, '')) = ?", [mb_strtolower($partyTypeName)])
                    ->orWhereRaw("LOWER(COALESCE(chart_of_accounts.account_name, '')) LIKE ?", ['%' . mb_strtolower($partyTypeName) . '%']);
            })
            ->pluck('chart_of_accounts.id');

        if ($ledgerIds->isEmpty()) {
            return 0.0;
        }

        $balance = $this->ledgerBalance($companyId, $ledgerIds);

        return $mode === 'payable'
            ? round(max(0, -1 * $balance), 2)
            : round(max(0, $balance), 2);
    }

    private function ledgerBalance(int $companyId, Collection $ledgerIds): float
    {
        if ($ledgerIds->isEmpty()) {
            return 0.0;
        }

        if ($this->usesJournalLines()) {
            $posted = DB::table('journal_lines as jl')
                ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
                ->leftJoin('voucher_headers as v', 'v.id', '=', 'jh.voucher_header_id')
                ->whereIn('jl.ledger_id', $ledgerIds->all())
                ->whereIn('jh.status', [VoucherHeader::STATUS_POSTED, VoucherHeader::STATUS_REVERSED])
                ->where(function ($query) {
                    $query->whereNull('v.id')->orWhereNull('v.deleted_at');
                })
                ->when($companyId > 0, fn ($query) => $query->where('jh.company_id', $companyId))
                ->selectRaw('COALESCE(SUM(jl.debit_amount), 0) - COALESCE(SUM(jl.credit_amount), 0) AS balance')
                ->value('balance');

            return round((float) $posted, 2);
        }

        $posted = DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->whereIn('d.account_id', $ledgerIds->all())
            ->where('v.status', VoucherHeader::STATUS_POSTED)
            ->whereNull('v.deleted_at')
            ->when($companyId > 0, fn ($query) => $query->where('v.company_id', $companyId))
            ->selectRaw('COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0) AS balance')
            ->value('balance');

        return round((float) $posted, 2);
    }

    private function movementByAccountType(int $companyId, array $accountTypes, string $from, string $to, bool $creditMinusDebit): float
    {
        $select = $creditMinusDebit
            ? 'COALESCE(SUM(d.credit), 0) - COALESCE(SUM(d.debit), 0) AS movement'
            : 'COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0) AS movement';

        if ($this->usesJournalLines()) {
            $select = $creditMinusDebit
                ? 'COALESCE(SUM(jl.credit_amount), 0) - COALESCE(SUM(jl.debit_amount), 0) AS movement'
                : 'COALESCE(SUM(jl.debit_amount), 0) - COALESCE(SUM(jl.credit_amount), 0) AS movement';

            $value = DB::table('journal_lines as jl')
                ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
                ->leftJoin('voucher_headers as v', 'v.id', '=', 'jh.voucher_header_id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'jl.ledger_id')
                ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->whereIn('jh.status', [VoucherHeader::STATUS_POSTED, VoucherHeader::STATUS_REVERSED])
                ->where(function ($query) {
                    $query->whereNull('v.id')->orWhereNull('v.deleted_at');
                })
                ->whereDate('jh.journal_date', '>=', $from)
                ->whereDate('jh.journal_date', '<=', $to)
                ->when($companyId > 0, fn ($query) => $query->where('jh.company_id', $companyId))
                ->where(function ($query) use ($accountTypes) {
                    $query->whereIn('at.name', $accountTypes)
                        ->orWhereIn('a.account_nature', $accountTypes)
                        ->orWhereIn('a.ledger_type', $accountTypes);
                })
                ->selectRaw($select)
                ->value('movement');

            return round(max(0, (float) $value), 2);
        }

        $value = DB::table('voucher_details as d')
            ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->leftJoin('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('v.status', VoucherHeader::STATUS_POSTED)
            ->whereNull('v.deleted_at')
            ->whereDate('v.voucher_date', '>=', $from)
            ->whereDate('v.voucher_date', '<=', $to)
            ->when($companyId > 0, fn ($query) => $query->where('v.company_id', $companyId))
            ->where(function ($query) use ($accountTypes) {
                $query->whereIn('at.name', $accountTypes)
                    ->orWhereIn('a.account_nature', $accountTypes)
                    ->orWhereIn('a.ledger_type', $accountTypes);
            })
            ->selectRaw($select)
            ->value('movement');

        return round(max(0, (float) $value), 2);
    }

    private function usesJournalLines(): bool
    {
        try {
            return Schema::hasTable('journal_headers') && Schema::hasTable('journal_lines');
        } catch (\Throwable) {
            return false;
        }
    }

    private function recentTransactions(int $companyId): Collection
    {
        return VoucherHeader::query()
            ->with(['transactionHead:id,name,head_code', 'party:id,party_name'])
            ->whereIn('status', [VoucherHeader::STATUS_POSTED, VoucherHeader::STATUS_PENDING_REVIEW, VoucherHeader::STATUS_DRAFT])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->latest('voucher_date')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function pendingApprovals(int $companyId): int
    {
        return VoucherHeader::query()
            ->where('status', VoucherHeader::STATUS_PENDING_REVIEW)
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function setupCounts(int $companyId): array
    {
        return [
            'companies' => Company::query()->count(),
            'financial_years' => FinancialYear::query()->whereIn('status', ['Active', 'Open'])->count(),
            'posting_ledgers' => ChartOfAccount::query()->postingLedgers()->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
            'cash_bank_accounts' => CashBankAccount::query()->where('status', 'Active')->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
            'parties' => Party::query()->where('status', 'Active')->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
            'transaction_heads' => TransactionHead::query()->where('status', 'Active')->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
            'accounting_rules' => AccountingRule::query()->active()->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count() + LedgerMappingRule::query()->where('status', 'Active')->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
            'opening_balances' => OpeningBalance::query()->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))->count(),
        ];
    }

    private function cashBankLedgerIds(int $companyId, string $type): Collection
    {
        return CashBankAccount::query()
            ->where('status', 'Active')
            ->where('type', $type)
            ->whereNotNull('linked_ledger_account_id')
            ->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->pluck('linked_ledger_account_id')
            ->unique()
            ->values();
    }

    private function ledgerIdsByType(int $companyId, array $types): Collection
    {
        return ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('posting_allowed', true)
            ->whereIn('ledger_type', $types)
            ->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->pluck('id')
            ->unique()
            ->values();
    }
}
