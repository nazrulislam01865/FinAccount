<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesBulkSetupActions;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StoreTransactionHeadRequest;
use App\Http\Requests\Accounting\UpdateTransactionHeadRequest;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionHeadService;
use App\Services\Accounting\AccountingSetupExportService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionHeadController extends Controller
{
    use HandlesBulkSetupActions, PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly TransactionHeadService $service,
        private readonly SafeDeleteService $safeDeleteService,
        private readonly AccountingSetupExportService $exportService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData($request->user()->company_id, $request->only([
            'search',
            'transaction_type',
            'coa_type',
            'party_type',
            'accounting_rule',
        ]));
        $data['addOnlyMode'] = ! $request->user()->canAccounting('transaction_heads.view');
        if ($data['addOnlyMode']) {
            $data['transactionHeads'] = collect();
        }

        return view('transaction-heads.index', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        return $this->exportService->transactionHeads(
            (int) $request->user()->company_id,
            (string) ($request->user()->company?->name ?? 'Company'),
        );
    }

    public function store(StoreTransactionHeadRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return $this->redirectAfterAccountingSave($request, 'transaction_heads.view', 'transaction-heads.index', 'Record saved');
    }

    public function update(UpdateTransactionHeadRequest $request, TransactionHead $transactionHead): RedirectResponse
    {
        $this->ensureCompany($request, $transactionHead);
        $this->service->update($transactionHead, $request->validated());

        return $this->redirectAfterAccountingSave($request, 'transaction_heads.view', 'transaction-heads.index', 'Record saved');
    }

    public function bulkAction(Request $request): JsonResponse|RedirectResponse
    {
        [$action, $heads] = $this->resolveBulkCompanyRecords(
            $request,
            TransactionHead::class,
            'Transaction Head',
            ['postingAccount'],
        );

        if ($action === 'delete') {
            $this->ensureBulkDeletePermission($request);

            // Let a normal form submit delete records too when the safe-delete
            // JavaScript/modal is stale or did not load. Preview requests still
            // return the dependency plan and are not auto-confirmed.
            if (! $request->boolean('preview') && ! $request->boolean('confirmed')) {
                $request->merge(['confirmed' => true]);
            }

            $plan = $this->buildBulkDeletionPlan(
                $heads,
                fn (TransactionHead $head) => $this->safeDeleteService->inspectTransactionHead($head),
                'Transaction Head Bulk Delete',
                'Transaction Head',
                'The selected transaction heads will be permanently deleted from the database.',
                'Transactions using the selected heads will lose their head link and will be marked incomplete. Continue only when these records are no longer needed.',
            );
            $count = $heads->count();

            return $this->performSafeDelete(
                $request,
                $plan,
                function () use ($heads): void {
                    DB::transaction(function () use ($heads): void {
                        foreach ($heads as $head) {
                            $this->safeDeleteService->deleteTransactionHead($head);
                        }
                    }, attempts: 5);
                },
                'transaction-heads.index',
                number_format($count).' Transaction Head '.($count === 1 ? 'record' : 'records').' deleted permanently.',
            );
        }

        $activate = $action === 'activate';
        $changed = $this->service->setActive($heads, $activate);
        $count = $heads->count();
        $state = $activate ? 'active' : 'inactive';
        $message = number_format($count).' Transaction Head '.($count === 1 ? 'record was' : 'records were').' set to '.$state.'.';

        if ($changed === 0) {
            $message = 'No changes were needed. All selected Transaction Head records were already '.$state.'.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'changed_count' => $changed,
                'redirect_url' => route('transaction-heads.index'),
            ]);
        }

        return redirect()->route('transaction-heads.index')->with('success', $message);
    }

    public function destroy(Request $request, TransactionHead $transactionHead): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $transactionHead);

        // Delete must still work from a normal form submit if the safe-delete
        // modal script is not available. Preview requests remain previews.
        if (! $request->boolean('preview') && ! $request->boolean('confirmed')) {
            $request->merge(['confirmed' => true]);
        }

        $plan = $this->safeDeleteService->inspectTransactionHead($transactionHead);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteTransactionHead($transactionHead),
            'transaction-heads.index',
            'Transaction Head deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, TransactionHead $head): void
    {
        abort_unless($head->company_id === $request->user()->company_id, 404);
    }
}
