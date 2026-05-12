<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\BusinessType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PartyType;
use App\Models\SettlementType;
use App\Models\TimeZone;
use App\Models\TransactionHead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\LedgerMappingRule;

class DropdownController extends Controller
{
    public function businessTypes(): JsonResponse
    {
        return $this->ok(
            BusinessType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                ])
        );
    }

    public function currencies(): JsonResponse
    {
        return $this->ok(
            Currency::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'symbol' => $item->symbol,
                    'display_name' => trim($item->code . ' - ' . $item->name),
                ])
        );
    }

    public function timeZones(): JsonResponse
    {
        return $this->ok(
            TimeZone::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'utc_offset' => $item->utc_offset,
                    'php_timezone' => $item->php_timezone,
                    'display_name' => trim($item->utc_offset . ' - ' . $item->name),
                ])
        );
    }

    public function accountTypes(): JsonResponse
    {
        return $this->ok(
            AccountType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'normal_balance' => $item->normal_balance,
                    'display_name' => $item->name,
                ])
        );
    }

    public function partyTypes(): JsonResponse
    {
        return $this->ok(
            PartyType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                ])
        );
    }

    public function banks(): JsonResponse
    {
        return $this->ok(
            Bank::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('bank_name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'bank_name' => $item->bank_name,
                    'short_name' => $item->short_name,
                    'name' => $item->bank_name,
                    'display_name' => $item->short_name
                        ? $item->bank_name . ' (' . $item->short_name . ')'
                        : $item->bank_name,
                ])
        );
    }

    public function cashBankAccountTypes(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Cash', 'name' => 'Cash', 'display_name' => 'Cash'],
            ['id' => 'Bank', 'name' => 'Bank', 'display_name' => 'Bank'],
            ['id' => 'Mobile Banking', 'name' => 'Mobile Banking', 'display_name' => 'Mobile Banking'],
        ]);
    }

    public function partyBalanceTypes(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Debit', 'name' => 'Debit', 'display_name' => 'Debit'],
            ['id' => 'Credit', 'name' => 'Credit', 'display_name' => 'Credit'],
        ]);
    }

    public function transactionHeadNatures(): JsonResponse
    {
        return $this->ok([
            ['id' => 'Payment', 'name' => 'Payment', 'display_name' => 'Payment'],
            ['id' => 'Receipt', 'name' => 'Receipt', 'display_name' => 'Receipt'],
            ['id' => 'Due', 'name' => 'Due', 'display_name' => 'Due'],
            ['id' => 'Advance', 'name' => 'Advance', 'display_name' => 'Advance'],
            ['id' => 'Adjustment', 'name' => 'Adjustment', 'display_name' => 'Adjustment'],
        ]);
    }

    public function yesNoOptions(): JsonResponse
    {
        return $this->ok([
            ['id' => 1, 'name' => 'Yes', 'display_name' => 'Yes'],
            ['id' => 0, 'name' => 'No', 'display_name' => 'No'],
        ]);
    }

    public function transactionHeads(): JsonResponse
    {
        return $this->ok(
            TransactionHead::query()
                ->where('status', 'Active')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                    'nature' => $item->nature,
                    'requires_party' => $item->requires_party,
                    'requires_reference' => $item->requires_reference,
                ])
        );
    }

    public function settlementTypes(): JsonResponse
    {
        return $this->ok(
            SettlementType::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                ])
        );
    }

    public function parentAccounts(Request $request): JsonResponse
    {
        $query = ChartOfAccount::query()
            ->where('status', 'Active');

        if ($request->filled('account_type_id')) {
            $query->where('account_type_id', $request->integer('account_type_id'));
        }

        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', $request->integer('exclude_id'));
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->map(fn ($account) => $this->formatAccount($account))
        );
    }

    public function cashBankLedgers(): JsonResponse
    {
        return $this->ok(
            ChartOfAccount::query()
                ->where('status', 'Active')
                ->where('is_cash_bank', true)
                ->orderBy('account_code')
                ->get()
                ->map(fn ($account) => $this->formatAccount($account))
        );
    }

    public function ledgerAccounts(): JsonResponse
    {
        return $this->ok(
            ChartOfAccount::query()
                ->where('status', 'Active')
                ->orderBy('account_code')
                ->get()
                ->map(fn ($account) => $this->formatAccount($account))
        );
    }

    private function formatAccount(ChartOfAccount $account): array
    {
        $displayName = trim($account->account_code . ' - ' . $account->account_name);

        return [
            'id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'name' => $account->account_name,
            'display_name' => $account->display_name ?: $displayName,
        ];
    }
    public function partyLedgerEffects(): JsonResponse
    {
        return $this->ok(
            collect(LedgerMappingRule::PARTY_EFFECTS)->map(fn ($effect) => [
                'id' => $effect,
                'name' => $effect,
                'display_name' => $effect,
            ])->values()
        );
    }

    private function ok($data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
