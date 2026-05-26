<?php

namespace App\AccountingReports\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CashFlowStatementReportService extends BaseVoucherDetailReportService
{
    public function build(array $filters = []): array
    {
        $companyId = ! empty($filters['company_id']) ? (int) $filters['company_id'] : null;
        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $section = $filters['section'] ?? 'All';

        $opening = (float) $this->cashLinesQuery($companyId, null, date('Y-m-d', strtotime($fromDate . ' -1 day')))
            ->selectRaw('COALESCE(SUM(d.debit),0) - COALESCE(SUM(d.credit),0) AS balance')
            ->value('balance');

        $rows = $this->cashLinesQuery($companyId, $fromDate, $toDate)
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->selectRaw('v.id AS voucher_id')
            ->selectRaw('v.voucher_date')
            ->selectRaw('v.voucher_number')
            ->selectRaw('v.reference')
            ->selectRaw('v.notes')
            ->selectRaw('th.name AS transaction_head')
            ->selectRaw('a.account_code AS cash_account_code')
            ->selectRaw('a.account_name AS cash_account_name')
            ->selectRaw('d.debit AS cash_inflow')
            ->selectRaw('d.credit AS cash_outflow')
            ->selectRaw('(d.debit - d.credit) AS net_cash_flow')
            ->get()
            ->map(function (object $row) {
                $row->section = $this->sectionForVoucher((int) $row->voucher_id);
                return $row;
            })
            ->when($section !== 'All', fn ($collection) => $collection->filter(fn (object $row) => $row->section === $section)->values());

        $operating = round((float) $rows->where('section', 'Operating Activities')->sum('net_cash_flow'), 2);
        $investing = round((float) $rows->where('section', 'Investing Activities')->sum('net_cash_flow'), 2);
        $financing = round((float) $rows->where('section', 'Financing Activities')->sum('net_cash_flow'), 2);
        $netMovement = round($operating + $investing + $financing, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'opening_cash' => round($opening, 2),
            'operating_cash_flow' => $operating,
            'investing_cash_flow' => $investing,
            'financing_cash_flow' => $financing,
            'net_cash_flow' => $netMovement,
            'closing_cash' => round($opening + $netMovement, 2),
            'rows' => $rows,
            'groups' => $rows->groupBy('section'),
        ];
    }

    private function cashLinesQuery(?int $companyId, ?string $fromDate, ?string $toDate): Builder
    {
        $query = $this->basePostedLinesQuery($companyId, $fromDate, $toDate)
            ->leftJoin('transaction_heads as th', 'th.id', '=', 'v.transaction_head_id')
            ->where(function (Builder $where) {
                $where->where('a.is_cash_bank', 1)
                    ->orWhereIn('a.ledger_type', ['Cash', 'Bank']);
            });

        return $query;
    }

    private function sectionForVoucher(int $voucherId): string
    {
        $contraTypes = $this->basePostedLinesQuery(null, null, null)
            ->where('d.voucher_header_id', $voucherId)
            ->where(function (Builder $where) {
                $where->where('a.is_cash_bank', '<>', 1)
                    ->orWhereNull('a.is_cash_bank');
            })
            ->pluck(DB::raw("COALESCE(at.name, a.ledger_type, '')"))
            ->filter()
            ->values();

        if ($contraTypes->contains(fn ($type) => in_array($type, ['Liability', 'Equity', 'Loan'], true))) {
            return 'Financing Activities';
        }

        if ($contraTypes->contains(fn ($type) => $type === 'Asset')) {
            return 'Investing Activities';
        }

        return 'Operating Activities';
    }
}
