@php
    $transaction = $transaction ?? null;
    $isEditing = $transaction !== null;
    $dueSettlementContext = $dueSettlementContext ?? ['active' => false];
    $isDueSettlement = (bool) ($dueSettlementContext['active'] ?? false);
    $formAction = $isEditing ? route('transactions.update', $transaction) : route('transactions.store');
    $categoryRepairRequired = $categoryRepairRequired ?? false;
    $transactionDateContext = $transactionDateContext ?? ['min' => null, 'max' => null, 'default' => now()->toDateString(), 'label' => null];
    $selectedHeadId = old('transaction_head_id', $isEditing ? $transaction->transaction_head_id : ($dueSettlementContext['transaction_head_id'] ?? ''));
    $selectedSettlement = old('settlement_type', $isEditing ? ($transaction->settlement_type ?: \App\Support\TransactionTypes::CASH) : \App\Support\TransactionTypes::CASH);
    $selectedAmount = old('amount', $isEditing ? $transaction->amount : ($dueSettlementContext['amount'] ?? ''));
    $selectedPaidAmount = old('paid_amount', $isEditing ? $transaction->paid_amount : ($isDueSettlement ? ($dueSettlementContext['amount'] ?? '') : ''));
    $transactionCategories = $transactionCategories ?? collect();
    $filteredTransactionCategories = $filteredTransactionCategories ?? $transactionCategories;
    $transactionDirectionOptions = $transactionDirectionOptions ?? \App\Support\TransactionTypes::flowLabels();
    $transactionCategoryDirections = $transactionCategoryDirections ?? $transactionCategories
        ->mapWithKeys(fn ($option) => [$option->value => \App\Support\TransactionTypes::flow((string) $option->value, is_array($option->metadata) ? $option->metadata : [])])
        ->all();
    $activeTransactionDirection = $activeTransactionDirection
        ?? ($transactionCategoryDirections[$category] ?? null);
    $transactionTypeLabel = $transactionTypeDefinition['label'] ?? ($categoryOption?->label ?? $category ?: 'Transaction');
    $isTransferTransaction = ($transactionTypeDefinition['flow'] ?? null) === \App\Support\TransactionTypes::FLOW_TRANSFER;
    $pageTransactionLabel = filled($category)
        ? ($category === \App\Support\TransactionTypes::SALE ? 'Sales' : $transactionTypeLabel)
        : 'Transaction';
    $moneyLabel = $isTransferTransaction ? 'Pay From' : ($transactionTypeDefinition['money_label'] ?? 'Cash / Bank / Mobile Account');
    $partyLabel = $transactionTypeDefinition['party_label'] ?? 'Party';
    $showTransactionTypeSelection = $showTransactionTypeSelection ?? ($isEditing || $isDueSettlement || request()->filled('direction') || request()->filled('category'));
    $hasSelectedTransactionDirection = $isEditing || $isDueSettlement || filled($activeTransactionDirection);
    $hasTransactionTypeForDirection = $hasSelectedTransactionDirection && $filteredTransactionCategories->isNotEmpty();
    $highlightTransactionDirection = $showTransactionTypeSelection && $hasSelectedTransactionDirection;
    $transactionHeadsAreUnfiltered = $transactionHeadsAreUnfiltered ?? false;
    $transactionHeadsAreDirectionFiltered = $transactionHeadsAreDirectionFiltered ?? false;
    $transactionTypeLabels = $transactionTypeLabels ?? $transactionCategories->mapWithKeys(fn ($option) => [$option->value => $option->label])->all();
    $saleSellingTypeOptions = $saleSellingTypeOptions ?? \App\Support\SaleSellingTypes::labels();
    $saleTrackingUnits = $saleTrackingUnits ?? collect();
    $defaultSaleTrackingUnitId = $defaultSaleTrackingUnitId ?? null;
    $isSalesTransaction = \App\Support\SaleSellingTypes::isSaleCategory($category);
    $saleBusinessAreas = $saleBusinessAreas ?? collect();
    $saleBusinessItemsForJs = $saleBusinessItemsForJs ?? collect();
    $saleBusinessLocations = $saleBusinessLocations ?? collect();
    $defaultSellingType = $saleBusinessAreas->first()?->code ?: \App\Support\SaleSellingTypes::OTHERS;
    $selectedSellingType = \App\Support\SaleSellingTypes::normalize(old('selling_type', $isEditing ? ($transaction->selling_type ?: $defaultSellingType) : $defaultSellingType));
    $defaultBusinessLocationId = $saleBusinessLocations->firstWhere('business_area', $selectedSellingType)?->id;
    $selectedTrackingUnitId = old('tracking_unit_id', $isEditing ? $transaction->tracking_unit_id : $defaultBusinessLocationId);
    $showSaleWarehouse = false;
    $saleFeedModeSelected = $isSalesTransaction && ! \App\Support\SaleSellingTypes::isOthers($selectedSellingType);

    $saleCustomers = $saleCustomers ?? collect();
    $saleFeedItems = $saleFeedItems ?? collect();
    $saleStockBalances = $saleStockBalances ?? collect();
    $selectedSalePartyId = old('party_id', $isEditing ? $transaction->party_id : ($dueSettlementContext['party_id'] ?? ''));
    $selectedTransferToMoneyAccountId = old('transfer_to_money_account_id', $isEditing ? $transaction->transfer_to_money_account_id : '');
    $selectedSaleOtherCharges = old('other_charges', 0);
    $defaultSaleBusinessItem = $saleBusinessItemsForJs
        ->first(fn ($item) => ($item['businessArea'] ?? null) === $selectedSellingType)
        ?? $saleBusinessItemsForJs->first();
    $saleInitialLines = old('lines', [[
        'item_name' => data_get($defaultSaleBusinessItem, 'name', ''),
        'unit' => 'Unit',
        'quantity' => 1,
        'rate' => data_get($defaultSaleBusinessItem, 'salePrice', 0),
        'discount' => 0,
    ]]);
    $saleFeedItemsForJs = $saleBusinessItemsForJs;
@endphp

<x-layouts::accounting title="Transaction Entry">
    <div class="hg-page-header">
        <div>
            <h1>{{ $isEditing ? 'Edit '.$pageTransactionLabel.' Transaction' : (filled($category) ? 'Record '.$pageTransactionLabel.' Transaction' : 'Record Transaction') }}</h1>
            <p class="hg-muted">{{ $isDueSettlement ? 'Settle the selected due. Party, due ledger, and transaction head are filled automatically.' : 'Enter the transaction details. Payment status and the journal are calculated automatically.' }}</p>
        </div>
        @if(! $isEditing && ! $isDueSettlement && auth()->user()?->canAccounting('transactions.manage'))
            <div class="hg-actions">
                <a class="hg-btn" href="{{ route('feed.purchases.create') }}">🛒 Feed Purchase</a>
                <a class="hg-btn" href="{{ route('feed.sales.create') }}">🧾 Feed Sale</a>
            </div>
        @endif
    </div>

    @if (! $isDueSettlement && (! $isEditing || $categoryRepairRequired))
        <div class="hg-entry-filter-panel">
            <div class="hg-page-kicker">Transaction Direction</div>
            <div class="hg-tabs hg-transaction-direction-tabs" aria-label="Transaction Direction">
                @foreach ($transactionDirectionOptions as $directionValue => $directionLabel)
                    <a
                        href="{{ $isEditing ? route('transactions.edit', [$transaction, 'direction' => $directionValue]) : route('transactions.create', ['direction' => $directionValue]) }}"
                        class="{{ $highlightTransactionDirection && $activeTransactionDirection === $directionValue ? 'active' : '' }}"
                        data-transaction-direction-tab
                        data-direction="{{ $directionValue }}"
                        @if($highlightTransactionDirection && $activeTransactionDirection === $directionValue) aria-current="page" @endif
                    >
                        {{ $directionLabel }}
                    </a>
                @endforeach
            </div>

            @if($showTransactionTypeSelection && $hasSelectedTransactionDirection)
                <div class="hg-page-kicker">Transaction Type</div>
                @if($filteredTransactionCategories->isEmpty())
                    <div class="hg-notice" style="margin-bottom:14px">
                        No active transaction type is set up under {{ $transactionDirectionOptions[$activeTransactionDirection] ?? \App\Support\TransactionTypes::flowLabel((string) $activeTransactionDirection) }}.
                        Add or edit a Transaction Type and select this Transaction Direction from Master Data.
                    </div>
                @else
                    <div class="hg-tabs hg-transaction-type-tabs" aria-label="Filtered Transaction Types">
                        @foreach ($filteredTransactionCategories as $categoryTab)
                            @php
                                $definition = \App\Support\TransactionTypes::definition($categoryTab->value);
                            @endphp
                            <a
                                href="{{ $isEditing ? route('transactions.edit', [$transaction, 'category' => $categoryTab->value]) : route('transactions.create', ['direction' => $activeTransactionDirection, 'category' => $categoryTab->value]) }}"
                                class="{{ strcasecmp((string) $category, (string) $categoryTab->value) === 0 ? 'active' : '' }}"
                                data-transaction-category-tab
                                data-direction="{{ $transactionCategoryDirections[$categoryTab->value] ?? $activeTransactionDirection }}"
                                data-category="{{ $categoryTab->value }}"
                                @if(strcasecmp((string) $category, (string) $categoryTab->value) === 0) aria-current="page" @endif
                            >
                                {{ $definition['label'] ?? $categoryTab->label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($isEditing && $transaction->status === 'incomplete')
        <div class="hg-notice" style="margin-bottom:14px"><strong>This transaction is incomplete.</strong> Select valid active setup records and update it to rebuild the journal.</div>
    @endif

    @if (! $categoryOption && ! $isDueSettlement)
        <section class="hg-card">
            <h2 class="hg-card-title">No Transaction Type Available</h2>
            <p class="hg-muted">Create or activate a Transaction Type before recording a transaction.</p>
        </section>
    @else
    <div class="hg-grid hg-grid-2 hg-entry-grid" data-transaction-entry>
        <section class="hg-card">
            <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="hg-form-grid hg-transaction-entry-form" data-transaction-form data-default-allowed-settlements='@json($isDueSettlement ? [\App\Support\TransactionTypes::CASH] : ($transactionTypeDefinition['allowed_settlements'] ?? \App\Support\TransactionTypes::ALL_SETTLEMENTS))' data-transaction-flow="{{ $transactionTypeDefinition['flow'] ?? '' }}" data-auto-sync-paid="{{ (! $isEditing && old('paid_amount') === null && ! $isDueSettlement) ? '1' : '0' }}" data-due-settlement="{{ $isDueSettlement ? '1' : '0' }}" data-draft-form data-draft-key="{{ $isEditing ? 'transactions.edit.'.$transaction->id : 'transactions.create.'.\Illuminate\Support\Str::slug((string) $category, '_') }}" data-draft-title="{{ $isEditing ? 'Edit Transaction' : 'New '.($categoryOption?->label ?? $category).' Transaction' }}">
                @csrf
                @if ($isEditing) @method('PUT') @else <input type="hidden" name="request_token" value="{{ $requestToken }}"> @endif
                <input type="hidden" name="category" value="{{ $category }}" data-transaction-category-input>
                <input type="hidden" id="settlement_type" name="settlement_type" value="{{ $isDueSettlement ? \App\Support\TransactionTypes::CASH : $selectedSettlement }}">
                @if($isDueSettlement)
                    <input type="hidden" name="due_settlement" value="1">
                    <input type="hidden" name="due_type" value="{{ $dueSettlementContext['due_type'] ?? '' }}">
                    <input type="hidden" name="due_party_id" value="{{ $dueSettlementContext['party_id'] ?? '' }}">
                    <input type="hidden" name="due_account_id" value="{{ $dueSettlementContext['account_id'] ?? '' }}">
                    <input type="hidden" name="due_as_of_date" value="{{ $dueSettlementContext['as_of_date'] ?? '' }}">
                @endif

                @if($isDueSettlement)
                    <div class="hg-field full hg-due-settlement-entry-card">
                        <div>
                            <span class="hg-overline">Due Settlement</span>
                            <strong>{{ ($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'Customer Due Collection' : 'Supplier Due Payment' }}</strong>
                            <small>{{ $dueSettlementContext['party_label'] ?? 'Party not selected' }}</small>
                        </div>
                        <div>
                            <span>Due Ledger</span>
                            <strong>{{ $dueSettlementContext['account_label'] ?? 'Not available' }}</strong>
                        </div>
                        <div>
                            <span>Total Outstanding</span>
                            <strong>{{ \App\Support\CompanyContext::money((float) ($dueSettlementContext['amount'] ?? 0)) }}</strong>
                        </div>
                        @if($dueSettlementContext['message'] ?? null)
                            <p class="hg-field-error full">{{ $dueSettlementContext['message'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="hg-field">
                    <label for="transaction_date">Date <span class="hg-required">*</span></label>
                    <input id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $isEditing ? $transaction->transaction_date->format('Y-m-d') : ($dueSettlementContext['as_of_date'] ?? $transactionDateContext['default'])) }}" @if($transactionDateContext['min']) min="{{ $transactionDateContext['min'] }}" @endif @if($transactionDateContext['max']) max="{{ $transactionDateContext['max'] }}" @endif required>
                    @if($transactionDateContext['label'])<small class="hg-field-help">Open period: {{ $transactionDateContext['label'] }}</small>@endif
                    @error('transaction_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                @if($isSalesTransaction && ! $isDueSettlement)
                    <div class="hg-field full">
                        <label for="selling_type">What are you selling? <span class="hg-required">*</span></label>
                        <select id="selling_type" name="selling_type" required data-sale-selling-type>
                            @foreach($saleSellingTypeOptions as $sellingTypeValue => $sellingTypeLabel)
                                @php
                                    $isOtherSellingType = \App\Support\SaleSellingTypes::isOthers($sellingTypeValue);
                                @endphp
                                <option
                                    value="{{ $sellingTypeValue }}"
                                    data-business-area="{{ $isOtherSellingType ? '' : $sellingTypeValue }}"
                                    data-requires-warehouse="0"
                                    data-feed-sale-mode="{{ $isOtherSellingType ? '0' : '1' }}"
                                    @selected((string) $selectedSellingType === (string) $sellingTypeValue)
                                >{{ $sellingTypeLabel }}</option>
                            @endforeach
                        </select>
                        <small class="hg-field-help">Business areas come from Business Tracking Setup. Select Others for a normal sale without item lines.</small>
                        @error('selling_type')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>
                @endif

                <div class="hg-field {{ $isDueSettlement ? 'hidden' : '' }}">
                    <label for="transaction_head_id">Transaction Head <span class="hg-required">*</span></label>
                    <select
                        id="transaction_head_id"
                        name="transaction_head_id"
                        required
                        data-hg-searchable
                        data-hg-search-placeholder="Search transaction head by name, code or ledger..."
                        data-hg-search-empty="No matching transaction head found"
                    >
                        <option value="">{{ $transactionHeads->isEmpty() ? 'No active transaction head available' : ($transactionHeadsAreUnfiltered ? 'Select transaction head from all types' : ($transactionHeadsAreDirectionFiltered ? 'Select transaction head for this direction' : 'Select transaction head')) }}</option>
                        @foreach ($transactionHeads as $head)
                            @php
                                $headCategory = (string) $head->category;
                                $headCategoryMetadata = is_array($transactionCategories->firstWhere('value', $headCategory)?->metadata ?? null)
                                    ? $transactionCategories->firstWhere('value', $headCategory)->metadata
                                    : [];
                                $headDefinition = \App\Support\TransactionTypes::configuredDefinition(
                                    $headCategory,
                                    $headCategoryMetadata,
                                    $transactionTypeLabels[$headCategory] ?? $headCategory
                                );
                            @endphp
                            <option
                                value="{{ $head->id }}"
                                data-title="{{ $transactionHeadsAreUnfiltered ? (($transactionTypeLabels[$headCategory] ?? $headCategory).' — ') : '' }}{{ $head->name }}"
                                data-meta="{{ $head->code }}{{ $head->postingAccount ? ' — '.$head->postingAccount->code.' '.$head->postingAccount->name : '' }}"
                                data-search-keywords="{{ $headCategory }} {{ $transactionTypeLabels[$headCategory] ?? '' }} {{ implode(' ', $head->allowedSettlementCodes()) }}"
                                data-allowed-settlements="{{ json_encode($head->allowedSettlementCodes()) }}"
                                data-category="{{ $headCategory }}"
                                data-direction="{{ $transactionCategoryDirections[$headCategory] ?? ($headDefinition['flow'] ?? '') }}"
                                data-party-type="{{ $head->party_type ?: ($headDefinition['party_type'] ?? ($transactionTypeDefinition['party_type'] ?? 'Any')) }}"
                                @selected((string) $selectedHeadId === (string) $head->id)
                            >{{ $transactionHeadsAreUnfiltered ? (($transactionTypeLabels[$headCategory] ?? $headCategory).' — ') : '' }}{{ $head->name }}</option>
                        @endforeach
                    </select>
                    @error('transaction_head_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                    @if($transactionHeads->isEmpty() && ! $isDueSettlement)
                        <small class="hg-field-error">{{ $isTransferTransaction ? 'No active Transfer Transaction Head is linked to '.$transactionTypeLabel.'. Activate or create a matching transfer head.' : ($transactionHeadsAreUnfiltered ? 'No active Transaction Head is available. Activate or create at least one head with an active posting account.' : 'No active Transaction Head is linked to '.$transactionTypeLabel.'. Activate or create a matching head with an active posting account.') }}</small>
                    @elseif($transactionHeadsAreUnfiltered)
                        <small class="hg-field-help">No transaction direction is selected, so all active transaction heads are shown. Selecting a direction or transaction type above will filter this list.</small>
                    @elseif($transactionHeadsAreDirectionFiltered)
                        <small class="hg-field-help">This list is filtered by the selected transaction direction. Choose a transaction type above to narrow it further.</small>
                    @endif
                </div>

                @if($isSalesTransaction && ! $isDueSettlement)
                    <div class="hg-field {{ $saleFeedModeSelected ? '' : 'hidden' }}" data-sale-warehouse-field>
                        <label for="tracking_unit_id">Location <span class="hg-required">*</span></label>
                        <select
                            id="tracking_unit_id"
                            name="tracking_unit_id"
                            data-sale-warehouse
                            data-hg-searchable
                            data-hg-search-placeholder="Search location by name or area..."
                            data-hg-search-empty="No location found for this business area"
                        >
                            <option value="">Select location</option>
                            @foreach($saleBusinessLocations as $location)
                                @php
                                    $locationLabel = trim((string) ($location->location ?: $location->name));
                                    $locationMeta = trim((string) ($location->name.($location->location ? ' — '.$location->location : '')));
                                @endphp
                                <option
                                    value="{{ $location->id }}"
                                    data-business-area="{{ $location->business_area }}"
                                    data-title="{{ $locationLabel }}"
                                    data-meta="{{ $locationMeta }}"
                                    data-search-keywords="{{ $location->business_area }} {{ $location->name }} {{ $location->location }}"
                                    @selected((string) $selectedTrackingUnitId === (string) $location->id)
                                >{{ $locationLabel }} @if($location->name && $location->location) — {{ $location->name }} @endif</option>
                            @endforeach
                        </select>
                        <small class="hg-field-help">Locations come from Business Tracking Setup for the selected business area.</small>
                        @if($saleBusinessLocations->isEmpty())
                            <small class="hg-field-error">Add an active location/unit in Business Tracking Setup before posting business-area sales.</small>
                        @endif
                        @error('tracking_unit_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field {{ $saleFeedModeSelected ? '' : 'hidden' }}" id="party-field" data-sale-customer-field>
                        <label for="party_id"><span id="party-label">Customer</span> <span class="hg-required">*</span></label>
                        <select
                            id="party_id"
                            name="party_id"
                            data-sale-customer
                            data-hg-searchable
                            data-hg-search-placeholder="Search customer by name or code..."
                            data-hg-search-empty="No matching customer found"
                        >
                            <option value="">{{ $saleCustomers->isEmpty() ? 'No active customer available' : 'Select customer' }}</option>
                            @foreach ($saleCustomers as $customer)
                                <option
                                    value="{{ $customer->id }}"
                                    data-title="{{ $customer->name }}"
                                    data-meta="{{ $customer->code }}"
                                    data-status="Customer"
                                    data-search-keywords="Customer"
                                    data-party-type="Customer"
                                    @selected((string) $selectedSalePartyId === (string) $customer->id)
                                >{{ $customer->code }} — {{ $customer->name }}</option>
                            @endforeach
                        </select>
                        @if($saleCustomers->isEmpty())
                            <small class="hg-field-error">Add an active Customer from Party Setup before posting sales.</small>
                        @endif
                        @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field full hg-transaction-sale-feed-section {{ $saleFeedModeSelected ? '' : 'hidden' }}" data-transaction-sale-feed data-sale-feed-only>
                        <label>Items <span class="hg-required">*</span></label>
                        <div class="hg-sale-feed-lines-wrap">
                            <div class="hg-table-wrap hg-feed-lines-wrap">
                                <table class="hg-table hg-feed-lines-table hg-sale-feed-lines-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Unit</th>
                                            <th>Quantity</th>
                                            <th>Sale Rate</th>
                                            <th>Line Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody data-transaction-sale-feed-lines></tbody>
                                </table>
                            </div>
                            <div class="hg-feed-line-actions">
                                <button class="hg-btn hg-btn-small" type="button" data-transaction-sale-feed-add>+ Add Another Item</button>
                                <span class="hg-muted">Items are loaded from the selected Business Area.</span>
                            </div>
                        </div>
                        @if($saleBusinessItemsForJs->isEmpty())
                            <small class="hg-field-error">Add active items in Business Tracking Setup before using business-area sales.</small>
                        @endif
                        @error('lines')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field {{ $saleFeedModeSelected ? '' : 'hidden' }}" data-sale-feed-only>
                        <label for="sale_items_total">Total Amount ({{ \App\Support\CompanyContext::currencyCode() }})</label>
                        <input id="sale_items_total" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" value="0" readonly data-sale-items-total class="hg-readonly-input">
                        <small class="hg-field-help">This is calculated from the Items section only.</small>
                    </div>

                    <div class="hg-field {{ $saleFeedModeSelected ? '' : 'hidden' }}" data-sale-feed-only>
                        <label for="other_charges">Other Charges ({{ \App\Support\CompanyContext::currencyCode() }})</label>
                        <input id="other_charges" name="other_charges" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ $selectedSaleOtherCharges }}" data-sale-other-charges>
                    </div>

                    <div class="hg-field">
                        <label for="amount"><span data-sale-total-bill-label>{{ $saleFeedModeSelected ? 'Total Bill' : 'Amount' }}</span> ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                        <input id="amount" name="amount" type="number" min="{{ \App\Support\CompanyContext::amountStep() }}" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ $selectedAmount }}" required data-sale-total-bill @readonly($saleFeedModeSelected) class="{{ $saleFeedModeSelected ? 'hg-readonly-input' : '' }}">
                        <small class="hg-field-help" data-sale-total-bill-help>{{ $saleFeedModeSelected ? 'Total Bill = Total Amount + Other Charges.' : 'Enter the total sale amount for all other sales.' }}</small>
                        @error('amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>
                @else
                    <div class="hg-field">
                        <label for="amount">{{ $isDueSettlement ? (($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'Received Amount' : 'Payment Amount') : 'Amount' }} ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                        <input id="amount" name="amount" type="number" min="{{ \App\Support\CompanyContext::amountStep() }}" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ $selectedAmount }}" @if($isDueSettlement && filled($dueSettlementContext['amount'] ?? null)) max="{{ $dueSettlementContext['amount'] }}" @endif required>
                        @if($isDueSettlement)<small class="hg-field-help">Enter the amount being {{ ($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'received from the customer' : 'paid to the supplier' }}. It cannot be more than the outstanding due.</small>@endif
                        @error('amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>
                @endif

                <div class="hg-field {{ $isDueSettlement ? 'hidden' : '' }}" id="paid-amount-field">
                    <label for="paid_amount"><span id="paid-amount-label">Paid/Received Now</span> ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                    <input id="paid_amount" name="paid_amount" type="number" min="0" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ $selectedPaidAmount }}">
                    <small class="hg-field-help" id="paid-amount-help">Enter 0 when the full amount will remain due.</small>
                    @error('paid_amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                    @error('settlement_type')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field hidden" id="money-field">
                    <label for="money_account_id"><span id="money-label">{{ $moneyLabel }}</span> <span class="hg-required">*</span></label>
                    <select
                        id="money_account_id"
                        name="money_account_id"
                        data-hg-searchable
                        data-hg-search-placeholder="Search cash, bank or mobile account..."
                        data-hg-search-empty="No matching money account found"
                    >
                        <option value="">{{ $moneyAccounts->isEmpty() ? 'No active money account available' : 'Select account' }}</option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option
                                value="{{ $moneyAccount->id }}"
                                data-title="{{ $moneyAccount->name }}"
                                data-meta="{{ $moneyAccount->chartOfAccount?->code }}{{ $moneyAccount->chartOfAccount ? ' — '.$moneyAccount->chartOfAccount->name : '' }}"
                                data-status="{{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}"
                                data-search-keywords="{{ $moneyAccount->kind }}"
                                @selected((string) old('money_account_id', $isEditing ? $transaction->money_account_id : '') === (string) $moneyAccount->id)
                            >{{ $moneyAccount->name }} — {{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}</option>
                        @endforeach
                    </select>
                    @error('money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field hidden" id="transfer-to-field">
                    <label for="transfer_to_money_account_id"><span id="transfer-to-label">Pay To</span> <span class="hg-required">*</span></label>
                    <select
                        id="transfer_to_money_account_id"
                        name="transfer_to_money_account_id"
                        data-hg-searchable
                        data-hg-search-placeholder="Search destination cash, bank or mobile account..."
                        data-hg-search-empty="No matching destination account found"
                    >
                        <option value="">{{ $moneyAccounts->isEmpty() ? 'No active money account available' : 'Select destination account' }}</option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option
                                value="{{ $moneyAccount->id }}"
                                data-title="{{ $moneyAccount->name }}"
                                data-meta="{{ $moneyAccount->chartOfAccount?->code }}{{ $moneyAccount->chartOfAccount ? ' — '.$moneyAccount->chartOfAccount->name : '' }}"
                                data-status="{{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}"
                                data-search-keywords="{{ $moneyAccount->kind }}"
                                @selected((string) $selectedTransferToMoneyAccountId === (string) $moneyAccount->id)
                            >{{ $moneyAccount->name }} — {{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}</option>
                        @endforeach
                    </select>
                    <small class="hg-field-help">Transfer entry will debit Pay To and credit Pay From.</small>
                    @error('transfer_to_money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>


                <div class="hg-field hidden" id="due-amount-field">
                    <label for="due_amount_preview">Remaining Due (auto)</label>
                    <input id="due_amount_preview" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('due_amount', $isEditing ? $transaction->due_amount : '') }}" readonly>
                </div>

                @unless($isSalesTransaction && ! $isDueSettlement)
                <div class="hg-field hidden" id="party-field">
                    <label for="party_id"><span id="party-label">{{ $partyLabel }}</span> <span class="hg-required">*</span></label>
                    <select
                        id="party_id"
                        name="party_id"
                        data-hg-searchable
                        data-hg-search-placeholder="Search party by name, code or type..."
                        data-hg-search-empty="No matching party found"
                    >
                        <option value="">Select {{ strtolower($partyLabel) }}</option>
                        @foreach ($parties as $party)
                            <option
                                value="{{ $party->id }}"
                                data-title="{{ $party->name }}"
                                data-meta="{{ $party->code }}"
                                data-status="{{ $partyTypeLabels[$party->type] ?? $party->type }}"
                                data-search-keywords="{{ $party->type }} {{ $partyTypeLabels[$party->type] ?? $party->type }}"
                                data-party-type="{{ $party->type }}"
                                @selected((string) old('party_id', $isEditing ? $transaction->party_id : ($dueSettlementContext['party_id'] ?? '')) === (string) $party->id)
                            >{{ $party->code }} — {{ $party->name }} ({{ $partyTypeLabels[$party->type] ?? $party->type }})</option>
                        @endforeach
                    </select>
                    @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                @endunless

                <div class="hg-field hidden" id="auto-party-notice" aria-live="polite">
                    <div class="hg-notice" style="margin:0">
                        <strong id="auto-party-label">Party selected automatically</strong>
                        <div class="hg-muted" id="auto-party-name"></div>
                    </div>
                </div>

                <div class="hg-field">
                    <label for="reference">Reference</label>
                    <input id="reference" name="reference" value="{{ old('reference', $isEditing ? $transaction->reference : '') }}" placeholder="Invoice, bill or receipt number">
                    @error('reference')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Short business description">{{ old('description', $isEditing ? $transaction->description : ($isDueSettlement ? ((($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'Customer due collected from ' : 'Supplier due paid to ').($dueSettlementContext['party_label'] ?? '')) : '')) }}</textarea>
                    @error('description')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full hg-transaction-attachment-field">
                    <label>Attachment</label>
                    <div class="hg-attachment-box hg-attachment-desktop">
                        <div>
                            <strong>Upload receipt or reference file</strong>
                            <small>Images, PDF, Word, Excel, CSV or text files. Maximum 10 MB each.</small>
                        </div>
                        <label class="hg-btn hg-btn-small" for="transaction_attachments_desktop">Choose Files</label>
                        <input
                            id="transaction_attachments_desktop"
                            class="hg-file-input"
                            type="file"
                            name="transaction_attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*,application/pdf"
                            data-attachment-input
                        >
                    </div>

                    <div class="hg-attachment-box hg-attachment-mobile" data-camera-widget>
                        <div class="hg-attachment-camera-copy">
                            <strong>Take receipt photo</strong>
                            <small>On phone or tablet this section opens the camera directly. The normal file-upload option stays hidden on mobile/tablet.</small>
                        </div>

                        <div class="hg-camera-actions">
                            <button class="hg-btn hg-btn-small hg-btn-camera-primary" type="button" data-camera-start>Open Camera</button>
                            <label class="hg-btn hg-btn-small hg-btn-soft hg-camera-fallback" for="transaction_attachments_mobile" data-camera-fallback hidden>Choose from Gallery</label>
                        </div>

                        <input
                            id="transaction_attachments_mobile"
                            class="hg-file-input"
                            type="file"
                            name="transaction_attachments[]"
                            accept="image/*"
                            capture="environment"
                            data-attachment-input
                            data-camera-file-input
                        >

                        <div class="hg-camera-panel" data-camera-panel hidden>
                            <video class="hg-camera-video" data-camera-video playsinline autoplay muted></video>
                            <canvas data-camera-canvas hidden></canvas>
                            <div class="hg-camera-preview" data-camera-preview hidden></div>
                            <div class="hg-camera-controls">
                                <button class="hg-btn hg-btn-small" type="button" data-camera-capture>Use This Photo</button>
                                <button class="hg-btn hg-btn-small hg-btn-soft" type="button" data-camera-retake hidden>Retake</button>
                                <button class="hg-btn hg-btn-small hg-btn-danger" type="button" data-camera-close>Close Camera</button>
                            </div>
                        </div>

                        <div class="hg-camera-message" data-camera-message hidden></div>
                    </div>

                    <div class="hg-attachment-selected" data-attachment-selected hidden></div>
                    @error('transaction_attachments')<small class="hg-field-error">{{ $message }}</small>@enderror
                    @error('transaction_attachments.*')<small class="hg-field-error">{{ $message }}</small>@enderror

                    @if($isEditing && $transaction->attachments->isNotEmpty())
                        <div class="hg-existing-attachments">
                            <strong>Existing attachments</strong>
                            <div class="hg-attachment-list">
                                @foreach($transaction->attachments as $attachment)
                                    <div class="hg-attachment-pill">
                                        <a href="{{ route('transactions.attachments.show', [$transaction, $attachment]) }}" target="_blank" rel="noopener">
                                            {{ $attachment->display_name }}
                                        </a>
                                        <span>{{ number_format(($attachment->size_bytes ?: 0) / 1024, 1) }} KB</span>
                                        <form method="POST" action="{{ route('transactions.attachments.destroy', [$transaction, $attachment]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="hg-btn-link-danger">Remove</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>


                <div class="hg-field full">
                    <x-accounting.form-actions :submit-label="$isDueSettlement ? (($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'Post Collection' : 'Post Payment') : ($isEditing ? 'Update Transaction' : 'Post Transaction')">
                        <button type="button" class="hg-btn" data-draft-clear data-draft-clear-url="{{ $isDueSettlement ? route('reports.due-management', ['as_of_date' => $dueSettlementContext['as_of_date'] ?? null, 'due_type' => strtolower((string) ($dueSettlementContext['due_type'] ?? 'all'))]) : route('transactions.create', ['category' => $category]) }}">Clear</button>
                    </x-accounting.form-actions>
                </div>
            </form>
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Transaction Summary</h2>
            <div id="journal-preview" data-preview-url="{{ route('transactions.preview') }}">@include('transactions.partials.preview-empty')</div>
        </section>
    </div>

    <template id="journal-preview-empty-template">@include('transactions.partials.preview-empty')</template>
    @if($isSalesTransaction && ! $isDueSettlement)
        @push('scripts')
            <script>
                window.HISEBGHOR_TRANSACTION_SALE = {
                    currencyCode: @json(\App\Support\CompanyContext::currencyCode()),
                    decimalPlaces: {{ \App\Support\CompanyContext::decimalPlaces() }},
                    items: @json($saleFeedItemsForJs),
                    initialLines: @json(array_values($saleInitialLines)),
                    stock: @json($saleStockBalances),
                };
            </script>
        @endpush
    @endif
    @endif
</x-layouts::accounting>
