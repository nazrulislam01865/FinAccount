<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PartyBalanceReportService extends BaseVoucherDetailReportService
{
    public function customerReceivable(array $filters = []): array
    {
        return $this->buildPartyBalance($filters, 'Customer Receivable', 'Customer', 'debit');
    }

    public function supplierPayable(array $filters = []): array
    {
        return $this->buildPartyBalance($filters, 'Supplier Payable', 'Supplier', 'credit');
    }

    private function buildPartyBalance(array $filters, string $title, string $partyKind, string $normalSide): array
    {
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $partyId = ! empty($filters['party_id']) ? (int) $filters['party_id'] : null;
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);

        $movementSub = $this->partyMovementQuery($companyId, null, $toDate, $partyKind)
            ->groupBy('d.party_id', 'd.account_id')
            ->selectRaw('d.party_id')
            ->selectRaw('d.account_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN v.voucher_date < ? THEN d.debit ELSE 0 END), 0) AS opening_debit', [$fromDate])
            ->selectRaw('COALESCE(SUM(CASE WHEN v.voucher_date < ? THEN d.credit ELSE 0 END), 0) AS opening_credit', [$fromDate])
            ->selectRaw('COALESCE(SUM(CASE WHEN v.voucher_date >= ? AND v.voucher_date <= ? THEN d.debit ELSE 0 END), 0) AS period_debit', [$fromDate, $toDate])
            ->selectRaw('COALESCE(SUM(CASE WHEN v.voucher_date >= ? AND v.voucher_date <= ? THEN d.credit ELSE 0 END), 0) AS period_credit', [$fromDate, $toDate]);

        $rows = DB::query()
            ->fromSub($movementSub, 'm')
            ->join('parties as p', 'p.id', '=', 'm.party_id')
            ->join('party_types as pt', 'pt.id', '=', 'p.party_type_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'm.account_id')
            ->whereNull('p.deleted_at')
            ->when($partyId, fn (Builder $query) => $query->where('p.id', $partyId))
            ->when($search !== '', function (Builder $query) use ($search) {
                $needle = '%' . $search . '%';
                $query->where(function (Builder $where) use ($needle) {
                    $where->whereRaw('LOWER(p.party_code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(p.party_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(a.account_name, "")) LIKE ?', [$needle]);
                });
            })
            ->selectRaw('p.id AS party_id')
            ->selectRaw('p.party_code')
            ->selectRaw('p.party_name')
            ->selectRaw('pt.name AS party_type')
            ->selectRaw('a.id AS account_id')
            ->selectRaw('a.account_code')
            ->selectRaw('a.account_name')
            ->selectRaw('COALESCE(m.opening_debit, 0) AS opening_debit')
            ->selectRaw('COALESCE(m.opening_credit, 0) AS opening_credit')
            ->selectRaw('COALESCE(m.period_debit, 0) AS period_debit')
            ->selectRaw('COALESCE(m.period_credit, 0) AS period_credit')
            ->orderBy('p.party_name')
            ->orderBy('a.account_code')
            ->get()
            ->map(function (object $row) use ($normalSide) {
                $openingNet = (float) $row->opening_debit - (float) $row->opening_credit;
                $periodNet = (float) $row->period_debit - (float) $row->period_credit;
                $closingDebitNet = $openingNet + $periodNet;
                $row->opening_balance = round($normalSide === 'debit' ? $openingNet : -$openingNet, 2);
                $row->debit_movement = round((float) $row->period_debit, 2);
                $row->credit_movement = round((float) $row->period_credit, 2);
                $row->closing_balance = round($normalSide === 'debit' ? $closingDebitNet : -$closingDebitNet, 2);
                return $row;
            })
            ->filter(fn (object $row) => $includeZero || abs((float) $row->closing_balance) >= 0.01 || abs((float) $row->debit_movement) >= 0.01 || abs((float) $row->credit_movement) >= 0.01)
            ->values();

        return [
            'title' => $title,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
            'total_opening' => round((float) $rows->sum('opening_balance'), 2),
            'total_debit_movement' => round((float) $rows->sum('debit_movement'), 2),
            'total_credit_movement' => round((float) $rows->sum('credit_movement'), 2),
            'total_closing' => round((float) $rows->sum('closing_balance'), 2),
            'parties' => DB::table('parties as p')
                ->join('party_types as pt', 'pt.id', '=', 'p.party_type_id')
                ->whereRaw('LOWER(pt.name) LIKE ?', ['%' . mb_strtolower($partyKind) . '%'])
                ->when($companyId, fn (Builder $query) => $query->where('p.company_id', $companyId))
                ->where('p.status', 'Active')
                ->orderBy('p.party_name')
                ->select('p.id', 'p.party_code', 'p.party_name')
                ->get(),
        ];
    }

    private function partyMovementQuery(?int $companyId, ?string $fromDate, ?string $toDate, string $partyKind): Builder
    {
        return $this->basePostedLinesQuery($companyId, $fromDate, $toDate)
            ->join('parties as p', 'p.id', '=', 'd.party_id')
            ->join('party_types as pt', 'pt.id', '=', 'p.party_type_id')
            ->whereNotNull('d.party_id')
            ->where(function (Builder $query) use ($partyKind) {
                $query->where('a.is_party_control', 1)
                    ->orWhereRaw('LOWER(a.ledger_type) = ?', ['party control']);
            })
            ->whereRaw('LOWER(pt.name) LIKE ?', ['%' . mb_strtolower($partyKind) . '%']);
    }
}
