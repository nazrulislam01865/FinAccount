<?php

use App\Http\Controllers\AccountingReports\CashBankBookController;
use App\Http\Controllers\AccountingReports\IncomeStatementController;
use App\Http\Controllers\AccountingReports\TransactionListController;
use App\Http\Controllers\AccountingReports\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active.user'])
    ->prefix(config('accounting_reports.route_prefix', 'accounting/reports'))
    ->name('accounting-reports.')
    ->group(function () {
        Route::get('/', fn () => view('accounting_reports.index'))
            ->middleware('permission:reports.view|cash-bank-book.view|transactions.view')
            ->name('index');

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
            ->middleware('permission:cash-bank-book.view|reports.view')
            ->name('cash-bank-book.index');
        Route::get('/cash-bank-book/export', [CashBankBookController::class, 'export'])
            ->middleware('permission:cash-bank-book.view|reports.view')
            ->name('cash-bank-book.export');

        Route::get('/trial-balance', [TrialBalanceController::class, 'index'])
            ->middleware('permission:reports.view|reports.full')
            ->name('trial-balance.index');
        Route::get('/trial-balance/export', [TrialBalanceController::class, 'export'])
            ->middleware('permission:reports.view|reports.full')
            ->name('trial-balance.export');

        Route::get('/income-statement', [IncomeStatementController::class, 'index'])
            ->middleware('permission:reports.view|reports.full')
            ->name('income-statement.index');
        Route::get('/income-statement/export', [IncomeStatementController::class, 'export'])
            ->middleware('permission:reports.view|reports.full')
            ->name('income-statement.export');
    });
