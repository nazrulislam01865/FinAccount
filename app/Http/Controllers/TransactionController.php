<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionEntryRequest;
use App\Models\CashBankAccount;
use App\Models\DueRegister;
use App\Models\Party;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherDetail;
use App\Models\VoucherHeader;
use App\Models\VoucherNumberingRule;
use App\Services\Accounting\FinancialYearService;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function create(
        Request $request,
        FinancialYearService $financialYearService
    ): View {
        $currentFinancialYear = $financialYearService->current($request->user()?->id);

        $transactionHeads = TransactionHead::query()
            ->where('status', 'Active')
            ->with(['settlementTypes' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order')])
            ->orderBy('name')
            ->get();

        $settlementTypes = SettlementType::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->get();

        $parties = Party::query()
            ->where('status', 'Active')
            ->with(['partyType', 'linkedLedger'])
            ->orderBy('party_name')
            ->get();

        $cashBankAccounts = CashBankAccount::query()
            ->where('status', 'Active')
            ->with('linkedLedger')
            ->orderBy('cash_bank_name')
            ->get();

        $voucherTypes = VoucherNumberingRule::query()
            ->where('status', 'Active')
            ->when($currentFinancialYear, fn ($query) => $query->where('financial_year_id', $currentFinancialYear->id))
            ->orderBy('voucher_type')
            ->pluck('voucher_type')
            ->unique()
            ->values();

        $today = now()->toDateString();

        $cashBankLedgerIds = $cashBankAccounts
            ->pluck('linked_ledger_account_id')
            ->filter()
            ->unique()
            ->values();

        $todayCashIn = VoucherDetail::query()
            ->whereIn('account_id', $cashBankLedgerIds)
            ->whereHas('voucherHeader', fn ($query) => $query
                ->whereDate('voucher_date', $today)
                ->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('debit');

        $todayCashOut = VoucherDetail::query()
            ->whereIn('account_id', $cashBankLedgerIds)
            ->whereHas('voucherHeader', fn ($query) => $query
                ->whereDate('voucher_date', $today)
                ->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('credit');

        $duePayable = DueRegister::query()
            ->where('due_type', 'Payable')
            ->sum('balance_effect');

        $dueReceivable = DueRegister::query()
            ->where('due_type', 'Receivable')
            ->sum('balance_effect');

        $recentTransactions = VoucherHeader::query()
            ->with(['transactionHead', 'party', 'settlementType'])
            ->latest('id')
            ->limit(3)
            ->get();

        return view('transactions.create', [
            'currentFinancialYear' => $currentFinancialYear,
            'transactionHeads' => $transactionHeads,
            'settlementTypes' => $settlementTypes,
            'parties' => $parties,
            'cashBankAccounts' => $cashBankAccounts,
            'voucherTypes' => $voucherTypes,
            'todayCashIn' => $todayCashIn,
            'todayCashOut' => $todayCashOut,
            'duePayable' => $duePayable,
            'dueReceivable' => $dueReceivable,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function preview(
        TransactionEntryRequest $request,
        TransactionPostingService $service
    ): JsonResponse {
        $preview = $service->preview(
            $request->validated(),
            $request->user()?->id,
            $request->input('status') === VoucherHeader::STATUS_DRAFT
        );

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    public function store(
        TransactionEntryRequest $request,
        TransactionPostingService $service
    ): JsonResponse {
        $voucher = $service->save(
            $request->validated(),
            $request->file('attachment'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => $voucher->status === VoucherHeader::STATUS_POSTED
                ? 'Transaction posted successfully.'
                : 'Transaction saved as draft.',
            'data' => [
                'id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'status' => $voucher->status,
            ],
            'redirect' => route('transactions.create'),
        ], 201);
    }
}
