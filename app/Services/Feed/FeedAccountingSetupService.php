<?php

namespace App\Services\Feed;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\ChartOfAccount;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedWarehouse;
use App\Models\TransactionHead;
use App\Support\TransactionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeedAccountingSetupService
{
    private const INVENTORY_CODE = 'SYS-FEED-INV';

    private const SALES_CODE = 'SYS-FEED-SALES';

    private const COGS_CODE = 'SYS-FEED-COGS';

    private const PURCHASE_HEAD_CODE = 'SYS-FEED-PUR';

    private const SALE_HEAD_CODE = 'SYS-FEED-SAL';

    public function ensure(int $companyId): FeedSetting
    {
        return DB::transaction(function () use ($companyId): FeedSetting {
            $companyExists = DB::table('companies')
                ->where('id', $companyId)
                ->lockForUpdate()
                ->exists();

            if (! $companyExists) {
                throw ValidationException::withMessages([
                    'company' => 'The company connected to this user is no longer available.',
                ]);
            }

            $inventoryAccount = $this->ensureSystemAccount(
                $companyId,
                self::INVENTORY_CODE,
                'Feed Inventory',
                'Asset',
                'Debit',
            );

            $salesAccount = $this->ensureSystemAccount(
                $companyId,
                self::SALES_CODE,
                'Feed Sales',
                'Income',
                'Credit',
            );

            $cogsAccount = $this->ensureSystemAccount(
                $companyId,
                self::COGS_CODE,
                'Feed Cost of Goods Sold',
                'Expense',
                'Debit',
                [$inventoryAccount->id],
            );

            $purchaseHead = $this->ensureSystemHead(
                $companyId,
                self::PURCHASE_HEAD_CODE,
                'Feed Purchase',
                TransactionTypes::PURCHASE,
                'Supplier',
                $inventoryAccount,
            );

            $saleHead = $this->ensureSystemHead(
                $companyId,
                self::SALE_HEAD_CODE,
                'Feed Sale',
                TransactionTypes::SALE,
                'Customer',
                $salesAccount,
            );

            $this->ensureHeadRules($purchaseHead, 'Supplier', false, 'PUR');
            $this->ensureHeadRules($saleHead, 'Customer', true, 'SAL');

            $defaultWarehouseId = FeedWarehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            $existing = FeedSetting::query()
                ->with([
                    'purchaseTransactionHead.postingAccount',
                    'saleTransactionHead.postingAccount',
                    'cogsAccount',
                    'defaultWarehouse',
                ])
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $updates = [];

                if ((int) ($existing->purchase_transaction_head_id ?? 0) !== (int) $purchaseHead->id || ! $existing->purchaseTransactionHead) {
                    $updates['purchase_transaction_head_id'] = $purchaseHead->id;
                }

                if ((int) ($existing->sale_transaction_head_id ?? 0) !== (int) $saleHead->id || ! $existing->saleTransactionHead) {
                    $updates['sale_transaction_head_id'] = $saleHead->id;
                }

                if ((int) ($existing->cogs_account_id ?? 0) !== (int) $cogsAccount->id || ! $existing->cogsAccount) {
                    $updates['cogs_account_id'] = $cogsAccount->id;
                }

                $existingDefaultWarehouseId = $existing->defaultWarehouse
                    && (int) $existing->defaultWarehouse->company_id === $companyId
                    && $existing->defaultWarehouse->is_active
                        ? (int) $existing->defaultWarehouse->id
                        : null;

                if ((int) ($existingDefaultWarehouseId ?? 0) !== (int) ($defaultWarehouseId ?? 0)) {
                    $updates['default_tracking_unit_id'] = $defaultWarehouseId;
                }

                if ($updates !== []) {
                    $existing->update($updates);
                }

                return $existing->fresh([
                    'purchaseTransactionHead.postingAccount',
                    'saleTransactionHead.postingAccount',
                    'cogsAccount',
                    'defaultWarehouse',
                ]);
            }

            $settings = FeedSetting::query()->create([
                'company_id' => $companyId,
                'purchase_transaction_head_id' => $purchaseHead->id,
                'sale_transaction_head_id' => $saleHead->id,
                'cogs_account_id' => $cogsAccount->id,
                'default_tracking_unit_id' => $defaultWarehouseId,
            ]);

            return $settings->fresh([
                'purchaseTransactionHead.postingAccount',
                'saleTransactionHead.postingAccount',
                'cogsAccount',
                'defaultWarehouse',
            ]);
        }, attempts: 5);
    }

    /** @param array<int, int> $excludedIds */
    private function ensureSystemAccount(
        int $companyId,
        string $baseCode,
        string $name,
        string $type,
        string $normalBalance,
        array $excludedIds = [],
    ): ChartOfAccount {
        $matchingByName = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('type', $type)
            ->where('level', 3)
            ->whereNotIn('id', $excludedIds)
            ->orderByDesc('is_active')
            ->first();

        if ($matchingByName) {
            if (! $matchingByName->is_active) {
                $matchingByName->update(['is_active' => true]);
            }

            return $matchingByName->refresh();
        }

        $candidate = $baseCode;
        $suffix = 2;

        while (true) {
            $existing = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [strtolower($candidate)])
                ->first();

            if (! $existing) {
                break;
            }

            if (
                $existing->type === $type
                && (int) $existing->level === 3
                && ! in_array((int) $existing->id, $excludedIds, true)
            ) {
                $existing->update([
                    'name' => $name,
                    'normal_balance' => $normalBalance,
                    'is_active' => true,
                ]);

                return $existing->refresh();
            }

            $candidate = $baseCode.'-'.$suffix++;
        }

        return ChartOfAccount::query()->create([
            'company_id' => $companyId,
            'parent_id' => null,
            'level' => 3,
            'code' => $candidate,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
        ]);
    }

    private function ensureSystemHead(
        int $companyId,
        string $baseCode,
        string $name,
        string $category,
        string $partyType,
        ChartOfAccount $postingAccount,
    ): TransactionHead {
        $candidate = $baseCode;
        $suffix = 2;

        while (true) {
            $existing = TransactionHead::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [strtolower($candidate)])
                ->first();

            if (! $existing) {
                break;
            }

            if (str_starts_with(strtoupper((string) $existing->code), 'SYS-FEED-')) {
                $existing->update([
                    'accounting_rule_id' => null,
                    'posting_account_id' => $postingAccount->id,
                    'name' => $name,
                    'category' => $category,
                    'allowed_settlements' => TransactionTypes::ALL_SETTLEMENTS,
                    'party_type' => $partyType,
                    'is_active' => true,
                ]);

                return $existing->refresh();
            }

            $candidate = $baseCode.'-'.$suffix++;
        }

        return TransactionHead::query()->create([
            'company_id' => $companyId,
            'accounting_rule_id' => null,
            'posting_account_id' => $postingAccount->id,
            'code' => $candidate,
            'name' => $name,
            'category' => $category,
            'allowed_settlements' => TransactionTypes::ALL_SETTLEMENTS,
            'party_type' => $partyType,
            'is_active' => true,
        ]);
    }

    private function ensureHeadRules(
        TransactionHead $head,
        string $partyType,
        bool $generatesInvoice,
        string $shortCode,
    ): void {
        foreach (TransactionTypes::ALL_SETTLEMENTS as $settlement) {
            $existing = AccountingRule::query()
                ->where('company_id', $head->company_id)
                ->where('transaction_head_id', $head->id)
                ->where('category', $head->category)
                ->where('settlement_type', $settlement)
                ->first();

            if ($existing) {
                continue;
            }

            $lines = $this->ruleLines((string) $head->category, $settlement);
            $firstDebit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_DEBIT);
            $firstCredit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_CREDIT);
            $baseCode = 'SYS-FEED-RULE-'.$shortCode.'-'.$settlement;
            $code = $this->availableRuleCode((int) $head->company_id, $baseCode);

            $rule = AccountingRule::query()->create([
                'company_id' => $head->company_id,
                'transaction_head_id' => $head->id,
                'code' => $code,
                'name' => $head->name.' — '.$this->settlementLabel($settlement),
                'category' => $head->category,
                'settlement_type' => $settlement,
                'debit_source' => $firstDebit['account_source'],
                'credit_source' => $firstCredit['account_source'],
                'party_required' => true,
                'party_type' => $partyType,
                'money_required' => in_array($settlement, [TransactionTypes::CASH, TransactionTypes::PARTIAL], true),
                'generates_invoice' => $generatesInvoice,
                'invoice_title' => $generatesInvoice ? 'Feed Sales Invoice' : null,
                'is_active' => true,
            ]);

            foreach ($lines as $index => $line) {
                $rule->lines()->create([
                    ...$line,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    /** @return array<int, array{line_side:string,account_source:string,amount_basis:string}> */
    private function ruleLines(string $category, string $settlement): array
    {
        if ($category === TransactionTypes::SALE) {
            return match ($settlement) {
                TransactionTypes::CASH => [
                    ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_SELECTED_MONEY, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                    ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ],
                TransactionTypes::CREDIT => [
                    ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_PARTY_RECEIVABLE, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                    ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ],
                default => [
                    ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_SELECTED_MONEY, 'amount_basis' => AccountingRuleLine::BASIS_PAID],
                    ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_PARTY_RECEIVABLE, 'amount_basis' => AccountingRuleLine::BASIS_DUE],
                    ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ],
            };
        }

        return match ($settlement) {
            TransactionTypes::CASH => [
                ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_SELECTED_MONEY, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
            ],
            TransactionTypes::CREDIT => [
                ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_PARTY_PAYABLE, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
            ],
            default => [
                ['line_side' => AccountingRuleLine::SIDE_DEBIT, 'account_source' => AccountingRule::SOURCE_HEAD_ACCOUNT, 'amount_basis' => AccountingRuleLine::BASIS_TOTAL],
                ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_SELECTED_MONEY, 'amount_basis' => AccountingRuleLine::BASIS_PAID],
                ['line_side' => AccountingRuleLine::SIDE_CREDIT, 'account_source' => AccountingRule::SOURCE_PARTY_PAYABLE, 'amount_basis' => AccountingRuleLine::BASIS_DUE],
            ],
        };
    }

    private function settlementLabel(string $settlement): string
    {
        return match ($settlement) {
            TransactionTypes::CASH => 'Paid in Full',
            TransactionTypes::CREDIT => 'Fully Due',
            default => 'Part Paid',
        };
    }

    private function availableRuleCode(int $companyId, string $baseCode): string
    {
        $candidate = $baseCode;
        $suffix = 2;

        while (AccountingRule::query()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = $baseCode.'-'.$suffix++;
        }

        return $candidate;
    }
}
