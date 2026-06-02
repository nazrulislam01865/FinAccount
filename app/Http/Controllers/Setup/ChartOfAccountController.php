<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\LedgerType;
use App\Models\PartyType;
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
            'accountTypes' => AccountType::active()->orderBy('sort_order')->orderBy('name')->get(),
            'partyTypes' => PartyType::active()->orderBy('sort_order')->orderBy('name')->get(),
            'parentAccountOptions' => ChartOfAccount::query()
                ->where('account_level', 'Group')
                ->where('status', 'Active')
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'coa_level', 'account_type_id']),
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
        $issues = $result['issues'] ?? [];
        $message = sprintf(
            'CoA import completed. Created: %d, Updated automatically: %d, Skipped for review: %d.',
            $result['created'],
            0,
            count($issues)
        );

        $redirect = redirect()
            ->route('setup.chart-of-accounts')
            ->with('status', $message)
            ->with('import_errors', array_slice($result['errors'], 0, 10));

        if ($issues !== []) {
            session([
                'coa_import_review' => [
                    'summary' => [
                        'created' => $result['created'],
                        'skipped' => $result['skipped'],
                        'conflicts' => count($result['conflicts'] ?? []),
                        'violations' => count($result['violations'] ?? []),
                    ],
                    'issues' => $issues,
                ],
            ]);
        } else {
            session()->forget('coa_import_review');
        }

        return $redirect;
    }

    public function resolveImportIssue(
        Request $request,
        ChartOfAccountExcelService $excel,
        ChartOfAccountService $service
    ): RedirectResponse {
        $data = $request->validate([
            'import_issue_id' => ['required', 'string'],
            'import_issue_action' => ['required', 'in:skip,create,update'],
            'existing_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'account_code' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'coa_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'account_type_id' => ['nullable', 'integer', 'exists:account_types,id'],
            'normal_balance' => ['nullable', 'in:Debit,Credit'],
            'ledger_type' => ['nullable', 'string', 'max:50'],
            'party_type_id' => ['nullable', 'integer', 'exists:party_types,id'],
            'is_user_selectable' => ['nullable'],
            'is_system_ledger' => ['nullable'],
            'description' => ['nullable', 'string', 'max:1000'],
            'example_usage' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:Active,Inactive'],
        ]);

        $review = session('coa_import_review', ['summary' => [], 'issues' => []]);
        $issues = collect($review['issues'] ?? [])->values();
        $issue = $issues->firstWhere('id', $data['import_issue_id']);

        if (! $issue) {
            return redirect()
                ->route('setup.chart-of-accounts')
                ->with('error', 'The selected import issue was not found. Please import the file again.');
        }

        if ($data['import_issue_action'] === 'skip') {
            $this->forgetImportIssue($data['import_issue_id'], $review);

            return redirect()
                ->route('setup.chart-of-accounts')
                ->with('status', 'Import issue skipped. No account was changed.');
        }

        $payload = $excel->normalizeResolvedPayload($data);
        $targetAccountId = $data['import_issue_action'] === 'update'
            ? (int) ($data['existing_account_id'] ?? ($issue['existing'][0]['id'] ?? 0))
            : null;

        if ($data['import_issue_action'] === 'update' && ! $targetAccountId) {
            $this->keepImportIssueError($data['import_issue_id'], $review, ['Select the existing account that should be updated.']);

            return redirect()
                ->route('setup.chart-of-accounts')
                ->with('error', 'Could not update the import issue. Select an existing account first.');
        }

        $ruleErrors = $excel->validateAccountPayload($payload, $targetAccountId);
        if ($ruleErrors !== []) {
            $this->keepImportIssueError($data['import_issue_id'], $review, $ruleErrors, $payload);

            return redirect()
                ->route('setup.chart-of-accounts')
                ->with('error', 'The selected import row still has accounting rule issues. Please correct it and try again.');
        }

        try {
            if ($data['import_issue_action'] === 'update') {
                $account = ChartOfAccount::query()->findOrFail($targetAccountId);
                $service->update($account, $payload, $request->user()?->id);
                $message = 'Existing account updated from import review.';
            } else {
                $service->create($payload, $request->user()?->id);
                $message = 'Corrected account added from import review.';
            }
        } catch (Throwable $exception) {
            $this->keepImportIssueError($data['import_issue_id'], $review, [$exception->getMessage() ?: 'Could not save this import row.'], $payload);

            return redirect()
                ->route('setup.chart-of-accounts')
                ->with('error', 'Could not save the selected import row. Please check the details and try again.');
        }

        $this->forgetImportIssue($data['import_issue_id'], $review);

        return redirect()
            ->route('setup.chart-of-accounts')
            ->with('status', $message);
    }

    private function forgetImportIssue(string $issueId, array $review): void
    {
        $review['issues'] = collect($review['issues'] ?? [])
            ->reject(fn (array $issue) => ($issue['id'] ?? '') === $issueId)
            ->values()
            ->all();

        if ($review['issues'] === []) {
            session()->forget('coa_import_review');
            return;
        }

        session(['coa_import_review' => $review]);
    }

    private function keepImportIssueError(string $issueId, array $review, array $errors, array $payload = []): void
    {
        $review['issues'] = collect($review['issues'] ?? [])
            ->map(function (array $issue) use ($issueId, $errors, $payload) {
                if (($issue['id'] ?? '') !== $issueId) {
                    return $issue;
                }

                $issue['resolve_errors'] = $errors;
                if ($payload !== []) {
                    $issue['payload'] = array_merge($issue['payload'] ?? [], $payload);
                }

                return $issue;
            })
            ->values()
            ->all();

        session(['coa_import_review' => $review]);
    }


    public function discardImportReview(): RedirectResponse
    {
        session()->forget('coa_import_review');

        return redirect()
            ->route('setup.chart-of-accounts')
            ->with('status', 'Chart of Accounts import review discarded. Skipped rows will not appear again unless you import the file again.');
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
