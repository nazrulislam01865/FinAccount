<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Throwable;
use Illuminate\Http\Request;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Requests\LedgerMappingRuleRequest;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use App\Services\Setup\LedgerMappingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LedgerMappingController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $rules = LedgerMappingRule::query()
            ->with([
                'transactionHead',
                'settlementType',
                'debitAccount.accountType',
                'creditAccount.accountType',
            ])
            ->orderByDesc('id')
            ->get();

        $transactionHeads = TransactionHead::query()
            ->where('status', 'Active')
            ->with('settlementTypes')
            ->orderBy('name')
            ->get();

        $settlementTypes = SettlementType::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $accounts = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->with('accountType')
            ->orderBy('account_code')
            ->orderBy('account_name')
            ->get();

        return view('setup.ledger-mapping', [
            'rules' => $rules,
            'transactionHeads' => $transactionHeads,
            'settlementTypes' => $settlementTypes,
            'accounts' => $accounts,
            'partyEffects' => LedgerMappingRule::PARTY_EFFECTS,
        ]);
    }

    public function store(
        LedgerMappingRuleRequest $request,
        LedgerMappingRuleService $service
    ): JsonResponse {
        $rule = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Ledger mapping rule saved successfully.',
            'data' => $rule,
            'redirect' => route('setup.ledger-mapping'),
        ], 201);
    }

    public function update(
        LedgerMappingRuleRequest $request,
        LedgerMappingRule $ledgerMappingRule,
        LedgerMappingRuleService $service
    ): JsonResponse {
        $rule = $service->update(
            $ledgerMappingRule,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Ledger mapping rule updated successfully.',
            'data' => $rule,
            'redirect' => route('setup.ledger-mapping'),
        ]);
    }

    public function destroy(
        Request $request,
        LedgerMappingRule $ledgerMappingRule,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        try {
            $deleteService->deleteLedgerMappingRule($ledgerMappingRule);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'setup.ledger-mapping',
                'This ledger mapping rule could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'setup.ledger-mapping',
            'Ledger mapping rule deleted successfully.'
        );
    }
}
