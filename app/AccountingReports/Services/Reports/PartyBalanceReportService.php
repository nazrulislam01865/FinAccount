<?php

namespace App\AccountingReports\Services\Reports;

use App\Models\PartyLedgerMapping;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PartyBalanceReportService extends BaseVoucherDetailReportService
{
    public function customerReceivable(array $filters = []): array
    {
        return $this->buildPartyBalance($filters, 'Customer Receivable', 'Customer', PartyLedgerMapping::PURPOSE_RECEIVABLE, 'debit');
    }

    public function supplierPayable(array $filters = []): array
    {
        return $this->buildPartyBalance($filters, 'Supplier Payable', 'Supplier', PartyLedgerMapping::PURPOSE_PAYABLE, 'credit');
    }

    private function buildPartyBalance(array $filters, string $title, string $partyKind, string $mappingPurpose, string $normalSide): array
    {
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $partyId = ! empty($filters['party_id']) ? (int) $filters['party_id'] : null;
        $includeZero = (bool) ($filters['include_zero_balances'] ?? false);

        $movementSub = $this->partyMovementQuery($companyId, null, $toDate, $mappingPurpose)
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
            ->map(function (object $row) use ($normalSide, $companyId, $toDate, $mappingPurpose) {
                $openingNet = (float) $row->opening_debit - (float) $row->opening_credit;
                $periodNet = (float) $row->period_debit - (float) $row->period_credit;
                $closingDebitNet = $openingNet + $periodNet;
                $row->opening_balance = round($normalSide === 'debit' ? $openingNet : -$openingNet, 2);
                $row->debit_movement = round((float) $row->period_debit, 2);
                $row->credit_movement = round((float) $row->period_credit, 2);
                $row->closing_balance = round($normalSide === 'debit' ? $closingDebitNet : -$closingDebitNet, 2);

                $aging = $this->agingBucketsFor(
                    companyId: $companyId,
                    toDate: $toDate,
                    mappingPurpose: $mappingPurpose,
                    partyId: (int) $row->party_id,
                    accountId: (int) $row->account_id,
                    normalSide: $normalSide
                );

                $row->aging_0_30 = $aging['0_30'];
                $row->aging_31_60 = $aging['31_60'];
                $row->aging_61_90 = $aging['61_90'];
                $row->aging_90_plus = $aging['90_plus'];

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
            'aging_totals' => [
                '0_30' => round((float) $rows->sum('aging_0_30'), 2),
                '31_60' => round((float) $rows->sum('aging_31_60'), 2),
                '61_90' => round((float) $rows->sum('aging_61_90'), 2),
                '90_plus' => round((float) $rows->sum('aging_90_plus'), 2),
            ],
            'parties' => DB::table('parties as p')
                ->join('party_ledger_mappings as plm', function ($join) use ($mappingPurpose) {
                    $join->on('plm.party_id', '=', 'p.id')
                        ->where('plm.mapping_purpose', '=', $mappingPurpose)
                        ->whereNotNull('plm.chart_of_account_id');
                })
                ->when($companyId, fn (Builder $query) => $query->where('p.company_id', $companyId))
                ->whereNull('p.deleted_at')
                ->where('p.status', 'Active')
                ->orderBy('p.party_name')
                ->select('p.id', 'p.party_code', 'p.party_name')
                ->distinct()
                ->get(),
        ];
    }

    private function agingBucketsFor(?int $companyId, string $toDate, string $mappingPurpose, int $partyId, int $accountId, string $normalSide): array
    {
        $buckets = [
            '0_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
        ];

        $to = Carbon::parse($toDate)->endOfDay();

        $this->partyMovementQuery($companyId, null, $toDate, $mappingPurpose)
            ->where('d.party_id', $partyId)
            ->where('d.account_id', $accountId)
            ->orderBy('v.voucher_date')
            ->selectRaw('v.voucher_date')
            ->selectRaw('d.debit')
            ->selectRaw('d.credit')
            ->get()
            ->each(function (object $line) use (&$buckets, $normalSide, $to) {
                $raw = $normalSide === 'debit'
                    ? (float) $line->debit - (float) $line->credit
                    : (float) $line->credit - (float) $line->debit;

                $amount = round(max(0, $raw), 2);
                if ($amount <= 0) {
                    return;
                }

                $age = max(0, Carbon::parse($line->voucher_date)->diffInDays($to, false));

                if ($age <= 30) {
                    $buckets['0_30'] += $amount;
                } elseif ($age <= 60) {
                    $buckets['31_60'] += $amount;
                } elseif ($age <= 90) {
                    $buckets['61_90'] += $amount;
                } else {
                    $buckets['90_plus'] += $amount;
                }
            });

        return array_map(fn (float $amount) => round($amount, 2), $buckets);
    }

    private function partyMovementQuery(?int $companyId, ?string $fromDate, ?string $toDate, string $mappingPurpose): Builder
    {
        return $this->basePostedLinesQuery($companyId, $fromDate, $toDate)
            ->join('parties as p', 'p.id', '=', 'd.party_id')
            ->join('party_types as pt', 'pt.id', '=', 'p.party_type_id')
            ->join('party_ledger_mappings as plm', function ($join) use ($mappingPurpose) {
                $join->on('plm.party_id', '=', 'd.party_id')
                    ->on('plm.chart_of_account_id', '=', 'd.account_id')
                    ->where('plm.mapping_purpose', '=', $mappingPurpose);
            })
            ->whereNotNull('d.party_id');
    }
}
