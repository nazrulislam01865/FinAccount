@php
    $defaultWarehouseId = old('warehouse_id', $settings->default_warehouse_id ?: $warehouses->first()?->id);
    $initialLines = old('lines', [[
        'item_id' => $items->first()?->id,
        'unit' => 'BAG',
        'quantity' => 1,
        'rate' => $items->first()?->default_sale_price ?? 0,
        'discount' => 0,
    ]]);

    $feedItemsForJs = $items->map(static fn ($item): array => [
        'id' => $item->id,
        'code' => $item->code,
        'name' => $item->name,
        'category' => $item->category,
        'brand' => $item->brand,
        'packSize' => (float) $item->pack_size,
        'purchasePrice' => (float) $item->default_purchase_price,
        'salePrice' => (float) $item->default_sale_price,
        'trackBatch' => (bool) $item->track_batch,
        'trackExpiry' => (bool) $item->track_expiry,
    ])->values();
@endphp

<x-layouts::accounting title="Feed Sale">
    <div class="feed-ui" data-feed-form="sale">
        <div class="feed-page-heading">
            <div>
                <h1>Record Feed Sale</h1>
                <p>Create a feed sale, check available quantity, reduce stock, and post revenue plus cost of goods automatically.</p>
            </div>
            <div class="feed-heading-actions">
                <a class="feed-btn" href="{{ route('transactions.index', ['category' => \App\Support\TransactionTypes::SALE]) }}">Sales Register</a>
                <a class="feed-btn" href="{{ route('feed.inventory.index') }}">Check Inventory</a>
            </div>
        </div>

        @include('feed.partials.tabs')

        <div class="feed-layout-2">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Sale Details</div>
                        <div class="feed-card-sub">Stock availability is checked from the selected warehouse.</div>
                    </div>
                    <span class="feed-status feed-status-green">Direct Ledger Posting</span>
                </div>

                <form class="feed-card-body" method="POST" action="{{ route('feed.sales.store') }}" enctype="multipart/form-data" data-feed-post-form>
                    @csrf
                    <input type="hidden" name="request_token" value="{{ $requestToken }}">

                    <div class="feed-grid-4">
                        <div class="feed-field">
                            <label for="transaction_date">Date <span class="feed-req">*</span></label>
                            <input class="feed-control" id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $transactionDateContext['default']) }}" @if($transactionDateContext['min']) min="{{ $transactionDateContext['min'] }}" @endif @if($transactionDateContext['max']) max="{{ $transactionDateContext['max'] }}" @endif required>
                            @if($transactionDateContext['label'])<div class="feed-help">Open period: {{ $transactionDateContext['label'] }}</div>@endif
                        </div>
                        <div class="feed-field">
                            <label for="party_id">Customer <span class="feed-req">*</span></label>
                            <select class="feed-control" id="party_id" name="party_id" required data-hg-searchable-ignore>
                                <option value="">Select customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((string) old('party_id') === (string) $customer->id) data-title="{{ $customer->name }}" data-meta="{{ $customer->code }}">{{ $customer->code }} — {{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @if($customers->isEmpty())<div class="feed-warning-text">Add an active Customer in Parties before posting.</div>@endif
                        </div>
                        <div class="feed-field">
                            <label>Sale Invoice</label>
                            <input class="feed-control feed-readonly" value="Generated automatically after posting" readonly>
                        </div>
                        <div class="feed-field">
                            <label for="warehouse_id">Warehouse <span class="feed-req">*</span></label>
                            <select class="feed-control" id="warehouse_id" name="warehouse_id" required data-feed-warehouse data-hg-searchable-ignore>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) $defaultWarehouseId === (string) $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="feed-grid-4 feed-section-gap">
                        <div class="feed-field">
                            <label>Posting Mode</label>
                            <input class="feed-control feed-readonly" value="Direct to Feed Ledger" readonly>
                        </div>
                        <div class="feed-field">
                            <label>Sale Type</label>
                            <input class="feed-control feed-readonly" value="Regular Sale" readonly>
                        </div>
                        <div class="feed-field">
                            <label>Delivery Method</label>
                            <input class="feed-control feed-readonly" value="Customer pickup / business delivery" readonly>
                        </div>
                        <div class="feed-field">
                            <label for="reference">Reference</label>
                            <input class="feed-control" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Order / delivery / payment reference">
                        </div>
                    </div>

                    <div class="feed-divider"></div>
                    <div class="feed-section-title">Feed Items</div>
                    <div class="feed-table-wrap">
                        <table class="feed-table feed-lines-table">
                            <thead>
                                <tr>
                                    <th style="width:26%">Feed Item</th>
                                    <th>Available</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Sale Rate</th>
                                    <th>Discount</th>
                                    <th>Line Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-feed-lines></tbody>
                        </table>
                    </div>
                    <div class="feed-line-actions">
                        <button class="feed-btn feed-btn-soft feed-btn-sm" type="button" data-feed-add-line>＋ Add Another Item</button>
                        <div class="feed-inline-note">Posting is blocked when requested quantity is higher than available stock.</div>
                    </div>

                    <div class="feed-divider"></div>
                    <div class="feed-grid-3">
                        <div class="feed-field">
                            <label for="delivery_charge">Delivery Charge</label>
                            <input class="feed-control" id="delivery_charge" name="delivery_charge" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('delivery_charge', 0) }}" data-feed-money-input>
                        </div>
                        <div class="feed-field">
                            <label for="overall_discount">Overall Discount</label>
                            <input class="feed-control" id="overall_discount" name="overall_discount" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('overall_discount', 0) }}" data-feed-money-input>
                        </div>
                        <div class="feed-field">
                            <label>Price Mode</label>
                            <input class="feed-control feed-readonly" value="Custom price by item line" readonly>
                        </div>
                    </div>

                    <div class="feed-divider"></div>
                    <div class="feed-grid-3">
                        <div class="feed-field">
                            <label for="paid_amount">Received Now ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="feed-req">*</span></label>
                            <input class="feed-control" id="paid_amount" name="paid_amount" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('paid_amount', 0) }}" required data-feed-money-input>
                            <div class="feed-help">0 = fully due; between 0 and total = partial; total = fully received.</div>
                        </div>
                        <div class="feed-field">
                            <label for="money_account_id">Receive Account</label>
                            <select class="feed-control" id="money_account_id" name="money_account_id" data-feed-money-account data-hg-searchable-ignore>
                                <option value="">Select when money is received now</option>
                                @foreach($moneyAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('money_account_id') === (string) $account->id) data-title="{{ $account->name }}" data-meta="{{ $moneyKindLabels[$account->kind] ?? $account->kind }}">{{ $account->name }} — {{ $moneyKindLabels[$account->kind] ?? $account->kind }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="feed-field">
                            <label for="description">Description</label>
                            <textarea class="feed-control" id="description" name="description" placeholder="Sale or delivery note">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="feed-grid-2 feed-section-gap">
                        <div class="feed-field">
                            <label for="transaction_attachments">Attachment</label>
                            <div class="feed-upload">
                                <div><strong>Upload order or delivery note</strong><div class="feed-help">Optional supporting image, PDF, Word, or Excel file.</div></div>
                                <input id="transaction_attachments" type="file" name="transaction_attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*,application/pdf">
                            </div>
                        </div>
                        <div class="feed-info-banner feed-info-green">
                            <strong>Posting behavior</strong><br>
                            The feed sale posts directly to the accounting ledger: Debit the selected money account and/or Customer Receivable, Credit Feed Sales, then Debit COGS and Credit Feed Inventory. Stock and journal posting succeed or fail together.
                        </div>
                    </div>

                    <div class="feed-action-bar">
                        <div class="feed-left-actions"><a class="feed-btn" href="{{ route('feed.sales.create') }}">Clear</a></div>
                        <div class="feed-right-actions"><button class="feed-btn feed-btn-primary" type="submit" @disabled($customers->isEmpty())>Post Sale</button></div>
                    </div>
                </form>
            </section>

            <aside class="feed-card feed-summary-card">
                <div class="feed-card-header"><div class="feed-card-title">Transaction Summary</div></div>
                <div class="feed-card-body">
                    <div class="feed-info-banner feed-info-green">Available stock is checked before posting. A sale journal and cost-of-goods journal are created together.</div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Sale Value</div>
                        <div class="feed-summary-row"><span>Items subtotal</span><b data-feed-summary="subtotal">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Delivery less discount</span><b data-feed-summary="extra">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row feed-grand"><span>Total sale</span><b data-feed-summary="total">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Collection Status</div>
                        <div class="feed-summary-row"><span>Received now</span><b data-feed-summary="paid">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Customer receivable</span><b data-feed-summary="due">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Status</span><span class="feed-status feed-status-amber" data-feed-summary="status" data-feed-status>Fully due</span></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Stock & Profit Estimate</div>
                        <div class="feed-summary-row"><span>Quantity removed</span><b data-feed-summary="quantity">0.0000 KG</b></div>
                        <div class="feed-summary-row"><span>Estimated COGS</span><b data-feed-summary="cogs">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Estimated gross profit</span><b data-feed-summary="profit">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Journal Preview</div>
                        <div class="feed-journal">
                            <div class="feed-journal-row feed-journal-head"><span>Account</span><span>Debit</span><span>Credit</span></div>
                            <div class="feed-journal-row"><span data-feed-summary="money-name">Cash / Bank</span><span data-feed-summary="paid">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span>Accounts Receivable</span><span data-feed-summary="due">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span>{{ $settings->saleTransactionHead->postingAccount->name }}</span><span></span><span data-feed-summary="total">{{ \App\Support\CompanyContext::money(0) }}</span></div>
                            <div class="feed-journal-row"><span>{{ $settings->cogsAccount->name }}</span><span data-feed-summary="cogs">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span>{{ $settings->purchaseTransactionHead->postingAccount->name }}</span><span></span><span data-feed-summary="cogs">{{ \App\Support\CompanyContext::money(0) }}</span></div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    @push('scripts')
        <script>
            window.HISEBGHOR_FEED_PAGE = {
                type: 'sale',
                currencyCode: @json(\App\Support\CompanyContext::currencyCode()),
                decimalPlaces: {{ \App\Support\CompanyContext::decimalPlaces() }},
                items: @json($feedItemsForJs),
                initialLines: @json(array_values($initialLines)),
                stock: @json($stockBalances)
            };
        </script>
    @endpush
</x-layouts::accounting>
