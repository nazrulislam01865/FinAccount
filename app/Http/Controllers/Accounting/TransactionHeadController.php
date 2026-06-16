<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Requests\Accounting\StoreTransactionHeadRequest;
use App\Http\Requests\Accounting\UpdateTransactionHeadRequest;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionHeadService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionHeadController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly TransactionHeadService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        return view('transaction-heads.index', $this->service->pageData($request->user()->company_id));
    }

    public function store(StoreTransactionHeadRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()->route('transaction-heads.index')->with('success', 'Record saved');
    }

    public function update(UpdateTransactionHeadRequest $request, TransactionHead $transactionHead): RedirectResponse
    {
        $this->ensureCompany($request, $transactionHead);
        $this->service->update($transactionHead, $request->validated());

        return redirect()->route('transaction-heads.index')->with('success', 'Record saved');
    }

    public function destroy(Request $request, TransactionHead $transactionHead): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $transactionHead);
        $plan = $this->safeDeleteService->inspectTransactionHead($transactionHead);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteTransactionHead($transactionHead),
            'transaction-heads.index',
            'Transaction Head deleted permanently. Dependent transactions were detached and made incomplete.',
        );
    }

    private function ensureCompany(Request $request, TransactionHead $head): void
    {
        abort_unless($head->company_id === $request->user()->company_id, 404);
    }
}
