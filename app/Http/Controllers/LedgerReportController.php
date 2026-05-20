<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\FinancialYear;
use App\Models\VoucherDetail;
use App\Models\VoucherHeader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LedgerReportController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->with('accountType')
            ->orderBy('account_code')
            ->get();

        $financialYear = FinancialYear::query()
            ->where('status', 'Active')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first();

        $fromDate = $request->query('from_date')
            ?: optional($financialYear?->start_date)->toDateString()
            ?: now()->startOfMonth()->toDateString();

        $toDate = $request->query('to_date') ?: now()->toDateString();

        $account = $accounts->firstWhere('id', (int) $request->query('account_id')) ?: $accounts->first();

        $report = $account
            ? $this->buildReport($account, $fromDate, $toDate)
            : $this->emptyReport();

        return view('reports.ledger', [
            'accounts' => $accounts,
            'account' => $account,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'report' => $report,
        ]);
    }

    private function buildReport(ChartOfAccount $account, string $fromDate, string $toDate): array
    {
        $from = Carbon::parse($fromDate)->toDateString();
        $to = Carbon::parse($toDate)->toDateString();

        $openingDebit = $this->baseLineQuery($account->id)
            ->whereDate('voucher_headers.voucher_date', '<', $from)
            ->sum('voucher_details.debit');

        $openingCredit = $this->baseLineQuery($account->id)
            ->whereDate('voucher_headers.voucher_date', '<', $from)
            ->sum('voucher_details.credit');

        $periodLines = $this->baseLineQuery($account->id)
            ->whereBetween('voucher_headers.voucher_date', [$from, $to])
            ->with([
                'voucherHeader.transactionHead',
                'voucherHeader.settlementType',
                'voucherHeader.party.partyType',
                'party.partyType',
                'account.accountType',
            ])
            ->orderBy('voucher_headers.voucher_date')
            ->orderBy('voucher_details.id')
            ->get();

        $runningBalance = $this->signedBalance($account, (float) $openingDebit, (float) $openingCredit);
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = $periodLines->map(function (VoucherDetail $line) use ($account, &$runningBalance, &$totalDebit, &$totalCredit) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $runningBalance += $this->lineSignedMovement($account, $debit, $credit);

            return [
                'date' => $line->voucherHeader?->voucher_date?->toDateString(),
                'voucher_number' => $line->voucherHeader?->voucher_number,
                'voucher_type' => $line->voucherHeader?->voucher_type,
                'transaction_head' => $line->voucherHeader?->transactionHead?->name,
                'settlement_type' => $line->voucherHeader?->settlementType?->name,
                'party_name' => $line->party?->party_name ?: $line->voucherHeader?->party?->party_name,
                'reference' => $line->voucherHeader?->reference,
                'narration' => $line->narration ?: $line->voucherHeader?->notes,
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

    private function baseLineQuery(int $accountId)
    {
        return VoucherDetail::query()
            ->select('voucher_details.*')
            ->join('voucher_headers', 'voucher_headers.id', '=', 'voucher_details.voucher_header_id')
            ->where('voucher_details.account_id', $accountId)
            ->where('voucher_headers.status', VoucherHeader::STATUS_POSTED);
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
