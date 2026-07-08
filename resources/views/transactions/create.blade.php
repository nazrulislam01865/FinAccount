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
    $transactionTypeLabel = $transactionTypeDefinition['label'] ?? ($categoryOption?->label ?? $category);
    $pageTransactionLabel = $category === \App\Support\TransactionTypes::SALE ? 'Sales' : $transactionTypeLabel;
    $moneyLabel = $transactionTypeDefinition['money_label'] ?? 'Cash / Bank / Mobile Account';
    $partyLabel = $transactionTypeDefinition['party_label'] ?? 'Party';
@endphp

<x-layouts::accounting title="Transaction Entry">
    <div class="hg-page-header">
        <div>
            <h1>{{ $isEditing ? 'Edit '.$pageTransactionLabel.' Transaction' : 'Record '.$pageTransactionLabel.' Transaction' }}</h1>
            <p class="hg-muted">{{ $isDueSettlement ? 'Settle the selected due. Party, due ledger, and transaction head are filled automatically.' : 'Enter the transaction details. Payment status and the journal are calculated automatically.' }}</p>
        </div>
    </div>

    @if (! $isDueSettlement && (! $isEditing || $categoryRepairRequired))
        <div class="hg-tabs hg-transaction-type-tabs">
            @foreach ($transactionCategories as $categoryTab)
                @php($definition = \App\Support\TransactionTypes::definition($categoryTab->value))
                <a href="{{ $isEditing ? route('transactions.edit', [$transaction, 'category' => $categoryTab->value]) : route('transactions.create', ['category' => $categoryTab->value]) }}" class="{{ $category === $categoryTab->value ? 'active' : '' }}">
                    {{ $definition['label'] ?? $categoryTab->label }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($isEditing && $transaction->status === 'incomplete')
        <div class="hg-notice" style="margin-bottom:14px"><strong>This transaction is incomplete.</strong> Select valid active setup records and update it to rebuild the journal.</div>
    @endif

    <div class="hg-grid hg-grid-2 hg-entry-grid" data-transaction-entry>
        <section class="hg-card">
            <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="hg-form-grid" data-transaction-form data-default-allowed-settlements='@json($isDueSettlement ? [\App\Support\TransactionTypes::CASH] : ($transactionTypeDefinition['allowed_settlements'] ?? \App\Support\TransactionTypes::ALL_SETTLEMENTS))' data-auto-sync-paid="{{ (! $isEditing && old('paid_amount') === null && ! $isDueSettlement) ? '1' : '0' }}" data-due-settlement="{{ $isDueSettlement ? '1' : '0' }}" data-draft-form data-draft-key="{{ $isEditing ? 'transactions.edit.'.$transaction->id : 'transactions.create.'.\Illuminate\Support\Str::slug((string) $category, '_') }}" data-draft-title="{{ $isEditing ? 'Edit Transaction' : 'New '.($categoryOption?->label ?? $category).' Transaction' }}">
                @csrf
                @if ($isEditing) @method('PUT') @else <input type="hidden" name="request_token" value="{{ $requestToken }}"> @endif
                <input type="hidden" name="category" value="{{ $category }}">
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
                        <option value="">{{ $transactionHeads->isEmpty() ? 'No active transaction head available' : 'Select transaction head' }}</option>
                        @foreach ($transactionHeads as $head)
                            <option
                                value="{{ $head->id }}"
                                data-title="{{ $head->name }}"
                                data-meta="{{ $head->code }}{{ $head->postingAccount ? ' — '.$head->postingAccount->code.' '.$head->postingAccount->name : '' }}"
                                data-search-keywords="{{ $head->category }} {{ implode(' ', $head->allowedSettlementCodes()) }}"
                                data-allowed-settlements="{{ json_encode($head->allowedSettlementCodes()) }}"
                                data-party-type="{{ $head->party_type ?: ($transactionTypeDefinition['party_type'] ?? 'Any') }}"
                                @selected((string) $selectedHeadId === (string) $head->id)
                            >{{ $head->name }}</option>
                        @endforeach
                    </select>
                    @error('transaction_head_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="amount">{{ $isDueSettlement ? (($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'Received Amount' : 'Payment Amount') : 'Amount' }} ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                    <input id="amount" name="amount" type="number" min="{{ \App\Support\CompanyContext::amountStep() }}" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ $selectedAmount }}" @if($isDueSettlement && filled($dueSettlementContext['amount'] ?? null)) max="{{ $dueSettlementContext['amount'] }}" @endif required>
                    @if($isDueSettlement)<small class="hg-field-help">Enter the amount being {{ ($dueSettlementContext['due_type'] ?? '') === 'Receivable' ? 'received from the customer' : 'paid to the supplier' }}. It cannot be more than the outstanding due.</small>@endif
                    @error('amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

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


                <div class="hg-field hidden" id="due-amount-field">
                    <label for="due_amount_preview">Remaining Due (auto)</label>
                    <input id="due_amount_preview" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" value="{{ old('due_amount', $isEditing ? $transaction->due_amount : '') }}" readonly>
                </div>

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
</x-layouts::accounting>
