<?php

namespace App\Http\Controllers\Accounting\Company;

use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreTimeZoneRequest;
use App\Http\Requests\Company\UpdateTimeZoneRequest;
use App\Models\TimeZone;
use App\Services\Company\CompanyMasterDeletionService;
use App\Services\Company\TimeZoneService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeZoneController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly TimeZoneService $service,
        private readonly CompanyMasterDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData((int) $request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('time_zones.view');
        if ($data['addOnlyMode']) {
            $data['timeZones'] = collect();
            $data['usage'] = [];
        }
        return view('company-masters.time-zones.index', $data);
    }

    public function store(StoreTimeZoneRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), (int) $request->user()->company_id);
        return $this->redirectAfterAccountingSave($request, 'time_zones.view', 'master.time-zones.index', 'Time Zone saved');
    }

    public function update(UpdateTimeZoneRequest $request, TimeZone $timeZone): RedirectResponse
    {
        $this->ensureCompany($request, $timeZone->company_id);
        $this->service->update($timeZone, $request->validated());
        return $this->redirectAfterAccountingSave($request, 'time_zones.view', 'master.time-zones.index', 'Time Zone updated');
    }

    public function destroy(Request $request, TimeZone $timeZone): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $timeZone->company_id);
        return $this->performSafeDelete(
            $request,
            $this->deletionService->inspectTimeZone($timeZone),
            fn () => $this->deletionService->deleteTimeZone($timeZone),
            'master.time-zones.index',
            'Time Zone deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, int $companyId): void
    {
        abort_unless($companyId === (int) $request->user()->company_id, 404);
    }
}
