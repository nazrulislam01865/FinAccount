<?php

namespace App\Http\Controllers\Accounting\Company;

use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCurrencyRequest;
use App\Http\Requests\Company\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Services\Company\CompanyMasterDeletionService;
use App\Services\Company\CurrencyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly CurrencyService $service,
        private readonly CompanyMasterDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData((int) $request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('currencies.view');
        if ($data['addOnlyMode']) {
            $data['currencies'] = collect();
            $data['usage'] = [];
        }
        return view('company-masters.currencies.index', $data);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), (int) $request->user()->company_id);
        return $this->redirectAfterAccountingSave($request, 'currencies.view', 'master.currencies.index', 'Currency saved');
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->ensureCompany($request, $currency->company_id);
        $this->service->update($currency, $request->validated());
        return $this->redirectAfterAccountingSave($request, 'currencies.view', 'master.currencies.index', 'Currency updated');
    }

    public function destroy(Request $request, Currency $currency): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $currency->company_id);
        return $this->performSafeDelete(
            $request,
            $this->deletionService->inspectCurrency($currency),
            fn () => $this->deletionService->deleteCurrency($currency),
            'master.currencies.index',
            'Currency deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, int $companyId): void
    {
        abort_unless($companyId === (int) $request->user()->company_id, 404);
    }
}
