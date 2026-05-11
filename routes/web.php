<?php

use App\Http\Controllers\Api\DropdownController;
use App\Http\Controllers\Settings\UserRoleController;
use App\Http\Controllers\Setup\CashBankAccountController;
use App\Http\Controllers\Setup\ChartOfAccountController;
use App\Http\Controllers\Setup\CompanyController;
use App\Http\Controllers\Setup\PartyController;
use App\Http\Controllers\Setup\TransactionHeadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/company', [CompanyController::class, 'edit'])
            ->name('company');

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
            ->name('chart-of-accounts');

        Route::get('/cash-bank-accounts', [CashBankAccountController::class, 'index'])
            ->name('cash-bank-accounts');

        Route::get('/parties', [PartyController::class, 'index'])
            ->name('parties');

        Route::get('/transaction-heads', [TransactionHeadController::class, 'index'])
            ->name('transaction-heads');
    });

    Route::view('/settings/users-roles', 'settings.users-roles')
        ->name('settings.users-roles');

    Route::prefix('api/dropdowns')->name('api.dropdowns.')->group(function () {
        Route::get('/business-types', [DropdownController::class, 'businessTypes'])
            ->name('business-types');

        Route::get('/currencies', [DropdownController::class, 'currencies'])
            ->name('currencies');

        Route::get('/time-zones', [DropdownController::class, 'timeZones'])
            ->name('time-zones');

        Route::get('/account-types', [DropdownController::class, 'accountTypes'])
            ->name('account-types');

        Route::get('/parent-accounts', [DropdownController::class, 'parentAccounts'])
            ->name('parent-accounts');

        Route::get('/party-types', [DropdownController::class, 'partyTypes'])
            ->name('party-types');

        Route::get('/banks', [DropdownController::class, 'banks'])
            ->name('banks');

        Route::get('/cash-bank-account-types', [DropdownController::class, 'cashBankAccountTypes'])
            ->name('cash-bank-account-types');

        Route::get('/cash-bank-ledgers', [DropdownController::class, 'cashBankLedgers'])
            ->name('cash-bank-ledgers');

        Route::get('/ledger-accounts', [DropdownController::class, 'ledgerAccounts'])
            ->name('ledger-accounts');

        Route::get('/linked-ledgers', [DropdownController::class, 'ledgerAccounts'])
            ->name('linked-ledgers');

        Route::get('/party-balance-types', [DropdownController::class, 'partyBalanceTypes'])
            ->name('party-balance-types');

        Route::get('/transaction-head-natures', [DropdownController::class, 'transactionHeadNatures'])
            ->name('transaction-head-natures');

        Route::get('/yes-no-options', [DropdownController::class, 'yesNoOptions'])
            ->name('yes-no-options');

        Route::get('/transaction-heads', [DropdownController::class, 'transactionHeads'])
            ->name('transaction-heads');

        Route::get('/settlement-types', [DropdownController::class, 'settlementTypes'])
            ->name('settlement-types');
    });

    Route::post('/api/company', [CompanyController::class, 'store'])
        ->name('api.company.store');

    Route::post('/api/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->name('api.chart-of-accounts.store');

    Route::post('/api/cash-bank-accounts', [CashBankAccountController::class, 'store'])
        ->name('api.cash-bank-accounts.store');

    Route::post('/api/parties', [PartyController::class, 'store'])
        ->name('api.parties.store');

    Route::post('/api/transaction-heads', [TransactionHeadController::class, 'store'])
        ->name('api.transaction-heads.store');

    Route::post('/api/users', [UserRoleController::class, 'storeUser'])
        ->name('api.users.store');
});

require __DIR__.'/auth.php';
