<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\VoucherHeader;
use App\Services\Accounting\FinancialYearService;
use App\Services\Setup\OpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function index(
        Request $request,
        FinancialYearService $financialYearService
    ): View {
        $company = Company::query()->first();

        $financialYears = FinancialYear::query()
            ->where('status', 'Active')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $currentFinancialYear = $request->filled('financial_year_id')
            ? $financialYears->firstWhere('id', (int) $request->input('financial_year_id'))
            : $financialYearService->current($request->user()?->id);

        $currentFinancialYear ??= $financialYears->first();

        $branchLocation = $request->input('branch_location', $company?->default_branch ?: 'Head Office');
        $balanceDate = $request->input('balance_date')
            ?: $currentFinancialYear?->start_date?->toDateString();

        $accounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->with('accountType')
            ->orderBy('account_code')
            ->get();

        $parties = Party::query()
            ->where('status', 'Active')
            ->with(['partyType', 'linkedLedger.accountType'])
            ->orderBy('party_name')
            ->get();

        $cashBankOpeningBalances = CashBankAccount::query()
            ->where('status', 'Active')
            ->selectRaw('linked_ledger_account_id, SUM(opening_balance) as opening_balance')
            ->groupBy('linked_ledger_account_id')
            ->pluck('opening_balance', 'linked_ledger_account_id');

        $openingBalances = collect();
        $openingIsFinal = false;
        $postedOpeningVoucher = null;

        if ($currentFinancialYear) {
            $openingBalances = OpeningBalance::query()
                ->with(['account.accountType', 'party.partyType'])
                ->where('financial_year_id', $currentFinancialYear->id)
                ->where(function ($query) use ($branchLocation) {
                    if ($branchLocation === null || $branchLocation === '') {
                        $query->whereNull('branch_location');
                    } else {
                        $query->where('branch_location', $branchLocation);
                    }
                })
                ->orderBy('id')
                ->get();

            $openingIsFinal = $openingBalances->contains(fn (OpeningBalance $balance) => $balance->status === 'Final');

            $postedOpeningVoucher = VoucherHeader::query()
                ->with(['details.account.accountType', 'details.party'])
                ->where('financial_year_id', $currentFinancialYear->id)
                ->where('voucher_type', 'Opening Voucher')
                ->where('status', VoucherHeader::STATUS_POSTED)
                ->where('reference', $branchLocation)
                ->latest('id')
                ->first();
        }

        $seedOpeningRows = $this->seedOpeningRows(
            $accounts,
            $parties,
            $cashBankOpeningBalances
        );

        return view('setup.opening-balances', [
            'currentFinancialYear' => $currentFinancialYear,
            'financialYears' => $financialYears,
            'branchLocation' => $branchLocation,
            'balanceDate' => $balanceDate,
            'branches' => array_values(array_unique(array_filter([
                $company?->default_branch ?: 'Head Office',
                'Head Office',
            ]))),
            'accounts' => $accounts,
            'parties' => $parties,
            'openingBalances' => $openingBalances,
            'openingIsFinal' => $openingIsFinal,
            'postedOpeningVoucher' => $postedOpeningVoucher,
            'seedOpeningRows' => $seedOpeningRows,
        ]);
    }

    public function store(
        OpeningBalanceRequest $request,
        OpeningBalanceService $service
    ): JsonResponse {
        $result = $service->save(
            $request->validated(),
            $request->user()?->id
        );

        $isFinal = $request->input('status') === 'Final';
        $voucherNumber = $result['voucher']?->voucher_number;

        return response()->json([
            'success' => true,
            'message' => $isFinal
                ? 'Opening balance posted successfully' . ($voucherNumber ? " as {$voucherNumber}." : '.')
                : 'Opening balance draft saved.',
            'data' => $result,
            'redirect' => route('setup.opening-balances', [
                'financial_year_id' => $request->input('financial_year_id'),
                'branch_location' => $request->input('branch_location'),
            ]),
        ], 201);
    }

    private function seedOpeningRows($accounts, $parties, $cashBankOpeningBalances)
    {
        $partiesByAccount = $parties->groupBy('linked_ledger_account_id');

        return $accounts->flatMap(function (ChartOfAccount $account) use ($partiesByAccount, $cashBankOpeningBalances) {
            $linkedParties = $partiesByAccount->get($account->id, collect());

            if ($linkedParties->isNotEmpty()) {
                return $linkedParties->map(function (Party $party) use ($account) {
                    $amount = $this->amount($party->opening_balance ?? 0);
                    $side = $party->opening_balance_type
                        ?: $this->openingSideForParty($party, $account)
                        ?: $account->normal_balance
                        ?: $account->accountType?->normal_balance
                        ?: 'Debit';

                    return [
                        'account' => $account,
                        'party_id' => $party->id,
                        'debit_opening' => $side === 'Debit' ? $amount : 0,
                        'credit_opening' => $side === 'Credit' ? $amount : 0,
                        'remarks' => $amount > 0 ? 'Auto-loaded from Party / Person setup' : null,
                    ];
                });
            }

            $amount = $this->amount($cashBankOpeningBalances[$account->id] ?? $account->opening_balance ?? 0);
            $normalBalance = $account->normal_balance ?: $account->accountType?->normal_balance ?: 'Debit';

            return [[
                'account' => $account,
                'party_id' => null,
                'debit_opening' => $normalBalance === 'Debit' ? $amount : 0,
                'credit_opening' => $normalBalance === 'Credit' ? $amount : 0,
                'remarks' => $amount > 0 ? 'Auto-loaded from previous setup' : null,
            ]];
        })->values();
    }

    private function openingSideForParty(Party $party, ChartOfAccount $account): ?string
    {
        $nature = $party->default_ledger_nature;

        if (in_array($nature, ['Receivable', 'Advance Paid'], true)) {
            return 'Debit';
        }

        if (in_array($nature, ['Payable', 'Advance Received'], true)) {
            return 'Credit';
        }

        return $account->normal_balance ?: $account->accountType?->normal_balance;
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }
}