<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherDetail;
use App\Models\VoucherHeader;
use App\Services\Accounting\FinancialYearService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LedgerReportController extends Controller
{
    public function index(Request $request): View
    {
        $accountGroups = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Group')
            ->with('accountType')
            ->orderBy('account_code')
            ->get();

        $allAccounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->with(['accountType', 'parent'])
            ->orderBy('account_code')
            ->get();

        $financialYearRange = app(FinancialYearService::class)
            ->reportRange((int) ($request->user()?->company_id ?? 0));

        $filters = [
            'from_date' => $request->query('from_date') ?: $financialYearRange['from_date'],
            'to_date' => $request->query('to_date') ?: $financialYearRange['to_date'],
            'account_group_id' => $request->query('account_group_id'),
            'account_id' => $request->query('account_id'),
            'party_id' => $request->query('party_id'),
            'voucher_type' => $request->query('voucher_type', 'All'),
            'transaction_head_id' => $request->query('transaction_head_id'),
            'status' => $request->query('status', VoucherHeader::STATUS_POSTED),
        ];

        $accounts = $this->filterAccountsByGroup($allAccounts, $accountGroups, $filters['account_group_id']);
        $account = $accounts->firstWhere('id', (int) $filters['account_id']) ?: $accounts->first();
        $filters['account_id'] = $account?->id;

        $report = $account
            ? $this->buildReport($account, $filters)
            : $this->emptyReport();

        return view('reports.ledger', [
            'accountGroups' => $accountGroups,
            'accounts' => $accounts,
            'allAccounts' => $allAccounts,
            'account' => $account,
            'parties' => Party::query()
                ->where('status', 'Active')
                ->orderBy('party_name')
                ->get(['id', 'party_code', 'party_name']),
            'voucherTypes' => $this->voucherTypes(),
            'transactionHeads' => TransactionHead::query()
                ->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'head_code', 'name']),
            'statusOptions' => $this->statusOptions(),
            'filters' => $filters,
            'fromDate' => $filters['from_date'],
            'toDate' => $filters['to_date'],
            'report' => $report,
        ]);
    }

    private function buildReport(ChartOfAccount $account, array $filters): array
    {
        $from = Carbon::parse($filters['from_date'])->toDateString();
        $to = Carbon::parse($filters['to_date'])->toDateString();

        $openingDebit = $this->baseLineQuery($account->id, $filters)
            ->whereDate('transaction_date', '<', $from)
            ->sum('debit');

        $openingCredit = $this->baseLineQuery($account->id, $filters)
            ->whereDate('transaction_date', '<', $from)
            ->sum('credit');

        $periodLines = $this->baseLineQuery($account->id, $filters)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')
            ->orderBy('line_id')
            ->get();

        $runningBalance = $this->signedBalance($account, (float) $openingDebit, (float) $openingCredit);
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = $periodLines->map(function (object $line) use ($account, &$runningBalance, &$totalDebit, &$totalCredit) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $runningBalance += $this->lineSignedMovement($account, $debit, $credit);

            return [
                'date' => $line->transaction_date,
                'voucher_number' => $line->voucher_number,
                'voucher_type' => $line->voucher_type,
                'transaction_head' => $line->transaction_head,
                'settlement_type' => $line->settlement_type,
                'party_name' => $line->party_name,
                'reference' => $line->reference,
                'status' => $line->status,
                'narration' => $line->narration,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
                'running_balance_label' => $this->formatAccountingBalance($account, $runningBalance),
            ];
        });

        $closingBalance = $runningBalance;

        return [
            'opening_debit' => round((float) $openingDebit, 2),
            'opening_credit' => round((float) $openingCredit, 2),
            'opening_balance' => $this->signedBalance($account, (float) $openingDebit, (float) $openingCredit),
            'opening_balance_label' => $this->formatAccountingBalance($account, $this->signedBalance($account, (float) $openingDebit, (float) $openingCredit)),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => round($closingBalance, 2),
            'closing_balance_label' => $this->formatAccountingBalance($account, $closingBalance),
            'rows' => $rows,
            'total_entries' => $rows->count(),
            'last_transaction' => $rows->last()['voucher_number'] ?? '-',
            'normal_balance' => $this->normalBalance($account),
            'account_type' => $account->accountType?->name ?? '-',
        ];
    }

    private function baseLineQuery(int $accountId, array $filters = []): Builder
    {
        $source = $this->usesJournalLines()
            ? DB::table('journal_lines as jl')
                ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
                ->leftJoin('voucher_headers as vh', 'vh.id', '=', 'jh.voucher_header_id')
                ->leftJoin('transaction_heads as th', 'th.id', '=', 'jh.transaction_head_id')
                ->leftJoin('settlement_types as st', 'st.id', '=', 'vh.settlement_type_id')
                ->leftJoin('parties as line_party', 'line_party.id', '=', 'jl.party_id')
                ->leftJoin('parties as header_party', 'header_party.id', '=', 'jh.party_id')
                ->where('jl.ledger_id', $accountId)
                ->where(function (Builder $query) {
                    $query->whereNull('vh.id')->orWhereNull('vh.deleted_at');
                })
                ->selectRaw('jl.id AS line_id')
                ->selectRaw('jh.voucher_header_id AS voucher_id')
                ->selectRaw('jh.journal_date AS transaction_date')
                ->selectRaw('jh.voucher_number AS voucher_number')
                ->selectRaw('jh.voucher_type AS voucher_type')
                ->selectRaw('th.name AS transaction_head')
                ->selectRaw('st.name AS settlement_type')
                ->selectRaw('COALESCE(line_party.party_name, header_party.party_name) AS party_name')
                ->selectRaw('COALESCE(jl.party_id, jh.party_id) AS party_id')
                ->selectRaw('vh.reference AS reference')
                ->selectRaw('jh.status AS status')
                ->selectRaw('COALESCE(jl.line_narration, jh.narration) AS narration')
                ->selectRaw('jl.debit_amount AS debit')
                ->selectRaw('jl.credit_amount AS credit')
                ->selectRaw('jh.transaction_head_id AS transaction_head_id')
            : DB::table('voucher_details as vd')
                ->join('voucher_headers as vh', 'vh.id', '=', 'vd.voucher_header_id')
                ->leftJoin('transaction_heads as th', 'th.id', '=', 'vh.transaction_head_id')
                ->leftJoin('settlement_types as st', 'st.id', '=', 'vh.settlement_type_id')
                ->leftJoin('parties as line_party', 'line_party.id', '=', 'vd.party_id')
                ->leftJoin('parties as header_party', 'header_party.id', '=', 'vh.party_id')
                ->where('vd.account_id', $accountId)
                ->whereNull('vh.deleted_at')
                ->selectRaw('vd.id AS line_id')
                ->selectRaw('vh.id AS voucher_id')
                ->selectRaw('vh.voucher_date AS transaction_date')
                ->selectRaw('vh.voucher_number AS voucher_number')
                ->selectRaw('vh.voucher_type AS voucher_type')
                ->selectRaw('th.name AS transaction_head')
                ->selectRaw('st.name AS settlement_type')
                ->selectRaw('COALESCE(line_party.party_name, header_party.party_name) AS party_name')
                ->selectRaw('COALESCE(vd.party_id, vh.party_id) AS party_id')
                ->selectRaw('vh.reference AS reference')
                ->selectRaw('vh.status AS status')
                ->selectRaw('COALESCE(vd.narration, vh.notes) AS narration')
                ->selectRaw('vd.debit AS debit')
                ->selectRaw('vd.credit AS credit')
                ->selectRaw('vh.transaction_head_id AS transaction_head_id');

        $query = DB::query()->fromSub($source, 'lines');
        $this->applyVoucherFilters($query, $filters);

        return $query;
    }

    private function applyVoucherFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['party_id'])) {
            $query->where('party_id', (int) $filters['party_id']);
        }

        if (! empty($filters['voucher_type']) && $filters['voucher_type'] !== 'All') {
            $query->where('voucher_type', $filters['voucher_type']);
        }

        if (! empty($filters['transaction_head_id'])) {
            $query->where('transaction_head_id', (int) $filters['transaction_head_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'All') {
            $query->where('status', $filters['status']);
        }
    }

    private function usesJournalLines(): bool
    {
        try {
            return Schema::hasTable('journal_headers') && Schema::hasTable('journal_lines');
        } catch (\Throwable) {
            return false;
        }
    }

    private function filterAccountsByGroup(Collection $accounts, Collection $groups, int|string|null $groupId): Collection
    {
        if (empty($groupId) || $groupId === 'All') {
            return $accounts->values();
        }

        $group = $groups->firstWhere('id', (int) $groupId);

        if (! $group) {
            return $accounts->values();
        }

        return $accounts
            ->filter(fn (ChartOfAccount $account) => (int) $account->parent_id === (int) $group->id
                || (empty($account->parent_id) && (int) $account->account_type_id === (int) $group->account_type_id))
            ->values();
    }

    private function voucherTypes(): Collection
    {
        return VoucherHeader::query()
            ->whereNotNull('voucher_type')
            ->where('voucher_type', '!=', '')
            ->distinct()
            ->orderBy('voucher_type')
            ->pluck('voucher_type');
    }

    private function statusOptions(): Collection
    {
        $defaults = collect([
            'All',
            VoucherHeader::STATUS_POSTED,
            VoucherHeader::STATUS_DRAFT,
            'Pending Review',
            'Reversed',
            'Cancelled',
        ]);

        $fromDb = VoucherHeader::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return $defaults->merge($fromDb)->filter()->unique()->values();
    }

    private function signedBalance(ChartOfAccount $account, float $debit, float $credit): float
    {
        return $this->normalBalance($account) === 'Debit'
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    private function lineSignedMovement(ChartOfAccount $account, float $debit, float $credit): float
    {
        return $this->normalBalance($account) === 'Debit'
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    private function formatAccountingBalance(ChartOfAccount $account, float $balance): string
    {
        if (round($balance, 2) == 0.0) {
            return 'BDT 0.00';
        }

        $normal = $this->normalBalance($account);
        $opposite = $normal === 'Debit' ? 'Cr' : 'Dr';
        $suffix = $balance >= 0 ? ($normal === 'Debit' ? 'Dr' : 'Cr') : $opposite;

        return 'BDT ' . number_format(abs($balance), 2) . ' ' . $suffix;
    }

    private function normalBalance(ChartOfAccount $account): string
    {
        $normal = $account->normal_balance ?: $account->accountType?->normal_balance;

        if (in_array($normal, ['Debit', 'Credit'], true)) {
            return $normal;
        }

        return match ($account->accountType?->name) {
            'Liability', 'Equity', 'Income' => 'Credit',
            default => 'Debit',
        };
    }

    private function emptyReport(): array
    {
        return [
            'opening_debit' => 0,
            'opening_credit' => 0,
            'opening_balance' => 0,
            'opening_balance_label' => 'BDT 0.00',
            'total_debit' => 0,
            'total_credit' => 0,
            'closing_balance' => 0,
            'closing_balance_label' => 'BDT 0.00',
            'rows' => collect(),
            'total_entries' => 0,
            'last_transaction' => '-',
            'normal_balance' => '-',
            'account_type' => '-',
        ];
    }
}
