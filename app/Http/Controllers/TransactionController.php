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
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function create(
        Request $request,
        FinancialYearService $financialYearService
    ): View {
        $currentFinancialYear = $financialYearService->current($request->user()?->id);

        $user = $request->user();

        $transactionHeads = TransactionHead::query()
            ->where('status', 'Active')
            ->with(['settlementTypes' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order')])
            ->orderBy('name')
            ->get()
            ->filter(fn (TransactionHead $head) => $this->transactionHeadVisibleForUser($user, $head))
            ->values();

        $settlementTypes = SettlementType::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->get();

        // Party / Person options must come from the Party setup screen.
        // This keeps transaction entry aligned with the PRD field "Party / Person"
        // and prevents static/demo party names from being used in real vouchers.
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
    private function transactionHeadVisibleForUser(?\App\Models\User $user,
        TransactionHead $head
    ): bool {
        if (!$user) {
            return false;
        }

        if ($user->hasPermission('transactions.draft') && !$user->hasPermission('transactions.create')) {
            return true;
        }

        if (!$user->hasPermission('transactions.create')) {
            return false;
        }

        $settlements = $head->settlementTypes->isNotEmpty()
            ? $head->settlementTypes
            : collect([null]);

        foreach ($settlements as $settlement) {
            $permissions = $this->requiredTransactionPermissions($head, $settlement);
            $allowed = true;

            foreach ($permissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    $allowed = false;
                    break;
                }
            }

            if ($allowed) {
                return true;
            }
        }

        return false;
    }

    private function requiredTransactionPermissions(TransactionHead $head, ?\App\Models\SettlementType $settlement = null): array
    {
        $permissions = [];
        $nature = trim((string) $head->nature);
        $headText = strtoupper(trim($head->name.' '.$head->nature.' '.($settlement?->name ?? '').' '.($settlement?->code ?? '')));
        $settlementKey = $this->settlementKey($settlement);

        $naturePermission = config('access.transaction_type_permissions.'.$nature);
        if ($naturePermission) {
            $permissions[] = $naturePermission;
        }

        if ($nature === 'Expense') {
            if ($settlementKey === 'due') {
                $permissions[] = 'transactions.purchase.create';
            } elseif (in_array($settlementKey, ['cash', 'bank', 'advance_paid', 'advance_received'], true)) {
                $permissions[] = 'transactions.payment.create';
            }
        }

        if ($nature === 'Receipt' && ($settlementKey === 'due' || str_contains($headText, 'SALE') || str_contains($headText, 'INVOICE'))) {
            $permissions[] = 'transactions.sales.create';
        }

        if (str_contains($headText, 'SUPPLIER') || str_contains($headText, 'PURCHASE') || str_contains($headText, 'VENDOR')) {
            if ($nature === 'Payment') {
                $permissions[] = 'transactions.payment.create';
            } elseif ($settlementKey === 'due') {
                $permissions[] = 'transactions.purchase.create';
            }
        }

        return array_values(array_unique($permissions));
    }

    private function settlementKey(?\App\Models\SettlementType $settlement): string
    {
        $code = strtoupper((string) $settlement?->code);
        $name = strtoupper((string) $settlement?->name);
        $value = $code . ' ' . $name;

        return match (true) {
            str_contains($value, 'ADVANCE_PAID') || str_contains($value, 'ADVANCE PAID') => 'advance_paid',
            str_contains($value, 'ADVANCE_RECEIVED') || str_contains($value, 'ADVANCE RECEIVED') => 'advance_received',
            str_contains($value, 'CASH') => 'cash',
            str_contains($value, 'BANK') => 'bank',
            str_contains($value, 'DUE') => 'due',
            str_contains($value, 'ADJUST') => 'adjustment',
            default => 'other',
        };
    }

}
