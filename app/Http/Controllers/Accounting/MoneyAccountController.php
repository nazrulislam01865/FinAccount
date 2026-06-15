<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreMoneyAccountRequest;
use App\Http\Requests\Accounting\UpdateMoneyAccountRequest;
use App\Models\MoneyAccount;
use App\Services\Accounting\MoneyAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MoneyAccountController extends Controller
{
    public function __construct(private readonly MoneyAccountService $service) {}

    public function index(Request $request): View
    {
        return view('money-accounts.index', $this->service->pageData($request->user()->company_id));
    }

    public function store(StoreMoneyAccountRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()->route('money-accounts.index')->with('success', 'Record saved');
    }

    public function update(UpdateMoneyAccountRequest $request, MoneyAccount $moneyAccount): RedirectResponse
    {
        $this->ensureCompany($request, $moneyAccount);
        $this->service->update($moneyAccount, $request->validated());

        return redirect()->route('money-accounts.index')->with('success', 'Record saved');
    }

    public function destroy(Request $request, MoneyAccount $moneyAccount): RedirectResponse
    {
        $this->ensureCompany($request, $moneyAccount);
        $this->service->delete($moneyAccount);

        return redirect()->route('money-accounts.index')->with('success', 'Record deleted');
    }

    private function ensureCompany(Request $request, MoneyAccount $moneyAccount): void
    {
        abort_unless($moneyAccount->company_id === $request->user()->company_id, 404);
    }
}
