<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Requests\Accounting\StoreVoucherSequenceRequest;
use App\Http\Requests\Accounting\UpdateVoucherSequenceRequest;
use App\Models\DocumentSequence;
use App\Services\Accounting\VoucherSequenceService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoucherSequenceController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly VoucherSequenceService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        return view('master-data.voucher-sequences', $this->service->pageData($request->user()->company_id));
    }

    public function store(StoreVoucherSequenceRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()
            ->route('master.voucher-sequences.index')
            ->with('success', 'Voucher numbering saved');
    }

    public function update(
        UpdateVoucherSequenceRequest $request,
        DocumentSequence $documentSequence,
    ): RedirectResponse {
        $this->service->update($documentSequence, $request->validated(), $request->user()->company_id);

        return redirect()->route('master.voucher-sequences.index')->with('success', 'Voucher numbering updated');
    }
    public function destroy(Request $request, DocumentSequence $documentSequence): JsonResponse|RedirectResponse
    {
        abort_unless($documentSequence->company_id === $request->user()->company_id, 404);
        $plan = $this->safeDeleteService->inspectVoucherSequence($documentSequence);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteVoucherSequence($documentSequence),
            'master.voucher-sequences.index',
            'Voucher numbering deleted permanently.',
        );
    }
}
