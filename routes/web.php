<?php

use App\Http\Controllers\Api\DropdownController;
use App\Http\Controllers\Settings\UserRoleController;
use App\Http\Controllers\Setup\CashBankAccountController;
use App\Http\Controllers\Setup\ChartOfAccountController;
use App\Http\Controllers\Setup\CompanyController;
use App\Http\Controllers\Setup\LedgerMappingController;
use App\Http\Controllers\Setup\MasterDataController;
use App\Http\Controllers\Setup\OpeningBalanceController;
use App\Http\Controllers\Setup\PartyController;
use App\Http\Controllers\Setup\TransactionHeadController;
use App\Http\Controllers\Setup\VoucherNumberingController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Transaction Entry
    |--------------------------------------------------------------------------
    */
    Route::get('/transactions/create', [TransactionController::class, 'create'])
        ->name('transactions.create');

    /*
    |--------------------------------------------------------------------------
    | Setup Pages
    |--------------------------------------------------------------------------
    */
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/company', [CompanyController::class, 'edit'])
            ->name('company');

        Route::get('/master-data', [MasterDataController::class, 'index'])
            ->name('master-data');

        Route::delete('/master-data/business-types/{business_type}', [MasterDataController::class, 'destroyBusinessType'])
            ->name('master-data.business-types.destroy');

        Route::delete('/master-data/currencies/{currency}', [MasterDataController::class, 'destroyCurrency'])
            ->name('master-data.currencies.destroy');

        Route::delete('/master-data/settlement-types/{settlement_type}', [MasterDataController::class, 'destroySettlementType'])
            ->name('master-data.settlement-types.destroy');

        Route::delete('/master-data/party-types/{party_type}', [MasterDataController::class, 'destroyPartyType'])
            ->name('master-data.party-types.destroy');

        Route::delete('/master-data/financial-years/{financial_year}', [MasterDataController::class, 'destroyFinancialYear'])
            ->name('master-data.financial-years.destroy');

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
            ->name('chart-of-accounts');

        Route::delete('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'destroy'])
            ->name('chart-of-accounts.destroy');

        Route::get('/cash-bank-accounts', [CashBankAccountController::class, 'index'])
            ->name('cash-bank-accounts');

        Route::delete('/cash-bank-accounts/{cash_bank_account}', [CashBankAccountController::class, 'destroy'])
            ->name('cash-bank-accounts.destroy');

        Route::get('/parties', [PartyController::class, 'index'])
            ->name('parties');

        Route::delete('/parties/{party}', [PartyController::class, 'destroy'])
            ->name('parties.destroy');

        Route::get('/transaction-heads', [TransactionHeadController::class, 'index'])
            ->name('transaction-heads');

        Route::delete('/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'destroy'])
            ->name('transaction-heads.destroy');

        Route::get('/ledger-mapping', [LedgerMappingController::class, 'index'])
            ->name('ledger-mapping');

        Route::delete('/ledger-mapping/{ledger_mapping_rule}', [LedgerMappingController::class, 'destroy'])
            ->name('ledger-mapping.destroy');

        Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])
            ->name('opening-balances');

        Route::get('/voucher-numbering', [VoucherNumberingController::class, 'index'])
            ->name('voucher-numbering');

        Route::delete('/voucher-numbering/{voucher_numbering_rule}', [VoucherNumberingController::class, 'destroy'])
            ->name('voucher-numbering.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Settings Pages
    |--------------------------------------------------------------------------
    */
    Route::get('/settings/users-roles', [UserRoleController::class, 'index'])
        ->name('settings.users-roles');

    Route::delete('/settings/users/{user}', [UserRoleController::class, 'destroyUser'])
        ->name('settings.users.destroy');

    /*
    |--------------------------------------------------------------------------
    | Dropdown APIs
    |--------------------------------------------------------------------------
    */
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

        Route::get('/cash-bank-accounts', [DropdownController::class, 'cashBankAccounts'])
            ->name('cash-bank-accounts');

        Route::get('/ledger-accounts', [DropdownController::class, 'ledgerAccounts'])
            ->name('ledger-accounts');

        Route::get('/linked-ledgers', [DropdownController::class, 'ledgerAccounts'])
            ->name('linked-ledgers');

        Route::get('/party-balance-types', [DropdownController::class, 'partyBalanceTypes'])
            ->name('party-balance-types');

        Route::get('/transaction-head-natures', [DropdownController::class, 'transactionHeadNatures'])
            ->name('transaction-head-natures');

        Route::get('/party-ledger-effects', [DropdownController::class, 'partyLedgerEffects'])
            ->name('party-ledger-effects');

        Route::get('/yes-no-options', [DropdownController::class, 'yesNoOptions'])
            ->name('yes-no-options');

        Route::get('/transaction-heads', [DropdownController::class, 'transactionHeads'])
            ->name('transaction-heads');

        Route::get('/settlement-types', [DropdownController::class, 'settlementTypes'])
            ->name('settlement-types');


        Route::get('/parties', [DropdownController::class, 'parties'])
            ->name('parties');
    });

    /*
    |--------------------------------------------------------------------------
    | Setup APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/company', [CompanyController::class, 'store'])
        ->name('api.company.store');

    Route::post('/api/master-data/business-types', [MasterDataController::class, 'storeBusinessType'])
        ->name('api.master-data.business-types.store');

    Route::match(['post', 'put'], '/api/master-data/business-types/{business_type}', [MasterDataController::class, 'updateBusinessType'])
        ->name('api.master-data.business-types.update');

    Route::post('/api/master-data/currencies', [MasterDataController::class, 'storeCurrency'])
        ->name('api.master-data.currencies.store');

    Route::match(['post', 'put'], '/api/master-data/currencies/{currency}', [MasterDataController::class, 'updateCurrency'])
        ->name('api.master-data.currencies.update');

    Route::post('/api/master-data/settlement-types', [MasterDataController::class, 'storeSettlementType'])
        ->name('api.master-data.settlement-types.store');

    Route::match(['post', 'put'], '/api/master-data/settlement-types/{settlement_type}', [MasterDataController::class, 'updateSettlementType'])
        ->name('api.master-data.settlement-types.update');

    Route::post('/api/master-data/party-types', [MasterDataController::class, 'storePartyType'])
        ->name('api.master-data.party-types.store');

    Route::match(['post', 'put'], '/api/master-data/party-types/{party_type}', [MasterDataController::class, 'updatePartyType'])
        ->name('api.master-data.party-types.update');

    Route::post('/api/master-data/financial-years', [MasterDataController::class, 'storeFinancialYear'])
        ->name('api.master-data.financial-years.store');

    Route::match(['post', 'put'], '/api/master-data/financial-years/{financial_year}', [MasterDataController::class, 'updateFinancialYear'])
        ->name('api.master-data.financial-years.update');

    Route::post('/api/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->name('api.chart-of-accounts.store');

    Route::match(['post', 'put'], '/api/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'update'])
        ->name('api.chart-of-accounts.update');

    Route::post('/api/cash-bank-accounts', [CashBankAccountController::class, 'store'])
        ->name('api.cash-bank-accounts.store');

    Route::match(['post', 'put'], '/api/cash-bank-accounts/{cash_bank_account}', [CashBankAccountController::class, 'update'])
        ->name('api.cash-bank-accounts.update');

    Route::post('/api/parties', [PartyController::class, 'store'])
        ->name('api.parties.store');

    Route::match(['post', 'put'], '/api/parties/{party}', [PartyController::class, 'update'])
        ->name('api.parties.update');

    Route::post('/api/transaction-heads', [TransactionHeadController::class, 'store'])
        ->name('api.transaction-heads.store');

    Route::match(['post', 'put'], '/api/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'update'])
        ->name('api.transaction-heads.update');

    Route::post('/api/ledger-mapping', [LedgerMappingController::class, 'store'])
        ->name('api.ledger-mapping.store');

    Route::match(['post', 'put'], '/api/ledger-mapping/{ledger_mapping_rule}', [LedgerMappingController::class, 'update'])
        ->name('api.ledger-mapping.update');

    Route::post('/api/opening-balances', [OpeningBalanceController::class, 'store'])
        ->name('api.opening-balances.store');

    Route::post('/api/voucher-numbering', [VoucherNumberingController::class, 'store'])
        ->name('api.voucher-numbering.store');

    Route::match(['post', 'put'], '/api/voucher-numbering/{voucher_numbering_rule}', [VoucherNumberingController::class, 'update'])
        ->name('api.voucher-numbering.update');

    /*
    |--------------------------------------------------------------------------
    | Transaction APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/transactions/preview', [TransactionController::class, 'preview'])
        ->name('api.transactions.preview');

    Route::post('/api/transactions', [TransactionController::class, 'store'])
        ->name('api.transactions.store');

    /*
    |--------------------------------------------------------------------------
    | User APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/users', [UserRoleController::class, 'storeUser'])
        ->name('api.users.store');

    Route::match(['post', 'put'], '/api/users/{user}', [UserRoleController::class, 'updateUser'])
        ->name('api.users.update');
});

require __DIR__.'/auth.php';
