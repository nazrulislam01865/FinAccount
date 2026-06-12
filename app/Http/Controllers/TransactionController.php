<?php

namespace App\Http\Controllers;

use App\AccountingEngine\Contracts\AccountingEngineContract;
use App\AccountingEngine\DTO\TransactionInput;
use App\Http\Requests\TransactionEntryRequest;
use App\Models\CashBankAccount;
use App\Models\DueRegister;
use App\Models\Party;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Models\VoucherDetail;
use App\Models\VoucherHeader;
use App\Services\Accounting\FinancialYearService;
use App\Services\Accounting\TransactionHeadConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TransactionController extends Controller
{
    public function create(
        Request $request,
        FinancialYearService $financialYearService,
        TransactionHeadConfigurationService $headConfiguration
    ): View {
        $currentFinancialYear = $financialYearService->current($request->user()?->id);

        $companyId = (int) ($request->user()?->company_id ?? 0);

        $transactionHeads = TransactionHead::query()
            ->where('status', 'Active')
            ->where('is_user_selectable', true)
            ->when($companyId > 0, fn ($query) => $query->where(function ($scope) use ($companyId) {
                $scope->where('company_id', $companyId)
                    ->orWhere(function ($global) {
                        $global->whereNull('company_id')->where('is_system_default', true);
                    });
            }))
            ->with([
                'defaultPrimaryLedger.accountType',
                'accountingRules.lines',
                'accountingRules.settlementType',
                'accountingRules.partyType',
                'ledgerMappingRules.settlementType',
                'settlementTypes',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $transactionHeadProfiles = $transactionHeads->mapWithKeys(
            fn (TransactionHead $head) => [$head->id => $headConfiguration->summarize($head)]
        );

        $transactionHeads = $transactionHeads
            ->filter(fn (TransactionHead $head) => (bool) data_get($transactionHeadProfiles, $head->id . '.ready'))
            ->values();

        foreach ($transactionHeads as $head) {
            $head->setRelation(
                'settlementTypes',
                collect(data_get($transactionHeadProfiles, $head->id . '.settlements', []))
            );
        }

        $settlementTypes = SettlementType::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->get();

        $parties = Party::query()
            ->where('status', 'Active')
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->with(['partyType', 'linkedLedger', 'ledgerMappings.ledger'])
            ->orderBy('party_name')
            ->get();

        $cashBankAccounts = CashBankAccount::query()
            ->where('status', 'Active')
            ->with('linkedLedger')
            ->orderBy('cash_bank_name')
            ->get();

        $today = now()->toDateString();
        $defaultVoucherDate = $financialYearService->defaultTransactionDate((int) ($request->user()?->company_id ?? 0));

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
            ->with(['transactionHead', 'party', 'settlementType', 'details'])
            ->latest('id')
            ->limit(3)
            ->get();

        return view('transactions.create', [
            'currentFinancialYear' => $currentFinancialYear,
            'transactionHeads' => $transactionHeads,
            'transactionHeadProfiles' => $transactionHeadProfiles,
            'settlementTypes' => $settlementTypes,
            'parties' => $parties,
            'cashBankAccounts' => $cashBankAccounts,
            'todayCashIn' => $todayCashIn,
            'todayCashOut' => $todayCashOut,
            'duePayable' => $duePayable,
            'dueReceivable' => $dueReceivable,
            'recentTransactions' => $recentTransactions,
            'defaultVoucherDate' => $defaultVoucherDate,
        ]);
    }

    public function preview(
        TransactionEntryRequest $request,
        AccountingEngineContract $engine
    ): JsonResponse {
        try {
            $request->validated();

            $preview = $engine->preview(TransactionInput::fromRequest($request));

            $request->ensureCanUseResolvedVoucherType($preview->voucherType);

            return response()->json([
                'success' => true,
                'data' => $preview->toArray(),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Transaction preview failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->transactionFailureMessage($exception, 'preview'),
            ], 500);
        }
    }

    public function store(
        TransactionEntryRequest $request,
        AccountingEngineContract $engine
    ): JsonResponse {
        try {
            $request->validated();

            $input = TransactionInput::fromRequest($request);
            $precheck = $engine->preview($input);

            $request->ensureCanUseResolvedVoucherType($precheck->voucherType);

            $result = $engine->post($input, $request->file('attachment'));

            return response()->json([
                'success' => true,
                'message' => $result->message(),
                'data' => $result->toArray(),
                'redirect' => Route::has('accounting-reports.transactions.index')
                    ? route('accounting-reports.transactions.index')
                    : route('transactions.create'),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Transaction posting failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->transactionFailureMessage($exception, 'posting'),
            ], 500);
        }
    }

    private function transactionFailureMessage(Throwable $exception, string $stage): string
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);
        $label = $stage === 'preview' ? 'Transaction preview failed' : 'Transaction posting failed';

        if ($exception instanceof QueryException) {
            if (str_contains($lower, 'duplicate') && str_contains($lower, 'voucher')) {
                return $label . ': voucher number already exists. The system will now skip used voucher numbers automatically; clear cache and try again.';
            }

            if (str_contains($lower, 'foreign key constraint')) {
                return $label . ': selected setup data is missing or inactive in cloud. Check Transaction Head, Settlement Type, Accounting Rules Setup, Party, Cash/Bank, and Financial Year setup.';
            }

            if (str_contains($lower, 'data truncated') || str_contains($lower, 'invalid enum') || str_contains($lower, 'incorrect')) {
                return $label . ': cloud database schema is older than the code. Run php artisan migrate --force and try again.';
            }
        }

        if ($message !== '') {
            return $label . ': ' . $message;
        }

        return $label . '. Please check Financial Year, Accounting Rules Setup, Cash/Bank setup, and Voucher Numbering setup.';
    }
}
