<?php

use App\Http\Controllers\Accounting\AccountingRuleController;
use App\Http\Controllers\Accounting\BalanceController;
use App\Http\Controllers\Accounting\BasicStatementController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\Company\BusinessTypeController;
use App\Http\Controllers\Accounting\Company\CompanySetupController;
use App\Http\Controllers\Accounting\Company\CurrencyController;
use App\Http\Controllers\Accounting\Company\FinancialYearController;
use App\Http\Controllers\Accounting\Company\TimeZoneController;
use App\Http\Controllers\Accounting\DashboardController;
use App\Http\Controllers\Accounting\DueManagementController;
use App\Http\Controllers\Accounting\FormDraftController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\MasterDataController;
use App\Http\Controllers\Accounting\MoneyAccountController;
use App\Http\Controllers\Accounting\NotificationController;
use App\Http\Controllers\Accounting\OpeningBalanceController;
use App\Http\Controllers\Accounting\ProfileController;
use App\Http\Controllers\Accounting\PartyController;
use App\Http\Controllers\Accounting\SalesInvoiceController;
use App\Http\Controllers\Accounting\Reports\FinancialReportController;
use App\Http\Controllers\Accounting\System\BrandSettingsController;
use App\Http\Controllers\Accounting\System\RoleMatrixController;
use App\Http\Controllers\Accounting\System\UserManagementController;
use App\Http\Controllers\Accounting\TransactionEntryController;
use App\Http\Controllers\Accounting\TransactionAttachmentController;
use App\Http\Controllers\Accounting\TransactionHeadController;
use App\Http\Controllers\Accounting\TransactionRegisterController;
use App\Http\Controllers\Accounting\VoucherSequenceController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\Landing\LandingAdminAuthController;
use App\Http\Controllers\Landing\LandingPageAdminController;
use App\Http\Controllers\Landing\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/brand/logo', [BrandAssetController::class, 'logo'])->name('brand.logo');
Route::get('/brand/favicon', [BrandAssetController::class, 'favicon'])->name('brand.favicon');

Route::get('/', [LandingPageController::class, 'show'])->name('landing.show');
Route::get('/home', [LandingPageController::class, 'show'])->name('home');
Route::get('/landing', [LandingPageController::class, 'show'])->name('landing.public');
Route::post('/landing-page/captcha', [LandingPageController::class, 'captchaChallenge'])
    ->middleware('throttle:landing-captcha')
    ->name('landing.captcha.challenge');
Route::post('/landing-page/inquiry', [LandingPageController::class, 'storeInquiry'])
    ->middleware('throttle:landing-inquiry')
    ->name('landing.inquiries.store');

Route::get('/landing-admin', [LandingAdminAuthController::class, 'create'])->name('landing-admin.login');
Route::post('/landing-admin', [LandingAdminAuthController::class, 'store'])->name('landing-admin.login.store');
Route::post('/landing-admin/logout', [LandingAdminAuthController::class, 'destroy'])->name('landing-admin.logout');
Route::post('/landing-admin/session/keep-alive', [LandingAdminAuthController::class, 'keepAlive'])
    ->middleware('landing.admin.auth')
    ->name('landing-admin.session.keep-alive');
Route::post('/landing-admin/session/timeout', [LandingAdminAuthController::class, 'timeout'])
    ->name('landing-admin.session.timeout');

Route::post('/session/keep-alive', [SessionController::class, 'keepAlive'])
    ->middleware('auth')
    ->name('session.keep-alive');
Route::post('/session/timeout', [SessionController::class, 'timeout'])
    ->name('session.timeout');

Route::middleware(['session.timeout', 'landing.admin.auth'])
    ->prefix('landing-admin')
    ->name('landing-admin.')
    ->group(function (): void {
        Route::get('/dashboard', [LandingPageAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/page', [LandingPageAdminController::class, 'edit'])->name('edit');
        Route::put('/page', [LandingPageAdminController::class, 'update'])->name('update');
        Route::post('/page/reset', [LandingPageAdminController::class, 'reset'])->name('reset');
        Route::put('/inquiries/{inquiry}', [LandingPageAdminController::class, 'updateInquiry'])->name('inquiries.update');
        Route::delete('/inquiries/{inquiry}', [LandingPageAdminController::class, 'destroyInquiry'])->name('inquiries.destroy');
    });

Route::middleware(['auth', 'verified', 'session.timeout', 'account.active', 'company.context', 'accounting.activity.notifications', 'form.draft.cleanup'])->group(function (): void {

    Route::get('/form-drafts/{draftKey}', [FormDraftController::class, 'show'])
        ->where('draftKey', '[A-Za-z0-9][A-Za-z0-9._:-]{0,190}')
        ->name('accounting.form-drafts.show');
    Route::put('/form-drafts/{draftKey}', [FormDraftController::class, 'store'])
        ->where('draftKey', '[A-Za-z0-9][A-Za-z0-9._:-]{0,190}')
        ->name('accounting.form-drafts.store');
    Route::delete('/form-drafts/{draftKey}', [FormDraftController::class, 'destroy'])
        ->where('draftKey', '[A-Za-z0-9][A-Za-z0-9._:-]{0,190}')
        ->name('accounting.form-drafts.destroy');

    Route::get('/my-profile', [ProfileController::class, 'index'])->name('accounting.profile');
    Route::get('/my-profile/photo', [ProfileController::class, 'photo'])->name('accounting.profile.photo');
    Route::put('/my-profile/photo', [ProfileController::class, 'updatePhoto'])->name('accounting.profile.photo.update');
    Route::put('/my-profile/password', [ProfileController::class, 'updatePassword'])->name('accounting.profile.password');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('accounting.notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('accounting.notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('accounting.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('accounting.notifications.read');
    Route::post('/notifications/pusher/auth', [NotificationController::class, 'pusherAuth'])->name('accounting.notifications.pusher-auth');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('accounting.permission:dashboard.view')->name('dashboard');
    Route::post('/dashboard/reset-demo', [DashboardController::class, 'resetDemo'])
        ->middleware(['system.admin', 'accounting.permission:settings.manage'])->name('dashboard.reset-demo');

    Route::get('/transactions', [TransactionRegisterController::class, 'index'])
        ->middleware('accounting.permission:transactions.view')->name('transactions.index');
    Route::get('/transactions/create', [TransactionEntryController::class, 'create'])
        ->middleware('accounting.permission:transactions.manage')->name('transactions.create');
    Route::get('/transactions/preview', [TransactionEntryController::class, 'preview'])
        ->middleware('accounting.permission:transactions.manage')->name('transactions.preview');
    Route::get('/transactions/export', [TransactionRegisterController::class, 'export'])
        ->middleware('accounting.permission:transactions.view')->name('transactions.export');
    Route::post('/transactions', [TransactionEntryController::class, 'store'])
        ->middleware('accounting.permission:transactions.manage')->name('transactions.store');
    Route::get('/transactions/{transaction}/edit', [TransactionRegisterController::class, 'edit'])
        ->middleware(['accounting.permission:transactions.view', 'accounting.permission:transactions.manage'])->name('transactions.edit');
    Route::put('/transactions/{transaction}', [TransactionRegisterController::class, 'update'])
        ->middleware(['accounting.permission:transactions.view', 'accounting.permission:transactions.manage'])->name('transactions.update');
    Route::get('/transactions/{transaction}/attachments/{attachment}', [TransactionAttachmentController::class, 'show'])
        ->middleware('accounting.permission:transactions.view')->name('transactions.attachments.show');

    Route::get('/sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show'])
        ->middleware('accounting.permission:transactions.view')->name('sales-invoices.show');
    Route::get('/sales-invoices/{salesInvoice}/download', [SalesInvoiceController::class, 'download'])
        ->middleware('accounting.permission:transactions.view')->name('sales-invoices.download');
    Route::post('/transactions/{transaction}/invoice', [SalesInvoiceController::class, 'generate'])
        ->middleware(['accounting.permission:transactions.view', 'accounting.permission:transactions.manage'])->name('transactions.invoice.generate');
    Route::delete('/transactions/{transaction}/attachments/{attachment}', [TransactionAttachmentController::class, 'destroy'])
        ->middleware(['accounting.permission:transactions.view', 'accounting.permission:transactions.manage'])->name('transactions.attachments.destroy');

    Route::delete('/transactions/{transaction}', [TransactionRegisterController::class, 'destroy'])
        ->middleware(['accounting.permission:transactions.manage', 'accounting.permission:records.delete'])->name('transactions.destroy');

    Route::get('/journal-entries', [JournalEntryController::class, 'index'])
        ->middleware('accounting.permission:journals.view')->name('journal-entries.index');
    Route::get('/balances', [BalanceController::class, 'index'])
        ->middleware('accounting.permission:balances.view')->name('balances.index');
    Route::get('/basic-statements', [BasicStatementController::class, 'index'])
        ->middleware('accounting.permission:statements.view')->name('basic-statements.index');

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/balance-sheet', [FinancialReportController::class, 'balanceSheet'])
            ->middleware('accounting.permission:statements.view')->name('balance-sheet');
        Route::get('/income-statement', [FinancialReportController::class, 'incomeStatement'])
            ->middleware('accounting.permission:statements.view')->name('income-statement');
        Route::get('/trial-balance', [FinancialReportController::class, 'trialBalance'])
            ->middleware('accounting.permission:statements.view')->name('trial-balance');
        Route::get('/ledger-report', [FinancialReportController::class, 'ledgerReport'])
            ->middleware('accounting.permission:balances.view')->name('ledger-report');
        Route::get('/due-report', [FinancialReportController::class, 'dueReport'])
            ->middleware('accounting.permission:balances.view')->name('due-report');
        Route::get('/due-management', [DueManagementController::class, 'index'])
            ->middleware('accounting.permission:balances.view')->name('due-management');
        Route::post('/due-management/settle', [DueManagementController::class, 'settle'])
            ->middleware(['accounting.permission:balances.view', 'accounting.permission:transactions.manage'])->name('due-management.settle');
    });

    Route::get('/company-setup', [CompanySetupController::class, 'edit'])
        ->name('company-setup.edit');
    Route::put('/company-setup', [CompanySetupController::class, 'update'])
        ->middleware('accounting.permission:company_setup.manage')->name('company-setup.update');

    Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])
        ->middleware('accounting.permission:chart_of_accounts.view')->name('chart-of-accounts.index');
    Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])
        ->middleware('accounting.permission:chart_of_accounts.manage')->name('chart-of-accounts.store');
    Route::put('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'update'])
        ->middleware('accounting.permission:chart_of_accounts.manage')->name('chart-of-accounts.update');
    Route::delete('/chart-of-accounts/bulk-delete', [ChartOfAccountController::class, 'bulkDestroy'])
        ->middleware(['accounting.permission:chart_of_accounts.manage', 'accounting.permission:records.delete'])->name('chart-of-accounts.bulk-destroy');
    Route::delete('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'destroy'])
        ->middleware(['accounting.permission:chart_of_accounts.manage', 'accounting.permission:records.delete'])->name('chart-of-accounts.destroy');

    Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])
        ->middleware('accounting.permission:opening_balances.view')->name('opening-balances.index');
    Route::post('/opening-balances', [OpeningBalanceController::class, 'store'])
        ->middleware('accounting.permission:opening_balances.manage')->name('opening-balances.store');
    Route::put('/opening-balances/{opening_balance}', [OpeningBalanceController::class, 'update'])
        ->middleware('accounting.permission:opening_balances.manage')->name('opening-balances.update');
    Route::delete('/opening-balances/{opening_balance}', [OpeningBalanceController::class, 'destroy'])
        ->middleware(['accounting.permission:opening_balances.manage', 'accounting.permission:records.delete'])->name('opening-balances.destroy');

    Route::get('/money-accounts', [MoneyAccountController::class, 'index'])
        ->middleware('accounting.permission:money_accounts.view')->name('money-accounts.index');
    Route::post('/money-accounts', [MoneyAccountController::class, 'store'])
        ->middleware('accounting.permission:money_accounts.manage')->name('money-accounts.store');
    Route::put('/money-accounts/{money_account}', [MoneyAccountController::class, 'update'])
        ->middleware('accounting.permission:money_accounts.manage')->name('money-accounts.update');
    Route::delete('/money-accounts/{money_account}', [MoneyAccountController::class, 'destroy'])
        ->middleware(['accounting.permission:money_accounts.manage', 'accounting.permission:records.delete'])->name('money-accounts.destroy');

    Route::get('/parties', [PartyController::class, 'index'])
        ->middleware('accounting.permission:parties.view')->name('parties.index');
    Route::post('/parties', [PartyController::class, 'store'])
        ->middleware('accounting.permission:parties.manage')->name('parties.store');
    Route::put('/parties/{party}', [PartyController::class, 'update'])
        ->middleware('accounting.permission:parties.manage')->name('parties.update');
    Route::delete('/parties/{party}', [PartyController::class, 'destroy'])
        ->middleware(['accounting.permission:parties.manage', 'accounting.permission:records.delete'])->name('parties.destroy');

    Route::get('/accounting-rules', [AccountingRuleController::class, 'index'])
        ->middleware('accounting.permission:accounting_rules.view')->name('accounting-rules.index');
    Route::post('/accounting-rules', [AccountingRuleController::class, 'store'])
        ->middleware('accounting.permission:accounting_rules.manage')->name('accounting-rules.store');
    Route::put('/accounting-rules/{accounting_rule}', [AccountingRuleController::class, 'update'])
        ->middleware('accounting.permission:accounting_rules.manage')->name('accounting-rules.update');
    Route::delete('/accounting-rules/{accounting_rule}', [AccountingRuleController::class, 'destroy'])
        ->middleware(['accounting.permission:accounting_rules.manage', 'accounting.permission:records.delete'])->name('accounting-rules.destroy');

    Route::get('/transaction-heads', [TransactionHeadController::class, 'index'])
        ->middleware('accounting.permission:transaction_heads.view')->name('transaction-heads.index');
    Route::post('/transaction-heads', [TransactionHeadController::class, 'store'])
        ->middleware('accounting.permission:transaction_heads.manage')->name('transaction-heads.store');
    Route::put('/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'update'])
        ->middleware('accounting.permission:transaction_heads.manage')->name('transaction-heads.update');
    Route::delete('/transaction-heads/{transaction_head}', [TransactionHeadController::class, 'destroy'])
        ->middleware(['accounting.permission:transaction_heads.manage', 'accounting.permission:records.delete'])->name('transaction-heads.destroy');

    Route::prefix('master')->name('master.')->group(function (): void {
        Route::get('/other-master-data', [MasterDataController::class, 'overview'])->name('overview');

        Route::get('/business-types', [BusinessTypeController::class, 'index'])
            ->middleware('accounting.permission:business_types.view')->name('business-types.index');
        Route::post('/business-types', [BusinessTypeController::class, 'store'])
            ->middleware('accounting.permission:business_types.manage')->name('business-types.store');
        Route::put('/business-types/{businessType}', [BusinessTypeController::class, 'update'])
            ->middleware('accounting.permission:business_types.manage')->name('business-types.update');
        Route::delete('/business-types/{businessType}', [BusinessTypeController::class, 'destroy'])
            ->middleware(['accounting.permission:business_types.manage', 'accounting.permission:records.delete'])->name('business-types.destroy');

        Route::get('/currencies', [CurrencyController::class, 'index'])
            ->middleware('accounting.permission:currencies.view')->name('currencies.index');
        Route::post('/currencies', [CurrencyController::class, 'store'])
            ->middleware('accounting.permission:currencies.manage')->name('currencies.store');
        Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])
            ->middleware('accounting.permission:currencies.manage')->name('currencies.update');
        Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])
            ->middleware(['accounting.permission:currencies.manage', 'accounting.permission:records.delete'])->name('currencies.destroy');

        Route::get('/time-zones', [TimeZoneController::class, 'index'])
            ->middleware('accounting.permission:time_zones.view')->name('time-zones.index');
        Route::post('/time-zones', [TimeZoneController::class, 'store'])
            ->middleware('accounting.permission:time_zones.manage')->name('time-zones.store');
        Route::put('/time-zones/{timeZone}', [TimeZoneController::class, 'update'])
            ->middleware('accounting.permission:time_zones.manage')->name('time-zones.update');
        Route::delete('/time-zones/{timeZone}', [TimeZoneController::class, 'destroy'])
            ->middleware(['accounting.permission:time_zones.manage', 'accounting.permission:records.delete'])->name('time-zones.destroy');

        Route::get('/financial-years', [FinancialYearController::class, 'index'])
            ->middleware('accounting.permission:financial_years.view')->name('financial-years.index');
        Route::post('/financial-years', [FinancialYearController::class, 'store'])
            ->middleware('accounting.permission:financial_years.manage')->name('financial-years.store');
        Route::put('/financial-years/{financialYear}', [FinancialYearController::class, 'update'])
            ->middleware('accounting.permission:financial_years.manage')->name('financial-years.update');
        Route::delete('/financial-years/{financialYear}', [FinancialYearController::class, 'destroy'])
            ->middleware(['accounting.permission:financial_years.manage', 'accounting.permission:records.delete'])->name('financial-years.destroy');

        Route::get('/voucher-sequences', [VoucherSequenceController::class, 'index'])
            ->middleware('accounting.permission:voucher_numbering.view')->name('voucher-sequences.index');
        Route::post('/voucher-sequences', [VoucherSequenceController::class, 'store'])
            ->middleware('accounting.permission:voucher_numbering.manage')->name('voucher-sequences.store');
        Route::put('/voucher-sequences/{documentSequence}', [VoucherSequenceController::class, 'update'])
            ->middleware('accounting.permission:voucher_numbering.manage')->name('voucher-sequences.update');
        Route::delete('/voucher-sequences/{documentSequence}', [VoucherSequenceController::class, 'destroy'])
            ->middleware(['accounting.permission:voucher_numbering.manage', 'accounting.permission:records.delete'])->name('voucher-sequences.destroy');

        Route::get('/{section}', [MasterDataController::class, 'index'])
            ->whereIn('section', ['party-types', 'money-account-types', 'transaction-categories'])
            ->middleware('master.permission:view')->name('index');
        Route::post('/{section}', [MasterDataController::class, 'store'])
            ->whereIn('section', ['party-types', 'money-account-types', 'transaction-categories'])
            ->middleware('master.permission:manage')->name('store');
        Route::put('/{section}/{accountingOption}', [MasterDataController::class, 'update'])
            ->whereIn('section', ['party-types', 'money-account-types', 'transaction-categories'])
            ->middleware('master.permission:manage')->name('update');
        Route::delete('/{section}/{accountingOption}', [MasterDataController::class, 'destroy'])
            ->whereIn('section', ['party-types', 'money-account-types', 'transaction-categories'])
            ->middleware(['master.permission:manage', 'accounting.permission:records.delete'])->name('destroy');
    });

    Route::prefix('system')->name('system.')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('accounting.permission:users.view')->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('accounting.permission:users.manage')->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('accounting.permission:users.manage')->name('users.update');

        Route::get('/role-matrix', [RoleMatrixController::class, 'index'])
            ->middleware('accounting.permission:role_matrix.view')->name('role-matrix.index');
        Route::post('/role-matrix/roles', [RoleMatrixController::class, 'storeRole'])
            ->middleware('accounting.permission:role_matrix.manage')->name('role-matrix.roles.store');
        Route::post('/role-matrix', [RoleMatrixController::class, 'update'])
            ->middleware('accounting.permission:role_matrix.manage')->name('role-matrix.update');

        Route::get('/settings', [BrandSettingsController::class, 'index'])
            ->middleware(['system.admin', 'accounting.permission:settings.manage'])->name('settings.index');
        Route::post('/settings/logo', [BrandSettingsController::class, 'updateLogo'])
            ->middleware(['system.admin', 'accounting.permission:settings.manage'])->name('settings.logo');
        Route::post('/settings/favicon', [BrandSettingsController::class, 'updateFavicon'])
            ->middleware(['system.admin', 'accounting.permission:settings.manage'])->name('settings.favicon');
    });
});

require __DIR__.'/settings.php';
