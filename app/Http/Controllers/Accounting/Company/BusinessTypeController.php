<?php

namespace App\Http\Controllers\Accounting\Company;

use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreBusinessTypeRequest;
use App\Http\Requests\Company\UpdateBusinessTypeRequest;
use App\Models\BusinessType;
use App\Services\Company\BusinessTypeService;
use App\Services\Company\CompanyMasterDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessTypeController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly BusinessTypeService $service,
        private readonly CompanyMasterDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData((int) $request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('business_types.view');
        if ($data['addOnlyMode']) {
            $data['businessTypes'] = collect();
            $data['usage'] = [];
        }

        return view('company-masters.business-types.index', $data);
    }

    public function store(StoreBusinessTypeRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), (int) $request->user()->company_id);
        return $this->redirectAfterAccountingSave($request, 'business_types.view', 'master.business-types.index', 'Business Type saved');
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $this->ensureCompany($request, $businessType->company_id);
        $this->service->update($businessType, $request->validated());
        return $this->redirectAfterAccountingSave($request, 'business_types.view', 'master.business-types.index', 'Business Type updated');
    }

    public function destroy(Request $request, BusinessType $businessType): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $businessType->company_id);
        return $this->performSafeDelete(
            $request,
            $this->deletionService->inspectBusinessType($businessType),
            fn () => $this->deletionService->deleteBusinessType($businessType),
            'master.business-types.index',
            'Business Type deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, int $companyId): void
    {
        abort_unless($companyId === (int) $request->user()->company_id, 404);
    }
}
