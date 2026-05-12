<?php

use App\Http\Controllers\Api\DropdownController;
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
|
| Example URLs:
| /api/dropdowns/business-types
| /api/dropdowns/currencies
| /api/dropdowns/time-zones
|
| Because these routes are in api.php, Laravel automatically prefixes them
| with /api.
*/
Route::middleware(['auth:sanctum'])->prefix('dropdowns')->name('api.dropdowns.')->group(function () {
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

    Route::get('/cash-bank-ledgers', [DropdownController::class, 'cashBankLedgers'])
        ->name('cash-bank-ledgers');

    Route::get('/ledger-accounts', [DropdownController::class, 'ledgerAccounts'])
        ->name('ledger-accounts');

    Route::get('/linked-ledgers', [DropdownController::class, 'ledgerAccounts'])
        ->name('linked-ledgers');

    Route::get('/settlement-types', [DropdownController::class, 'settlementTypes'])
        ->name('settlement-types');

    Route::get('/party-ledger-effects', [DropdownController::class, 'partyLedgerEffects'])
        ->name('party-ledger-effects');
});
