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
use App\Services\Accounting\FinancialYearService;
use App\Services\Accounting\TransactionPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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
        try {
            $preview = $service->preview(
                $request->validated(),
                $request->user()?->id,
                $request->input('status') === VoucherHeader::STATUS_DRAFT
            );

            $request->ensureCanUseResolvedVoucherType($preview['voucher_type'] ?? null);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Transaction preview failed.', [
                'message' => $exception->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction preview failed. Please check Financial Year, Ledger Mapping, and Voucher Numbering setup.',
            ], 500);
        }
    }

    public function store(
        TransactionEntryRequest $request,
        TransactionPostingService $service
    ): JsonResponse {
        try {
            $validated = $request->validated();
            $draft = ($validated['status'] ?? VoucherHeader::STATUS_POSTED) === VoucherHeader::STATUS_DRAFT;

            $precheck = $service->preview(
                $validated,
                $request->user()?->id,
                $draft
            );

            $request->ensureCanUseResolvedVoucherType($precheck['voucher_type'] ?? null);

            $voucher = $service->save(
                $validated,
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
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Transaction posting failed.', [
                'message' => $exception->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction posting failed. Please check Financial Year, Ledger Mapping, and Voucher Numbering setup.',
            ], 500);
        }
    }
}
