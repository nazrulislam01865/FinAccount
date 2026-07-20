@php
    $defaultTransactionHeadId = old('transaction_head_id', $settings->sale_transaction_head_id ?: $transactionHeads->first()?->id);
    $defaultTransactionHead = $transactionHeads->firstWhere('id', (int) $defaultTransactionHeadId) ?: $transactionHeads->first();
    $defaultPostingAccountName = $defaultTransactionHead?->postingAccount?->name ?? 'Account not configured';
    $defaultWarehouseId = old('tracking_unit_id', $settings->default_tracking_unit_id ?: $warehouses->first()?->id);
    $initialLines = old('lines', [[
        'item_id' => $items->first()?->id,
        'unit' => 'BAG',
        'quantity' => 1,
        'rate' => $items->first()?->default_sale_price ?? 0,
    ]]);
    $initialPayments = old('payments');
    if (! is_array($initialPayments) || $initialPayments === []) {
        $initialPayments = [[
            'money_account_id' => old('money_account_id', ''),
            'amount' => old('paid_amount', ''),
        ]];
    }

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
    $moneyAccountsForJs = $moneyAccounts->map(fn ($account): array => [
        'id' => $account->id,
        'name' => $account->name,
        'kind' => $moneyKindLabels[$account->kind] ?? $account->kind,
        'accountCode' => $account->chartOfAccount?->code,
        'accountName' => $account->chartOfAccount?->name,
        'meta' => trim(($account->chartOfAccount?->code ? $account->chartOfAccount->code.' — ' : '').$account->name),
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

                    <div class="feed-grid-1">
                        <div class="feed-field">
                            <label for="transaction_head_id">Transaction Head <span class="feed-req">*</span></label>
                            <select class="feed-control" id="transaction_head_id" name="transaction_head_id" required data-feed-transaction-head data-hg-searchable-ignore>
                                <option value="">Select transaction head</option>
                                @foreach($transactionHeads as $head)
                                    <option value="{{ $head->id }}" @selected((string) $defaultTransactionHeadId === (string) $head->id) data-account-name="{{ $head->postingAccount?->name }}" data-account-code="{{ $head->postingAccount?->code }}">{{ $head->code }} — {{ $head->name }} @if($head->postingAccount) ({{ $head->postingAccount->code }} — {{ $head->postingAccount->name }}) @endif</option>
                                @endforeach
                            </select>
                            <div class="feed-help">This head decides the accounting rule and sales income account for this feed sale.</div>
                            @if($transactionHeads->isEmpty())<div class="feed-warning-text">Add an active Sale transaction head linked with a level-3 Income account before posting.</div>@endif
                        </div>
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
                            <label for="tracking_unit_id">Warehouse <span class="feed-req">*</span></label>
                            <select class="feed-control" id="tracking_unit_id" name="tracking_unit_id" required data-feed-warehouse data-hg-searchable-ignore>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) $defaultWarehouseId === (string) $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="feed-field">
                            <label for="reference">Reference</label>
                            <input class="feed-control" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Order / delivery / payment reference">
                            <div class="feed-help">Sale invoice will be generated automatically after posting.</div>
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
                    <div class="feed-grid-2">
                        <div class="feed-field">
                            <label for="overall_discount">Overall Commission (%)</label>
                            <input class="feed-control" id="overall_discount" name="overall_discount" type="text" inputmode="decimal" value="{{ old('overall_discount', 0) }}" placeholder="0 or 0%" data-feed-money-input>
                            <div class="feed-help">Enter percentage with or without %. It is calculated from items subtotal.</div>
                        </div>
                        <div class="feed-field">
                            <label for="commission_amount_sale">Commission Amount ({{ \App\Support\CompanyContext::currencyCode() }})</label>
                            <input class="feed-control feed-readonly" id="commission_amount_sale" data-feed-commission-output readonly>
                        </div>
                    </div>
                    <div class="feed-grid-1">
                        <div class="feed-field">
                            <label for="transport_cost">Transportation Cost</label>
                            <input class="feed-control" id="transport_cost" name="transport_cost" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('transport_cost', 0) }}" data-feed-money-input>
                        </div>
                        <div class="feed-field">
                            <label for="other_cost">Other Cost</label>
                            <input class="feed-control" id="other_cost" name="other_cost" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('other_cost', 0) }}" data-feed-money-input>
                        </div>
                        <div class="feed-field">
                            <label for="calculated_total_sale">Total Sale</label>
                            <input class="feed-control feed-readonly" id="calculated_total_sale" data-feed-calculated-total readonly>
                        </div>
                    </div>

                    <div class="feed-divider"></div>
                    <input id="paid_amount" name="paid_amount" type="hidden" value="{{ old('paid_amount', 0) }}">
                    <input id="money_account_id" name="money_account_id" type="hidden" value="{{ old('money_account_id') }}">
                    <div class="feed-payment-panel">
                        <div class="feed-payment-table">
                            <div class="feed-payment-table-head">
                                <span>Receive Account</span>
                                <span>Reference</span>
                                <span>Amount</span>
                                <span></span>
                            </div>
                            <div class="feed-payment-list" data-feed-payments>
                            @foreach(array_values($initialPayments) as $paymentIndex => $payment)
                                @php
                                    $selectedMoneyAccountId = data_get($payment, 'money_account_id', '');
                                    $selectedMoneyAccount = $moneyAccounts->firstWhere('id', (int) $selectedMoneyAccountId);
                                    $selectedMoneyAccountMeta = $selectedMoneyAccount
                                        ? trim(($selectedMoneyAccount->chartOfAccount?->code ? $selectedMoneyAccount->chartOfAccount->code.' — ' : '').$selectedMoneyAccount->name)
                                        : '';
                                @endphp
                                <div class="feed-payment-row" data-feed-payment-row>
                                    <div class="feed-field">
                                        <label>Receive Account</label>
                                        <select name="payments[{{ $paymentIndex }}][money_account_id]" data-feed-payment-account data-hg-searchable-ignore>
                                            <option value="">Select receive account</option>
                                            @foreach($moneyAccounts as $moneyAccount)
                                                <option value="{{ $moneyAccount->id }}" @selected((string) $moneyAccount->id === (string) $selectedMoneyAccountId)>
                                                    {{ $moneyAccount->name }} — {{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small data-feed-payment-account-meta>{{ $selectedMoneyAccountMeta }}</small>
                                    </div>
                                    <div class="feed-field">
                                        <label>Reference</label>
                                        <input name="payments[{{ $paymentIndex }}][reference]" data-feed-payment-reference maxlength="100" value="{{ data_get($payment, 'reference', '') }}" placeholder="CHQ / TXN / note">
                                    </div>
                                    <div class="feed-field">
                                        <label>Amount ({{ \App\Support\CompanyContext::currencyCode() }})</label>
                                        <input name="payments[{{ $paymentIndex }}][amount]" data-feed-payment-amount type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ data_get($payment, 'amount', '') }}" placeholder="0.00">
                                    </div>
                                    <button class="feed-icon-btn" type="button" data-feed-remove-payment aria-label="Remove receive method">×</button>
                                </div>
                            @endforeach
                            </div>
                        </div>
                        <div class="feed-payment-footer">
                            <button class="feed-payment-add-row" type="button" data-feed-add-payment>＋ Add another bank/cash/mobile row</button>
                            <span>Total received can be full, partial, or zero due.</span>
                        </div>
                        <div class="feed-payment-total"><span>Total received now</span><strong data-feed-payment-total>{{ \App\Support\CompanyContext::money(0) }}</strong></div>
                        @if($errors->has('payments') || $errors->has('payments.*.money_account_id') || $errors->has('payments.*.amount'))
                            <div class="feed-warning-text">{{ $errors->first('payments') ?: ($errors->first('payments.*.money_account_id') ?: $errors->first('payments.*.amount')) }}</div>
                        @endif
                        @error('paid_amount')<div class="feed-warning-text">{{ $message }}</div>@enderror
                        @error('money_account_id')<div class="feed-warning-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="feed-grid-1 feed-section-gap">
                        <div class="feed-field">
                            <label for="description">Description</label>
                            <textarea class="feed-control" id="description" name="description" placeholder="Sale or delivery note">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="feed-grid-1 feed-section-gap">
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
                        <div class="feed-right-actions"><button class="feed-btn feed-btn-primary" type="submit" @disabled($customers->isEmpty() || $transactionHeads->isEmpty())>Post Sale</button></div>
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
                        <div class="feed-summary-row"><span>Commission amount</span><b data-feed-summary="commission">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Transportation cost</span><b data-feed-summary="transport">{{ \App\Support\CompanyContext::money(0) }}</b></div>
                        <div class="feed-summary-row"><span>Other cost</span><b data-feed-summary="other">{{ \App\Support\CompanyContext::money(0) }}</b></div>
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
                            <div data-feed-payment-journal></div>
                            <div class="feed-journal-row"><span>Accounts Receivable</span><span data-feed-summary="due">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span data-feed-summary="posting-account">{{ $defaultPostingAccountName }}</span><span></span><span data-feed-summary="total">{{ \App\Support\CompanyContext::money(0) }}</span></div>
                            <div class="feed-journal-row"><span>{{ $settings->cogsAccount?->name ?? 'Account not configured' }}</span><span data-feed-summary="cogs">{{ \App\Support\CompanyContext::money(0) }}</span><span></span></div>
                            <div class="feed-journal-row"><span>{{ $settings->purchaseTransactionHead?->postingAccount?->name ?? 'Account not configured' }}</span><span></span><span data-feed-summary="cogs">{{ \App\Support\CompanyContext::money(0) }}</span></div>
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
                type: 'sale',
                currencyCode: @json(\App\Support\CompanyContext::currencyCode()),
                decimalPlaces: {{ \App\Support\CompanyContext::decimalPlaces() }},
                items: @json($feedItemsForJs),
                initialLines: @json(array_values($initialLines)),
                moneyAccounts: @json($moneyAccountsForJs),
                initialPayments: @json(array_values($initialPayments)),
                transactionHeads: @json($transactionHeadsForJs),
                stock: @json($stockBalances)
            };
        </script>
    @endpush
</x-layouts::accounting>
