<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashBankAccountRequest;
use App\Models\CashBankAccount;
use App\Services\Setup\CashBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CashBankAccountController extends Controller
{
    public function index(): View
    {
        $accounts = CashBankAccount::query()
            ->with(['linkedLedger', 'bank'])
            ->orderBy('cash_bank_name')
            ->get();

        return view('setup.cash-bank-accounts', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        CashBankAccountRequest $request,
        CashBankAccountService $service
    ): JsonResponse {
        $account = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash / Bank account saved successfully.',
            'data' => $account->load(['linkedLedger', 'bank']),
            'redirect' => route('setup.cash-bank-accounts'),
        ], 201);
    }
}
