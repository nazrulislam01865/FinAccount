<?php

namespace App\Http\Controllers\AccountingReports;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class ReportIndexController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        $routes = [
            'accounting-reports.transactions.index',
            'accounting-reports.cash-bank-book.index',
            'accounting-reports.ledger-report.index',
            'accounting-reports.trial-balance.index',
            'accounting-reports.income-statement.index',
            'accounting-reports.balance-sheet.index',
            'accounting-reports.cash-flow-statement.index',
            'accounting-reports.customer-receivables.index',
            'accounting-reports.supplier-payables.index',
            'accounting-reports.sales-report.index',
            'accounting-reports.expense-report.index',
            'audit-trail.index',
        ];

        foreach ($routes as $route) {
            if (Route::has($route) && ($user?->canViewRoute($route) ?? false)) {
                return redirect()->route($route);
            }
        }

        abort(403, 'You do not have permission to view reports.');
    }
}
