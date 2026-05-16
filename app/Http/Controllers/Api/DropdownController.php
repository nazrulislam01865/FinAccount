<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\BusinessType;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\SettlementType;
use App\Models\TimeZone;
use App\Models\TransactionHead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                ->with('defaultLedger.accountType')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (PartyType $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'default_ledger_account_id' => $item->default_ledger_account_id,
                    'default_ledger_nature' => $this->inferPartyLedgerNature($item),
                    'default_ledger_name' => $item->defaultLedger?->display_name,
                    'default_ledger_account_type' => $item->defaultLedger?->accountType?->name,
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
            ['id' => 'Expense', 'name' => 'Expense', 'display_name' => 'Expense'],
            ['id' => 'Journal', 'name' => 'Journal', 'display_name' => 'Journal'],
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
                ->with(['settlementTypes' => fn ($query) => $query
                    ->where('status', 'Active')
                    ->orderBy('sort_order')
                    ->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (TransactionHead $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_name' => $item->name,
                    'nature' => $item->nature,
                    'default_party_type_id' => $item->default_party_type_id,
                    'requires_party' => (bool) $item->requires_party,
                    'requires_reference' => (bool) $item->requires_reference,
                    'settlement_type_ids' => $item->settlementTypes
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values(),
                    'settlement_types' => $item->settlementTypes->map(fn (SettlementType $settlement) => [
                        'id' => $settlement->id,
                        'name' => $settlement->name,
                        'code' => $settlement->code,
                        'display_name' => $settlement->name,
                    ])->values(),
                ])
        );
    }

    public function settlementTypes(Request $request): JsonResponse
    {
        $query = SettlementType::query()
            ->where('status', 'Active');

        if ($request->filled('transaction_head_id')) {
            $head = TransactionHead::query()
                ->where('status', 'Active')
                ->whereKey($request->integer('transaction_head_id'))
                ->first();

            if (!$head) {
                return $this->ok(collect());
            }

            $query->whereHas('transactionHeads', fn ($relation) => $relation
                ->where('transaction_heads.id', $head->id)
                ->where('transaction_heads.status', 'Active')
                ->whereNull('transaction_heads.deleted_at'));

            if ($request->boolean('mapped_only')) {
                $mappedSettlementIds = LedgerMappingRule::query()
                    ->where('transaction_head_id', $head->id)
                    ->where('status', 'Active')
                    ->whereNull('deleted_at')
                    ->pluck('settlement_type_id');

                $query->whereIn('id', $mappedSettlementIds);
            }
        }

        return $this->ok(
            $query->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (SettlementType $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'display_name' => $item->name,
                ])
        );
    }

    public function cashBankAccounts(): JsonResponse
    {
        return $this->ok(
            CashBankAccount::query()
                ->where('status', 'Active')
                ->with(['linkedLedger.accountType', 'bank'])
                ->orderBy('cash_bank_name')
                ->get()
                ->map(fn (CashBankAccount $account) => [
                    'id' => $account->id,
                    'cash_bank_code' => $account->cash_bank_code,
                    'cash_bank_name' => $account->cash_bank_name,
                    'name' => $account->cash_bank_name,
                    'type' => $account->type,

                    'linked_ledger_account_id' => $account->linked_ledger_account_id,
                    'linked_ledger_name' => $account->linkedLedger?->display_name
                        ?: trim(($account->linkedLedger?->account_code ? $account->linkedLedger->account_code . ' - ' : '') . ($account->linkedLedger?->account_name ?? '')),
                    'linked_ledger_account_type' => $account->linkedLedger?->accountType?->name,
                    'linked_ledger_normal_balance' => $account->linkedLedger?->normal_balance
                        ?: $account->linkedLedger?->accountType?->normal_balance,

                    'bank_name' => $account->bank_name ?? $account->bank?->bank_name,
                    'branch_name' => $account->branch_name,
                    'account_number' => $account->account_number,
                    'opening_balance' => $account->opening_balance,
                    'usage_note' => $account->usage_note,

                    'display_name' => trim(($account->cash_bank_code ? $account->cash_bank_code . ' - ' : '') . $account->cash_bank_name),
                ])
        );
    }

    public function parties(Request $request): JsonResponse
    {
        $query = Party::query()
            ->where('status', 'Active')
            ->with(['partyType', 'linkedLedger.accountType']);

        if ($request->filled('party_type_id')) {
            $query->where('party_type_id', $request->integer('party_type_id'));
        }

        if ($request->filled('ledger_nature')) {
            $query->where('default_ledger_nature', $request->string('ledger_nature'));
        }

        if ($request->filled('linked_ledger_account_id')) {
            $query->where('linked_ledger_account_id', $request->integer('linked_ledger_account_id'));
        }

        return $this->ok(
            $query->orderBy('party_name')
                ->get()
                ->map(fn (Party $party) => [
                    'id' => $party->id,
                    'party_code' => $party->party_code,
                    'party_name' => $party->party_name,
                    'sub_type' => $party->sub_type,
                    'default_ledger_nature' => $party->default_ledger_nature,
                    'opening_balance' => $party->opening_balance,
                    'opening_balance_type' => $party->opening_balance_type,
                    'party_type_id' => $party->party_type_id,
                    'party_type_name' => $party->partyType?->name,
                    'linked_ledger_account_id' => $party->linked_ledger_account_id,
                    'linked_ledger_name' => $party->linkedLedger?->display_name
                        ?: trim(($party->linkedLedger?->account_code ? $party->linkedLedger->account_code . ' - ' : '') . ($party->linkedLedger?->account_name ?? '')),
                    'linked_ledger_account_type' => $party->linkedLedger?->accountType?->name,
                    'linked_ledger_normal_balance' => $party->linkedLedger?->normal_balance
                        ?: $party->linkedLedger?->accountType?->normal_balance,
                    'display_name' => trim(($party->party_code ? $party->party_code . ' - ' : '') . $party->party_name),
                ])
        );
    }

    public function parentAccounts(Request $request): JsonResponse
    {
        $query = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Group')
            ->with('accountType');

        if ($request->filled('account_type_id')) {
            $query->where('account_type_id', $request->integer('account_type_id'));
        }

        if ($request->filled('exclude_id')) {
            $excludedIds = $this->descendantAccountIds($request->integer('exclude_id'));
            $excludedIds[] = $request->integer('exclude_id');

            $query->whereNotIn('id', array_unique($excludedIds));
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    public function cashBankLedgers(Request $request): JsonResponse
    {
        $usedLedgerIds = CashBankAccount::query()
            ->when($request->filled('exclude_cash_bank_account_id'), fn ($query) => $query
                ->whereKeyNot($request->integer('exclude_cash_bank_account_id')))
            ->pluck('linked_ledger_account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->ok(
            ChartOfAccount::query()
                ->where('status', 'Active')
                ->where('account_level', 'Ledger')
                ->where('is_cash_bank', true)
                ->where('posting_allowed', true)
                ->whereNotIn('id', $usedLedgerIds)
                ->whereHas('accountType', fn ($query) => $query
                    ->where('name', 'Asset')
                    ->where('normal_balance', 'Debit'))
                ->with('accountType')
                ->orderBy('account_code')
                ->get()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    public function ledgerAccounts(Request $request): JsonResponse
    {
        $partyType = $request->filled('party_type_id')
            ? PartyType::query()->find($request->integer('party_type_id'))
            : null;

        $ledgerNature = $request->input('ledger_nature')
            ?: ($partyType ? $this->inferPartyLedgerNature($partyType) : null);

        $query = ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('account_level', 'Ledger')
            ->where('posting_allowed', true)
            ->with('accountType');

        if ($request->boolean('for_party') || $partyType || $ledgerNature) {
            $query->where('is_cash_bank', false);
            $this->applyPartyLedgerNatureFilter($query, $ledgerNature);
        }

        return $this->ok(
            $query->orderBy('account_code')
                ->get()
                ->map(fn (ChartOfAccount $account) => $this->formatAccount($account))
        );
    }

    private function formatAccount(ChartOfAccount $account): array
    {
        $displayName = trim($account->account_code . ' - ' . $account->account_name);

        return [
            'id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'account_level' => $account->account_level,
            'account_type_id' => $account->account_type_id,
            'account_type' => $account->accountType?->name,
            'normal_balance' => $account->normal_balance ?: $account->accountType?->normal_balance,
            'posting_allowed' => (bool) $account->posting_allowed,
            'is_cash_bank' => (bool) $account->is_cash_bank,
            'name' => $account->account_name,
            'display_name' => $account->display_name ?: $displayName,
        ];
    }

    private function descendantAccountIds(int $accountId): array
    {
        $childrenByParent = ChartOfAccount::query()
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $stack = [$accountId];

        while ($stack) {
            $currentId = array_pop($stack);

            foreach ($childrenByParent->get($currentId, collect()) as $child) {
                $ids[] = (int) $child->id;
                $stack[] = (int) $child->id;
            }
        }

        return $ids;
    }

    private function inferPartyLedgerNature(?PartyType $partyType): string
    {
        $code = strtoupper((string) $partyType?->code);
        $name = strtoupper((string) $partyType?->name);
        $value = $code . ' ' . $name;

        if (str_contains($value, 'CUSTOMER') || str_contains($value, 'CUS') || str_contains($value, 'TENANT')) {
            return 'Receivable';
        }

        if (
            str_contains($value, 'SUPPLIER')
            || str_contains($value, 'SUP')
            || str_contains($value, 'VENDOR')
            || str_contains($value, 'LANDLORD')
        ) {
            return 'Payable';
        }

        if (str_contains($value, 'EMPLOYEE') || str_contains($value, 'DRIVER')) {
            return 'Payable';
        }

        if (str_contains($value, 'OWNER')) {
            return 'No Effect';
        }

        return 'No Effect';
    }

    private function applyPartyLedgerNatureFilter($query, ?string $ledgerNature): void
    {
        match ($ledgerNature) {
            'Receivable', 'Advance Paid' => $query->whereHas('accountType', fn ($relation) => $relation
                ->where('name', 'Asset')
                ->where('normal_balance', 'Debit')),

            'Payable', 'Advance Received' => $query->whereHas('accountType', fn ($relation) => $relation
                ->where('name', 'Liability')
                ->where('normal_balance', 'Credit')),

            'No Effect' => $query->whereHas('accountType', fn ($relation) => $relation
                ->whereIn('name', ['Asset', 'Liability', 'Equity'])),

            default => null,
        };
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