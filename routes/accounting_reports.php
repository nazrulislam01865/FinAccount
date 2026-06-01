<?php

use App\Http\Controllers\AccountingReports\CashBankBookController;
use App\Http\Controllers\AccountingReports\BalanceSheetController;
use App\Http\Controllers\AccountingReports\CashFlowStatementController;
use App\Http\Controllers\AccountingReports\CustomerReceivableController;
use App\Http\Controllers\AccountingReports\ExpenseReportController;
use App\Http\Controllers\AccountingReports\IncomeStatementController;
use App\Http\Controllers\AccountingReports\ReportIndexController;
use App\Http\Controllers\AccountingReports\ReportExportController;
use App\Http\Controllers\AccountingReports\SalesReportController;
use App\Http\Controllers\AccountingReports\SupplierPayableController;
use App\Http\Controllers\AccountingReports\TransactionListController;
use App\Http\Controllers\AccountingReports\TrialBalanceController;
use App\Http\Controllers\LedgerReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active.user'])
    ->prefix(config('accounting_reports.route_prefix', 'accounting/reports'))
    ->name('accounting-reports.')
    ->group(function () {
        Route::get('/', ReportIndexController::class)
            ->middleware('permission:reports.view|reports.full|cash-bank-book.view|transactions.view|ledger-report.view|customer-ledgers.view|supplier-ledgers.view|audit-trail.view')
            ->name('index');


        Route::post('/exports/{reportName}', [ReportExportController::class, 'store'])
            ->middleware('permission:reports.view')
            ->name('exports.store');
        Route::get('/exports/download/{reportExport}', [ReportExportController::class, 'download'])
            ->middleware('permission:reports.view')
            ->name('exports.download');

        Route::get('/transactions', [TransactionListController::class, 'index'])
            ->middleware('permission:transactions.view|reports.view')
            ->name('transactions.index');
        Route::get('/transactions/export', [TransactionListController::class, 'export'])
            ->middleware('permission:transactions.view|reports.view')
            ->name('transactions.export');
        Route::get('/transactions/{voucherId}', [TransactionListController::class, 'show'])
            ->middleware('permission:transactions.view|reports.view')
            ->name('transactions.show');
        Route::post('/transactions/{voucherId}/reverse', [TransactionListController::class, 'reverse'])
            ->middleware('permission:transactions.reverse')
            ->name('transactions.reverse');

        Route::get('/cash-bank-book', [CashBankBookController::class, 'index'])
            ->middleware('permission:cash-bank-book.view|reports.full')
            ->name('cash-bank-book.index');
        Route::get('/cash-bank-book/export', [CashBankBookController::class, 'export'])
            ->middleware('permission:cash-bank-book.view|reports.full')
            ->name('cash-bank-book.export');

        Route::get('/ledger-report', [LedgerReportController::class, 'index'])
            ->middleware('permission:ledger-report.view|reports.view|customer-ledgers.view|supplier-ledgers.view')
            ->name('ledger-report.index');

        Route::get('/trial-balance', [TrialBalanceController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('trial-balance.index');
        Route::get('/trial-balance/export', [TrialBalanceController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('trial-balance.export');

        Route::get('/income-statement', [IncomeStatementController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('income-statement.index');
        Route::get('/income-statement/export', [IncomeStatementController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('income-statement.export');

        Route::get('/balance-sheet', [BalanceSheetController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('balance-sheet.index');
        Route::get('/balance-sheet/export', [BalanceSheetController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('balance-sheet.export');

        Route::get('/cash-flow-statement', [CashFlowStatementController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('cash-flow-statement.index');
        Route::get('/cash-flow-statement/export', [CashFlowStatementController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('cash-flow-statement.export');

        Route::get('/customer-receivables', [CustomerReceivableController::class, 'index'])
            ->middleware('permission:reports.full|customer-ledgers.view')
            ->name('customer-receivables.index');
        Route::get('/customer-receivables/export', [CustomerReceivableController::class, 'export'])
            ->middleware('permission:reports.full|customer-ledgers.view')
            ->name('customer-receivables.export');

        Route::get('/supplier-payables', [SupplierPayableController::class, 'index'])
            ->middleware('permission:reports.full|supplier-ledgers.view')
            ->name('supplier-payables.index');
        Route::get('/supplier-payables/export', [SupplierPayableController::class, 'export'])
            ->middleware('permission:reports.full|supplier-ledgers.view')
            ->name('supplier-payables.export');

        Route::get('/sales-report', [SalesReportController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('sales-report.index');
        Route::get('/sales-report/export', [SalesReportController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('sales-report.export');

        Route::get('/expense-report', [ExpenseReportController::class, 'index'])
            ->middleware('permission:reports.full')
            ->name('expense-report.index');
        Route::get('/expense-report/export', [ExpenseReportController::class, 'export'])
            ->middleware('permission:reports.full')
            ->name('expense-report.export');

    });
