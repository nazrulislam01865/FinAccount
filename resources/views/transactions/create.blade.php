@php
    $transaction = $transaction ?? null;
    $isEditing = $transaction !== null;
    $formAction = $isEditing
        ? route('transactions.update', $transaction)
        : route('transactions.store');
    $categoryRepairRequired = $categoryRepairRequired ?? false;
    $transactionDateContext = $transactionDateContext ?? [
        'min' => null,
        'max' => null,
        'default' => now()->toDateString(),
        'label' => null,
    ];
@endphp

<x-layouts::accounting title="Transaction Entry">
    <div class="hg-page-header">
        <div>
            <h1>Record {{ $categoryOption?->label ?? $category }} Transaction</h1>
        </div>
    </div>

    @if (! $isEditing || $categoryRepairRequired)
        <div class="hg-tabs">
            @foreach ($transactionCategories as $categoryTab)
                <a
                    href="{{ $isEditing
                        ? route('transactions.edit', [$transaction, 'category' => $categoryTab->value])
                        : route('transactions.create', ['category' => $categoryTab->value]) }}"
                    class="{{ $category === $categoryTab->value ? 'active' : '' }}"
                >{{ $categoryTab->label }}</a>
            @endforeach
        </div>
    @endif

    @if ($isEditing && $transaction->status === 'incomplete')
        <div class="hg-notice" style="margin-bottom:14px">
            <strong>This transaction is incomplete.</strong>
            A related setup record was deleted. Select valid active dependencies and update the transaction to rebuild its journal and return it to Posted status.
        </div>
    @endif

    <div class="hg-grid hg-grid-2 hg-entry-grid" data-transaction-entry>
        <section class="hg-card">
            <form
                method="POST"
                action="{{ $formAction }}"
                enctype="multipart/form-data"
                class="hg-form-grid"
                data-transaction-form
                data-draft-form
                data-draft-key="{{ $isEditing ? 'transactions.edit.'.$transaction->id : 'transactions.create.'.\Illuminate\Support\Str::slug((string) $category, '_') }}"
                data-draft-title="{{ $isEditing ? 'Edit Transaction' : 'New '.($categoryOption?->label ?? $category).' Transaction' }}"
            >
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @else
                    <input type="hidden" name="request_token" value="{{ $requestToken }}">
                @endif

                <input type="hidden" name="category" value="{{ $category }}">

                <div class="hg-field">
                    <label for="transaction_date">Date <span class="hg-required">*</span></label>
                    <input
                        id="transaction_date"
                        name="transaction_date"
                        type="date"
                        value="{{ old('transaction_date', $isEditing ? $transaction->transaction_date->format('Y-m-d') : $transactionDateContext['default']) }}"
                        @if($transactionDateContext['min']) min="{{ $transactionDateContext['min'] }}" @endif
                        @if($transactionDateContext['max']) max="{{ $transactionDateContext['max'] }}" @endif
                        required
                    >
                    @if($transactionDateContext['label'])<small class="hg-field-help">Open period: {{ $transactionDateContext['label'] }}</small>@endif
                    @error('transaction_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="transaction_head_id">Transaction Head <span class="hg-required">*</span></label>
                    <select id="transaction_head_id" name="transaction_head_id" required>
                        <option value="">
                            {{ $transactionHeads->isEmpty()
                                ? 'No active '.($categoryOption?->label ?? $category).' transaction head available'
                                : 'Select transaction head' }}
                        </option>
                        @foreach ($transactionHeads as $head)
                            <option
                                value="{{ $head->id }}"
                                @selected((string) old('transaction_head_id', $isEditing ? $transaction->transaction_head_id : '') === (string) $head->id)
                            >
                                {{ $head->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('transaction_head_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field" id="money-field">
                    <label for="money_account_id">
                        <span id="money-label">{{ $categoryOption?->metadata['money_label'] ?? 'Money Account' }}</span>
                        <span class="hg-required">*</span>
                    </label>
                    <select id="money_account_id" name="money_account_id">
                        <option value="">
                            {{ $moneyAccounts->isEmpty() ? 'No active money account available' : 'Select money account' }}
                        </option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option
                                value="{{ $moneyAccount->id }}"
                                @selected((string) old('money_account_id', $isEditing ? $transaction->money_account_id : '') === (string) $moneyAccount->id)
                            >
                                {{ $moneyAccount->name }} — {{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}
                            </option>
                        @endforeach
                    </select>
                    @error('money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field" id="party-field">
                    <label for="party_id">Party <span class="hg-required">*</span></label>
                    <select id="party_id" name="party_id">
                        <option value="">Select party</option>
                        @foreach ($parties as $party)
                            <option
                                value="{{ $party->id }}"
                                data-party-type="{{ $party->type }}"
                                @selected((string) old('party_id', $isEditing ? $transaction->party_id : '') === (string) $party->id)
                            >
                                {{ $party->code }} — {{ $party->name }} ({{ $partyTypeLabels[$party->type] ?? $party->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="amount">Amount ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="{{ \App\Support\CompanyContext::amountStep() }}"
                        step="{{ \App\Support\CompanyContext::amountStep() }}"
                        value="{{ old('amount', $isEditing ? $transaction->amount : '') }}"
                        required
                    >
                    @error('amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field hidden" id="paid-amount-field">
                    <label for="paid_amount">Paid Amount ({{ \App\Support\CompanyContext::currencyCode() }}) <span class="hg-required">*</span></label>
                    <input
                        id="paid_amount"
                        name="paid_amount"
                        type="number"
                        min="{{ \App\Support\CompanyContext::amountStep() }}"
                        step="{{ \App\Support\CompanyContext::amountStep() }}"
                        value="{{ old('paid_amount', $isEditing ? $transaction->paid_amount : '') }}"
                    >
                    <small class="hg-field-help">Shown automatically when the selected Transaction Head uses an accounting rule line with Paid/Due amount basis.</small>
                    @error('paid_amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field hidden" id="due-amount-field">
                    <label for="due_amount_preview">Due Amount (auto)</label>
                    <input
                        id="due_amount_preview"
                        type="number"
                        step="{{ \App\Support\CompanyContext::amountStep() }}"
                        value="{{ old('due_amount', $isEditing ? $transaction->due_amount : '') }}"
                        readonly
                    >
                    <small class="hg-field-help">Calculated as total amount minus paid amount. It is posted according to the selected accounting rule.</small>
                </div>

                <div class="hg-field hidden" id="due-date-field">
                    <label for="due_date">Due Date</label>
                    <input
                        id="due_date"
                        name="due_date"
                        type="date"
                        value="{{ old('due_date', $isEditing ? $transaction->due_date?->format('Y-m-d') : '') }}"
                    >
                    @error('due_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="reference">Reference</label>
                    <input
                        id="reference"
                        name="reference"
                        value="{{ old('reference', $isEditing ? $transaction->reference : '') }}"
                        placeholder="Invoice, bill, receipt or loan ref"
                    >
                    @error('reference')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Short business description">{{ old('description', $isEditing ? $transaction->description : '') }}</textarea>
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
                    <x-accounting.form-actions :submit-label="$isEditing ? 'Update Transaction' : 'Post Transaction'">
                        <button type="button" class="hg-btn" data-draft-clear data-draft-clear-url="{{ route('transactions.create', ['category' => $category]) }}">Clear</button>
                    </x-accounting.form-actions>
                </div>
            </form>
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Automatic Journal Preview</h2>
            <div id="journal-preview" data-preview-url="{{ route('transactions.preview') }}">
                @include('transactions.partials.preview-empty')
            </div>
        </section>
    </div>

    <template id="journal-preview-empty-template">
        @include('transactions.partials.preview-empty')
    </template>
</x-layouts::accounting>
