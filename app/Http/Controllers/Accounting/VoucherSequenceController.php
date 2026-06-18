<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
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
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly VoucherSequenceService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData($request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('voucher_numbering.view');
        if ($data['addOnlyMode']) {
            $data['sequences'] = collect();
        }

        return view('master-data.voucher-sequences', $data);
    }

    public function store(StoreVoucherSequenceRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return $this->redirectAfterAccountingSave(
            $request,
            'voucher_numbering.view',
            'master.voucher-sequences.index',
            'Voucher numbering saved',
        );
    }

    public function update(
        UpdateVoucherSequenceRequest $request,
        DocumentSequence $documentSequence,
    ): RedirectResponse {
        $this->service->update($documentSequence, $request->validated(), $request->user()->company_id);

        return $this->redirectAfterAccountingSave($request, 'voucher_numbering.view', 'master.voucher-sequences.index', 'Voucher numbering updated');
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
