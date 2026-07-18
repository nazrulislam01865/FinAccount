<?php

namespace App\Services\Feed;

use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\Feed\FeedDocument;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedStockMovement;
use App\Models\Feed\FeedWarehouse;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Support\TransactionTypes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeedPostingService
{
    public function __construct(
        private readonly FeedLedgerPostingService $ledgerPostingService,
        private readonly FeedAccountingSetupService $accountingSetupService,
    ) {}

    /** @param array<string, mixed> $data */
    public function postPurchase(array $data, User $user): FeedDocument
    {
        return DB::transaction(function () use ($data, $user): FeedDocument {
            $settings = $this->settings((int) $user->company_id, true);
            $warehouse = $this->warehouse((int) $user->company_id, (int) $data['tracking_unit_id']);
            $preparedLines = $this->prepareLines((int) $user->company_id, (array) $data['lines']);

            $subtotal = round($preparedLines->sum('line_total'), 2);
            $commission = $this->overallCommission($subtotal, $data['overall_discount'] ?? 0);
            $transport = round((float) ($data['transport_cost'] ?? 0), 2);
            $other = round((float) ($data['other_cost'] ?? 0), 2);
            $extra = round($other - $transport, 2);
            $total = round($subtotal - $commission - $transport + $other, 2);
            $paid = round((float) ($data['paid_amount'] ?? 0), 2);

            $this->assertPaymentAmount($total, $paid);

            $transaction = $this->ledgerPostingService->postPurchase([
                'transaction_date' => $data['transaction_date'],
                'transaction_head_id' => $data['transaction_head_id'] ?? null,
                'transaction_head_id' => $data['transaction_head_id'] ?? null,
                'money_account_id' => $data['money_account_id'] ?? null,
                'party_id' => $data['party_id'],
                'amount' => $this->money($total),
                'paid_amount' => $this->money($paid),
                'reference' => null,
                'description' => $data['description'] ?? 'Feed purchase',
                'request_token' => $data['request_token'],
            ], $user, $settings);

            $existing = FeedDocument::query()->where('transaction_id', $transaction->id)->first();
            if ($existing) {
                return $existing->load(['transaction', 'warehouse', 'party', 'lines.item']);
            }

            $document = FeedDocument::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_id' => $transaction->id,
                'tracking_unit_id' => $warehouse->id,
                'party_id' => $data['party_id'],
                'created_by' => $user->id,
                'document_type' => FeedDocument::TYPE_PURCHASE,
                'external_invoice_no' => null,
                'reference' => null,
                'cost_allocation' => 'value',
                'subtotal' => $this->money($subtotal),
                'transport_cost' => $this->money($transport),
                'other_cost' => $this->money($other),
                'delivery_charge' => '0.00',
                'overall_discount' => $this->money($commission),
                'total_amount' => $this->money($total),
                'cogs_total' => '0.00',
            ]);

            $otherCostAllocations = $this->allocateExtraCost($preparedLines, $other, (string) ($data['cost_allocation'] ?? 'value'));
            $transportDeductions = $this->allocateExtraCost($preparedLines, $transport, 'value');
            $commissionAllocations = $this->allocateExtraCost($preparedLines, $commission, 'value');

            foreach ($preparedLines->values() as $index => $line) {
                $allocatedCost = ($otherCostAllocations[$index] ?? 0.0) - ($transportDeductions[$index] ?? 0.0);
                $allocatedCommission = $commissionAllocations[$index] ?? 0.0;
                $inventoryValue = round(max(0, (float) $line['line_total'] - $allocatedCommission + $allocatedCost), 2);
                $unitCost = $line['base_quantity'] > 0
                    ? round($inventoryValue / $line['base_quantity'], 6)
                    : 0.0;
                $balance = $this->lockBalance((int) $user->company_id, (int) $line['item']->id, (int) $warehouse->id);
                $quantityBefore = round((float) $balance->quantity, 4);
                $averageBefore = round((float) $balance->average_cost, 6);
                $quantityAfter = round($quantityBefore + $line['base_quantity'], 4);
                $valueBefore = round($quantityBefore * $averageBefore, 6);
                $averageAfter = $quantityAfter > 0
                    ? round(($valueBefore + $inventoryValue) / $quantityAfter, 6)
                    : 0.0;

                $document->lines()->create([
                    'company_id' => $user->company_id,
                    'feed_item_id' => $line['item']->id,
                    'quantity' => $this->quantity($line['quantity']),
                    'unit' => $line['unit'],
                    'base_quantity' => $this->quantity($line['base_quantity']),
                    'rate' => $this->money($line['rate']),
                    'discount' => $this->money($line['discount']),
                    'line_total' => $this->money($line['line_total']),
                    'allocated_cost' => $this->money($allocatedCost),
                    'unit_cost' => $this->unitCost($unitCost),
                    'cogs_total' => '0.00',
                    'batch_no' => $line['batch_no'],
                    'expiry_date' => $line['expiry_date'],
                ]);

                $balance->update([
                    'quantity' => $this->quantity($quantityAfter),
                    'average_cost' => $this->unitCost($averageAfter),
                ]);

                $document->movements()->create([
                    'company_id' => $user->company_id,
                    'transaction_id' => $transaction->id,
                    'feed_item_id' => $line['item']->id,
                    'tracking_unit_id' => $warehouse->id,
                    'movement_type' => FeedStockMovement::TYPE_PURCHASE,
                    'movement_date' => $data['transaction_date'],
                    'quantity_in' => $this->quantity($line['base_quantity']),
                    'quantity_out' => '0.0000',
                    'unit_cost' => $this->unitCost($unitCost),
                    'total_value' => $this->money($inventoryValue),
                    'quantity_before' => $this->quantity($quantityBefore),
                    'quantity_after' => $this->quantity($quantityAfter),
                    'average_cost_before' => $this->unitCost($averageBefore),
                    'average_cost_after' => $this->unitCost($averageAfter),
                    'reference' => $transaction->voucher_no,
                ]);
            }

            return $document->load(['transaction', 'warehouse', 'party', 'lines.item']);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function postSale(array $data, User $user): FeedDocument
    {
        return DB::transaction(function () use ($data, $user): FeedDocument {
            $settings = $this->settings((int) $user->company_id, true);
            $warehouse = $this->warehouse((int) $user->company_id, (int) $data['tracking_unit_id']);
            $preparedLines = $this->prepareLines((int) $user->company_id, (array) $data['lines']);

            $subtotal = round($preparedLines->sum('line_total'), 2);
            $commission = $this->overallCommission($subtotal, $data['overall_discount'] ?? 0);
            $transport = round((float) ($data['transport_cost'] ?? 0), 2);
            $other = round((float) ($data['other_cost'] ?? 0), 2);
            $extra = round($transport + $other, 2);
            $total = round($subtotal - $commission + $extra, 2);
            $paid = round((float) ($data['paid_amount'] ?? 0), 2);

            $this->assertPaymentAmount($total, $paid);

            $transaction = $this->ledgerPostingService->postSale([
                'transaction_date' => $data['transaction_date'],
                'money_account_id' => $data['money_account_id'] ?? null,
                'party_id' => $data['party_id'],
                'amount' => $this->money($total),
                'paid_amount' => $this->money($paid),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'Feed sale',
                'request_token' => $data['request_token'],
                'selling_type' => $data['selling_type'] ?? null,
                'tracking_unit_id' => $warehouse->id,
            ], $user, $settings);

            $existing = FeedDocument::query()->where('transaction_id', $transaction->id)->first();
            if ($existing) {
                return $existing->load(['transaction', 'warehouse', 'party', 'lines.item']);
            }

            $document = FeedDocument::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_id' => $transaction->id,
                'tracking_unit_id' => $warehouse->id,
                'party_id' => $data['party_id'],
                'created_by' => $user->id,
                'document_type' => FeedDocument::TYPE_SALE,
                'external_invoice_no' => null,
                'reference' => $data['reference'] ?? null,
                'cost_allocation' => null,
                'subtotal' => $this->money($subtotal),
                'transport_cost' => $this->money($transport),
                'other_cost' => $this->money($other),
                'delivery_charge' => '0.00',
                'overall_discount' => $this->money($commission),
                'total_amount' => $this->money($total),
                'cogs_total' => '0.00',
            ]);

            $cogsTotal = 0.0;

            foreach ($preparedLines as $line) {
                $balance = $this->lockBalance((int) $user->company_id, (int) $line['item']->id, (int) $warehouse->id);
                $quantityBefore = round((float) $balance->quantity, 4);
                $averageBefore = round((float) $balance->average_cost, 6);

                if ($line['base_quantity'] > $quantityBefore + 0.00005) {
                    throw ValidationException::withMessages([
                        'lines' => $line['item']->name.' has only '.$this->quantity($quantityBefore).' KG available in '.$warehouse->name.'.',
                    ]);
                }

                $quantityAfter = round($quantityBefore - $line['base_quantity'], 4);
                $lineCogs = round($line['base_quantity'] * $averageBefore, 2);
                $cogsTotal = round($cogsTotal + $lineCogs, 2);
                $averageAfter = $quantityAfter > 0 ? $averageBefore : 0.0;

                $document->lines()->create([
                    'company_id' => $user->company_id,
                    'feed_item_id' => $line['item']->id,
                    'quantity' => $this->quantity($line['quantity']),
                    'unit' => $line['unit'],
                    'base_quantity' => $this->quantity($line['base_quantity']),
                    'rate' => $this->money($line['rate']),
                    'discount' => $this->money($line['discount']),
                    'line_total' => $this->money($line['line_total']),
                    'allocated_cost' => '0.00',
                    'unit_cost' => $this->unitCost($averageBefore),
                    'cogs_total' => $this->money($lineCogs),
                    'batch_no' => null,
                    'expiry_date' => null,
                ]);

                $balance->update([
                    'quantity' => $this->quantity($quantityAfter),
                    'average_cost' => $this->unitCost($averageAfter),
                ]);

                $document->movements()->create([
                    'company_id' => $user->company_id,
                    'transaction_id' => $transaction->id,
                    'feed_item_id' => $line['item']->id,
                    'tracking_unit_id' => $warehouse->id,
                    'movement_type' => FeedStockMovement::TYPE_SALE,
                    'movement_date' => $data['transaction_date'],
                    'quantity_in' => '0.0000',
                    'quantity_out' => $this->quantity($line['base_quantity']),
                    'unit_cost' => $this->unitCost($averageBefore),
                    'total_value' => $this->money($lineCogs),
                    'quantity_before' => $this->quantity($quantityBefore),
                    'quantity_after' => $this->quantity($quantityAfter),
                    'average_cost_before' => $this->unitCost($averageBefore),
                    'average_cost_after' => $this->unitCost($averageAfter),
                    'reference' => $transaction->voucher_no,
                ]);
            }

            $document->update(['cogs_total' => $this->money($cogsTotal)]);
            $this->appendCostOfGoodsJournal($transaction, $settings, $cogsTotal);

            return $document->load(['transaction', 'warehouse', 'party', 'lines.item']);
        }, attempts: 5);
    }

    public function reverseDocument(FeedDocument $document): void
    {
        $document->loadMissing(['movements', 'transaction']);

        $groups = $document->movements
            ->sortBy('id')
            ->groupBy(fn (FeedStockMovement $movement): string => $movement->feed_item_id.':'.$movement->tracking_unit_id);

        foreach ($groups as $movements) {
            /** @var FeedStockMovement $first */
            $first = $movements->first();
            /** @var FeedStockMovement $last */
            $last = $movements->last();

            $hasLaterMovement = FeedStockMovement::query()
                ->where('company_id', $document->company_id)
                ->where('feed_item_id', $first->feed_item_id)
                ->where('tracking_unit_id', $first->tracking_unit_id)
                ->where('id', '>', $last->id)
                ->where('feed_document_id', '!=', $document->id)
                ->exists();

            if ($hasLaterMovement) {
                throw ValidationException::withMessages([
                    'transaction' => 'This feed transaction cannot be deleted because later stock movements exist. Delete the newest related feed movements first.',
                ]);
            }

            $balance = $this->lockBalance(
                (int) $document->company_id,
                (int) $first->feed_item_id,
                (int) $first->tracking_unit_id,
            );

            $balance->update([
                'quantity' => $this->quantity((float) $first->quantity_before),
                'average_cost' => $this->unitCost((float) $first->average_cost_before),
            ]);
        }

        $document->delete();
    }

    private function appendCostOfGoodsJournal(Transaction $transaction, FeedSetting $settings, float $cogsTotal): void
    {
        if ($cogsTotal <= 0) {
            return;
        }

        $inventoryAccount = $this->inventoryAccountForCogs($settings, (int) $transaction->company_id);
        $cogsAccount = $settings->cogsAccount;

        if (! $inventoryAccount || ! $cogsAccount) {
            throw ValidationException::withMessages([
                'feed_setup' => 'Feed inventory or cost-of-goods account is no longer available.',
            ]);
        }

        if ($inventoryAccount->is($cogsAccount)) {
            throw ValidationException::withMessages([
                'feed_setup' => 'Feed Inventory and Feed Cost of Goods Sold must be different COA accounts.',
            ]);
        }

        /** @var JournalEntry $journalEntry */
        $journalEntry = $transaction->journalEntry()->lockForUpdate()->firstOrFail();
        $sequence = (int) $journalEntry->lines()->max('sequence');
        $description = 'Feed cost of goods sold for '.$transaction->voucher_no;

        $journalEntry->lines()->create([
            'company_id' => $transaction->company_id,
            'chart_of_account_id' => $cogsAccount->id,
            'money_account_id' => null,
            'party_id' => null,
            'sequence' => ++$sequence,
            'description' => $description,
            'debit' => $this->money($cogsTotal),
            'credit' => '0.00',
        ]);

        $journalEntry->lines()->create([
            'company_id' => $transaction->company_id,
            'chart_of_account_id' => $inventoryAccount->id,
            'money_account_id' => null,
            'party_id' => null,
            'sequence' => ++$sequence,
            'description' => $description,
            'debit' => '0.00',
            'credit' => $this->money($cogsTotal),
        ]);
    }

    private function settings(int $companyId, bool $lock = false): FeedSetting
    {
        $this->accountingSetupService->ensure($companyId);

        $query = FeedSetting::query()
            ->with([
                'purchaseTransactionHead.postingAccount',
                'saleTransactionHead.postingAccount',
                'cogsAccount',
                'defaultWarehouse',
            ])
            ->where('company_id', $companyId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $settings = $query->firstOrFail();
        $cogsAccount = $settings->cogsAccount;

        if (
            ! $cogsAccount
            || (int) $cogsAccount->company_id !== $companyId
            || ! $cogsAccount->is_active
            || $cogsAccount->type !== 'Expense'
            || (int) $cogsAccount->level !== 3
        ) {
            throw ValidationException::withMessages([
                'feed_setup' => 'Feed Cost of Goods Sold account is missing or inactive. Repair the Feed setup before posting.',
            ]);
        }

        return $settings;
    }

    private function inventoryAccountForCogs(FeedSetting $settings, int $companyId): ?ChartOfAccount
    {
        $account = $settings->purchaseTransactionHead?->postingAccount;

        if (
            $account
            && (int) $account->company_id === $companyId
            && $account->is_active
            && $account->type === 'Asset'
            && (int) $account->level === 3
        ) {
            return $account;
        }

        $fallbackHead = TransactionHead::query()
            ->with('postingAccount')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(category) = ?', [strtolower(TransactionTypes::PURCHASE)])
            ->where('is_active', true)
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('type', 'Asset')
                ->where('level', 3))
            ->orderByRaw("CASE WHEN code LIKE 'SYS-FEED-%' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        return $fallbackHead?->postingAccount;
    }

    private function warehouse(int $companyId, int $warehouseId): FeedWarehouse
    {
        return FeedWarehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($warehouseId);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function prepareLines(int $companyId, array $lines): Collection
    {
        $itemIds = collect($lines)->pluck('item_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $items = FeedItem::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        if ($items->count() !== $itemIds->count()) {
            throw ValidationException::withMessages(['lines' => 'One or more selected feed items are inactive or unavailable.']);
        }

        return collect($lines)->map(function (array $line, int $index) use ($items): array {
            /** @var FeedItem $item */
            $item = $items->get((int) $line['item_id']);
            $quantity = round((float) $line['quantity'], 4);
            $rate = round((float) $line['rate'], 2);
            $gross = round($quantity * $rate, 2);

            $unit = strtoupper((string) $line['unit']);
            $baseQuantity = $unit === 'BAG'
                ? round($quantity * (float) $item->pack_size, 4)
                : $quantity;

            if ($baseQuantity <= 0) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.quantity' => 'Quantity must create a positive stock quantity.',
                ]);
            }

            return [
                'item' => $item,
                'quantity' => $quantity,
                'unit' => $unit,
                'base_quantity' => $baseQuantity,
                'rate' => $rate,
                'discount' => 0.0,
                'line_total' => $gross,
                'batch_no' => filled($line['batch_no'] ?? null) ? trim((string) $line['batch_no']) : null,
                'expiry_date' => filled($line['expiry_date'] ?? null) ? (string) $line['expiry_date'] : null,
            ];
        });
    }

    /** @return array<int, float> */
    private function allocateExtraCost(Collection $lines, float $extra, string $method): array
    {
        if ($extra <= 0 || $lines->isEmpty()) {
            return array_fill(0, $lines->count(), 0.0);
        }

        $effectiveMethod = $method === 'quantity' ? 'quantity' : 'value';
        $basis = $effectiveMethod === 'quantity'
            ? $lines->sum('base_quantity')
            : $lines->sum('line_total');

        if ($basis <= 0) {
            $effectiveMethod = 'quantity';
            $basis = $lines->sum('base_quantity');
        }

        $allocated = [];
        $running = 0.0;
        $lastIndex = $lines->count() - 1;

        foreach ($lines->values() as $index => $line) {
            if ($index === $lastIndex) {
                $share = round($extra - $running, 2);
            } else {
                $lineBasis = $effectiveMethod === 'quantity'
                    ? $line['base_quantity']
                    : $line['line_total'];
                $share = round($extra * ($lineBasis / $basis), 2);
                $running = round($running + $share, 2);
            }
            $allocated[$index] = $share;
        }

        return $allocated;
    }

    private function lockBalance(int $companyId, int $itemId, int $warehouseId): FeedStockBalance
    {
        $balance = FeedStockBalance::query()
            ->where('company_id', $companyId)
            ->where('feed_item_id', $itemId)
            ->where('tracking_unit_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        try {
            FeedStockBalance::query()->create([
                'company_id' => $companyId,
                'feed_item_id' => $itemId,
                'tracking_unit_id' => $warehouseId,
                'quantity' => '0.0000',
                'average_cost' => '0.000000',
            ]);
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw $exception;
            }
        }

        return FeedStockBalance::query()
            ->where('company_id', $companyId)
            ->where('feed_item_id', $itemId)
            ->where('tracking_unit_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function overallCommission(float $subtotal, mixed $value): float
    {
        $commissionPercent = $this->percentage($value);
        $commission = round($subtotal * ($commissionPercent / 100), 2);

        if ($commission > $subtotal) {
            throw ValidationException::withMessages([
                'overall_discount' => 'Overall commission percentage cannot be greater than 100%.',
            ]);
        }

        return $commission;
    }

    private function assertPaymentAmount(float $total, float $paid): void
    {
        if ($total <= 0) {
            throw ValidationException::withMessages(['lines' => 'The feed transaction total must be greater than zero.']);
        }

        if ($paid > $total + 0.005) {
            throw ValidationException::withMessages(['paid_amount' => 'Paid/received amount cannot be greater than the transaction total.']);
        }
    }

    private function percentage(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $normalized = trim(str_replace(['%', ','], '', (string) $value));

        return max(0.0, round((float) $normalized, 4));
    }

    private function money(float|int|string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function quantity(float|int|string $value): string
    {
        return number_format(round((float) $value, 4), 4, '.', '');
    }

    private function unitCost(float|int|string $value): string
    {
        return number_format(round((float) $value, 6), 6, '.', '');
    }
}
