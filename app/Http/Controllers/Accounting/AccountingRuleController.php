<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesBulkSetupActions;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StoreAccountingRuleRequest;
use App\Http\Requests\Accounting\UpdateAccountingRuleRequest;
use App\Models\AccountingRule;
use App\Services\Accounting\AccountingRuleService;
use App\Services\Accounting\AccountingSetupExportService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingRuleController extends Controller
{
    use HandlesBulkSetupActions, PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly AccountingRuleService $service,
        private readonly SafeDeleteService $safeDeleteService,
        private readonly AccountingSetupExportService $exportService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData($request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('accounting_rules.view');
        if ($data['addOnlyMode']) {
            $data['rules'] = collect();
        }

        return view('accounting-rules.index', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        return $this->exportService->accountingRules(
            (int) $request->user()->company_id,
            (string) ($request->user()->company?->name ?? 'Company'),
        );
    }

    public function store(StoreAccountingRuleRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return $this->redirectAfterAccountingSave($request, 'accounting_rules.view', 'accounting-rules.index', 'Record saved');
    }

    public function update(UpdateAccountingRuleRequest $request, AccountingRule $accountingRule): RedirectResponse
    {
        $this->ensureCompany($request, $accountingRule);
        $this->service->update($accountingRule, $request->validated());

        return $this->redirectAfterAccountingSave($request, 'accounting_rules.view', 'accounting-rules.index', 'Record saved');
    }

    public function bulkAction(Request $request): JsonResponse|RedirectResponse
    {
        [$action, $rules] = $this->resolveBulkCompanyRecords(
            $request,
            AccountingRule::class,
            'Accounting Rule',
            ['lines'],
        );

        if ($action === 'delete') {
            $this->ensureBulkDeletePermission($request);
            $plan = $this->buildBulkDeletionPlan(
                $rules,
                fn (AccountingRule $rule) => $this->safeDeleteService->inspectAccountingRule($rule),
                'Accounting Rule Bulk Delete',
                'Accounting Rule',
                'The selected accounting rules and their generated posting lines will be permanently deleted from the database.',
                'Legacy transaction-head links to the selected rules will be cleared and those heads will become inactive. Continue only when these rules are no longer required.',
            );
            $count = $rules->count();

            return $this->performSafeDelete(
                $request,
                $plan,
                function () use ($rules): void {
                    DB::transaction(function () use ($rules): void {
                        foreach ($rules as $rule) {
                            $this->safeDeleteService->deleteAccountingRule($rule);
                        }
                    }, attempts: 5);
                },
                'accounting-rules.index',
                number_format($count).' Accounting Rule '.($count === 1 ? 'record' : 'records').' deleted permanently.',
            );
        }

        $activate = $action === 'activate';
        $changed = $this->service->setActive($rules, $activate);
        $count = $rules->count();
        $state = $activate ? 'active' : 'inactive';
        $message = number_format($count).' Accounting Rule '.($count === 1 ? 'record was' : 'records were').' set to '.$state.'.';

        if ($changed === 0) {
            $message = 'No changes were needed. All selected Accounting Rule records were already '.$state.'.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'changed_count' => $changed,
                'redirect_url' => route('accounting-rules.index'),
            ]);
        }

        return redirect()->route('accounting-rules.index')->with('success', $message);
    }

    public function destroy(Request $request, AccountingRule $accountingRule): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $accountingRule);
        $plan = $this->safeDeleteService->inspectAccountingRule($accountingRule);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteAccountingRule($accountingRule),
            'accounting-rules.index',
            'Accounting Rule deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, AccountingRule $rule): void
    {
        abort_unless($rule->company_id === $request->user()->company_id, 404);
    }
}
