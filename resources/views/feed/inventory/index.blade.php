@php
    $alertRows = $rows->whereIn('status', ['low', 'out'])->values();
    $recentMovementCards = $recentMovements->take(8);
@endphp

<x-layouts::accounting title="Feed Inventory">
    <div class="feed-ui">
        <div class="feed-page-heading">
            <div>
                <h1>Feed Inventory</h1>
                <p>Monitor purchased quantity, sold quantity, stock value, reorder needs, and recent movements.</p>
            </div>
            <div class="feed-heading-actions">
                @if(auth()->user()?->canAccounting('transactions.manage'))
                    <a class="feed-btn" href="{{ route('feed.sales.create') }}">New Feed Sale</a>
                    <a class="feed-btn feed-btn-primary" href="{{ route('feed.purchases.create') }}">＋ New Feed Purchase</a>
                @endif
            </div>
        </div>

        @include('feed.partials.tabs')

        <div class="feed-metric-grid">
            <div class="feed-metric">
                <div class="feed-metric-icon">▦</div>
                <div class="feed-metric-label">Stocked Feed Items</div>
                <div class="feed-metric-value">{{ $metrics['items'] }}</div>
                <div class="feed-metric-foot">Across {{ $warehouses->count() }} active warehouse{{ $warehouses->count() === 1 ? '' : 's' }}</div>
            </div>
            <div class="feed-metric">
                <div class="feed-metric-icon">৳</div>
                <div class="feed-metric-label">Current Stock Value</div>
                <div class="feed-metric-value">{{ \App\Support\CompanyContext::money($metrics['stock_value']) }}</div>
                <div class="feed-metric-foot">Weighted-average valuation</div>
            </div>
            <div class="feed-metric">
                <div class="feed-metric-icon">＋</div>
                <div class="feed-metric-label">Total Purchased</div>
                <div class="feed-metric-value">{{ number_format($metrics['purchased'], 2) }} KG</div>
                <div class="feed-metric-foot">All posted feed purchases</div>
            </div>
            <div class="feed-metric">
                <div class="feed-metric-icon">−</div>
                <div class="feed-metric-label">Total Sold</div>
                <div class="feed-metric-value">{{ number_format($metrics['sold'], 2) }} KG</div>
                <div class="feed-metric-foot">{{ $metrics['low'] }} low and {{ $metrics['out'] }} out of stock</div>
            </div>
        </div>

        <section class="feed-card">
            <div class="feed-card-header feed-card-header-responsive">
                <div>
                    <div class="feed-card-title">Stock Position</div>
                    <div class="feed-card-sub">Quantity is shown in kilograms and equivalent bags. Purchased and sold totals come from posted stock movements.</div>
                </div>
                <form method="GET" class="feed-toolbar">
                    <div class="feed-search-box">
                        <span class="feed-search-icon">⌕</span>
                        <input class="feed-control" type="search" name="search" value="{{ $search }}" placeholder="Search item, code or brand">
                    </div>
                    <select class="feed-control" name="tracking_unit_id" data-hg-searchable-ignore>
                        <option value="">All warehouses</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <select class="feed-control" name="status" data-hg-searchable-ignore>
                        <option value="">All stock status</option>
                        <option value="in" @selected($status === 'in')>In Stock</option>
                        <option value="low" @selected($status === 'low')>Low Stock</option>
                        <option value="out" @selected($status === 'out')>Out of Stock</option>
                    </select>
                    <button class="feed-btn feed-btn-primary" type="submit">Apply</button>
                    <a class="feed-btn" href="{{ route('feed.inventory.index') }}">Clear</a>
                </form>
            </div>

            <div class="feed-card-body">
                @if($rows->isEmpty())
                    <div class="feed-empty-note">No feed stock is available yet. Post a Feed Purchase to create the first stock movement.</div>
                @else
                    <div class="feed-table-wrap">
                        <table class="feed-table feed-inventory-table">
                            <thead>
                                <tr>
                                    <th>Feed Item</th>
                                    <th>Category</th>
                                    <th>Warehouse</th>
                                    <th>Purchased</th>
                                    <th>Sold</th>
                                    <th>On Hand</th>
                                    <th>Average Cost</th>
                                    <th>Stock Value</th>
                                    <th>Reorder Level</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $balance = $row['balance'];
                                        $item = $balance->item;
                                        $packSize = (float) $item->pack_size;
                                        $bags = $packSize > 0 ? (float) $balance->quantity / $packSize : 0;
                                        $purchasedBags = $packSize > 0 ? $row['purchased'] / $packSize : 0;
                                        $soldBags = $packSize > 0 ? $row['sold'] / $packSize : 0;
                                        $reorderKg = (float) $item->reorder_level * $packSize;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="feed-stock-name">{{ $item->name }}</div>
                                            <div class="feed-stock-code">{{ $item->code }}{{ $item->brand ? ' · '.$item->brand : '' }}</div>
                                        </td>
                                        <td>{{ $item->category ?: '—' }}</td>
                                        <td><strong>{{ $balance->warehouse->name }}</strong><div class="feed-small">{{ $balance->warehouse->code }}</div></td>
                                        <td><strong class="feed-positive">{{ number_format($row['purchased'], 2) }} KG</strong><div class="feed-small">{{ number_format($purchasedBags, 2) }} bags</div></td>
                                        <td><strong class="feed-negative">{{ number_format($row['sold'], 2) }} KG</strong><div class="feed-small">{{ number_format($soldBags, 2) }} bags</div></td>
                                        <td><span class="feed-stock-qty">{{ number_format((float) $balance->quantity, 2) }} KG</span><div class="feed-small">{{ number_format($bags, 2) }} bags</div></td>
                                        <td>{{ \App\Support\CompanyContext::money((float) $balance->average_cost) }}<div class="feed-small">per KG</div></td>
                                        <td><strong>{{ \App\Support\CompanyContext::money($row['stock_value']) }}</strong></td>
                                        <td>{{ number_format($reorderKg, 2) }} KG<div class="feed-small">{{ number_format((float) $item->reorder_level, 2) }} bags</div></td>
                                        <td>
                                            @if($row['status'] === 'out')
                                                <span class="feed-status feed-status-red">Out of Stock</span>
                                            @elseif($row['status'] === 'low')
                                                <span class="feed-status feed-status-amber">Low Stock</span>
                                            @else
                                                <span class="feed-status feed-status-green">In Stock</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <div class="feed-split-panels">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Recent Stock Movements</div>
                        <div class="feed-card-sub">Latest posted purchase and sale entries.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    @if($recentMovementCards->isEmpty())
                        <div class="feed-empty-note">No feed stock movements found.</div>
                    @else
                        <div class="feed-movement-list">
                            @foreach($recentMovementCards as $movement)
                                <div class="feed-movement-item">
                                    <div>
                                        <strong>{{ $movement->item->name }}</strong>
                                        <span>{{ $movement->movement_date->format('Y-m-d') }} · {{ $movement->transaction->voucher_no }} · {{ $movement->warehouse->name }}</span>
                                    </div>
                                    <div class="{{ $movement->movement_type === 'PURCHASE' ? 'feed-positive' : 'feed-negative' }}">
                                        {{ $movement->movement_type === 'PURCHASE' ? '+' : '−' }}{{ number_format((float) ($movement->movement_type === 'PURCHASE' ? $movement->quantity_in : $movement->quantity_out), 2) }} KG
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Stock Alerts</div>
                        <div class="feed-card-sub">Items needing attention.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    @if($alertRows->isEmpty())
                        <div class="feed-info-banner feed-info-green">All visible feed stock is above its reorder level.</div>
                    @else
                        <div class="feed-alert-list">
                            @foreach($alertRows->take(8) as $row)
                                @php($balance = $row['balance'])
                                <div class="feed-alert-item">
                                    <div>
                                        <strong>{{ $balance->item->name }}</strong>
                                        <span>{{ $balance->warehouse->name }} · {{ number_format((float) $balance->quantity, 2) }} KG remaining</span>
                                    </div>
                                    <span class="feed-status {{ $row['status'] === 'out' ? 'feed-status-red' : 'feed-status-amber' }}">{{ $row['status'] === 'out' ? 'Out' : 'Low' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <section class="feed-card feed-section-gap">
            <div class="feed-card-header">
                <div>
                    <div class="feed-card-title">Stock Ledger</div>
                    <div class="feed-card-sub">The voucher is the same voucher stored in Transaction Register and Journal Entries.</div>
                </div>
            </div>
            <div class="feed-card-body">
                @if($recentMovements->isEmpty())
                    <div class="feed-empty-note">No feed stock movements found.</div>
                @else
                    <div class="feed-table-wrap">
                        <table class="feed-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher</th>
                                    <th>Movement</th>
                                    <th>Item</th>
                                    <th>Warehouse</th>
                                    <th>Party</th>
                                    <th>Quantity In</th>
                                    <th>Quantity Out</th>
                                    <th>Running Balance</th>
                                    <th>Cost Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentMovements as $movement)
                                    <tr>
                                        <td>{{ $movement->movement_date->format('Y-m-d') }}</td>
                                        <td><strong>{{ $movement->transaction->voucher_no }}</strong></td>
                                        <td><span class="feed-status {{ $movement->movement_type === 'PURCHASE' ? 'feed-status-blue' : 'feed-status-green' }}">{{ $movement->movement_type === 'PURCHASE' ? 'Feed Purchase' : 'Feed Sale' }}</span></td>
                                        <td><strong>{{ $movement->item->name }}</strong><div class="feed-small">{{ $movement->item->code }}</div></td>
                                        <td>{{ $movement->warehouse->name }}</td>
                                        <td>{{ $movement->transaction->party?->name ?? '—' }}</td>
                                        <td>{{ (float) $movement->quantity_in > 0 ? number_format((float) $movement->quantity_in, 2).' KG' : '—' }}</td>
                                        <td>{{ (float) $movement->quantity_out > 0 ? number_format((float) $movement->quantity_out, 2).' KG' : '—' }}</td>
                                        <td><strong>{{ number_format((float) $movement->quantity_after, 2) }} KG</strong></td>
                                        <td>{{ \App\Support\CompanyContext::money((float) $movement->total_value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-layouts::accounting>
