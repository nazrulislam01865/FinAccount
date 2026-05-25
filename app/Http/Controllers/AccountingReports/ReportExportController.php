<?php

namespace App\Http\Controllers\AccountingReports;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Services\Reports\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function __construct(private readonly ReportExportService $reportExportService)
    {
    }

    public function store(Request $request, string $reportName): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyPermission($this->permissionsForReport($reportName)), 403, 'You do not have permission to export this report.');

        $export = $this->reportExportService->request($reportName, $request->except('_token'), $request->user());

        return back()->with('success', 'Export request queued. Export ID: ' . $export->id);
    }

    public function download(ReportExport $reportExport): BinaryFileResponse
    {
        abort_unless($reportExport->status === 'Completed' && $reportExport->file_path, 404);
        abort_unless(Storage::disk('local')->exists($reportExport->file_path), 404);
        abort_unless(auth()->id() === (int) $reportExport->user_id || auth()->user()?->hasPermission('reports.full'), 403, 'You do not have permission to download this export.');

        return response()->download(Storage::disk('local')->path($reportExport->file_path));
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForReport(string $reportName): array
    {
        $key = Str::of($reportName)->lower()->replace(['_', ' '], '-')->toString();

        return match ($key) {
            'trial-balance', 'income-statement', 'balance-sheet', 'cash-flow-statement', 'sales-report', 'expense-report' => ['reports.full'],
            'cash-bank-book' => ['cash-bank-book.view', 'reports.full'],
            'customer-receivables', 'customer-receivable', 'customer-due' => ['customer-ledgers.view', 'reports.full'],
            'supplier-payables', 'supplier-payable', 'supplier-due' => ['supplier-ledgers.view', 'reports.full'],
            'transactions', 'transaction-list' => ['transactions.view', 'reports.full'],
            default => ['reports.full'],
        };
    }
}
