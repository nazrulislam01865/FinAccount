<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Models\Party;
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
        $financialYears = FinancialYear::query()
            ->where('status', 'Active')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $currentFinancialYear = $request->filled('financial_year_id')
            ? $financialYears->firstWhere('id', (int) $request->input('financial_year_id'))
            : $financialYearService->current($request->user()?->id);

        $currentFinancialYear ??= $financialYears->first();

        $branchLocation = $request->input('branch_location', 'Head Office (Dhaka)');

        $accounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->with('accountType')
            ->where('posting_allowed', true)
            ->withCount(['children as active_children_count' => function ($query) {
                $query->where('status', 'Active');
            }])
            ->orderBy('account_code')
            ->get();

        $postingAccounts = $accounts
            ->where('active_children_count', 0)
            ->values();

        if ($postingAccounts->isEmpty()) {
            $postingAccounts = $accounts->values();
        }

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

        if ($currentFinancialYear) {
            $openingBalances = OpeningBalance::query()
                ->with(['account.accountType', 'party'])
                ->where('financial_year_id', $currentFinancialYear->id)
                ->where('branch_location', $branchLocation)
                ->orderBy('id')
                ->get();
        }

        $seedOpeningRows = $this->seedOpeningRows(
            $postingAccounts,
            $parties,
            $cashBankOpeningBalances
        );

        return view('setup.opening-balances', [
            'currentFinancialYear' => $currentFinancialYear,
            'financialYears' => $financialYears,
            'branchLocation' => $branchLocation,
            'branches' => ['Head Office (Dhaka)', 'Chattogram Branch'],
            'accounts' => $postingAccounts,
            'parties' => $parties,
            'openingBalances' => $openingBalances,
            'seedOpeningRows' => $seedOpeningRows,
        ]);
    }



    private function seedOpeningRows($accounts, $parties, $cashBankOpeningBalances)
    {
        $partiesByAccount = $parties->groupBy('linked_ledger_account_id');

        return $accounts->flatMap(function (ChartOfAccount $account) use ($partiesByAccount, $cashBankOpeningBalances) {
            $linkedParties = $partiesByAccount->get($account->id, collect());

            if ($linkedParties->isNotEmpty()) {
                return $linkedParties->map(function (Party $party) use ($account) {
                    $amount = $this->amount($party->opening_balance ?? 0);
                    $side = $party->opening_balance_type ?: $account->normal_balance ?: $account->accountType?->normal_balance ?: 'Debit';

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

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }


    public function store(
        OpeningBalanceRequest $request,
        OpeningBalanceService $service
    ): JsonResponse {
        $service->save(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => $request->input('status') === 'Final'
                ? 'Opening balance setup saved and completed.'
                : 'Opening balance draft saved.',
            'redirect' => route('setup.opening-balances', [
                'branch_location' => $request->input('branch_location'),
            ]),
        ], 201);
    }
}
