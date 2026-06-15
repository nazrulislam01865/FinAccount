<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreChartOfAccountRequest;
use App\Http\Requests\Accounting\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountBalanceService $balanceService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $companyId = $request->user()->company_id;

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->get();

        $oldAccountId = (int) $request->old('account_id', 0);
        $modalAccount = $oldAccountId > 0
            ? ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->find($oldAccountId)
            : null;

        return view('chart-of-accounts.index', [
            'accounts' => $accounts,
            'balances' => $this->balanceService->balancesFor($accounts, $companyId),
            'search' => $search,
            'modalAccount' => $modalAccount,
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        ChartOfAccount::query()->create([
            'company_id' => $request->user()->company_id,
            ...$request->validated(),
        ]);

        return redirect()
            ->route('chart-of-accounts.index')
            ->with('success', 'Record saved');
    }

    public function update(
        UpdateChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount,
    ): RedirectResponse {
        $this->ensureCompany($request, $chartOfAccount);
        $chartOfAccount->update($request->validated());

        return redirect()
            ->route('chart-of-accounts.index')
            ->with('success', 'Record saved');
    }

    public function destroy(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->ensureCompany($request, $chartOfAccount);

        $uses = collect([
            'money account' => $chartOfAccount->moneyAccounts()->exists(),
            'party' => $chartOfAccount->receivableParties()->exists() || $chartOfAccount->payableParties()->exists(),
            'transaction head' => $chartOfAccount->transactionHeads()->exists(),
            'journal' => $chartOfAccount->journalLines()->exists(),
        ])->filter()->keys();

        if ($uses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete. Used by '.$uses->implode(', ').'.',
            ]);
        }

        $chartOfAccount->delete();

        return redirect()
            ->route('chart-of-accounts.index')
            ->with('success', 'Record deleted');
    }

    private function ensureCompany(Request $request, ChartOfAccount $account): void
    {
        abort_unless($account->company_id === $request->user()->company_id, 404);
    }
}
