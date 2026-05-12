<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningBalanceRequest;
use App\Models\ChartOfAccount;
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
        $currentFinancialYear = $financialYearService->current($request->user()?->id);

        $branchLocation = $request->input('branch_location', 'Head Office (Dhaka)');

        $accounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->with('accountType')
            ->orderBy('account_code')
            ->get();

        $parties = Party::query()
            ->where('status', 'Active')
            ->with(['partyType', 'linkedLedger.accountType'])
            ->orderBy('party_name')
            ->get();

        $openingBalances = collect();

        if ($currentFinancialYear) {
            $openingBalances = OpeningBalance::query()
                ->with(['account.accountType', 'party'])
                ->where('financial_year_id', $currentFinancialYear->id)
                ->where('branch_location', $branchLocation)
                ->orderBy('id')
                ->get();
        }

        return view('setup.opening-balances', [
            'currentFinancialYear' => $currentFinancialYear,
            'branchLocation' => $branchLocation,
            'branches' => ['Head Office (Dhaka)', 'Chattogram Branch'],
            'accounts' => $accounts,
            'parties' => $parties,
            'openingBalances' => $openingBalances,
        ]);
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
