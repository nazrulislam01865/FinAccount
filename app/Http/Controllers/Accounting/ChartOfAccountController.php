<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StoreChartOfAccountRequest;
use App\Http\Requests\Accounting\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\SafeDelete\DeletionPlan;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChartOfAccountController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly ChartOfAccountService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $oldAccountId = (int) $request->old('account_id', '0');
        $levelFilter = (int) $request->integer('level');
        if (! in_array($levelFilter, [0, 1, 2, 3], true)) {
            $levelFilter = 0;
        }

        $data = $this->service->pageData(
            $request->user()->company_id,
            $search,
            $oldAccountId,
            $levelFilter,
        );
        $data['addOnlyMode'] = ! $request->user()->canAccounting('chart_of_accounts.view');
        if ($data['addOnlyMode']) {
            $data['accounts'] = collect();
            $data['balances'] = [];
            $data['modalAccount'] = null;
        }

        return view('chart-of-accounts.index', $data);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return $this->redirectAfterAccountingSave($request, 'chart_of_accounts.view', 'chart-of-accounts.index', 'Record saved');
    }

    public function update(
        UpdateChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount,
    ): RedirectResponse {
        $this->ensureCompany($request, $chartOfAccount);
        $this->service->update($chartOfAccount, $request->validated());

        return $this->redirectAfterAccountingSave($request, 'chart_of_accounts.view', 'chart-of-accounts.index', 'Record saved');
    }


    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        [$accounts, $ids] = $this->resolveBulkAccounts($request);
        $this->ensureBulkHierarchyCanBeDeleted($request, $ids);
        $plan = $this->bulkDeletionPlan($accounts);

        if ($request->boolean('preview')) {
            return response()->json([
                'success' => true,
                'preview' => true,
                'plan' => $plan,
            ]);
        }

        if (! $request->boolean('confirmed')) {
            $message = 'Bulk deletion was not completed because explicit confirmation is required.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'plan' => $plan,
                ], 409);
            }

            return redirect()->route('chart-of-accounts.index')->with('error', $message);
        }

        DB::transaction(function () use ($accounts): void {
            $accounts
                ->sortByDesc(fn (ChartOfAccount $account): string => sprintf('%03d-%020s', (int) $account->level, (string) $account->code))
                ->each(fn (ChartOfAccount $account) => $this->safeDeleteService->deleteChartOfAccount($account));
        }, attempts: 5);

        $recordWord = $accounts->count() === 1 ? 'record' : 'records';
        $message = number_format($accounts->count()).' Chart of Account '.$recordWord.' deleted permanently.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect_url' => route('chart-of-accounts.index'),
            ]);
        }

        return redirect()->route('chart-of-accounts.index')->with('success', $message);
    }

    public function destroy(Request $request, ChartOfAccount $chartOfAccount): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $chartOfAccount);

        if ($chartOfAccount->children()->exists()) {
            $message = 'Move or delete the child accounts before deleting this parent account.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->route('chart-of-accounts.index')->with('error', $message);
        }

        $plan = $this->safeDeleteService->inspectChartOfAccount($chartOfAccount);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteChartOfAccount($chartOfAccount),
            'chart-of-accounts.index',
            'Chart of Account deleted permanently.',
        );
    }


    /**
     * @return array{0: Collection<int, ChartOfAccount>, 1: Collection<int, int>}
     */
    private function resolveBulkAccounts(Request $request): array
    {
        $ids = collect((array) $request->input('account_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(response()->json([
                'success' => false,
                'message' => 'Select at least one Chart of Account record to delete.',
            ], 422));
        }

        $accounts = ChartOfAccount::query()
            ->with('parent:id,code,name,level,type')
            ->withCount('children')
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $ids)
            ->get();

        if ($accounts->count() !== $ids->count()) {
            abort(response()->json([
                'success' => false,
                'message' => 'One or more selected accounts are invalid for this company.',
            ], 422));
        }

        return [$accounts, $ids];
    }

    private function ensureBulkHierarchyCanBeDeleted(Request $request, Collection $ids): void
    {
        $blockedParents = ChartOfAccount::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $ids)
            ->whereHas('children', fn ($query) => $query->whereNotIn('id', $ids))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        if ($blockedParents->isNotEmpty()) {
            $names = $blockedParents
                ->take(5)
                ->map(fn (ChartOfAccount $account): string => $account->code.' — '.$account->name)
                ->implode(', ');

            abort(response()->json([
                'success' => false,
                'message' => 'Cannot bulk delete parent accounts while their child accounts remain. Also select the child accounts or delete child accounts first. Blocked parent: '.$names,
            ], 422));
        }
    }

    /**
     * @param Collection<int, ChartOfAccount> $accounts
     */
    private function bulkDeletionPlan(Collection $accounts): DeletionPlan
    {
        $dependencies = [];

        foreach ($accounts as $account) {
            foreach ($this->safeDeleteService->inspectChartOfAccount($account)->dependencies as $dependency) {
                $label = $dependency['label'];
                if (! isset($dependencies[$label])) {
                    $dependencies[$label] = [
                        'label' => $label,
                        'count' => 0,
                        'effect' => $dependency['effect'],
                    ];
                }
                $dependencies[$label]['count'] += (int) $dependency['count'];
            }
        }

        return new DeletionPlan(
            'Chart of Account Bulk Delete',
            number_format($accounts->count()).' selected COA '.($accounts->count() === 1 ? 'record' : 'records'),
            array_values(array_filter($dependencies, fn (array $dependency): bool => (int) $dependency['count'] > 0)),
            'The selected Chart of Account records will be deleted. Child accounts must be selected together with their parent, otherwise the delete is blocked.',
            'Mapped records will be detached or made inactive exactly like single COA safe delete. Opening balance rows mapped to selected COA records will be removed.'
        );
    }

    private function ensureCompany(Request $request, ChartOfAccount $account): void
    {
        abort_unless($account->company_id === $request->user()->company_id, 404);
    }
}
