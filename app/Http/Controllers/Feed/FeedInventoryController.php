<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedStockMovement;
use App\Models\Feed\FeedWarehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FeedInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $search = trim($request->string('search')->toString());
        $warehouseId = (int) $request->query('tracking_unit_id', 0);
        $status = $request->string('status')->toString();

        $balances = FeedStockBalance::query()
            ->with(['item', 'warehouse'])
            ->where('company_id', $companyId)
            ->when($warehouseId > 0, fn (Builder $query) => $query->where('tracking_unit_id', $warehouseId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('item', fn (Builder $itemQuery) => $itemQuery
                    ->where(fn (Builder $searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")));
            })
            ->orderBy('tracking_unit_id')
            ->orderBy('feed_item_id')
            ->get();

        $movementTotals = FeedStockMovement::query()
            ->selectRaw('feed_item_id, tracking_unit_id, SUM(quantity_in) as purchased, SUM(quantity_out) as sold')
            ->where('company_id', $companyId)
            ->groupBy('feed_item_id', 'tracking_unit_id')
            ->get()
            ->keyBy(fn ($row): string => $row->feed_item_id.':'.$row->tracking_unit_id);

        $rows = $balances->map(function (FeedStockBalance $balance) use ($movementTotals): array {
            $totals = $movementTotals->get($balance->feed_item_id.':'.$balance->tracking_unit_id);
            $quantity = (float) $balance->quantity;
            $reorder = (float) $balance->item->reorder_level * (float) $balance->item->pack_size;
            $stockStatus = $quantity <= 0 ? 'out' : ($reorder > 0 && $quantity <= $reorder ? 'low' : 'in');

            return [
                'balance' => $balance,
                'purchased' => (float) ($totals->purchased ?? 0),
                'sold' => (float) ($totals->sold ?? 0),
                'stock_value' => round($quantity * (float) $balance->average_cost, 2),
                'status' => $stockStatus,
            ];
        })->when(in_array($status, ['in', 'low', 'out'], true), fn ($collection) => $collection->where('status', $status))->values();

        $recentMovements = FeedStockMovement::query()
            ->with(['item', 'warehouse', 'transaction.party', 'document'])
            ->where('company_id', $companyId)
            ->when($warehouseId > 0, fn (Builder $query) => $query->where('tracking_unit_id', $warehouseId))
            ->latest('id')
            ->limit(100)
            ->get();

        $warehouses = FeedWarehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $metrics = [
            'items' => $rows->filter(fn (array $row): bool => (float) $row['balance']->quantity > 0)->count(),
            'stock_value' => $rows->sum('stock_value'),
            'purchased' => $rows->sum('purchased'),
            'sold' => $rows->sum('sold'),
            'low' => $rows->where('status', 'low')->count(),
            'out' => $rows->where('status', 'out')->count(),
        ];

        return view('feed.inventory.index', compact(
            'rows', 'recentMovements', 'warehouses', 'metrics', 'search', 'warehouseId', 'status',
        ));
    }
}
