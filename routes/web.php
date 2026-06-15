<?php

use App\Http\Controllers\Accounting\AccountingRuleController;
use App\Http\Controllers\Accounting\BalanceController;
use App\Http\Controllers\Accounting\BasicStatementController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\MoneyAccountController;
use App\Http\Controllers\Accounting\PartyController;
use App\Http\Controllers\Accounting\TransactionHeadController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\DashboardController;
use App\Http\Controllers\Accounting\TransactionEntryController;
use App\Http\Controllers\Accounting\TransactionRegisterController;
use App\Http\Controllers\Landing\LandingAdminAuthController;
use App\Http\Controllers\Landing\LandingPageAdminController;
use App\Http\Controllers\Landing\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'show'])->name('landing.show');
Route::get('/home', [LandingPageController::class, 'show'])->name('home');
Route::get('/landing', [LandingPageController::class, 'show'])->name('landing.public');
Route::post('/landing-page/inquiry', [LandingPageController::class, 'storeInquiry'])
    ->middleware('throttle:landing-inquiry')
    ->name('landing.inquiries.store');

Route::get('/landing-admin', [LandingAdminAuthController::class, 'create'])->name('landing-admin.login');
Route::post('/landing-admin', [LandingAdminAuthController::class, 'store'])->name('landing-admin.login.store');
Route::post('/landing-admin/logout', [LandingAdminAuthController::class, 'destroy'])->name('landing-admin.logout');

Route::middleware(['session.timeout', 'landing.admin.auth'])
    ->prefix('landing-admin')
    ->name('landing-admin.')
    ->group(function () {
        Route::get('/dashboard', [LandingPageAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/page', [LandingPageAdminController::class, 'edit'])->name('edit');
        Route::put('/page', [LandingPageAdminController::class, 'update'])->name('update');
        Route::post('/page/reset', [LandingPageAdminController::class, 'reset'])->name('reset');
        Route::put('/inquiries/{inquiry}', [LandingPageAdminController::class, 'updateInquiry'])->name('inquiries.update');
        Route::delete('/inquiries/{inquiry}', [LandingPageAdminController::class, 'destroyInquiry'])->name('inquiries.destroy');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/dashboard/reset-demo', [DashboardController::class, 'resetDemo'])
        ->name('dashboard.reset-demo');

    Route::get('/transactions', [TransactionRegisterController::class, 'index'])
        ->name('transactions.index');

    Route::get('/transactions/create', [TransactionEntryController::class, 'create'])
        ->name('transactions.create');

    Route::get('/transactions/preview', [TransactionEntryController::class, 'preview'])
        ->name('transactions.preview');

    Route::get('/transactions/export', [TransactionRegisterController::class, 'export'])
        ->name('transactions.export');

    Route::post('/transactions', [TransactionEntryController::class, 'store'])
        ->name('transactions.store');

    Route::get('/transactions/{transaction}/edit', [TransactionRegisterController::class, 'edit'])
        ->name('transactions.edit');

    Route::put('/transactions/{transaction}', [TransactionRegisterController::class, 'update'])
        ->name('transactions.update');

    Route::delete('/transactions/{transaction}', [TransactionRegisterController::class, 'destroy'])
        ->name('transactions.destroy');

    Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
        ->name('chart-of-accounts.index');

    Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->name('chart-of-accounts.store');

    Route::put('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'update'])
        ->name('chart-of-accounts.update');

    Route::delete('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'destroy'])
        ->name('chart-of-accounts.destroy');


    Route::resource('money-accounts', MoneyAccountController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('parties', PartyController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('accounting-rules', AccountingRuleController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('transaction-heads', TransactionHeadController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/journal-entries', [JournalEntryController::class, 'index'])
        ->name('journal-entries.index');

    Route::get('/balances', [BalanceController::class, 'index'])
        ->name('balances.index');

    Route::get('/basic-statements', [BasicStatementController::class, 'index'])
        ->name('basic-statements.index');
});

require __DIR__.'/settings.php';
