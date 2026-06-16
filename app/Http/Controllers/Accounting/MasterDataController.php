<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Requests\Accounting\StoreMasterDataOptionRequest;
use App\Http\Requests\Accounting\UpdateMasterDataOptionRequest;
use App\Models\AccountingOption;
use App\Services\Accounting\MasterDataService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly MasterDataService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request, string $section): View
    {
        return view('master-data.index', $this->service->pageData($section));
    }

    public function store(StoreMasterDataOptionRequest $request, string $section): RedirectResponse
    {
        $this->service->create($section, $request->validated());

        return redirect()->route('master.index', $section)->with('success', 'Master value saved');
    }

    public function update(
        UpdateMasterDataOptionRequest $request,
        string $section,
        AccountingOption $accountingOption,
    ): RedirectResponse {
        $this->service->update($section, $accountingOption, $request->validated());

        return redirect()->route('master.index', $section)->with('success', 'Master value updated');
    }

    public function destroy(
        Request $request,
        string $section,
        AccountingOption $accountingOption,
    ): JsonResponse|RedirectResponse {
        $this->service->assertSafeDeletable($section, $accountingOption);
        $plan = $this->safeDeleteService->inspectAccountingOption($accountingOption);

        if ($request->boolean('preview')) {
            return response()->json(['success' => true, 'preview' => true, 'plan' => $plan]);
        }

        if (! $request->boolean('confirmed')) {
            $message = 'Deletion was not completed because explicit confirmation is required.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'plan' => $plan], 409)
                : redirect()->route('master.index', $section)->with('error', $message);
        }

        $this->safeDeleteService->deleteAccountingOption($accountingOption);
        $message = 'Master value deleted permanently. Dependent records were detached and made inactive or incomplete.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'redirect_url' => route('master.index', $section)])
            : redirect()->route('master.index', $section)->with('success', $message);
    }
}
