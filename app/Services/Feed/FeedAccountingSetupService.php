<?php

namespace App\Services\Feed;

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

            $inventoryAccount = $this->validAccount(
                $existing?->purchaseTransactionHead?->postingAccount,
                $companyId,
                'Asset',
            ) ?: $this->ensureSystemAccount(
                $companyId,
                self::INVENTORY_CODE,
                'Feed Inventory',
                'Asset',
                'Debit',
            );

            $salesAccount = $this->validAccount(
                $existing?->saleTransactionHead?->postingAccount,
                $companyId,
                'Income',
            ) ?: $this->ensureSystemAccount(
                $companyId,
                self::SALES_CODE,
                'Feed Sales',
                'Income',
                'Credit',
            );

            $cogsAccount = $this->validAccount(
                $existing?->cogsAccount,
                $companyId,
                'Expense',
            );

            if (! $cogsAccount || $cogsAccount->is($inventoryAccount)) {
                $cogsAccount = $this->ensureSystemAccount(
                    $companyId,
                    self::COGS_CODE,
                    'Feed Cost of Goods Sold',
                    'Expense',
                    'Debit',
                    [$inventoryAccount->id],
                );
            }

            // Feed inventory documents use dedicated internal heads. Existing valid
            // posting ledgers are preserved, but generic transaction heads are never
            // reused because that would allow stock-changing entries to be posted
            // outside the Feed Purchase/Feed Sale workflow.
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

            $defaultWarehouseId = $existing?->defaultWarehouse
                && (int) $existing->defaultWarehouse->company_id === $companyId
                && $existing->defaultWarehouse->is_active
                    ? (int) $existing->defaultWarehouse->id
                    : FeedWarehouse::query()
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->value('id');

            $settings = FeedSetting::query()->updateOrCreate(
                ['company_id' => $companyId],
                [
                    'purchase_transaction_head_id' => $purchaseHead->id,
                    'sale_transaction_head_id' => $saleHead->id,
                    'cogs_account_id' => $cogsAccount->id,
                    'default_warehouse_id' => $defaultWarehouseId,
                ],
            );

            return $settings->fresh([
                'purchaseTransactionHead.postingAccount',
                'saleTransactionHead.postingAccount',
                'cogsAccount',
                'defaultWarehouse',
            ]);
        }, attempts: 5);
    }

    private function validAccount(
        ?ChartOfAccount $account,
        int $companyId,
        string $type,
    ): ?ChartOfAccount {
        if (
            ! $account
            || (int) $account->company_id !== $companyId
            || ! $account->is_active
            || $account->type !== $type
            || (int) $account->level !== 3
        ) {
            return null;
        }

        return $account;
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
}
