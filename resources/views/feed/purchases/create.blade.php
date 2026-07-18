@php
    $defaultTransactionHeadId = old('transaction_head_id', $settings->purchase_transaction_head_id ?: $transactionHeads->first()?->id);
    $defaultTransactionHead = $transactionHeads->firstWhere('id', (int) $defaultTransactionHeadId) ?: $transactionHeads->first();
    $defaultPostingAccountName = $defaultTransactionHead?->postingAccount?->name ?? 'Account not configured';
    $defaultWarehouseId = old('tracking_unit_id', $settings->default_tracking_unit_id ?: $warehouses->first()?->id);
    $initialLines = old('lines', [[
        'item_id' => $items->first()?->id,
        'unit' => 'BAG',
        'quantity' => 1,
        'rate' => $items->first()?->default_purchase_price ?? 0,
        'batch_no' => '',
        'expiry_date' => '',
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

<x-layouts::accounting title="Feed Purchase">
    <div class="feed-ui" data-feed-form="purchase">
        <div class="feed-page-heading">
            <div>
                <h1>Record Feed Purchase</h1>
                <p>Record supplier purchase, update feed stock, and create the accounting journal automatically.</p>
            </div>
            <div class="feed-heading-actions">
                <a class="feed-btn" href="{{ route('transactions.index', ['category' => \App\Support\TransactionTypes::PURCHASE]) }}">Purchase Register</a>
                <a class="feed-btn" href="{{ route('feed.inventory.index') }}">View Stock Ledger</a>
            </div>
        </div>

        @include('feed.partials.tabs')

        <div class="feed-layout-2">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Purchase Details</div>
                        <div class="feed-card-sub">Fields marked with * are required.</div>
                    </div>
                    <span class="feed-status feed-status-blue">Direct Ledger Posting</span>
                </div>

                <form class="feed-card-body" method="POST" action="{{ route('feed.purchases.store') }}" enctype="multipart/form-data" data-feed-post-form>
                    @csrf
                    <input type="hidden" name="request_token" value="{{ $requestToken }}">

                    <div class="feed-grid-1">
                        <div class="feed-field">
                            <label for="transaction_head_id">Transaction Head <span class="feed-req">*</span></label>
                            <select class="feed-control" id="transaction_head_id" name="transaction_head_id" required data-feed-transaction-head data-hg-searchable-ignore>
                                <option value="">Select transaction head</option>
                                @foreach($transactionHeads as $head)
                                    <option value="{{ $head->id }}" @selected((string) $defaultTransactionHeadId === (string) $head->id) data-account-name="{{ $head->postingAccount?->name }}" data-account-code="{{ $head->postingAccount?->code }}">{{ $head->code }} — {{ $head->name }} @if($head->postingAccount) ({{ $head->postingAccount->code }} — {{ $head->postingAccount->name }}) @endif</option>
                                @endforeach
                            </select>
                            <div class="feed-help">This head decides the accounting rule and inventory account for this feed purchase.</div>
                            @if($transactionHeads->isEmpty())<div class="feed-warning-text">Add an active Purchase transaction head linked with a level-3 Asset account before posting.</div>@endif
                        </div>
                        <div class="feed-field">
                            <label for="transaction_date">Date <span class="feed-req">*</span></label>
                            <input class="feed-control" id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $transactionDateContext['default']) }}" @if($transactionDateContext['min']) min="{{ $transactionDateContext['min'] }}" @endif @if($transactionDateContext['max']) max="{{ $transactionDateContext['max'] }}" @endif required>
                            @if($transactionDateContext['label'])<div class="feed-help">Open period: {{ $transactionDateContext['label'] }}</div>@endif
                        </div>
                        <div class="feed-field">
                            <label for="party_id">Supplier <span class="feed-req">*</span></label>
                            <select class="feed-control" id="party_id" name="party_id" required data-hg-searchable-ignore>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('party_id') === (string) $supplier->id) data-title="{{ $supplier->name }}" data-meta="{{ $supplier->code }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @if($suppliers->isEmpty())<div class="feed-warning-text">Add an active Supplier in Parties before posting.</div>@endif
                        </div>
                        <div class="feed-field">
                            <label for="tracking_unit_id">Warehouse <span class="feed-req">*</span></label>
                            <select class="feed-control" id="tracking_unit_id" name="tracking_unit_id" required data-feed-warehouse data-hg-searchable-ignore>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) $defaultWarehouseId === (string) $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>



                    <div class="feed-divider"></div>
                    <div class="feed-section-title">Feed Items</div>
                    <div class="feed-table-wrap">
                        <table class="feed-table feed-lines-table">
                            <thead>
                                <tr>
                                    <th style="width:30%">Feed Item</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Rate ({{ \App\Support\CompanyContext::currencyCode() }})</th>
                                    <th>Line Total</th>
                                    <th>Batch / Expiry</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-feed-lines></tbody>
                        </table>
                    </div>
                    <div class="feed-line-actions">
                        <button class="feed-btn feed-btn-soft feed-btn-sm" type="button" data-feed-add-line>＋ Add Another Item</button>
                        <div class="feed-inline-note">Stock is increased only after the transaction and journal are posted successfully.</div>
                    </div>

                    <div class="feed-divider"></div>
                    <div class="feed-grid-2">
                        <div class="feed-field">
                            <label for="overall_discount">Overall Commission (%)</label>
                            <input class="feed-control" id="overall_discount" name="overall_discount" type="text" inputmode="decimal" value="{{ old('overall_discount', 0) }}" placeholder="0 or 0%" data-feed-money-input>
                            <div class="feed-help">Enter percentage with or without %. It is calculated from items subtotal.</div>
                        </div>
                        <div class="feed-field">
                            <label for="commission_amount_purchase">Commission Amount ({{ \App\Support\CompanyContext::currencyCode() }})</label>
                            <input class="feed-control feed-readonly" id="commission_amount_purchase" data-feed-commission-output readonly>
                        </div>
                    </div>
                    <div class="feed-grid-1">
                        <div class="feed-field">
                            <label for="transport_cost">Transportation Cost <span class="feed-negative">(-)</span></label>
                            <input class="feed-control" id="transport_cost" name="transport_cost" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('transport_cost', 0) }}" data-feed-money-input>
                            <div class="feed-help">This amount is deducted from the purchase total.</div>
                        </div>
                        <div class="feed-field">
                            <label for="other_cost">Other Cost</label>
                            <input class="feed-control" id="other_cost" name="other_cost" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('other_cost', 0) }}" data-feed-money-input>
                        </div>
                        <div class="feed-field">
                            <label for="calculated_total_cost">Total Purchase</label>
                            <input class="feed-control feed-readonly" id="calculated_total_cost" data-feed-calculated-total readonly>
                        </div>
                    </div>

                    <div class="feed-divider"></div>
                    <div class="feed-grid-1">
                        <div class="feed-field">
                            <label for="paid_amount">Paid Now ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="feed-req">*</span></label>
                            <input class="feed-control" id="paid_amount" name="paid_amount" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('paid_amount', 0) }}" required data-feed-money-input>
                            <div class="feed-help">0 = fully due; between 0 and total = partially paid; total = fully paid.</div>
                        </div>
                        <div class="feed-field">
                            <label for="money_account_id">Payment Account</label>
                            <select class="feed-control" id="money_account_id" name="money_account_id" data-feed-money-account data-hg-searchable-ignore>
                                <option value="">Select when money is paid now</option>
                                @foreach($moneyAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('money_account_id') === (string) $account->id) data-title="{{ $account->name }}" data-meta="{{ $moneyKindLabels[$account->kind] ?? $account->kind }}">{{ $account->name }} — {{ $moneyKindLabels[$account->kind] ?? $account->kind }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="feed-field">
                            <label for="description">Description</label>
                            <textarea class="feed-control" id="description" name="description" placeholder="Purchase purpose or note">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="feed-grid-1 feed-section-gap">
                        <div class="feed-field">
                            <label for="transaction_attachments">Attachment</label>
                            <div class="feed-upload">
                                <div><strong>Upload invoice or challan</strong><div class="feed-help">Images, PDF, Word or Excel; maximum 10 MB each.</div></div>
                                <input id="transaction_attachments" type="file" name="transaction_attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*,application/pdf">
                            </div>
                        </div>
                        <div class="feed-info-banner feed-info-blue">
                            <strong>Posting behavior</strong><br>
                            The feed purchase posts directly to the accounting ledger: Debit Feed Inventory, then Credit the selected money account and/or Supplier Payable. Stock and journal posting succeed or fail together.
                        </div>
                    </div>

                    <div class="feed-action-bar">
                        <div class="feed-left-actions">
                            <a class="feed-btn" href="{{ route('feed.purchases.create') }}">Clear</a>
                        </div>
                        <div class="feed-right-actions">
                            <button class="feed-btn feed-btn-primary" type="submit" @disabled($suppliers->isEmpty() || $transactionHeads->isEmpty())>Post Purchase</button>
                        </div>
                    </div>
                </form>
            </section>

            <aside class="feed-card feed-summary-card">
                <div class="feed-card-header"><div class="feed-card-title">Transaction Summary</div></div>
                <div class="feed-card-body">
                    <div class="feed-info-banner">This purchase increases feed stock. The paid portion and supplier payable are calculated automatically.</div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Purchase Value</div>
                        <div class="feed-summary-row"><span>Items subtotal</span><b data-feed-summary="subtotal">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Commission amount</span><b class="feed-negative" data-feed-summary="commission">(-) {{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Transportation cost</span><b class="feed-negative" data-feed-summary="transport">(-) {{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Other cost</span><b data-feed-summary="other">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row feed-grand"><span>Total purchase</span><b data-feed-summary="total">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Payment Status</div>
                        <div class="feed-summary-row"><span>Paid now</span><b data-feed-summary="paid">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Supplier payable</span><b data-feed-summary="due">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Status</span><span class="feed-status feed-status-amber" data-feed-summary="status" data-feed-status>Fully due</span></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Inventory Impact</div>
                        <div class="feed-summary-row"><span>Quantity added</span><b data-feed-summary="quantity">0.0000 KG</b></div>
                        <div class="feed-summary-row"><span>Valuation method</span><b>Weighted average</b></div>
                    </div>

                    <div class="feed-summary-group">
                        <div class="feed-summary-label">Journal Preview</div>
                        <div class="feed-journal">
                            <div class="feed-journal-row feed-journal-head"><span>Account</span><span>Debit</span><span>Credit</span></div>
                            <div class="feed-journal-row"><span data-feed-summary="posting-account">{{ $defaultPostingAccountName }}</span><span data-feed-summary="total">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span data-feed-summary="money-name">Cash / Bank</span><span></span><span data-feed-summary="paid">{{ \App\Support\CompanyContext::money(0) }}</span></div>
                            <div class="feed-journal-row"><span>Supplier Payable</span><span></span><span data-feed-summary="due">{{ \App\Support\CompanyContext::money(0) }}</span></div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    @php
        $transactionHeadsForJs = $transactionHeads->map(function ($head) {
            return [
                'id' => $head->id,
                'name' => $head->name,
                'code' => $head->code,
                'accountName' => $head->postingAccount?->name,
                'accountCode' => $head->postingAccount?->code,
            ];
        })->values();
    @endphp

    @push('scripts')
        <script>
            window.HISEBGHOR_FEED_PAGE = {
                type: 'purchase',
                currencyCode: @json(\App\Support\CompanyContext::currencyCode()),
                decimalPlaces: {{ \App\Support\CompanyContext::decimalPlaces() }},
                items: @json($feedItemsForJs),
                initialLines: @json(array_values($initialLines)),
                transactionHeads: @json($transactionHeadsForJs),
                stock: {}
            };
        </script>
    @endpush
</x-layouts::accounting>
