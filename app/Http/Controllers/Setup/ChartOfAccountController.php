<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Models\LedgerType;
use App\Services\Setup\ChartOfAccountExcelService;
use App\Services\Setup\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $accounts = ChartOfAccount::query()
            ->with(['accountType', 'parent', 'partyType'])
            ->orderBy('account_code')
            ->get();

        $stats = [
            'total' => $accounts->count(),
            'posting' => $accounts->where('posting_allowed', true)->count(),
            'groups' => $accounts->where('posting_allowed', false)->count(),
            'cash_bank' => $accounts->where('is_cash_bank', true)->count(),
            'party_control' => $accounts->where('is_party_control', true)->count(),
            'active' => $accounts->where('status', 'Active')->count(),
        ];

        return view('setup.chart-of-accounts', [
            'accounts' => $accounts,
            'stats' => $stats,
            'coaLevels' => ChartOfAccount::COA_LEVELS,
            'ledgerTypes' => LedgerType::activeNames(),
        ]);
    }

    public function export(ChartOfAccountExcelService $excel): BinaryFileResponse
    {
        $path = $excel->exportPath();
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx';
        $contentType = $extension === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return response()->download(
            $path,
            'chart-of-accounts-' . now()->format('Ymd-His') . '.' . $extension,
            ['Content-Type' => $contentType]
        )->deleteFileAfterSend(true);
    }

    public function import(
        Request $request,
        ChartOfAccountExcelService $excel,
        ChartOfAccountService $service
    ): RedirectResponse {
        $request->validate([
            'coa_file' => ['required', 'file', 'mimes:xlsx,xlsm,csv,txt', 'max:5120'],
        ]);

        $result = $excel->import($request->file('coa_file'), $service, $request->user()?->id);
        $message = sprintf(
            'CoA import completed. Created: %d, Updated: %d, Skipped: %d.',
            $result['created'],
            $result['updated'],
            $result['skipped']
        );

        return redirect()
            ->route('setup.chart-of-accounts')
            ->with('status', $message)
            ->with('import_errors', array_slice($result['errors'], 0, 10));
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
            'data' => $account->load(['accountType', 'parent', 'partyType']),
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
                $exception->getMessage() ?: 'This account could not be deleted. Please try again or check related records.',
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
