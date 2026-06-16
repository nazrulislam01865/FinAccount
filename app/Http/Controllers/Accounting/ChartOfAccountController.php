<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Requests\Accounting\StoreChartOfAccountRequest;
use App\Http\Requests\Accounting\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly ChartOfAccountService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $oldAccountId = (int) $request->old('account_id', '0');

        return view('chart-of-accounts.index', $this->service->pageData(
            $request->user()->company_id,
            $search,
            $oldAccountId,
        ));
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()->route('chart-of-accounts.index')->with('success', 'Record saved');
    }

    public function update(
        UpdateChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount,
    ): RedirectResponse {
        $this->ensureCompany($request, $chartOfAccount);
        $this->service->update($chartOfAccount, $request->validated());

        return redirect()->route('chart-of-accounts.index')->with('success', 'Record saved');
    }

    public function destroy(Request $request, ChartOfAccount $chartOfAccount): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $chartOfAccount);
        $plan = $this->safeDeleteService->inspectChartOfAccount($chartOfAccount);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteChartOfAccount($chartOfAccount),
            'chart-of-accounts.index',
            'Chart of Account deleted permanently. Dependent records were detached and made inactive or incomplete.',
        );
    }

    private function ensureCompany(Request $request, ChartOfAccount $account): void
    {
        abort_unless($account->company_id === $request->user()->company_id, 404);
    }
}
