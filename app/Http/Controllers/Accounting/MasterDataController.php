<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StoreMasterDataOptionRequest;
use App\Http\Requests\Accounting\UpdateMasterDataOptionRequest;
use App\Models\AccountingOption;
use App\Services\Accounting\MasterDataService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use App\Services\Company\CompanyMasterOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly MasterDataService $service,
        private readonly SafeDeleteService $safeDeleteService,
        private readonly CompanyMasterOverviewService $companyMasterOverviewService,
    ) {}

    public function overview(Request $request): View
    {
        $user = $request->user();
        $permissionMap = [
            'party-types' => ['party_types.view', 'party_types.manage'],
            'money-account-types' => ['money_account_types.view', 'money_account_types.manage'],
            'transaction-categories' => ['transaction_categories.view', 'transaction_categories.manage'],
        ];

        $configurations = collect($this->service->configurations())
            ->filter(fn (array $configuration, string $section): bool => $user->canAnyAccounting($permissionMap[$section] ?? []))
            ->all();

        $companyMasterCards = $this->companyMasterOverviewService->cards($user);
        $canOpenVoucherNumbering = $user->canAnyAccounting(['voucher_numbering.view', 'voucher_numbering.manage']);

        abort_if($configurations === [] && $companyMasterCards === [] && ! $canOpenVoucherNumbering, 403,
            'Your role is not allowed to open any master-data module.');

        return view('master-data.overview', [
            'configurations' => $configurations,
            'companyMasterCards' => $companyMasterCards,
            'canOpenVoucherNumbering' => $canOpenVoucherNumbering,
        ]);
    }

    public function index(Request $request, string $section): View
    {
        $permissionPrefix = match ($section) {
            'party-types' => 'party_types',
            'money-account-types' => 'money_account_types',
            'transaction-categories' => 'transaction_categories',
            default => abort(404),
        };
        $data = $this->service->pageData($section);
        $data['addOnlyMode'] = ! $request->user()->canAccounting($permissionPrefix.'.view');
        if ($data['addOnlyMode']) {
            $data['options'] = collect();
            $data['usage'] = [];
        }

        return view('master-data.index', $data);
    }

    public function store(StoreMasterDataOptionRequest $request, string $section): RedirectResponse
    {
        $this->service->create($section, $request->validated());

        $permissionPrefix = match ($section) {
            'party-types' => 'party_types',
            'money-account-types' => 'money_account_types',
            'transaction-categories' => 'transaction_categories',
            default => abort(404),
        };

        return $this->redirectAfterAccountingSave($request, $permissionPrefix.'.view', 'master.index', 'Master value saved', ['section' => $section]);
    }

    public function update(
        UpdateMasterDataOptionRequest $request,
        string $section,
        AccountingOption $accountingOption,
    ): RedirectResponse {
        $this->service->update($section, $accountingOption, $request->validated());

        $permissionPrefix = match ($section) {
            'party-types' => 'party_types',
            'money-account-types' => 'money_account_types',
            'transaction-categories' => 'transaction_categories',
            default => abort(404),
        };

        return $this->redirectAfterAccountingSave($request, $permissionPrefix.'.view', 'master.index', 'Master value updated', ['section' => $section]);
    }

    public function destroy(
        Request $request,
        string $section,
        AccountingOption $accountingOption,
    ): JsonResponse|RedirectResponse {
        $this->service->assertSafeDeletable($section, $accountingOption);

        // Normal form submits must still delete when the safe-delete modal JS is
        // stale or unavailable. Preview requests remain previews for the modal.
        if (! $request->boolean('preview') && ! $request->boolean('confirmed')) {
            $request->merge(['confirmed' => true]);
        }

        $plan = $this->safeDeleteService->inspectAccountingOption($accountingOption);

        if ($request->boolean('preview')) {
            return response()->json(['success' => true, 'preview' => true, 'plan' => $plan]);
        }

        $this->safeDeleteService->deleteAccountingOption($accountingOption);
        $message = 'Master value deleted permanently. Dependent records were detached and made inactive or incomplete.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'redirect_url' => route('master.index', $section)])
            : redirect()->route('master.index', $section)->with('success', $message);
    }
}
