<?php

use App\Http\Controllers\Api\DropdownController;
use App\Http\Controllers\Api\SrsApiController;
use App\Http\Controllers\ManualJournalController;
use App\Http\Controllers\Setup\OpeningBalanceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'message' => 'API is working.',
]));

/*
|--------------------------------------------------------------------------
| Dropdown APIs
|--------------------------------------------------------------------------
| These APIs are used by frontend dynamic dropdowns.
*/
Route::middleware(['auth'])->prefix('native/dropdowns')->name('api.native.dropdowns.')->group(function () {
    Route::get('/business-types', [DropdownController::class, 'businessTypes'])
        ->name('business-types');

    Route::get('/currencies', [DropdownController::class, 'currencies'])
        ->name('currencies');

    Route::get('/time-zones', [DropdownController::class, 'timeZones'])
        ->name('time-zones');

    Route::get('/coa-levels', [DropdownController::class, 'coaLevels'])
        ->name('coa-levels');

    Route::get('/ledger-types', [DropdownController::class, 'ledgerTypes'])
        ->name('ledger-types');

    Route::get('/account-types', [DropdownController::class, 'accountTypes'])
        ->name('account-types');

    Route::get('/parent-accounts', [DropdownController::class, 'parentAccounts'])
        ->name('parent-accounts');

    Route::get('/party-types', [DropdownController::class, 'partyTypes'])
        ->name('party-types');

    Route::get('/banks', [DropdownController::class, 'banks'])
        ->name('banks');

    Route::get('/cash-bank-ledgers', [DropdownController::class, 'cashBankLedgers'])
        ->name('cash-bank-ledgers');

    Route::get('/ledger-accounts', [DropdownController::class, 'ledgerAccounts'])
        ->name('ledger-accounts');

    Route::get('/linked-ledgers', [DropdownController::class, 'ledgerAccounts'])
        ->name('linked-ledgers');

    Route::get('/settlement-types', [DropdownController::class, 'settlementTypes'])
        ->name('settlement-types');

    Route::get('/parties', [DropdownController::class, 'parties'])
        ->name('parties');

    Route::get('/party-ledger-effects', [DropdownController::class, 'partyLedgerEffects'])
        ->name('party-ledger-effects');
});

/*
|--------------------------------------------------------------------------
| SRS / PRD API Contract
|--------------------------------------------------------------------------
| These endpoints are thin, permission-controlled API aliases over the same
| setup, posting, journal, and report sources used by the Blade UI. They keep
| the project web-first while satisfying the SRS/PRD endpoint contract.
*/
Route::middleware(['auth'])->name('api.srs.')->group(function () {
    Route::get('/accounts', [SrsApiController::class, 'accounts'])
        ->middleware('permission:api.view|chart-of-accounts.view')
        ->name('accounts.index');

    Route::post('/accounts', [SrsApiController::class, 'storeAccount'])
        ->middleware('permission:api.manage|chart-of-accounts.manage')
        ->name('accounts.store');

    Route::get('/voucher-numbering', [SrsApiController::class, 'voucherNumbering'])
        ->middleware('permission:api.view|voucher-numbering.view')
        ->name('voucher-numbering.index');

    Route::get('/transaction-purposes', [SrsApiController::class, 'transactionPurposes'])
        ->middleware('permission:api.view|transaction-heads.view')
        ->name('transaction-purposes.index');

    Route::post('/accounting-rules', [SrsApiController::class, 'storeAccountingRule'])
        ->middleware('permission:api.manage|ledger-mapping.manage')
        ->name('accounting-rules.store');

    Route::post('/opening-balances/post', [OpeningBalanceController::class, 'store'])
        ->middleware('permission:api.manage|opening-balances.manage')
        ->name('opening-balances.post');

    Route::post('/transactions/post', [TransactionController::class, 'store'])
        ->middleware('permission:api.manage|transactions.create|transactions.draft')
        ->name('transactions.post');

    Route::post('/manual-journals/post', [ManualJournalController::class, 'store'])
        ->middleware('permission:api.manage|transactions.journal.create')
        ->name('manual-journals.post');

    Route::get('/vouchers', [SrsApiController::class, 'vouchers'])
        ->middleware('permission:api.view|transactions.view')
        ->name('vouchers.index');

    Route::get('/journal-entries', [SrsApiController::class, 'journalEntries'])
        ->middleware('permission:api.view|reports.view')
        ->name('journal-entries.index');

    Route::get('/reports/general-ledger', [SrsApiController::class, 'generalLedger'])
        ->middleware('permission:api.view|ledger-report.view|reports.view')
        ->name('reports.general-ledger');

    Route::get('/reports/trial-balance', [SrsApiController::class, 'trialBalance'])
        ->middleware('permission:api.view|reports.full')
        ->name('reports.trial-balance');

    Route::get('/reports/profit-loss', [SrsApiController::class, 'profitLoss'])
        ->middleware('permission:api.view|reports.full')
        ->name('reports.profit-loss');

    Route::get('/reports/balance-sheet', [SrsApiController::class, 'balanceSheet'])
        ->middleware('permission:api.view|reports.full')
        ->name('reports.balance-sheet');

    Route::get('/reports/customer-due', [SrsApiController::class, 'customerDue'])
        ->middleware('permission:api.view|customer-ledgers.view|reports.full')
        ->name('reports.customer-due');

    Route::get('/reports/supplier-due', [SrsApiController::class, 'supplierDue'])
        ->middleware('permission:api.view|supplier-ledgers.view|reports.full')
        ->name('reports.supplier-due');
});
