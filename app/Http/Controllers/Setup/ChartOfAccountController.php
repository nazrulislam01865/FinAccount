<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Setup\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $accounts = ChartOfAccount::query()
            ->with(['accountType', 'parent'])
            ->orderBy('account_code')
            ->get();

        return view('setup.chart-of-accounts', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        ChartOfAccountRequest $request,
        ChartOfAccountService $service
    ): JsonResponse {
        $account = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Account saved successfully.',
            'data' => $account->load(['accountType', 'parent']),
            'redirect' => route('setup.chart-of-accounts'),
        ], 201);
    }

    public function update(
        ChartOfAccountRequest $request,
        ChartOfAccount $chartOfAccount,
        ChartOfAccountService $service
    ): JsonResponse {
        $account = $service->update(
            $chartOfAccount,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully.',
            'data' => $account,
            'redirect' => route('setup.chart-of-accounts'),
        ]);
    }

    public function destroy(
        Request $request,
        ChartOfAccount $chartOfAccount,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        try {
            $deleteService->deleteChartOfAccount($chartOfAccount);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.chart-of-accounts',
                'This account could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.chart-of-accounts',
            'Account deleted successfully.'
        );
    }
}
