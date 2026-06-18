<?php

namespace App\Http\Controllers\Accounting\Company;

use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreFinancialYearRequest;
use App\Http\Requests\Company\UpdateFinancialYearRequest;
use App\Models\FinancialYear;
use App\Services\Company\CompanyMasterDeletionService;
use App\Services\Company\FinancialYearService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinancialYearController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly FinancialYearService $service,
        private readonly CompanyMasterDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData((int) $request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('financial_years.view');
        if ($data['addOnlyMode']) {
            $data['financialYears'] = collect();
            $data['transactionUsage'] = [];
        }
        return view('company-masters.financial-years.index', $data);
    }

    public function store(StoreFinancialYearRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), (int) $request->user()->company_id, $request->user());
        return $this->redirectAfterAccountingSave($request, 'financial_years.view', 'master.financial-years.index', 'Financial Year saved');
    }

    public function update(UpdateFinancialYearRequest $request, FinancialYear $financialYear): RedirectResponse
    {
        $this->ensureCompany($request, $financialYear->company_id);
        $this->service->update($financialYear, $request->validated(), $request->user());
        return $this->redirectAfterAccountingSave($request, 'financial_years.view', 'master.financial-years.index', 'Financial Year updated');
    }

    public function destroy(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $financialYear->company_id);
        return $this->performSafeDelete(
            $request,
            $this->deletionService->inspectFinancialYear($financialYear),
            fn () => $this->deletionService->deleteFinancialYear($financialYear),
            'master.financial-years.index',
            'Financial Year deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, int $companyId): void
    {
        abort_unless($companyId === (int) $request->user()->company_id, 404);
    }
}
