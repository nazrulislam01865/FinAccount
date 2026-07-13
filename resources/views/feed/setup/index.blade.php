<x-layouts::accounting title="Feed Setup">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Feed Business & Inventory</div>
            <h1>Feed Setup</h1>
            <p>Manage feed items and warehouses. Feed purchases and sales post directly to the main accounting ledger without any manual accounting connection.</p>
        </div>
    </div>

    @include('feed.partials.tabs')

    <div class="hg-notice">
        <strong>Direct ledger posting is active.</strong> Purchases debit <strong>{{ $settings->purchaseTransactionHead->postingAccount->name }}</strong>; sales credit <strong>{{ $settings->saleTransactionHead->postingAccount->name }}</strong>; and every sale automatically posts COGS to <strong>{{ $settings->cogsAccount->name }}</strong>. Cash, bank, supplier payable, and customer receivable are selected directly from each feed transaction.
    </div>

    <div class="hg-grid hg-grid-2 hg-feed-section-gap">
        <section class="hg-card">
            <div class="hg-feed-card-heading">
                <div><h2 class="hg-card-title">Feed Items</h2><p>Prices are default suggestions per bag. Inventory is always stored in kilograms.</p></div>
            </div>
            @if($items->isEmpty())
                <div class="hg-empty">No feed item added yet.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead><tr><th>Item</th><th>Pack</th><th class="right">Default Prices</th><th class="right">Stock</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td><strong>{{ $item->name }}</strong><br><span class="hg-muted">{{ $item->code }}{{ $item->brand ? ' · '.$item->brand : '' }}{{ $item->category ? ' · '.$item->category : '' }}</span></td>
                                    <td>{{ number_format((float) $item->pack_size, 2) }} KG / bag</td>
                                    <td class="right">Buy {{ \App\Support\CompanyContext::money((float) $item->default_purchase_price) }}<br>Sell {{ \App\Support\CompanyContext::money((float) $item->default_sale_price) }}</td>
                                    <td class="right">{{ number_format((float) ($item->stock_quantity ?? 0), 4) }} KG</td>
                                    <td><span class="hg-badge {{ $item->is_active ? 'on' : 'off' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <form method="POST" action="{{ route('feed.setup.items.toggle', $item) }}">
                                            @csrf @method('PATCH')
                                            <button class="hg-btn hg-btn-small {{ $item->is_active ? 'hg-btn-warning' : 'hg-btn-soft' }}" type="submit">{{ $item->is_active ? 'Set Inactive' : 'Set Active' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Add Feed Item</h2>
            <form method="POST" action="{{ route('feed.setup.items.store') }}" class="hg-form-grid">
                @csrf
                <div class="hg-field"><label>Item Code <span class="hg-required">*</span></label><input name="code" value="{{ old('code') }}" placeholder="e.g. PF-001" required></div>
                <div class="hg-field"><label>Item Name <span class="hg-required">*</span></label><input name="name" value="{{ old('name') }}" placeholder="e.g. Broiler Grower Feed" required></div>
                <div class="hg-field"><label>Category</label><input name="category" value="{{ old('category') }}" placeholder="Poultry / Fish / Cattle"></div>
                <div class="hg-field"><label>Brand</label><input name="brand" value="{{ old('brand') }}" placeholder="Brand name"></div>
                <div class="hg-field"><label>Pack Size (KG) <span class="hg-required">*</span></label><input name="pack_size" type="number" min="0.0001" step="0.0001" value="{{ old('pack_size', 50) }}" required></div>
                <div class="hg-field"><label>Reorder Level (Bags)</label><input name="reorder_level" type="number" min="0" step="0.0001" value="{{ old('reorder_level', 10) }}"></div>
                <div class="hg-field"><label>Default Purchase Price / Bag</label><input name="default_purchase_price" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('default_purchase_price', 0) }}"></div>
                <div class="hg-field"><label>Default Sale Price / Bag</label><input name="default_sale_price" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('default_sale_price', 0) }}"></div>
                <div class="hg-field full hg-feed-checkboxes">
                    <label><input type="checkbox" name="track_batch" value="1" @checked(old('track_batch', true))> Track batch number</label>
                    <label><input type="checkbox" name="track_expiry" value="1" @checked(old('track_expiry', true))> Track expiry date</label>
                </div>
                <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Add Feed Item</button></div>
            </form>
        </section>
    </div>

    <div class="hg-grid hg-grid-2 hg-feed-section-gap">
        <section class="hg-card">
            <h2 class="hg-card-title">Warehouses</h2>
            @if($warehouses->isEmpty())
                <div class="hg-empty">No warehouse added yet.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead><tr><th>Warehouse</th><th>Location</th><th>Default</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($warehouses as $warehouse)
                                @php($isDefaultWarehouse = (int) $settings->default_warehouse_id === (int) $warehouse->id)
                                <tr>
                                    <td><strong>{{ $warehouse->name }}</strong><br><span class="hg-muted">{{ $warehouse->code }}</span></td>
                                    <td>{{ $warehouse->location ?: '—' }}</td>
                                    <td>
                                        @if($isDefaultWarehouse)
                                            <span class="hg-badge on">Default</span>
                                        @elseif($warehouse->is_active)
                                            <form method="POST" action="{{ route('feed.setup.warehouses.default', $warehouse) }}">
                                                @csrf @method('PATCH')
                                                <button class="hg-btn hg-btn-small hg-btn-soft" type="submit">Set Default</button>
                                            </form>
                                        @else
                                            <span class="hg-muted">—</span>
                                        @endif
                                    </td>
                                    <td><span class="hg-badge {{ $warehouse->is_active ? 'on' : 'off' }}">{{ $warehouse->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <form method="POST" action="{{ route('feed.setup.warehouses.toggle', $warehouse) }}">
                                            @csrf @method('PATCH')
                                            <button class="hg-btn hg-btn-small {{ $warehouse->is_active ? 'hg-btn-warning' : 'hg-btn-soft' }}" type="submit">{{ $warehouse->is_active ? 'Set Inactive' : 'Set Active' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Add Warehouse</h2>
            <form method="POST" action="{{ route('feed.setup.warehouses.store') }}" class="hg-form-grid">
                @csrf
                <div class="hg-field"><label>Warehouse Code <span class="hg-required">*</span></label><input name="code" value="{{ old('code') }}" placeholder="e.g. WH-01" required></div>
                <div class="hg-field"><label>Warehouse Name <span class="hg-required">*</span></label><input name="name" value="{{ old('name') }}" placeholder="Main Godown" required></div>
                <div class="hg-field full"><label>Location</label><input name="location" value="{{ old('location') }}" placeholder="Optional address or note"></div>
                <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Add Warehouse</button></div>
            </form>
        </section>
    </div>
</x-layouts::accounting>
