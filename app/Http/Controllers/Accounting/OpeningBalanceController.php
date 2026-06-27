<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StoreOpeningBalanceRequest;
use App\Models\OpeningBalance;
use App\Services\Accounting\OpeningBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OpeningBalanceController extends Controller
{
    use RedirectsByAccountingAccess;

    public function __construct(private readonly OpeningBalanceService $service) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData($request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('opening_balances.view');

        if ($data['addOnlyMode']) {
            $data['openingBalances'] = collect();
        }

        return view('opening-balances.index', $data);
    }

    public function store(StoreOpeningBalanceRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user());

        return $this->redirectAfterAccountingSave($request, 'opening_balances.view', 'opening-balances.index', 'Opening balance saved');
    }

    public function update(StoreOpeningBalanceRequest $request, OpeningBalance $openingBalance): RedirectResponse
    {
        $this->ensureCompany($request, $openingBalance);
        $this->service->update($openingBalance, $request->validated(), $request->user());

        return $this->redirectAfterAccountingSave($request, 'opening_balances.view', 'opening-balances.index', 'Opening balance saved');
    }

    public function destroy(Request $request, OpeningBalance $openingBalance): RedirectResponse
    {
        abort_unless($request->user()?->canAccounting('opening_balances.manage'), 403);
        $this->ensureCompany($request, $openingBalance);
        $this->service->delete($openingBalance);

        return redirect()->route('opening-balances.index')->with('success', 'Opening balance deleted.');
    }

    private function ensureCompany(Request $request, OpeningBalance $openingBalance): void
    {
        abort_unless((int) $openingBalance->company_id === (int) $request->user()->company_id, 404);
    }
}
