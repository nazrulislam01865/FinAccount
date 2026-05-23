<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AccountMovementReportService extends BaseVoucherDetailReportService
{
    public function sales(array $filters = []): array
    {
        return $this->buildMovementReport($filters, 'Sales Report', ['Income'], 'credit');
    }

    public function expenses(array $filters = []): array
    {
        return $this->buildMovementReport($filters, 'Expense Report', ['Expense'], 'debit');
    }

    private function buildMovementReport(array $filters, string $title, array $accountTypes, string $normalSide): array
    {
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $accountId = ! empty($filters['account_id']) ? (int) $filters['account_id'] : null;
        $transactionHeadId = ! empty($filters['transaction_head_id']) ? (int) $filters['transaction_head_id'] : null;
        $partyId = ! empty($filters['party_id']) ? (int) $filters['party_id'] : null;
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);

        $rows = $this->basePostedLinesQuery($companyId, $fromDate, $toDate)
            ->leftJoin('transaction_heads as th', 'th.id', '=', 'v.transaction_head_id')
            ->leftJoin('parties as p', 'p.id', '=', 'd.party_id')
            ->whereIn('at.name', $accountTypes)
            ->when($accountId, fn (Builder $query) => $query->where('a.id', $accountId))
            ->when($transactionHeadId, fn (Builder $query) => $query->where('v.transaction_head_id', $transactionHeadId))
            ->when($partyId, fn (Builder $query) => $query->where('d.party_id', $partyId))
            ->when($search !== '', function (Builder $query) use ($search) {
                $needle = '%' . $search . '%';
                $query->where(function (Builder $where) use ($needle) {
                    $where->whereRaw('LOWER(a.account_code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(a.account_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(v.voucher_number, "")) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(th.name, "")) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(p.party_name, "")) LIKE ?', [$needle]);
                });
            })
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->selectRaw('v.id AS voucher_id')
            ->selectRaw('v.voucher_date')
            ->selectRaw('v.voucher_number')
            ->selectRaw('v.reference')
            ->selectRaw('th.name AS transaction_head')
            ->selectRaw('p.party_name')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code')
            ->selectRaw('a.account_name')
            ->selectRaw('d.debit')
            ->selectRaw('d.credit')
            ->selectRaw('d.narration')
            ->get()
            ->map(function (object $row) use ($normalSide) {
                $row->amount = round($normalSide === 'credit'
                    ? (float) $row->credit - (float) $row->debit
                    : (float) $row->debit - (float) $row->credit, 2);
                return $row;
            })
            ->filter(fn (object $row) => $includeZero || abs((float) $row->amount) >= 0.01)
            ->values();

        $byAccount = $rows->groupBy('account_id')->map(function ($group) {
            $first = $group->first();
            return (object) [
                'account_id' => $first->account_id,
                'account_code' => $first->account_code,
                'account_name' => $first->account_name,
                'total_amount' => round((float) $group->sum('amount'), 2),
                'entry_count' => $group->count(),
            ];
        })->values();

        return [
            'title' => $title,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
            'by_account' => $byAccount,
            'total_amount' => round((float) $rows->sum('amount'), 2),
            'entry_count' => $rows->count(),
            'accounts' => DB::table('chart_of_accounts as a')
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->whereIn('at.name', $accountTypes)
                ->when($companyId, fn (Builder $query) => $query->where('a.company_id', $companyId))
                ->where('a.status', 'Active')
                ->orderBy('a.account_code')
                ->select('a.id', 'a.account_code', 'a.account_name')
                ->get(),
            'transaction_heads' => DB::table('transaction_heads')
                ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
                ->where('status', 'Active')
                ->orderBy('name')
                ->select('id', 'head_code', 'name')
                ->get(),
            'parties' => DB::table('parties')
                ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
                ->where('status', 'Active')
                ->orderBy('party_name')
                ->select('id', 'party_code', 'party_name')
                ->get(),
        ];
    }
}
