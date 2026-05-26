<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualJournalRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Party;
use App\Services\Accounting\FinancialYearService;
use App\Services\Accounting\ManualJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ManualJournalController extends Controller
{
    public function index(Request $request, FinancialYearService $financialYearService): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $currentFinancialYear = $financialYearService->current($request->user()?->id);

        $ledgers = ChartOfAccount::query()
            ->postingLedgers()
            ->with(['accountType', 'partyType'])
            ->orderBy('account_code')
            ->get();

        $parties = Party::query()
            ->where('status', 'Active')
            ->with('partyType')
            ->orderBy('party_name')
            ->get();

        $recentJournals = JournalHeader::query()
            ->with(['voucherHeader', 'lines.ledger', 'lines.party'])
            ->when($companyId > 0, fn ($query) => $query->where(function ($where) use ($companyId) {
                $where->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->where(function ($query) {
                $query->where('source_type', 'Manual Journal')
                    ->orWhere('voucher_type', 'Journal Voucher');
            })
            ->latest('id')
            ->limit(10)
            ->get();

        return view('manual-journals.index', [
            'currentFinancialYear' => $currentFinancialYear,
            'defaultJournalDate' => $financialYearService->defaultTransactionDate($companyId),
            'ledgers' => $ledgers,
            'parties' => $parties,
            'recentJournals' => $recentJournals,
        ]);
    }

    public function store(ManualJournalRequest $request, ManualJournalService $service): JsonResponse
    {
        try {
            $voucher = $service->post($request->validated(), $request->user()?->id);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Manual journal posting failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Manual journal posting failed: ' . $exception->getMessage()
                    : 'Manual journal posting failed. Please check Financial Year, posting ledgers, party requirement, and voucher numbering setup.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $voucher->status === 'Draft'
                ? 'Manual journal saved as draft.'
                : 'Manual journal posted successfully as ' . $voucher->voucher_number . '.',
            'data' => [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'journal_no' => $voucher->journalHeader?->journal_no,
                'total_debit' => $voucher->total_debit,
                'total_credit' => $voucher->total_credit,
            ],
            'redirect' => route('manual-journals.index'),
        ], 201);
    }
}
