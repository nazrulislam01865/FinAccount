<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Setup\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        $accounts = ChartOfAccount::query()
            ->with(['accountType', 'parent'])
            ->orderBy('account_code')
            ->get();

        return view('setup.chart-of-accounts', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        ChartOfAccountRequest $request,
        ChartOfAccountService $service
    ): JsonResponse {
        $account = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Account saved successfully.',
            'data' => $account->load(['accountType', 'parent']),
            'redirect' => route('setup.chart-of-accounts'),
        ], 201);
    }
}
