<?php

use App\Http\Controllers\Api\DropdownController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Audit\AuditTrailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\System\HealthController;
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
use App\Http\Controllers\AdvanceManagementController;
use App\Http\Controllers\DueManagementController;
use App\Http\Controllers\LedgerReportController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->middleware('permission:approvals.view|approvals.manage')
        ->name('approvals.index');

    Route::post('/approvals/{voucher}/approve', [ApprovalController::class, 'approve'])
        ->middleware('permission:approvals.manage')
        ->name('approvals.approve');

    Route::post('/approvals/{voucher}/reject', [ApprovalController::class, 'reject'])
        ->middleware('permission:approvals.manage')
        ->name('approvals.reject');

    Route::get('/audit-trail', AuditTrailController::class)
        ->middleware('permission:audit-trail.view')
        ->name('audit-trail.index');

    /*
    |--------------------------------------------------------------------------
    | Transaction Entry
    |--------------------------------------------------------------------------
    */
    Route::get('/transactions/create', [TransactionController::class, 'create'])
        ->middleware('permission:transactions.create|transactions.draft')
        ->name('transactions.create');


    /*
    |--------------------------------------------------------------------------
    | Due Management and Ledger Reports
    |--------------------------------------------------------------------------
    */
    Route::get('/due-management', [DueManagementController::class, 'index'])
        ->middleware('permission:due-management.view|customer-ledgers.view|supplier-ledgers.view|reports.view')
        ->name('due-management.index');

    Route::post('/api/due-management/settle', [DueManagementController::class, 'settle'])
        ->middleware('permission:due-management.manage|transactions.create')
        ->name('api.due-management.settle');

    Route::get('/advance-management', [AdvanceManagementController::class, 'index'])
        ->middleware('permission:advance-management.view|reports.view|customer-ledgers.view|supplier-ledgers.view')
        ->name('advance-management.index');

    Route::post('/api/advance-management', [AdvanceManagementController::class, 'store'])
        ->middleware('permission:advance-management.manage|transactions.create')
        ->name('api.advance-management.store');

    Route::get('/ledger-report', [LedgerReportController::class, 'index'])
        ->middleware('permission:ledger-report.view|reports.view|customer-ledgers.view|supplier-ledgers.view')
        ->name('ledger-report.index');


    /*
    |--------------------------------------------------------------------------
    | Release Tracker
    |--------------------------------------------------------------------------
    | Access is controlled by the editable role permission matrix.
    */
    Route::prefix('release-notes')->name('release-notes.')->group(function () {
        Route::get('/', [ReleaseNoteController::class, 'index'])
            ->middleware('permission:release-notes.view')
            ->name('index');

        Route::post('/', [ReleaseNoteController::class, 'store'])
            ->middleware('permission:release-notes.manage')
            ->name('store');

        Route::match(['post', 'put'], '/{releaseItem}', [ReleaseNoteController::class, 'update'])
            ->middleware('permission:release-notes.manage')
            ->name('update');

        Route::delete('/{releaseItem}', [ReleaseNoteController::class, 'destroy'])
            ->middleware('permission:release-notes.manage')
            ->name('destroy');
    });

    Route::prefix('api/release-notes')->name('api.release-notes.')->group(function () {
        Route::post('/', [ReleaseNoteController::class, 'store'])
            ->middleware('permission:release-notes.manage')
            ->name('store');

        Route::match(['post', 'put'], '/{releaseItem}', [ReleaseNoteController::class, 'update'])
            ->middleware('permission:release-notes.manage')
            ->name('update');

        Route::delete('/{releaseItem}', [ReleaseNoteController::class, 'destroy'])
            ->middleware('permission:release-notes.manage')
            ->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Setup Pages
    |--------------------------------------------------------------------------
    */
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/company', [CompanyController::class, 'edit'])
            ->middleware('permission:company.view')
            ->name('company');

        Route::get('/master-data', [MasterDataController::class, 'index'])
            ->middleware('permission:master-data.view')
            ->name('master-data');

        Route::get('/master-data/business-types', [MasterDataController::class, 'businessTypes'])
            ->middleware('permission:master-data.view')
            ->name('master-data.business-types');

        Route::get('/master-data/currencies', [MasterDataController::class, 'currencies'])
            ->middleware('permission:master-data.view')
            ->name('master-data.currencies');

        Route::get('/master-data/settlement-types', [MasterDataController::class, 'settlementTypes'])
            ->middleware('permission:master-data.view')
            ->name('master-data.settlement-types');

        Route::get('/master-data/party-types', [MasterDataController::class, 'partyTypes'])
            ->middleware('permission:master-data.view')
            ->name('master-data.party-types');

        Route::get('/master-data/financial-years', [MasterDataController::class, 'financialYears'])
            ->middleware('permission:master-data.view')
            ->name('master-data.financial-years');

        Route::delete('/master-data/business-types/{business_type}', [MasterDataController::class, 'destroyBusinessType'])
            ->middleware('permission:master-data.manage')
            ->name('master-data.business-types.destroy');

        Route::delete('/master-data/currencies/{currency}', [MasterDataController::class, 'destroyCurrency'])
            ->middleware('permission:master-data.manage')
            ->name('master-data.currencies.destroy');

        Route::delete('/master-data/settlement-types/{settlement_type}', [MasterDataController::class, 'destroySettlementType'])
            ->middleware('permission:master-data.manage')
            ->name('master-data.settlement-types.destroy');

        Route::delete('/master-data/party-types/{party_type}', [MasterDataController::class, 'destroyPartyType'])
            ->middleware('permission:master-data.manage')
            ->name('master-data.party-types.destroy');

        Route::delete('/master-data/financial-years/{financial_year}', [MasterDataController::class, 'destroyFinancialYear'])
            ->middleware('permission:master-data.manage')
            ->name('master-data.financial-years.destroy');

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
            ->middleware('permission:chart-of-accounts.view')
            ->name('chart-of-accounts');

        Route::delete('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'destroy'])
            ->middleware('permission:chart-of-accounts.manage')
            ->name('chart-of-accounts.destroy');

        Route::get('/cash-bank-accounts', [CashBankAccountController::class, 'index'])
            ->middleware('permission:cash-bank.view')
            ->name('cash-bank-accounts');

        Route::delete('/cash-bank-accounts/{cash_bank_account}', [CashBankAccountController::class, 'destroy'])
            ->middleware('permission:cash-bank.manage')
            ->name('cash-bank-accounts.destroy');

        Route::get('/parties', [PartyController::class, 'index'])
            ->middleware('permission:parties.view')
            ->name('parties');

        Route::delete('/parties/{party}', [PartyController::class, 'destroy'])
            ->middleware('permission:parties.manage')
            ->name('parties.destroy');

        Route::get('/transaction-heads', [TransactionHeadController::class, 'index'])
            ->middleware('permission:transaction-heads.view')
            ->name('transaction-heads');

        Route::delete('/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'destroy'])
            ->middleware('permission:transaction-heads.manage')
            ->name('transaction-heads.destroy');

        Route::get('/accounting-rules-setup', [LedgerMappingController::class, 'index'])
            ->middleware('permission:ledger-mapping.view')
            ->name('accounting-rules-setup');

        Route::get('/ledger-mapping', fn () => redirect()->route('setup.accounting-rules-setup'))
            ->middleware('permission:ledger-mapping.view')
            ->name('ledger-mapping');

        Route::delete('/accounting-rules-setup/{ledger_mapping_rule}', [LedgerMappingController::class, 'destroy'])
            ->middleware('permission:ledger-mapping.manage')
            ->name('accounting-rules-setup.destroy');

        Route::delete('/ledger-mapping/{ledger_mapping_rule}', [LedgerMappingController::class, 'destroy'])
            ->middleware('permission:ledger-mapping.manage')
            ->name('ledger-mapping.destroy');

        Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])
            ->middleware('permission:opening-balances.view')
            ->name('opening-balances');

        Route::get('/voucher-numbering', [VoucherNumberingController::class, 'index'])
            ->middleware('permission:voucher-numbering.view')
            ->name('voucher-numbering');

        Route::delete('/voucher-numbering/{voucher_numbering_rule}', [VoucherNumberingController::class, 'destroy'])
            ->middleware('permission:voucher-numbering.manage')
            ->name('voucher-numbering.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Settings Pages
    |--------------------------------------------------------------------------
    */
    Route::get('/settings/users-roles', [UserRoleController::class, 'index'])
        ->middleware('permission:users.view|roles.manage')
        ->name('settings.users-roles');

    Route::delete('/settings/users/{user}', [UserRoleController::class, 'destroyUser'])
        ->middleware('permission:users.manage')
        ->name('settings.users.destroy');

    /*
    |--------------------------------------------------------------------------
    | Dropdown APIs
    |--------------------------------------------------------------------------
    */
    $companyDropdownPermission = 'permission:company.view|company.manage|master-data.view|master-data.manage';
    $ledgerDropdownPermission = 'permission:chart-of-accounts.view|chart-of-accounts.manage|cash-bank.view|cash-bank.manage|parties.view|parties.manage|ledger-mapping.view|ledger-mapping.manage|opening-balances.view|opening-balances.manage|transactions.view|transactions.create|transactions.draft';
    $cashBankDropdownPermission = 'permission:cash-bank.view|cash-bank.manage|ledger-mapping.view|ledger-mapping.manage|transactions.view|transactions.create|transactions.draft|cash-bank-book.view|reports.view';
    $partyDropdownPermission = 'permission:parties.view|parties.manage|transactions.view|transactions.create|transactions.draft|due-management.view|due-management.manage|advance-management.view|advance-management.manage|customer-ledgers.view|supplier-ledgers.view|reports.view';
    $transactionDropdownPermission = 'permission:transaction-heads.view|transaction-heads.manage|transactions.view|transactions.create|transactions.draft|ledger-mapping.view|ledger-mapping.manage|due-management.view|due-management.manage|advance-management.view|advance-management.manage|reports.view';
    $settlementDropdownPermission = 'permission:master-data.view|master-data.manage|ledger-mapping.view|ledger-mapping.manage|transactions.view|transactions.create|transactions.draft|due-management.view|due-management.manage|advance-management.view|advance-management.manage|reports.view';

    Route::prefix('api/dropdowns')->name('api.dropdowns.')->group(function () use (
        $companyDropdownPermission,
        $ledgerDropdownPermission,
        $cashBankDropdownPermission,
        $partyDropdownPermission,
        $transactionDropdownPermission,
        $settlementDropdownPermission
    ) {
        Route::get('/business-types', [DropdownController::class, 'businessTypes'])
            ->middleware($companyDropdownPermission)
            ->name('business-types');
        Route::get('/currencies', [DropdownController::class, 'currencies'])
            ->middleware($companyDropdownPermission)
            ->name('currencies');
        Route::get('/time-zones', [DropdownController::class, 'timeZones'])
            ->middleware($companyDropdownPermission)
            ->name('time-zones');
        Route::get('/coa-levels', [DropdownController::class, 'coaLevels'])
            ->middleware($ledgerDropdownPermission)
            ->name('coa-levels');
        Route::get('/ledger-types', [DropdownController::class, 'ledgerTypes'])
            ->middleware($ledgerDropdownPermission)
            ->name('ledger-types');
        Route::get('/account-types', [DropdownController::class, 'accountTypes'])
            ->middleware($ledgerDropdownPermission)
            ->name('account-types');
        Route::get('/parent-accounts', [DropdownController::class, 'parentAccounts'])
            ->middleware($ledgerDropdownPermission)
            ->name('parent-accounts');
        Route::get('/party-types', [DropdownController::class, 'partyTypes'])
            ->middleware($partyDropdownPermission . '|master-data.view|master-data.manage|transaction-heads.view|transaction-heads.manage')
            ->name('party-types');
        Route::get('/banks', [DropdownController::class, 'banks'])
            ->middleware($cashBankDropdownPermission . '|master-data.view|master-data.manage')
            ->name('banks');
        Route::get('/cash-bank-account-types', [DropdownController::class, 'cashBankAccountTypes'])
            ->middleware($cashBankDropdownPermission)
            ->name('cash-bank-account-types');
        Route::get('/cash-bank-ledgers', [DropdownController::class, 'cashBankLedgers'])
            ->middleware($cashBankDropdownPermission)
            ->name('cash-bank-ledgers');
        Route::get('/cash-bank-accounts', [DropdownController::class, 'cashBankAccounts'])
            ->middleware($cashBankDropdownPermission)
            ->name('cash-bank-accounts');
        Route::get('/ledger-accounts', [DropdownController::class, 'ledgerAccounts'])
            ->middleware($ledgerDropdownPermission)
            ->name('ledger-accounts');
        Route::get('/linked-ledgers', [DropdownController::class, 'ledgerAccounts'])
            ->middleware($ledgerDropdownPermission)
            ->name('linked-ledgers');
        Route::get('/party-balance-types', [DropdownController::class, 'partyBalanceTypes'])
            ->middleware($partyDropdownPermission . '|opening-balances.view|opening-balances.manage')
            ->name('party-balance-types');
        Route::get('/transaction-head-natures', [DropdownController::class, 'transactionHeadNatures'])
            ->middleware($transactionDropdownPermission)
            ->name('transaction-head-natures');
        Route::get('/party-ledger-effects', [DropdownController::class, 'partyLedgerEffects'])
            ->middleware($transactionDropdownPermission . '|ledger-mapping.view|ledger-mapping.manage')
            ->name('party-ledger-effects');
        Route::get('/yes-no-options', [DropdownController::class, 'yesNoOptions'])
            ->middleware('permission:company.view|master-data.view|chart-of-accounts.view|cash-bank.view|parties.view|transaction-heads.view|ledger-mapping.view|opening-balances.view|voucher-numbering.view|transactions.view|transactions.create|transactions.draft')
            ->name('yes-no-options');
        Route::get('/transaction-heads', [DropdownController::class, 'transactionHeads'])
            ->middleware($transactionDropdownPermission)
            ->name('transaction-heads');
        Route::get('/settlement-types', [DropdownController::class, 'settlementTypes'])
            ->middleware($settlementDropdownPermission)
            ->name('settlement-types');
        Route::get('/parties', [DropdownController::class, 'parties'])
            ->middleware($partyDropdownPermission)
            ->name('parties');
    });

    /*
    |--------------------------------------------------------------------------
    | Setup APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/company', [CompanyController::class, 'store'])
        ->middleware('permission:company.manage')
        ->name('api.company.store');

    Route::post('/api/master-data/business-types', [MasterDataController::class, 'storeBusinessType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.business-types.store');
    Route::match(['post', 'put'], '/api/master-data/business-types/{business_type}', [MasterDataController::class, 'updateBusinessType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.business-types.update');

    Route::post('/api/master-data/currencies', [MasterDataController::class, 'storeCurrency'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.currencies.store');
    Route::match(['post', 'put'], '/api/master-data/currencies/{currency}', [MasterDataController::class, 'updateCurrency'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.currencies.update');

    Route::post('/api/master-data/settlement-types', [MasterDataController::class, 'storeSettlementType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.settlement-types.store');
    Route::match(['post', 'put'], '/api/master-data/settlement-types/{settlement_type}', [MasterDataController::class, 'updateSettlementType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.settlement-types.update');

    Route::post('/api/master-data/party-types', [MasterDataController::class, 'storePartyType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.party-types.store');
    Route::match(['post', 'put'], '/api/master-data/party-types/{party_type}', [MasterDataController::class, 'updatePartyType'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.party-types.update');

    Route::post('/api/master-data/financial-years', [MasterDataController::class, 'storeFinancialYear'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.financial-years.store');
    Route::match(['post', 'put'], '/api/master-data/financial-years/{financial_year}', [MasterDataController::class, 'updateFinancialYear'])
        ->middleware('permission:master-data.manage')
        ->name('api.master-data.financial-years.update');

    Route::post('/api/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->middleware('permission:chart-of-accounts.manage')
        ->name('api.chart-of-accounts.store');
    Route::match(['post', 'put'], '/api/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'update'])
        ->middleware('permission:chart-of-accounts.manage')
        ->name('api.chart-of-accounts.update');

    Route::post('/api/cash-bank-accounts', [CashBankAccountController::class, 'store'])
        ->middleware('permission:cash-bank.manage')
        ->name('api.cash-bank-accounts.store');
    Route::match(['post', 'put'], '/api/cash-bank-accounts/{cash_bank_account}', [CashBankAccountController::class, 'update'])
        ->middleware('permission:cash-bank.manage')
        ->name('api.cash-bank-accounts.update');

    Route::post('/api/parties', [PartyController::class, 'store'])
        ->middleware('permission:parties.manage')
        ->name('api.parties.store');
    Route::match(['post', 'put'], '/api/parties/{party}', [PartyController::class, 'update'])
        ->middleware('permission:parties.manage')
        ->name('api.parties.update');

    Route::post('/api/transaction-heads', [TransactionHeadController::class, 'store'])
        ->middleware('permission:transaction-heads.manage')
        ->name('api.transaction-heads.store');
    Route::match(['post', 'put'], '/api/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'update'])
        ->middleware('permission:transaction-heads.manage')
        ->name('api.transaction-heads.update');

    Route::post('/api/accounting-rules-setup', [LedgerMappingController::class, 'store'])
        ->middleware('permission:ledger-mapping.manage')
        ->name('api.accounting-rules-setup.store');
    Route::match(['post', 'put'], '/api/accounting-rules-setup/{ledger_mapping_rule}', [LedgerMappingController::class, 'update'])
        ->middleware('permission:ledger-mapping.manage')
        ->name('api.accounting-rules-setup.update');

    // Legacy API aliases kept so older cached pages or bookmarks do not break immediately after deployment.
    Route::post('/api/ledger-mapping', [LedgerMappingController::class, 'store'])
        ->middleware('permission:ledger-mapping.manage')
        ->name('api.ledger-mapping.store');
    Route::match(['post', 'put'], '/api/ledger-mapping/{ledger_mapping_rule}', [LedgerMappingController::class, 'update'])
        ->middleware('permission:ledger-mapping.manage')
        ->name('api.ledger-mapping.update');

    Route::post('/api/opening-balances', [OpeningBalanceController::class, 'store'])
        ->middleware('permission:opening-balances.manage')
        ->name('api.opening-balances.store');

    Route::post('/api/voucher-numbering', [VoucherNumberingController::class, 'store'])
        ->middleware('permission:voucher-numbering.manage')
        ->name('api.voucher-numbering.store');
    Route::match(['post', 'put'], '/api/voucher-numbering/{voucher_numbering_rule}', [VoucherNumberingController::class, 'update'])
        ->middleware('permission:voucher-numbering.manage')
        ->name('api.voucher-numbering.update');

    /*
    |--------------------------------------------------------------------------
    | Transaction APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/transactions/preview', [TransactionController::class, 'preview'])
        ->middleware('permission:transactions.create|transactions.draft')
        ->name('api.transactions.preview');

    Route::post('/api/transactions', [TransactionController::class, 'store'])
        ->middleware('permission:transactions.create|transactions.draft')
        ->name('api.transactions.store');

    /*
    |--------------------------------------------------------------------------
    | User APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/api/users', [UserRoleController::class, 'storeUser'])
        ->middleware('permission:users.manage')
        ->name('api.users.store');

    Route::match(['post', 'put'], '/api/users/{user}', [UserRoleController::class, 'updateUser'])
        ->middleware('permission:users.manage')
        ->name('api.users.update');

    Route::post('/api/roles/permissions', [UserRoleController::class, 'updateRolePermissions'])
        ->middleware('permission:roles.manage')
        ->name('api.roles.permissions.update');
});

require __DIR__.'/auth.php';
