<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountingRuleRequest;
use App\Http\Requests\Accounting\UpdateAccountingRuleRequest;
use App\Models\AccountingRule;
use App\Services\Accounting\AccountingRuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountingRuleController extends Controller
{
    public function __construct(private readonly AccountingRuleService $service) {}

    public function index(Request $request): View
    {
        return view('accounting-rules.index', [
            'rules' => $this->service->allForCompany($request->user()->company_id),
        ]);
    }

    public function store(StoreAccountingRuleRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()->route('accounting-rules.index')->with('success', 'Record saved');
    }

    public function update(UpdateAccountingRuleRequest $request, AccountingRule $accountingRule): RedirectResponse
    {
        $this->ensureCompany($request, $accountingRule);
        $this->service->update($accountingRule, $request->validated());

        return redirect()->route('accounting-rules.index')->with('success', 'Record saved');
    }

    public function destroy(Request $request, AccountingRule $accountingRule): RedirectResponse
    {
        $this->ensureCompany($request, $accountingRule);
        $this->service->delete($accountingRule);

        return redirect()->route('accounting-rules.index')->with('success', 'Record deleted');
    }

    private function ensureCompany(Request $request, AccountingRule $rule): void
    {
        abort_unless($rule->company_id === $request->user()->company_id, 404);
    }
}
